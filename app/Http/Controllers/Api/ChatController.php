<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * "AI 상담" 탭을 구동하는 컨트롤러. routes/web.php 에서 auth 미들웨어로 감싸져 있어
 * 여기 도달하는 요청은 전부 로그인(카카오/네이버)된 사용자의 요청입니다.
 *
 * 프론트(사주 계산 결과)를 saju_context 로 받아 세션을 만들고, 이후 메시지마다
 * 그 사주 요약을 시스템 프롬프트에 녹여 Anthropic Messages API를 서버에서 호출합니다.
 * ANTHROPIC_API_KEY 는 .env 에만 존재하며 브라우저로 전달되지 않습니다.
 */
class ChatController extends Controller
{
    // 최근 이 개수만큼의 메시지(대략 6턴 + 방금 보낸 메시지)는 항상 그대로 전송합니다.
    // 홀수로 둬야 "요약 이후 구간이 항상 user 메시지로 시작한다"는 조건이 유지돼요
    // (짝수 개씩 요약해서 잘라내기 때문 — compressHistoryIfNeeded 참고).
    private const RECENT_WINDOW = 13;

    // 요약되지 않은 오래된 메시지가 이 개수 이상 쌓이면 한 번에 요약해서 압축합니다.
    private const SUMMARIZE_BATCH_SIZE = 12;

    /**
     * 로그인한 사용자의 이전 상담 목록 (사이드바용).
     */
    public function index(Request $request): JsonResponse
    {
        $sessions = $request->user()->chatSessions()
            ->withCount('messages')
            ->get()
            ->map(fn (ChatSession $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'message_count' => $s->messages_count,
                'updated_at' => $s->updated_at->toIso8601String(),
            ]);

        return response()->json(['sessions' => $sessions]);
    }

    /**
     * 지난 상담 하나를 불러오기 (이어보기).
     */
    public function show(Request $request, ChatSession $chatSession): JsonResponse
    {
        $this->authorizeOwner($request, $chatSession);

        return response()->json([
            'chat_session_id' => $chatSession->id,
            'name' => $chatSession->name,
            'saju_context' => $chatSession->saju_context,
            'messages' => $chatSession->messages()->get(['role', 'content'])->map(fn ($m) => [
                'role' => $m->role,
                'content' => $m->content,
            ]),
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        if ($this->needsPayment($request)) {
            return $this->paymentRequiredResponse();
        }

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:50'],
            'saju_context' => ['nullable', 'array'],
        ]);

        $session = $request->user()->chatSessions()->create([
            'name' => $data['name'] ?? $request->user()->name,
            'saju_context' => $data['saju_context'] ?? null,
        ]);

        $greeting = $this->firstGreeting($session);

        $session->messages()->create([
            'role' => 'assistant',
            'content' => $greeting,
        ]);

        return response()->json([
            'chat_session_id' => $session->id,
            'message' => $greeting,
        ]);
    }

    public function sendMessage(Request $request, ChatSession $chatSession): JsonResponse
    {
        $this->authorizeOwner($request, $chatSession);

        if ($this->needsPayment($request)) {
            return $this->paymentRequiredResponse();
        }

        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        if (! config('services.anthropic.key')) {
            // 화면에 노출되는 문구라 벤더명 없이 일반화합니다. 실제 원인은 .env의 ANTHROPIC_API_KEY 미설정입니다.
            return response()->json([
                'error' => 'AI 상담 기능이 아직 설정되지 않았어요. 서버 관리자에게 문의해 주세요.',
            ], 422);
        }

        $chatSession->messages()->create([
            'role' => 'user',
            'content' => $data['message'],
        ]);
        $chatSession->touch();

        // 세션 시작 시 만들어 둔 인사말(assistant)은 실제 AI가 생성한 턴이 아니므로
        // Anthropic API에는 첫 메시지가 반드시 user 여야 하는 규칙에 맞춰 제외합니다.
        $filtered = $chatSession->messages()
            ->get()
            ->skipWhile(fn ($m) => $m->role !== 'user')
            ->values();

        // 대화가 길어지면(기본 약 25턴 이상) 오래된 구간을 요약해서 history_summary에 접어 넣고,
        // 최근 구간(RECENT_WINDOW)만 그대로 전송합니다. 매 턴 전체 히스토리를 재전송하면
        // 대화가 길어질수록 턴당 비용이 끝없이 커지는 문제를 막기 위함이에요.
        $this->compressHistoryIfNeeded($chatSession, $filtered);

        $history = $filtered->slice($chatSession->summarized_count)
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->values()
            ->all();

        $response = Http::withHeaders([
            'x-api-key' => config('services.anthropic.key'),
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
            'model' => config('services.anthropic.model'),
            'max_tokens' => config('services.anthropic.max_tokens'),
            'system' => $this->systemPrompt($chatSession),
            'messages' => $history,
        ]);

        if ($response->failed()) {
            return response()->json([
                'error' => 'AI 응답을 받아오지 못했어요. (status '.$response->status().')',
                'detail' => $response->json('error.message'),
            ], 502);
        }

        $reply = collect($response->json('content', []))
            ->where('type', 'text')
            ->pluck('text')
            ->implode('');

        if ($reply === '') {
            $reply = '죄송해요, 지금은 답변을 만들지 못했어요. 다시 한 번 말씀해 주시겠어요?';
        }

        $chatSession->messages()->create([
            'role' => 'assistant',
            'content' => $reply,
        ]);

        // AI 응답 1회 = 코인 1개. 실패한 호출은 위에서 이미 return 됐으므로 성공한 턴만 차감됩니다.
        $request->user()->decrement('credits');

        return response()->json([
            'message' => $reply,
            'credits' => $request->user()->fresh()->credits,
        ]);
    }

    private function authorizeOwner(Request $request, ChatSession $chatSession): void
    {
        abort_unless($chatSession->user_id === $request->user()->id, 403, '내 상담 세션이 아니에요.');
    }

    /**
     * $filtered(인사말을 제외한 전체 대화, user로 시작해서 계속 교대하는 배열)에서
     * "아직 요약에 반영되지 않은 + 최근 구간(RECENT_WINDOW)보다 오래된" 메시지가
     * SUMMARIZE_BATCH_SIZE개 이상 쌓였으면, 그 구간을 한 번에 요약해서
     * $chatSession->history_summary/summarized_count에 반영합니다.
     *
     * RECENT_WINDOW가 홀수이고 $filtered 길이가 항상 홀수(마지막이 방금 보낸 답장 없는
     * user 메시지)이기 때문에, recentStart는 항상 짝수가 되고 그 결과 요약 이후 구간은
     * 항상 user 메시지로 시작하게 됩니다 — Anthropic API가 요구하는 규칙을 계속 만족시켜요.
     */
    private function compressHistoryIfNeeded(ChatSession $chatSession, Collection $filtered): void
    {
        $recentStart = max(0, $filtered->count() - self::RECENT_WINDOW);
        $oldUnsummarizedCount = $recentStart - $chatSession->summarized_count;

        if ($oldUnsummarizedCount < self::SUMMARIZE_BATCH_SIZE) {
            return;
        }

        $batch = $filtered->slice($chatSession->summarized_count, $oldUnsummarizedCount);
        $newSummary = $this->summarizeBatch($chatSession->history_summary, $batch);

        if ($newSummary === null) {
            // 요약 호출이 실패해도 치명적이지 않음 — 이번 턴은 오래된 구간까지 그대로
            // 전송되고, 다음 턴에 다시 압축을 시도합니다.
            return;
        }

        $chatSession->update([
            'history_summary' => $newSummary,
            'summarized_count' => $recentStart,
        ]);
    }

    /**
     * 기존 요약(있다면) + 새로 오간 대화 구간을 하나로 합쳐 짧은 요약으로 갱신합니다.
     * 이 호출 자체의 입력 크기는 항상 일정(기존 요약 + 배치 하나 분량)해서, 대화가
     * 아무리 길어져도 이 요약 호출의 비용은 커지지 않습니다.
     */
    private function summarizeBatch(?string $existingSummary, Collection $batch): ?string
    {
        $transcript = $batch->map(fn ($m) => ($m->role === 'user' ? '상담자: ' : '코치: ').$m->content)->implode("\n");

        $prompt = "당신은 연애 상담 대화의 요약가입니다. 아래 '기존 요약'(있다면)과 '새로 오간 대화'를 하나로 합쳐서, ".
            "앞으로 상담을 자연스럽게 이어가는 데 필요한 맥락만 남긴 요약으로 갱신하세요. ".
            "상담자의 상황·고민·성향, 이미 나눈 조언의 핵심을 중심으로 400자 이내 한국어 문단 하나로 쓰고, ".
            "새 요약 본문만 출력하세요(다른 설명이나 따옴표 없이).\n\n".
            ($existingSummary ? "--- 기존 요약 ---\n{$existingSummary}\n\n" : '').
            "--- 새로 오간 대화 ---\n{$transcript}";

        $response = Http::withHeaders([
            'x-api-key' => config('services.anthropic.key'),
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(30)->post('https://api.anthropic.com/v1/messages', [
            'model' => config('services.anthropic.model'),
            'max_tokens' => 400,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        if ($response->failed()) {
            return null;
        }

        $text = collect($response->json('content', []))
            ->where('type', 'text')
            ->pluck('text')
            ->implode('');

        return $text !== '' ? trim($text) : null;
    }

    /**
     * 결제 페이지로 보내야 하는지 판단합니다.
     * - 운영 환경: 코인(credits)이 0 이하일 때만.
     * - 로컬 환경(APP_ENV=local): 코인과 상관없이 항상 결제 페이지를 보여줍니다.
     *   실제 AI 호출 없이 결제 화면 UI/흐름만 테스트하기 위한 용도입니다.
     */
    private function needsPayment(Request $request): bool
    {
        return app()->environment('local') || $request->user()->credits <= 0;
    }

    private function paymentRequiredResponse(): JsonResponse
    {
        return response()->json([
            'needs_payment' => true,
            'error' => '무료로 받은 메시지를 다 쓰셨어요. 코인을 충전하면 계속 대화할 수 있어요.',
        ], 402);
    }

    private function systemPrompt(ChatSession $session): string
    {
        $ctx = $session->saju_context ?? [];
        $name = $session->name ?: '이 사람';

        $lines = [
            "당신은 '결'이라는 사주 기반 연애 상담 서비스의 연애 코치입니다. 수많은 연애 상담 사례를 참고해 답하는 전문 코치처럼 행동하세요.",
            "상담자를 '{$name}님'이라고 부르며, 아래 사주 정보를 참고 자료로 삼아 공감적이고 실질적인 연애 상담을 제공하세요.",
        ];

        if (! empty($ctx)) {
            $lines[] = '--- 상담자의 사주 요약 ---';

            if (! empty($ctx['pillars'])) {
                $p = $ctx['pillars'];
                $lines[] = '사주팔자: 년주 '.($p['year'] ?? '-').', 월주 '.($p['month'] ?? '-').
                    ', 일주 '.($p['day'] ?? '-').', 시주 '.($p['hour'] ?? '시간 미상');
            }
            if (! empty($ctx['dayElement'])) {
                $lines[] = '일간(본인을 상징하는 글자): '.$ctx['dayElement'].' ('.($ctx['dayYinYang'] ?? '').')';
            }
            if (! empty($ctx['wuxingCount'])) {
                $counts = collect($ctx['wuxingCount'])->map(fn ($v, $k) => "{$k} {$v}개")->implode(', ');
                $lines[] = '오행 분포: '.$counts;
            }
            if (! empty($ctx['sinsals'])) {
                $lines[] = '눈에 띄는 신살: '.implode(', ', $ctx['sinsals']);
            }
        } else {
            $lines[] = '(사주 정보 없이 시작된 상담이에요. 일반적인 연애 상담으로 진행하세요.)';
        }

        if (! empty($session->history_summary)) {
            $lines[] = '--- 지금까지 상담 맥락 요약(오래된 대화를 압축한 것) ---';
            $lines[] = $session->history_summary;
        }

        $lines = array_merge($lines, [
            '--- 답변 원칙 ---',
            '1. 단정적인 예언("반드시 ~된다")이나 미신적 확언은 피하고, 사주 해석은 성향을 이해하는 참고 관점으로만 자연스럽게 녹여내세요.',
            '2. 실제 상담처럼 상대의 상황과 감정에 먼저 공감한 뒤, 구체적이고 실행 가능한 조언을 주세요.',
            '3. 한국어로, 친근하지만 신뢰감 있는 코치 톤으로 답하세요. 한 번에 3~6문장 정도로, 장황하게 늘어놓지 마세요.',
            '4. 자해·폭력·스토킹 등 안전이 걸린 이야기가 나오면 사주 해석보다 안전을 우선하고, 전문기관 상담을 권하세요.',
            '5. 사용자가 AI인지 직접 물어보면 정직하게 연애상담 사례를 많이 학습한 AI라고 답하세요.',
        ]);

        return implode("\n", $lines);
    }

    private function firstGreeting(ChatSession $session): string
    {
        $name = $session->name ?: '';
        $prefix = $name ? "{$name}님, " : '';

        return "{$prefix}반가워요. 저는 결의 연애 코치예요. 지금까지 많은 연애 상담 사례를 참고해서 도와드릴게요. 어떤 고민이 있으신지 편하게 이야기해 주세요.";
    }
}
