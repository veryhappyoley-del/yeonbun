<?php

namespace App\Http\Controllers;

use App\Http\Middleware\TrackPageView;
use App\Jobs\GenerateChapterPreviewJob;
use App\Models\ChapterPreview;
use App\Models\PreviewView;
use App\ReportTypes\ReportTypeRegistry;
use App\Services\ChapterGenerator;
use App\Support\BotDetector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * 결제 전 "무료 미리보기" 챕터 1개를 요청/폴링하는 엔드포인트. 로그인 여부와 무관하게
 * 접근 가능합니다(무료 궁합 결과 화면은 비로그인 사용자도 볼 수 있음, routes/web.php에서
 * auth 미들웨어 밖에 등록됨) — 대신 throttle 미들웨어로 남용을 막습니다.
 *
 * ReportType::$freePreviewChapterKey로 명시적으로 허용된 (type, chapter) 조합만 받고,
 * 나머지는 404로 막습니다 — 아무 챕터나 결제 전에 생성 요청할 수 있게 열어두면 20챕터
 * 전체를 무료로 다 만들 수 있게 되어버리므로, 타입별로 정확히 1개 챕터만 허용됩니다.
 *
 * 같은 (type, chapter, input_hash) 조합이면 App\Models\ChapterPreview가 이미 있는지부터
 * 확인해서(App\Services\ChapterGenerator::previewInputHash()), 있으면 API를 다시 부르지
 * 않고 그 상태/내용을 그대로 돌려줍니다 — 프론트(public/js/app.js)가 이 엔드포인트를
 * 폴링용으로도 그대로 재사용합니다(멱등: 여러 번 불러도 새로 생성 요청이 중복되지 않음).
 *
 * (2026-09-08 추가) 이 요청 자체가 "몇 명이 정보를 입력하고 결제 전 미리보기까지
 * 봤는지"를 보여주는 신호라, App\Models\PreviewView에 방문자 쿠키 기준으로 한 번만
 * 기록해서 관리자 대시보드 퍼널(방문 → 미리보기 → 가입 → 결제)에 쓴다.
 */
class ChapterPreviewController extends Controller
{
    // 같은 챕터가 반복 실패해도 무한정 재시도하지 않도록. 결제 후 정식 생성(재시도 2회)과
    // 같은 기준.
    private const MAX_RETRY_ATTEMPTS = 2;

    public function store(Request $request, ChapterGenerator $generator): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(ReportTypeRegistry::keys())],
            'chapter' => ['required', 'string'],
            'input' => ['required', 'array'],
        ]);

        $type = ReportTypeRegistry::get($data['type']);

        if (! $type || $type->freePreviewChapterKey !== $data['chapter']) {
            abort(404);
        }

        $chapterSpec = $type->findChapter($data['chapter']);

        if (! $chapterSpec) {
            abort(404);
        }

        $hash = $generator->previewInputHash($chapterSpec, $data['input']);

        $preview = ChapterPreview::firstOrCreate(
            [
                'report_type' => $data['type'],
                'chapter_key' => $data['chapter'],
                'input_hash' => $hash,
            ],
            [
                'input' => $data['input'],
                'status' => 'pending',
            ]
        );

        if ($preview->wasRecentlyCreated) {
            GenerateChapterPreviewJob::dispatch($preview);
        } elseif ($preview->status === 'failed' && $preview->attempts < self::MAX_RETRY_ATTEMPTS) {
            $preview->update(['status' => 'pending']);
            GenerateChapterPreviewJob::dispatch($preview);
        }

        // (2026-09-08 추가) "결제 전, 정보를 입력하고 미리보기까지 본 사람"을 관리자
        // 대시보드 퍼널에 넣어 달라는 요청 대응 — App\Models\ChapterPreview는 입력값이
        // 같으면 서로 다른 두 사람도 캐시 1건을 공유해서 "몇 명"인지와는 다르다.
        // page_views와 같은 방문자 쿠키(yeonbun_visitor)로 사람을 구분해서 별도로 기록한다.
        // 이 요청 자체가 이미 "정보를 입력하고 미리보기를 요청"한 시점이라(성공/실패와
        // 무관하게) 여기서 기록하는 게 맞다 — 생성 성공/실패 비율은 이미 무료 미리보기
        // 생성 현황(chapter_previews)에서 따로 보고 있다.
        $visitorId = $request->cookie(TrackPageView::COOKIE);
        $isNewVisitorCookie = ! $visitorId;
        if ($isNewVisitorCookie) {
            $visitorId = (string) Str::uuid();
        }

        PreviewView::firstOrCreate(
            [
                'visitor_id' => $visitorId,
                'report_type' => $data['type'],
                'chapter_key' => $data['chapter'],
            ],
            [
                'user_id' => $request->user()?->id,
                'user_agent' => $request->userAgent() ? Str::limit($request->userAgent(), 500, '') : null,
                'is_bot' => BotDetector::isBot($request->userAgent()),
            ]
        );

        $response = response()->json([
            'status' => $preview->status,
            'content' => $preview->status === 'ready' ? $preview->content : null,
        ]);

        // 방문 쿠키가 아예 없던 요청(이론상 드묾 — 보통 계산기 페이지 로드 때 이미
        // 심어짐)이면 여기서도 심어서, 다음 폴링 호출부터는 같은 방문자로 잡히게 한다.
        if ($isNewVisitorCookie) {
            $response->headers->setCookie(cookie(TrackPageView::COOKIE, $visitorId, 60 * 24 * 365));
        }

        return $response;
    }
}
