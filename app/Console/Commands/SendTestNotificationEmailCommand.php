<?php

namespace App\Console\Commands;

use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Console\Command;

class SendTestNotificationEmailCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'app:notification:test-email
        {email : Email du compte destinataire}
        {--type=info : Type de notification (info|success|warning|error)}
        {--title= : Titre personnalise}
        {--body= : Corps personnalise}
        {--action= : URL action optionnelle}';

    /**
     * @var string
     */
    protected $description = 'Cree une notification interne et declenche l email associe.';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $this->error("Aucun utilisateur trouve pour l email: {$email}");
            return self::FAILURE;
        }

        if (! (bool) ($user->email_notifications ?? false)) {
            $this->warn("Le compte {$email} a email_notifications desactive.");
            $this->line('Activez "Notifications email operationnelles" dans le profil puis retestez.');
            return self::FAILURE;
        }

        $type = (string) $this->option('type');
        if (! in_array($type, ['info', 'success', 'warning', 'error'], true)) {
            $this->error('Type invalide. Valeurs autorisees: info, success, warning, error.');
            return self::FAILURE;
        }

        $title = (string) ($this->option('title') ?: 'Test notification email');
        $body = (string) ($this->option('body') ?: 'Ceci est une notification de test envoyee depuis la commande Artisan.');
        $actionUrl = (string) ($this->option('action') ?: route('notifications.index'));

        $notification = AppNotification::query()->create([
            'user_id' => $user->id,
            'title' => $title,
            'body' => $body,
            'type' => $type,
            'action_url' => $actionUrl,
        ]);

        $this->info('Notification creee avec succes.');
        $this->line('ID: '.$notification->id);
        $this->line('Destinataire: '.$email);
        $this->line('Type: '.$type);
        $this->line('Note: si MAIL_MAILER n est pas "sync", lancer un worker queue pour l envoi.');

        return self::SUCCESS;
    }
}
