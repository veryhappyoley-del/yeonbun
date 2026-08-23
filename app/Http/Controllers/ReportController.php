<?php

namespace App\Http\Controllers;

use App\Models\Report;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * "심층 개인 리포트" / "프리미엄 궁합 리포트" 단건 결제 + AI 리포트 생성.
 *
 * 흐름은 BillingController와 거의 동일합니다: checkout()이 pending 리포트를 만들고,
 * 토스 결제창에서 결제가 끝나면 success()가 /v1/payments/confirm으로 승인을 검증한
 * 뒤에만 status를 paid로 바꿉니다. 여기서 한 단계 더 나아가, paid로 바뀐 직후 서버가
 * Anthropic API를 한 번 호출해서 심층 해석(content)을 생성해 저장합니다.
 *
 * 이 컨트롤러는 사주 계산 로직을 전혀 모릅니다 — 프론트(app.js)가 이미 계산해 둔
 * 사주/궁합 요약(JSON)을 input으로 받아서 그걸 그대로 프롬프트에 녹일 뿐입니다.
 * 가격은 항상 서버(self::TYPES)가 결정하고, 클라이언트가 보내는 값은 절대 신뢰하지 않습니다.
 */
class ReportController extends Controller
{
    private const TYPES = [
        'single' => [
            'label' => '심층 연애 리포트',
            'price' => 4900,
        ],
        'compat' => [
            'label' => '프리미엄 궁합 리포트',
            'price' => 3900,
        ],
    ];

    // 리포트 본문에 허용하는 태그. Anthropic 응답을 그대로 저장하지 않고
    // 이 목록으로 한 번 걸러서 저장합니다(예상 밖의 태그·스크립트 방지).
    private const ALLOWED_TAGS = '<h3><p><ul><li><strong><em>';

    // 프론트가 보내는 input(JSON)이 지나치게 커지는 걸 막기 위한 안전장치.
    private const MAX_INPUT_JSON_LENGTH = 4000;

    public function index(Request $request): View
    {
        return view('reports.index', [
            'reports' => $request->user()->reports()->where('status', 'paid')->get(),
            'types' => self::TYPES,
        ]);
    }

    /**
     * 결제창을 띄우기 직전, pending 리포트 건을 만들고 주문 정보를 내려줍니다.
     */
    public function checkout(Request $request): JsonResponse
    {
        if (! config('services.toss.client_key')) {
            return response()->json(['error' => '결제 기능이 아직 설정되지 않았어요.'], 422);
        }

        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(self::TYPES))],
            'input' => ['required', 'array'],
            'title' => ['nullable', 'string', 'max:120'],
        ]);

        $type = self::TYPES[$data['type']];

        $report = Report::create([
            'user_id' => $request->user()->id,
            'type' => $data['type'],
            'order_id' => 'gyeol_report_'.Str::uuid()->toString(),
            'amount' => $type['price'],
            'status' => 'pending',
            'title' => $data['title'] ?? null,
            'input' => $data['input'],
        ]);

        return response()->json([
            'order_id' => $report->order_id,
            'amount' => $report->amount,
            'order_name' => "결 {$type['label']}",
            'customer_name' => $request->user()->name,
        ]);
    }

    /**
     * 토스 결제창에서 결제를 마치고 successUrl로 돌아왔을 때 호출됩니다.
     * /v1/payments/confirm 을 서버가 직접 호출해 승인 여부를 검증한 뒤에만
     * status를 paid로 바꾸고, 곧바로 AI 리포트 생성을 시도합니다.
     */
    public function success(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'paymentKey' => ['required', 'string'],
            'orderId' => ['required', 'string'],
            'amount' => ['required', 'integer'],
        ]);

        $amount = (int) $data['amount'];

        $report = Report::where('order_id', $data['orderId'])
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $report || $report->status !== 'pending' || $report->amount !== $amount) {
            return redirect()->route('home')->with('billing_error', '리포트 결제 정보를 확인할 수 없어요. 이미 처리됐거나 금액이 일치하지 않아요.');
        }

        $secretKey = config('services.toss.secret_key');

        $response = Http::withHeaders([
            'Authorization' => 'Basic '.base64_encode($secretKey.':'),
            'Content-Type' => 'application/json',
        ])->post('https://api.tosspayments.com/v1/payments/confirm', [
            'paymentKey' => $data['paymentKey'],
            'orderId' => $data['orderId'],
            'amount' => $amount,
        ]);

        if ($response->failed()) {
            $report->update(['status' => 'failed']);

            return redirect()->route('home')->with(
                'billing_error',
                '리포트 결제 승인에 실패했어요: '.($response->json('message') ?? '알 수 없는 오류')
            );
        }

        $report->update([
            'status' => 'paid',
            'payment_key' => $data['paymentKey'],
        ]);

        $this->generateContent($report);

        return redirect()->route('reports.show', $report);
    }

    /**
     * 결제 취소/실패 시 토스가 failUrl로 리다이렉트할 때 호출됩니다.
     */
    public function fail(Request $request): RedirectResponse
    {
        $orderId = $request->query('orderId');

        if ($orderId) {
            Report::where('order_id', $orderId)
                ->where('user_id', $request->user()->id)
                ->where('status', 'pending')
                ->update(['status' => 'failed']);
        }

        $message = $request->query('message', '결제가 취소됐어요.');

        return redirect()->route('home')->with('billing_error', $message);
    }

    /**
     * 결제 완료된 리포트 열람 페이지. 본인 소유 + paid 상태인 리포트만 볼 수 있어요.
     */
    public function show(Request $request, Report $report): View
    {
        if ($report->user_id !== $request->user()->id || $report->status !== 'paid') {
            abort(404);
        }

        return view('reports.show', [
            'report' => $report,
            'type' => self::TYPES[$report->type] ?? null,
        ]);
    }

    /**
     * AI 생성이 실패해서 content가 비어있는 리포트를 한 번 더 시도합니다.
     * 이미 결제된 건이라 재결제 없이 생성만 재시도해요.
     */
    public function regenerate(Request $request, Report $report): RedirectResponse
    {
        if ($report->user_id !== $request->user()->id || $report->status !== 'paid') {
            abort(404);
        }

        if (! $report->content) {
            $this->generateContent($report);
        }

        return redirect()->route('reports.show', $report);
    }

    /**
     * 결제가 확인된 리포트에 대해 Anthropic API를 한 번 호출해서 본문을 생성합니다.
     * 실패해도 이미 결제는 완료된 상태이므로 예외를 여기서 삼키고, content는 null로
     * 남겨둔 채 조용히 리턴합니다 — show() 화면에서 "다시 생성하기" 버튼으로 재시도할 수 있어요.
     */
    private function generateContent(Report $report): void
    {
        if (! config('services.anthropic.key')) {
            return;
        }

        try {
            $prompt = $report->type === 'compat'
                ? $this->compatPrompt($report->input ?? [])
                : $this->singlePrompt($report->input ?? []);

            $response = Http::withHeaders([
                'x-api-key' => config('services.anthropic.key'),
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
                'model' => config('services.anthropic.model'),
                'max_tokens' => 1600,
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

            if ($response->failed()) {
                Log::warning('결 리포트 생성 실패', ['report_id' => $report->id, 'status' => $response->status()]);

                return;
            }

            $text = collect($response->json('content', []))
                ->where('type', 'text')
                ->pluck('text')
                ->implode('');

            $text = trim($text);

            if ($text === '') {
                return;
            }

            $report->update([
                'content' => strip_tags($text, self::ALLOWED_TAGS),
            ]);
        } catch (\Throwable $e) {
            Log::warning('결 리포트 생성 예외', ['report_id' => $report->id, 'message' => $e->getMessage()]);
        }
    }

    private function inputJson(array $input): string
    {
        $json = json_encode($input, JSON_UNESCAPED_UNICODE) ?: '{}';

        return mb_substr($json, 0, self::MAX_INPUT_JSON_LENGTH);
    }

    private function singlePrompt(array $input): string
    {
        $json = $this->inputJson($input);

        return "당신은 사주 명리학과 연애 상담에 모두 능숙한 '결'의 연애 코치입니다. ".
            "아래는 한 사람의 사주 계산 결과(JSON)입니다. 이걸 바탕으로 유료로 판매하는 '심층 연애 리포트'를 작성하세요.\n\n".
            "--- 사주 데이터 ---\n{$json}\n\n".
            "요구사항:\n".
            "- 출력은 아래 태그만 사용한 HTML 조각으로 작성하세요: <h3>, <p>, <ul>, <li>, <strong>, <em>. 다른 태그·마크다운·설명 문구는 절대 쓰지 마세요.\n".
            "- <h3> 섹션을 4~5개 구성하세요: 연애 스타일 심층 분석, 매력과 강점, 관계에서 자주 반복되는 패턴(주의할 점), 나에게 잘 맞는 상대 유형, 지금 실천할 수 있는 조언.\n".
            "- 이미 무료로 보여준 요약(연애 스타일/매력/조심할 점)보다 훨씬 구체적이고 개인화된 내용으로, 실제 값(오행 분포, 일간, 신살 등)을 자연스럽게 언급하며 풀어주세요.\n".
            "- 전체 분량은 800~1200자 내외의 한국어로 작성하세요. 과장된 확언이나 운명론적 단정은 피하고, 통계적·성향적 해석으로 서술하세요.";
    }

    private function compatPrompt(array $input): string
    {
        $json = $this->inputJson($input);

        return "당신은 사주 명리학과 연애 상담에 모두 능숙한 '결'의 연애 코치입니다. ".
            "아래는 두 사람의 궁합 계산 결과(JSON)입니다. 이걸 바탕으로 유료로 판매하는 '프리미엄 궁합 리포트'를 작성하세요.\n\n".
            "--- 궁합 데이터 ---\n{$json}\n\n".
            "요구사항:\n".
            "- 출력은 아래 태그만 사용한 HTML 조각으로 작성하세요: <h3>, <p>, <ul>, <li>, <strong>, <em>. 다른 태그·마크다운·설명 문구는 절대 쓰지 마세요.\n".
            "- <h3> 섹션을 4~5개 구성하세요: 궁합 총평, 서로에게 끌리는 지점, 부딪히기 쉬운 지점과 이유, 오래 잘 만나기 위한 조언, 이런 순간엔 특히 조심.\n".
            "- 이미 무료로 보여준 점수와 짧은 풀이(notes)를 그대로 반복하지 말고, 그걸 근거로 훨씬 구체적이고 두 사람 각자의 이름을 자연스럽게 언급하며 풀어주세요.\n".
            "- 전체 분량은 800~1200자 내외의 한국어로 작성하세요. 과장된 확언이나 운명론적 단정은 피하고, 통계적·성향적 해석으로 서술하세요.";
    }
}
