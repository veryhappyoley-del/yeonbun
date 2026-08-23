<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'plan', 'order_id', 'credits', 'amount', 'status', 'payment_key'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
