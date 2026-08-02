<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordResetOtpMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public User $user,
        public string $otp,
        public int $expiresInMinutes
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Code OTP de réinitialisation - '.config('app.name'))
            ->view('emails.password-reset-otp');
    }
}
