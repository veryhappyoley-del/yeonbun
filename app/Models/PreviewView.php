<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "이 방문자(visitor_id)가 이 리포트 타입의 무료 미리보기 단계까지 도달했다"는 사실을
 * 딱 1행만 남기는 테이블 — 자세한 설계 이유는 마이그레이션
 * (2026_09_08_000002_create_preview_views_table) 주석 참고. App\Models\ChapterPreview와
 * 달리 "몇 건 생성됐는지"가 아니라 "몇 명이 봤는지"를 세기 위한 용도다.
 */
class PreviewView extends Model
{
    protected $fillable = ['visitor_id', 'report_type', 'chapter_key', 'user_id', 'user_agent', 'is_bot'];

    protected $casts = [
        'is_bot' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
