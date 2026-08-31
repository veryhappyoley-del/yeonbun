<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FortuneSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'status',
        'toss_customer_key',
        'toss_billing_key',
        'price',
        'next_billing_date',
        'failed_attempts',
        'canceled_at',
    ];

    protected function casts(): array
    {
        return [
            'next_billing_date' => 'date',
            'canceled_at' => 'datetime',
            'failed_attempts' => 'integer',
            'price' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
