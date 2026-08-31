<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyFortune extends Model
{
    protected $fillable = [
        'user_id',
        'fortune_date',
        'status',
        'content',
        'last_error',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'fortune_date' => 'date',
            'content' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isReady(): bool
    {
        return $this->status === 'ready' && $this->content !== null;
    }
}
