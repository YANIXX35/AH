<?php

namespace App\Mail;

use App\Models\AppNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public AppNotification $notification
    ) {
    }

    public function envelope(): Envelope
    {
        $typeLabel = match ((string) $this->notification->type) {
            'success' => 'Succes',
            'warning' => 'Alerte',
            'error' => 'Erreur',
            default => 'Info',
        };

        return new Envelope(
            subject: '[SITIAME]['.$typeLabel.'] '.$this->notification->title
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.app-notification',
            with: [
                'notification' => $this->notification,
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
