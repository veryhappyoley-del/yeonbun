<?php

namespace App\Mail;

use App\Models\DailyFortune;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * "오늘의 운세" 이메일 1건. 이 프로젝트에 Mail 발송이 처음 생기는 지점이다 —
 * .env의 MAIL_MAILER가 기본값 'log'인 동안은 실제로 발송되지 않고
 * storage/logs/laravel.log에 렌더링된 내용만 남는다(배포 전 실제 SMTP/발송
 * 서비스 연결 필요, claude/출시전_점검리스트.md 참고).
 */
class DailyFortuneMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public DailyFortune $dailyFortune)
    {
    }

    public function build(): self
    {
        $content = $this->dailyFortune->content ?? [];
        $headline = $content['headline'] ?? '오늘의 운세';

        return $this->subject("[연록] 오늘의 운세 — {$headline}")
            ->view('emails.daily-fortune', [
                'dailyFortune' => $this->dailyFortune,
                'content' => $content,
            ]);
    }
}
