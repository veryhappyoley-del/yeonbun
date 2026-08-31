<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "오늘의 운세" 구독 가입 시 한 번 입력받아 저장하는 생년월일시. 계산기(saju.blade.php)는
 * 여전히 매번 입력을 클라이언트에서 처리하고 이 테이블을 읽지 않는다 — 이 프로필은
 * 서버(GenerateDailyFortunes 커맨드)가 사용자 접속 없이도 매일 계산해야 할 때만 쓰인다.
 */
class SajuProfile extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'birth_date',
        'birth_hour',
        'birth_minute',
        'birth_time_unknown',
        'gender',
        'sido',
        'sigungu',
        'longitude',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'birth_time_unknown' => 'boolean',
            'longitude' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
