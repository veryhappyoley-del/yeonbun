<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ChatSession extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'name',
        'saju_context',
        'history_summary',
        'summarized_count',
    ];

    protected function casts(): array
    {
        return [
            'saju_context' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)->orderBy('created_at');
    }

    /**
     * 관리자 목록에서 미리보기로 쓸 가장 최근 메시지 한 건.
     */
    public function lastMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class)->latestOfMany();
    }
}
