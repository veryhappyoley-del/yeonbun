<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateReportChapterJob;
use App\Jobs\GenerateReportJob;
use App\Models\Report;
use App\ReportTypes\ReportTypeRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * 프리미엄 사주 리포트(연애운분석/궁합분석 등, 단건 결제) + AI 리포트 생성.
 *
 * 흐름은 BillingController와 거의 동일합니다: checkout()이 pending 리포트를 만들고,
 * 토스 결제창에서 결제가 끝나면 success()가 /v1/payments/confirm으로 승인을 검증한
 * 뒤에만 status를 paid로 바꿉니다.
 *
 * AI 리포트 생성은 이 컨트롤러가 직접 하지 않고 GenerateReportJob(큐)에 맡깁니다 —
 * success()가 결제 확인 직후 job을 dispatch만 하고 바로 리다이렉트하고, reports.show
 * 화면이 /reports/{report}/status를 주기적으로 폴링해서 완료되면 새로고침합니다.
 *
 * **새 구매(schema_version=2, 챕터형)**: `App\ReportTypes\ReportTypeRegistry`에 등록된
 * 타입(현재 love_fortune=연애운분석, compatibility=궁합분석)만 checkout()에서 허용합니다.
 * 리포트 하나가 챕터(`report_chapters`) 여러 개로 구성되고, `App\Jobs\GenerateReportJob`이
 * `Http::pool()`로 병렬 생성합니다(자세한 아키텍처는 App\Services\ChapterGenerator 참고).
 *
 * **예전 구매(schema_version=1, 레거시)**: 예전에 팔던 single(심층 연애 리포트)/compat
 * (프리미엄 궁합 리포트)은 더 이상 checkout()에서 판매하지 않지만, 이미 결제한 고객의
 * 리포트는 self::LEGACY_TYPES 매핑 + 기존 App\Services\ReportGenerator(리포트 전체를 한
 * 번의 Anthropic 호출로 생성) 경로로 영구히 그대로 서비스됩니다 — single은 content에
 * JSON을 저장해 partials/single-report.blade.php가 렌더링하고, compat은 제한된 태그만
 * 쓰는 HTML 조각을 그대로 저장합니다.
 *
 * 이 컨트롤러는 사주 계산 로직을 전혀 모릅니다 — 프론트(app.js/reports.js)가 이미
 * 계산해 둔 사주/궁합 요약(JSON)을 input으로 받아서 그걸 그대로 프롬프트에 녹일 뿐입니다.
 * 가격은 항상 서버(ReportTypeRegistry/self::LEGACY_TYPES)가 결정하고, 클라이언트가
 * 보내는 값은 절대 신뢰하지 않습니다.
 */
class ReportController extends Controller
{
    /**
     * 더 이상 판매하지 않는 예전 타입의 라벨/가격 — 이미 결제한 고객의 리포트를
     * index()/show()에서 표시하기 위해서만 남겨둡니다(checkout()은 이 배열을 쓰지 않음).
     */
    private const LEGACY_TYPES = [
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
        $types = self::LEGACY_TYPES;

        foreach (ReportTypeRegistry::all() as $key => $type) {
            $types[$key] = ['label' => $type->label, 'price' => $type->price];
        }

        return view('reports.index', [
            'reports' => $request->user()->reports()->where('status', 'paid')->get(),
            'types' => $types,
        ]);
    }

    /**
     * 결제창을 띄우기 직전, pending 리포트 건을 만들고 주문 정보를 내려줍니다.
     * ReportTypeRegistry에 등록된 챕터형 타입만 새로 구매할 수 있습니다(예전 단일호출형
     * single/compat은 더 이상 checkout 대상이 아님 — self::LEGACY_TYPES 참고).
     */
    public function checkout(Request $request): JsonResponse
    {
        if (! config('services.toss.client_key')) {
            return response()->json(['error' => '결제 기능이 아직 설정되지 않았어요.'], 422);
        }

        $data = $request->validate([
            'type' => ['required', Rule::in(ReportTypeRegistry::keys())],
            'input' => ['required', 'array'],
            'title' => ['nullable', 'string', 'max:120'],
        ]);

        $type = ReportTypeRegistry::get($data['type']);

        $report = Report::create([
            'user_id' => $request->user()->id,
            'type' => $data['type'],
            'schema_version' => $type->schemaVersion,
            'order_id' => 'gyeol_report_'.Str::uuid()->toString(),
            'amount' => $type->price,
            'status' => 'pending',
            'title' => $data['title'] ?? null,
            'input' => $data['input'],
        ]);

        return response()->json([
            'order_id' => $report->order_id,
            'amount' => $report->amount,
            'order_name' => "결 {$type->label}",
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
            return redirect()->route('calculator.index')->with('billing_error', '리포트 결제 정보를 확인할 수 없어요. 이미 처리됐거나 금액이 일치하지 않아요.');
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

            return redirect()->route('calculator.index')->with(
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

        return redirect()->route('calculator.index')->with('billing_error', $message);
    }

    /**
     * 결제 완료된 리포트 열람 페이지. 본인 소유 + paid 상태인 리포트만 볼 수 있어요.
     *
     * schema_version=2(챕터형)는 챕터를 미리 로드해서, 아직 pending/generating 챕터가
     * 남아있으면 chapter-progress 화면을, 전부 끝났으면(일부 실패해도 됨) chapter-toc+
     * chapter-reader 화면을 보여줍니다. schema_version=1(레거시)은 예전 그대로
     * single-report/pending 분기입니다.
     */
    public function show(Request $request, Report $report): View
    {
        if ($report->user_id !== $request->user()->id || $report->status !== 'paid') {
            abort(404);
        }

        if ($report->isChaptered()) {
            $report->load('chapters');

            $reportType = ReportTypeRegistry::get($report->type);
            $chaptersReady = $reportType !== null
                && $report->chapters->isNotEmpty()
                && $report->chapters->whereIn('status', ['pending', 'generating'])->isEmpty();

            return view('reports.show', [
                'report' => $report,
                'type' => $reportType ? ['label' => $reportType->label, 'price' => $reportType->price] : null,
                'reportType' => $reportType,
                'chaptersReady' => $chaptersReady,
                'data' => null,
            ]);
        }

        // "심층 연애 리포트"(single, 레거시)는 content에 JSON을 저장하므로, 뷰에서 다루기
        // 쉽게 여기서 미리 배열로 디코딩해서 넘깁니다. compat(궁합, 레거시)은 기존처럼
        // HTML 그대로 씁니다.
        $data = $report->type !== 'compat' ? $this->decodeSingleContent($report) : null;

        return view('reports.show', [
            'report' => $report,
            'type' => self::LEGACY_TYPES[$report->type] ?? null,
            'reportType' => null,
            'chaptersReady' => false,
            'data' => $data,
        ]);
    }

    /**
     * reports/partials/pending.blade.php(레거시)나 chapter-progress.blade.php(챕터형)가
     * 몇 초 간격으로 폴링하는 가벼운 상태 확인 엔드포인트.
     *
     * 레거시(schema_version=1)는 예전처럼 {ready} 하나만 내려줍니다(가짜 시간 기반
     * 게이지로 표현). 챕터형(schema_version=2)은 실제 챕터 진행 상황을 그대로 내려줘서,
     * 화면이 "몇 초 지났으니 몇 %"가 아니라 "20개 중 몇 개 완료"라는 진짜 진행률을
     * 보여줄 수 있습니다.
     */
    public function status(Request $request, Report $report): JsonResponse
    {
        if ($report->user_id !== $request->user()->id || $report->status !== 'paid') {
            abort(404);
        }

        if ($report->isChaptered()) {
            $chapters = $report->chapters()->get(['chapter_key', 'title', 'status']);

            $total = $chapters->count();
            $ready = $chapters->where('status', 'ready')->count();
            $failed = $chapters->where('status', 'failed')->count();

            return response()->json([
                // 챕터 하나라도 실패해도(나머지가 다 ready라면) 사용자는 일단 리포트를
                // 열람할 수 있어야 하므로, ready는 "전체가 다 끝났는지"가 아니라
                // "더 이상 pending/generating이 없는지"로 판단합니다.
                'ready' => $total > 0 && ($ready + $failed) === $total,
                'total' => $total,
                'completed' => $ready,
                'failed' => $failed,
                'chapters' => $chapters->map(fn ($c) => [
                    'key' => $c->chapter_key,
                    'title' => $c->title,
                    'status' => $c->status,
                ]),
            ]);
        }

        return response()->json(['ready' => $this->hasUsableContent($report)]);
    }

    /**
     * 챕터형(schema_version=2) 리포트에서 챕터 하나만 재시도합니다. 리포트 전체를
     * 다시 생성하는 regenerate()와 달리, 이미 ready인 나머지 챕터는 그대로 두고
     * 실패한(또는 아직 생성 안 된) 챕터 하나만 큐에 다시 올립니다.
     */
    public function regenerateChapter(Request $request, Report $report, string $chapterKey): JsonResponse
    {
        if ($report->user_id !== $request->user()->id || $report->status !== 'paid' || ! $report->isChaptered()) {
            abort(404);
        }

        $chapter = $report->chapters()->where('chapter_key', $chapterKey)->first();

        if (! $chapter) {
            abort(404);
        }

        if (! $chapter->isReady()) {
            GenerateReportChapterJob::dispatch($chapter);
        }

        return response()->json(['status' => $chapter->fresh()->status]);
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
        if ($report->isChaptered()) {
            // 챕터형은 content 컬럼을 쓰지 않으므로(항상 null), 챕터들이 더 이상
            // pending/generating이 아니면(일부 실패해도 됨) "쓸 수 있는 상태"로 본다 —
            // 안 그러면 regenerate()가 이미 완료된 챕터형 리포트에도 매번 불필요하게
            // GenerateReportJob을 다시 dispatch하게 된다.
            return $report->chapters()->exists()
                && $report->chapters()->whereIn('status', ['pending', 'generating'])->doesntExist();
        }

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
