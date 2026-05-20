<?php

namespace App\Notifications;

use App\Services\Auth\OtpService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailOtpNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $code,
        public readonly string $purpose,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your verification code')
            ->line('Your verification code:')
            ->line($this->code)
            ->line('This code expires in '.OtpService::TTL_MINUTES.' minutes.')
            ->line('If you did not request this code, ignore this email.');
    }
}
