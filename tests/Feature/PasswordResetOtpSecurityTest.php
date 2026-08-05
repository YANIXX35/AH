<?php

namespace Tests\Feature;

use App\Mail\PasswordResetOtpMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetOtpSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_otp_is_emailed_and_the_user_is_told_it_was_sent(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $response = $this->post('/forgot-password', ['email' => $user->email]);

        $response->assertRedirect(route('password.reset.form', ['email' => $user->email]));
        $response->assertSessionHas('status');
        Mail::assertSent(PasswordResetOtpMail::class);
    }

    public function test_when_the_mail_provider_fails_the_otp_is_never_written_to_the_logs(): void
    {
        $user = User::factory()->create();

        Mail::shouldReceive('to')
            ->once()
            ->andThrow(new \RuntimeException('Connexion SMTP refusée (simulation de test).'));

        Log::spy();

        $response = $this->post('/forgot-password', ['email' => $user->email]);

        // L'échec doit être franc : pas de faux message de succès, pas de secours "log mailer".
        $response->assertSessionHasErrors('email');

        Log::shouldNotHaveReceived('warning', function (string $message, array $context) {
            return array_key_exists('otp', $context);
        });
        Log::shouldNotHaveReceived('info', function (string $message, array $context) {
            return array_key_exists('otp', $context);
        });
    }
}
