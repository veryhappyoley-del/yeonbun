<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateReportJob;
use App\Models\Report;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * "심층 개인 리포트"(single) / "프리미엄 궁합 리포트"(compat) 단건 결제 + AI 리포트 생성.
 *
 * 흐름은 BillingController와 거의 동일합니다: checkout()이 pending 리포트를 만들고,
 * 토스 결제창에서 결제가 끝나면 success()가 /v1/payments/confirm으로 승인을 검증한
 * 뒤에만 status를 paid로 바꿉니다.
 *
 * AI 리포트 생성은 이 컨트롤러가 직접 하지 않고 GenerateReportJob(큐)에 맡깁니다 —
 * success()가 결제 확인 직후 job을 dispatch만 하고 바로 리다이렉트하고, reports.show
 * 화면(reports/partials/pending.blade.php)이 /reports/{report}/status를 주기적으로
 * 폴링해서 완료되면 새로고침합니다. 예전엔 결제 확인과 생성을 한 요청 안에서 하다가
 * 게이트웨이 타임아웃을 겪었고, 그다음엔 두 요청으로 나눴지만 생성 자체가 여전히
 * "regenerate 요청 하나가 끝날 때까지 동기로 기다리는" 구조라 스키마가 커지면서 또
 * 타임아웃이 났습니다 — 이번엔 생성을 아예 큐(백그라운드)로 옮겨서, 웹 요청이 생성
 * 소요 시간과 완전히 무관해지도록 구조 자체를 바꿨습니다.
 *
 * single(심층 연애 리포트)은 HTML이 아니라 고정 스키마의 JSON을 content에 저장하고
 * resources/views/reports/partials/single-report.blade.php가 섹션별로 렌더링합니다.
 * compat(프리미엄 궁합 리포트)은 기존처럼 제한된 태그만 쓰는 HTML 조각을 저장합니다.
 *
 * 이 컨트롤러는 사주 계산 로직을 전혀 모릅니다 — 프론트(app.js/reports.js)가 이미
 * 계산해 둔 사주/궁합 요약(JSON, single은 십신·지장간·합충형파해·신강신약·용신 포함)을
 * input으로 받아서 그걸 그대로 프롬프트에 녹일 뿐입니다(프롬프트/실제 생성 로직은
 * App\Services\ReportGenerator에 있습니다).
 * 가격은 항상 서버(self::TYPES)가 결정하고, 클라이언트가 보내는 값은 절대 신뢰하지 않습니다.
 */
class ReportController extends Controller
{
    private const TYPES = [
        'single' => [
            'label' => '심층 연애 리포트',
            'price' => 8900,
        ],
        'compat' => [
            'label' => '프리미엄 궁합 리포트',
            'price' => 3900,
        ],
    ];

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
     * status를 paid로 바꾸고, 리포트 생성 job을 큐에 올린 뒤 곧바로 리다이렉트합니다.
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

        // AI 리포트 생성은 여기서 직접 하지 않고 큐에 dispatch만 합니다(즉시 리턴 —
        // 결제 콜백 요청이 생성 소요 시간의 영향을 전혀 받지 않음). 실제 생성은
        // 큐 워커가 백그라운드에서 처리하고, reports.show 화면이 완료를 폴링합니다.
        GenerateReportJob::dispatch($report);

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

        // "심층 연애 리포트"(single)는 content에 JSON을 저장하므로, 뷰에서 다루기 쉽게
        // 여기서 미리 배열로 디코딩해서 넘깁니다. compat(궁합)은 기존처럼 HTML 그대로 씁니다.
        $data = $report->type !== 'compat' ? $this->decodeSingleContent($report) : null;

        return view('reports.show', [
            'report' => $report,
            'type' => self::TYPES[$report->type] ?? null,
            'data' => $data,
        ]);
    }

    /**
     * reports/partials/pending.blade.php가 몇 초 간격으로 폴링하는 가벼운 상태 확인
     * 엔드포인트. 화면 새로고침 없이 "리포트가 준비됐는지"만 확인합니다.
     */
    public function status(Request $request, Report $report): JsonResponse
    {
        if ($report->user_id !== $request->user()->id || $report->status !== 'paid') {
            abort(404);
        }

        return response()->json(['ready' => $this->hasUsableContent($report)]);
    }

    /**
     * AI 생성이 실패했거나(또는 이 업데이트 이전의 예전 형식 content라) 콘텐츠를 그대로
     * 쓸 수 없는 리포트를 한 번 더 생성 시도합니다(큐에 다시 dispatch). 이미 결제된
     * 건이라 재결제 없이 생성만 재시도해요.
     */
    public function regenerate(Request $request, Report $report): RedirectResponse
    {
        if ($report->user_id !== $request->user()->id || $report->status !== 'paid') {
            abort(404);
        }

        if (! $this->hasUsableContent($report)) {
            GenerateReportJob::dispatch($report);
        }

        return redirect()->route('reports.show', $report);
    }

    /**
     * content가 "그대로 화면에 쓸 수 있는" 상태인지 판단합니다. compat은 비어있지만
     * 않으면 되고, single은 반드시 singlePrompt()의 스키마를 만족하는 JSON이어야 합니다.
     * (이 검사 덕분에, 이번 업데이트 이전에 저장된 옛 형식의 single 콘텐츠가 있어도
     * "content가 있으니 재생성 안 함" 상태로 멈추지 않고 정상적으로 다시 생성됩니다.)
     */
    private function hasUsableContent(Report $report): bool
    {
        if ($report->type === 'compat') {
            return (bool) $report->content;
        }

        return $this->decodeSingleContent($report) !== null;
    }

    private function decodeSingleContent(Report $report): ?array
    {
        if (! $report->content) {
            return null;
        }

        $decoded = json_decode($report->content, true);

        if (! is_array($decoded) || ! isset($decoded['love_profile'], $decoded['final_verdict'], $decoded['love_os'])) {
            return null;
        }

        return $decoded;
    }
}
