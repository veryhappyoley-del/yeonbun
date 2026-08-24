<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Report extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'type',
        'schema_version',
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
            'schema_version' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * schema_version=2(챕터형) 리포트의 챕터들. 레거시(schema_version=1) 리포트는
     * 이 관계가 항상 빈 컬렉션을 반환합니다(report_chapters에 행을 만들지 않으므로).
     */
    public function chapters(): HasMany
    {
        return $this->hasMany(ReportChapter::class)->orderBy('sort_order');
    }

    public function isChaptered(): bool
    {
        return $this->schema_version === 2;
    }
}
