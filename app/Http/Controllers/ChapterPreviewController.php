<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateChapterPreviewJob;
use App\Models\ChapterPreview;
use App\ReportTypes\ReportTypeRegistry;
use App\Services\ChapterGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        return response()->json([
            'status' => $preview->status,
            'content' => $preview->status === 'ready' ? $preview->content : null,
        ]);
    }
}
