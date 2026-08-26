<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BillingController;
use App\Http\Controllers\Controller;
use App\Models\ChapterPreview;
use App\Models\ChatSession;
use App\Models\PageView;
use App\Models\Payment;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * /admin 대시보드. EnsureIsAdmin 미들웨어로 보호돼서 users.is_admin = true 인
 * 계정만 들어올 수 있습니다.
 */
class DashboardController extends Controller
{
    // 막대그래프에 찍을 수 있는 최대 일수. 조회 기간이 이보다 길면 최근 이 일수만큼만
    // 그래프에 표시하고(카드 숫자는 전체 기간 기준 그대로), 화면에 안내 문구를 띄웁니다.
    private const MAX_CHART_DAYS = 60;

    // (2026-08-26 신설) 리포트 종류 라벨. 예전엔 admin/dashboard.blade.php 안에 배열
    // 리터럴로 하드코딩돼 있어서 "상품별 매출" 집계용으로 재사용할 방법이 없었다 — 여기로
    // 옮기고 뷰에도 그대로 넘겨서 한 군데만 고치면 되게 정리했다. single/compat은 레거시
    // (schema_version=1) 타입, love_fortune/compatibility는 현재 판매 중인 챕터형 타입.
    public const REPORT_TYPE_LABELS = [
        'single' => '심층 연애 리포트',
        'compat' => '프리미엄 궁합 리포트',
        'love_fortune' => '연애운분석',
        'compatibility' => '궁합분석',
    ];

    // 가입 경로(users.provider) 라벨. provider가 없는 행(과거 시드 데이터 등)은 "기타"로 묶는다.
    public const PROVIDER_LABELS = [
        'kakao' => '카카오',
        'naver' => '네이버',
    ];

    public function index(Request $request)
    {
        [$stDate, $edDate] = $this->resolveDateRange(
            $request->query('st_date'),
            $request->query('ed_date'),
            defaultDaysBack: 7,
        );

        $totalVisitors = PageView::whereBetween('created_at', [$stDate, $edDate])->distinct()->count('visitor_id');
        $totalPageViews = PageView::whereBetween('created_at', [$stDate, $edDate])->count();
        $totalUsers = User::whereBetween('created_at', [$stDate, $edDate])->count();
        // 매출은 코인 결제(payments)와 프리미엄 리포트 결제(reports) 두 테이블에 걸쳐 있어서 합산합니다.
        $paymentUserIds = Payment::where('status', 'paid')->whereBetween('created_at', [$stDate, $edDate])->pluck('user_id');
        $reportUserIds = Report::where('status', 'paid')->whereBetween('created_at', [$stDate, $edDate])->pluck('user_id');
        $payingUsers = $paymentUserIds->merge($reportUserIds)->unique()->count();

        $totalRevenue = (int) Payment::where('status', 'paid')->whereBetween('created_at', [$stDate, $edDate])->sum('amount')
            + (int) Report::where('status', 'paid')->whereBetween('created_at', [$stDate, $edDate])->sum('amount');
        $totalPayments = Payment::where('status', 'paid')->whereBetween('created_at', [$stDate, $edDate])->count()
            + Report::where('status', 'paid')->whereBetween('created_at', [$stDate, $edDate])->count();

        $visitorToSignup = $totalVisitors > 0 ? round($totalUsers / $totalVisitors * 100, 1) : 0;
        $signupToPaid = $totalUsers > 0 ? round($payingUsers / $totalUsers * 100, 1) : 0;
        $visitorToPaid = $totalVisitors > 0 ? round($payingUsers / $totalVisitors * 100, 1) : 0;

        // (2026-08-26 추가) 객단가(ARPU) — 결제 고객 1인당 평균 결제 금액. 코인 충전과
        // 리포트 결제를 모두 합친 $totalRevenue/$payingUsers 기준이라, 한 사람이 코인도
        // 사고 리포트도 샀으면 그 둘을 합친 금액이 분자에 들어간다.
        $arpu = $payingUsers > 0 ? (int) round($totalRevenue / $payingUsers) : 0;

        // (2026-08-26 추가) 상품별 매출 분리 — 리포트는 종류별(연애운분석/궁합분석/레거시),
        // 코인 충전은 플랜별(스몰/미디엄/라지팩)로 각각 건수·매출을 집계해서 하나의
        // 목록으로 합친다. 매출 큰 순으로 정렬해서 어떤 상품이 실제로 잘 팔리는지 한눈에
        // 보이게 한다.
        $reportRevenueByType = Report::where('status', 'paid')
            ->whereBetween('created_at', [$stDate, $edDate])
            ->selectRaw('type, COUNT(*) as cnt, SUM(amount) as total')
            ->groupBy('type')
            ->get()
            ->map(fn ($row) => [
                'label' => self::REPORT_TYPE_LABELS[$row->type] ?? $row->type,
                'count' => (int) $row->cnt,
                'amount' => (int) $row->total,
            ]);

        $paymentRevenueByPlan = Payment::where('status', 'paid')
            ->whereBetween('created_at', [$stDate, $edDate])
            ->selectRaw('plan, COUNT(*) as cnt, SUM(amount) as total')
            ->groupBy('plan')
            ->get()
            ->map(fn ($row) => [
                'label' => (BillingController::PLANS[$row->plan]['label'] ?? $row->plan).' (코인)',
                'count' => (int) $row->cnt,
                'amount' => (int) $row->total,
            ]);

        $revenueByProduct = $reportRevenueByType->concat($paymentRevenueByPlan)
            ->sortByDesc('amount')
            ->values();

        // (2026-08-26 추가) 결제 퍼널 이탈 현황 — 결제창까지는 갔지만(행이 생성됨) 승인이
        // 안 끝난(pending) 건, 실패(failed)한 건을 완료(paid)와 나란히 보여준다. 코인 충전과
        // 리포트 결제는 실패 원인이 다를 수 있어(리포트는 AI 생성 실패 재시도 포함) 따로 집계.
        $reportFunnel = $this->funnelStats(Report::class, $stDate, $edDate);
        $paymentFunnel = $this->funnelStats(Payment::class, $stDate, $edDate);

        // (2026-08-26 추가) 가입 경로 비율 — 카카오/네이버 중 어느 쪽으로 더 많이
        // 가입하는지. 두 소셜 로그인 버튼 노출 우선순위 등을 정할 때 참고할 수 있다.
        $signupCountsByProvider = User::whereBetween('created_at', [$stDate, $edDate])
            ->selectRaw('provider, COUNT(*) as cnt')
            ->groupBy('provider')
            ->pluck('cnt', 'provider');
        $signupProviderTotal = (int) $signupCountsByProvider->sum();
        $signupsByProvider = collect(self::PROVIDER_LABELS)
            ->map(fn ($label, $key) => [
                'label' => $label,
                'count' => (int) ($signupCountsByProvider[$key] ?? 0),
                'pct' => $signupProviderTotal > 0 ? round((($signupCountsByProvider[$key] ?? 0) / $signupProviderTotal) * 100, 1) : 0,
            ])
            ->values();
        // 카카오/네이버 둘 다 아닌 값(과거 시드 데이터 등)이 있으면 "기타"로 한 줄 더
        // 추가해서 퍼센트 합이 항상 100%가 되게 맞춘다.
        $labeledProviderCount = $signupsByProvider->sum('count');
        if ($signupProviderTotal > $labeledProviderCount) {
            $otherCount = $signupProviderTotal - $labeledProviderCount;
            $signupsByProvider->push([
                'label' => '기타',
                'count' => $otherCount,
                'pct' => round($otherCount / $signupProviderTotal * 100, 1),
            ]);
        }

        // (2026-08-26 추가) 무료 미리보기(챕터 프리뷰) 생성 현황 — 결제 전 무료로 보여주는
        // 1개 챕터가 실제로 얼마나 만들어지는지, 그중 AI 생성이 실패하는 비율은 얼마나
        // 되는지 본다. chapter_previews는 로그인 없이 익명으로 동작해서(입력값 해시로만
        // 구분) 개별 사용자와 연결할 수 없어 전체 건수로만 집계된다.
        $previewCountsByStatus = ChapterPreview::whereBetween('created_at', [$stDate, $edDate])
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');
        $previewTotal = (int) $previewCountsByStatus->sum();
        $previewReady = (int) ($previewCountsByStatus['ready'] ?? 0);
        $previewFailed = (int) ($previewCountsByStatus['failed'] ?? 0);
        $previewPending = $previewTotal - $previewReady - $previewFailed;

        // 그래프용 일자 범위: 선택 기간이 너무 길면 최근 MAX_CHART_DAYS일만 자릅니다.
        $chartStart = $edDate->copy()->startOfDay()->subDays(self::MAX_CHART_DAYS - 1);
        $chartTruncated = $chartStart->gt($stDate->copy()->startOfDay());
        if (! $chartTruncated) {
            $chartStart = $stDate->copy()->startOfDay();
        }
        $chartDayCount = $chartStart->diffInDays($edDate->copy()->startOfDay()) + 1;

        $dailyVisitors = PageView::whereBetween('created_at', [$chartStart, $edDate])
            ->selectRaw('DATE(created_at) as day, COUNT(DISTINCT visitor_id) as count')
            ->groupBy('day')
            ->pluck('count', 'day');

        $dailyRevenuePayments = Payment::where('status', 'paid')
            ->whereBetween('created_at', [$chartStart, $edDate])
            ->selectRaw('DATE(created_at) as day, SUM(amount) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $dailyRevenueReports = Report::where('status', 'paid')
            ->whereBetween('created_at', [$chartStart, $edDate])
            ->selectRaw('DATE(created_at) as day, SUM(amount) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        // 코인 결제 + 리포트 결제 일별 매출 합산.
        $dailyRevenue = collect();
        foreach ([$dailyRevenuePayments, $dailyRevenueReports] as $series) {
            foreach ($series as $day => $amount) {
                $dailyRevenue[$day] = ($dailyRevenue[$day] ?? 0) + (int) $amount;
            }
        }

        $chartDays = collect(range(0, $chartDayCount - 1))
            ->map(fn ($i) => $chartStart->copy()->addDays($i)->format('Y-m-d'));

        $maxVisitors = max(1, $dailyVisitors->max() ?? 0);
        $maxRevenue = max(1, $dailyRevenue->max() ?? 0);

        $chart = $chartDays->map(fn ($day) => [
            'day' => $day,
            'label' => Carbon::parse($day)->format('n/j'),
            'visitors' => (int) ($dailyVisitors[$day] ?? 0),
            'visitors_pct' => round((($dailyVisitors[$day] ?? 0) / $maxVisitors) * 100),
            'revenue' => (int) ($dailyRevenue[$day] ?? 0),
            'revenue_pct' => round((($dailyRevenue[$day] ?? 0) / $maxRevenue) * 100),
            'revenue_manwon_label' => $this->formatManwon((int) ($dailyRevenue[$day] ?? 0)),
        ]);

        $recentPayments = Payment::with('user')
            ->where('status', 'paid')
            ->whereBetween('created_at', [$stDate, $edDate])
            ->latest()
            ->take(10)
            ->get();

        $recentReports = Report::with('user')
            ->where('status', 'paid')
            ->whereBetween('created_at', [$stDate, $edDate])
            ->latest()
            ->take(10)
            ->get();

        return view('admin.dashboard', [
            'stDate' => $stDate->format('Y-m-d'),
            'edDate' => $edDate->format('Y-m-d'),
            'totalVisitors' => $totalVisitors,
            'totalPageViews' => $totalPageViews,
            'totalUsers' => $totalUsers,
            'payingUsers' => $payingUsers,
            'totalRevenue' => $totalRevenue,
            'totalPayments' => $totalPayments,
            'visitorToSignup' => $visitorToSignup,
            'signupToPaid' => $signupToPaid,
            'visitorToPaid' => $visitorToPaid,
            'chart' => $chart,
            'chartTruncated' => $chartTruncated,
            'recentPayments' => $recentPayments,
            'recentReports' => $recentReports,
            'reportTypeLabels' => self::REPORT_TYPE_LABELS,
            'planLabels' => BillingController::PLANS,
            'arpu' => $arpu,
            'revenueByProduct' => $revenueByProduct,
            'reportFunnel' => $reportFunnel,
            'paymentFunnel' => $paymentFunnel,
            'signupsByProvider' => $signupsByProvider,
            'previewTotal' => $previewTotal,
            'previewReady' => $previewReady,
            'previewFailed' => $previewFailed,
            'previewPending' => $previewPending,
        ]);
    }

    /**
     * status 컬럼을 가진 모델(Report/Payment)의 결제 퍼널 집계 — 기간 내 생성된
     * 행을 status별로 세어서 시작(전체)/완료(paid)/대기중(pending)/실패(failed)/완료율을
     * 반환합니다. Report와 Payment 둘 다 이 형태의 status 컬럼을 갖고 있어서 공용으로 씁니다.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     * @return array{started: int, paid: int, pending: int, failed: int, completion_rate: float}
     */
    private function funnelStats(string $modelClass, Carbon $stDate, Carbon $edDate): array
    {
        $counts = $modelClass::whereBetween('created_at', [$stDate, $edDate])
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $started = (int) $counts->sum();
        $paid = (int) ($counts['paid'] ?? 0);

        return [
            'started' => $started,
            'paid' => $paid,
            'pending' => (int) ($counts['pending'] ?? 0),
            'failed' => (int) ($counts['failed'] ?? 0),
            'completion_rate' => $started > 0 ? round($paid / $started * 100, 1) : 0,
        ];
    }

    public function chats(Request $request)
    {
        $userKeyword = trim((string) $request->query('user', ''));
        $stDateInput = $this->parseDateInput($request->query('st_date'));
        $edDateInput = $this->parseDateInput($request->query('ed_date'));

        $hasFilters = $userKeyword !== '' || $stDateInput || $edDateInput;

        $query = ChatSession::with(['user', 'lastMessage'])->withCount('messages');

        if ($stDateInput) {
            $query->where('created_at', '>=', $stDateInput->copy()->startOfDay());
        }
        if ($edDateInput) {
            $query->where('created_at', '<=', $edDateInput->copy()->endOfDay());
        }
        if ($userKeyword !== '') {
            $query->whereHas('user', function ($q) use ($userKeyword) {
                $q->where('name', 'like', "%{$userKeyword}%")
                    ->orWhere('email', 'like', "%{$userKeyword}%");
            });
        }

        $sessions = $query->latest('updated_at')
            ->paginate($hasFilters ? 20 : 10)
            ->withQueryString();

        return view('admin.chats.index', [
            'sessions' => $sessions,
            'hasFilters' => $hasFilters,
            'stDate' => $stDateInput?->format('Y-m-d'),
            'edDate' => $edDateInput?->format('Y-m-d'),
            'userKeyword' => $userKeyword,
        ]);
    }

    /**
     * st_date/ed_date 쿼리 파라미터를 실제 날짜 범위로 바꿉니다. 둘 다 없으면
     * "오늘부터 $defaultDaysBack일 전"이 기본값이고, 시작일이 종료일보다 뒤면 서로 바꿉니다.
     * 형식이 잘못된 입력은 무시하고 기본값으로 대체합니다.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveDateRange(?string $stDateInput, ?string $edDateInput, int $defaultDaysBack): array
    {
        $stDate = $this->parseDateInput($stDateInput)?->startOfDay() ?? Carbon::now()->subDays($defaultDaysBack)->startOfDay();
        $edDate = $this->parseDateInput($edDateInput)?->endOfDay() ?? Carbon::now()->endOfDay();

        if ($stDate->gt($edDate)) {
            [$stDate, $edDate] = [$edDate->copy()->startOfDay(), $stDate->copy()->endOfDay()];
        }

        return [$stDate, $edDate];
    }

    /**
     * 원 단위 금액을 그래프 라벨용 "만원" 단위 문자열로 바꿉니다.
     * 만원 단위로 딱 떨어지면 소수점을 생략하고, 아니면 소수 첫째 자리까지 보여줍니다.
     * 예: 32000 -> "3.2만", 30000 -> "3만", 0 -> "0".
     */
    private function formatManwon(int $amountWon): string
    {
        if ($amountWon === 0) {
            return '0';
        }

        $manwon = $amountWon / 10000;
        $decimals = ($manwon == floor($manwon)) ? 0 : 1;

        return number_format($manwon, $decimals).'만';
    }

    /**
     * "YYYY-MM-DD" 형식의 날짜 쿼리 파라미터를 Carbon으로 안전하게 변환합니다.
     * 비어있거나 형식이 잘못되면 null을 반환합니다(호출부에서 기본값으로 대체).
     */
    private function parseDateInput(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }

        return $date ?: null;
    }

    public function chatShow(ChatSession $chatSession)
    {
        $chatSession->load(['user', 'messages']);

        return view('admin.chats.show', compact('chatSession'));
    }
}
