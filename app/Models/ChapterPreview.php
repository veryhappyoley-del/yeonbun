<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * 결제 전 "무료 미리보기"용 챕터 캐시 1건. ReportChapter와 거의 같은 모양이지만 Report에
 * 묶이지 않고 (report_type, chapter_key, input_hash)로 찾습니다 — 자세한 설계 이유는
 * 마이그레이션(2026_08_24_000002_create_chapter_previews_table)과
 * App\Services\ChapterGenerator::previewInputHash() 주석 참고.
 */
class ChapterPreview extends Model
{
    use HasUuids;

    protected $fillable = [
        'report_type',
        'chapter_key',
        'input_hash',
        'input',
        'status',
        'content',
        'stop_reason',
        'output_tokens',
        'attempts',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'input' => 'array',
            'content' => 'array',
            'output_tokens' => 'integer',
            'attempts' => 'integer',
        ];
    }

    public function isReady(): bool
    {
        return $this->status === 'ready' && $this->content !== null;
    }
}
