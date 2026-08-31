<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'provider', 'provider_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    public function chatSessions(): HasMany
    {
        return $this->hasMany(ChatSession::class)->latest('updated_at');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest();
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class)->latest();
    }

    // (2026-08-31 추가) "오늘의 운세" 구독 — SajuProfile/DailyFortune/FortuneSubscription
    // 자세한 내용은 각 모델 참고.
    public function sajuProfile(): HasOne
    {
        return $this->hasOne(SajuProfile::class);
    }

    public function fortuneSubscription(): HasOne
    {
        return $this->hasOne(FortuneSubscription::class);
    }

    public function dailyFortunes(): HasMany
    {
        return $this->hasMany(DailyFortune::class)->latest('fortune_date');
    }
}
