<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatSession;
use App\Models\PageView;
use App\Models\Payment;
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
        $payingUsers = Payment::where('status', 'paid')->whereBetween('created_at', [$stDate, $edDate])->distinct()->count('user_id');
        $totalRevenue = (int) Payment::where('status', 'paid')->whereBetween('created_at', [$stDate, $edDate])->sum('amount');
        $totalPayments = Payment::where('status', 'paid')->whereBetween('created_at', [$stDate, $edDate])->count();

        $visitorToSignup = $totalVisitors > 0 ? round($totalUsers / $totalVisitors * 100, 1) : 0;
        $signupToPaid = $totalUsers > 0 ? round($payingUsers / $totalUsers * 100, 1) : 0;
        $visitorToPaid = $totalVisitors > 0 ? round($payingUsers / $totalVisitors * 100, 1) : 0;

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

        $dailyRevenue = Payment::where('status', 'paid')
            ->whereBetween('created_at', [$chartStart, $edDate])
            ->selectRaw('DATE(created_at) as day, SUM(amount) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

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
        ]);
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
