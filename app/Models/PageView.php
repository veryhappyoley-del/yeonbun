<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageView extends Model
{
    protected $fillable = ['path', 'user_id', 'visitor_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
