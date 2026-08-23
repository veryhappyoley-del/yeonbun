<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'type',
        'order_id',
        'amount',
        'status',
        'payment_key',
        'title',
        'input',
        'content',
    ];

    protected function casts(): array
    {
        return [
            'input' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
