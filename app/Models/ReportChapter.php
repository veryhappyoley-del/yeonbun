<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * schema_version=2(챕터형) 리포트를 구성하는 챕터 1개. ChapterGenerator(2단계)가
 * 채워 넣고, chapter-reader.blade.php(3단계)가 렌더링합니다.
 *
 * 레거시(schema_version=1) 리포트는 이 모델을 전혀 쓰지 않습니다.
 */
class ReportChapter extends Model
{
    use HasUuids;

    protected $fillable = [
        'report_id',
        'chapter_key',
        'sort_order',
        'title',
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
            'content' => 'array',
            'sort_order' => 'integer',
            'output_tokens' => 'integer',
            'attempts' => 'integer',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function isReady(): bool
    {
        return $this->status === 'ready' && $this->content !== null;
    }
}
