<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class TestMail extends Command
{
    protected $signature = 'itassel:test-mail
        {email : Adresse de destination du message de test}';

    protected $description = 'Envoie un email de test avec la configuration mail actuelle (diagnostic SMTP).';

    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Adresse email invalide.');

            return self::FAILURE;
        }

        $mailer = (string) config('mail.default');
        $smtp = config('mail.mailers.smtp', []);

        $this->info('Configuration mail actuelle');
        $this->line('  MAIL_MAILER : '.$mailer);
        $this->line('  MAIL_HOST   : '.(string) ($smtp['host'] ?? ''));
        $this->line('  MAIL_PORT   : '.(string) ($smtp['port'] ?? ''));
        $this->line('  MAIL_USERNAME : '.(filled($smtp['username'] ?? null) ? '(défini)' : '(vide)'));
        $this->line('  MAIL_PASSWORD : '.(filled($smtp['password'] ?? null) ? '(défini, non affiché)' : '(vide)'));
        $this->line('  FROM        : '.(string) config('mail.from.address').' / '.(string) config('mail.from.name'));
        $this->newLine();

        try {
            Mail::raw(
                "Message de test ITASSEL.\nMailer={$mailer}\nEnvoyé le ".now()->toDateTimeString(),
                function ($message) use ($email) {
                    $message->to($email)->subject('ITASSEL — Test mail');
                },
            );
        } catch (Throwable $e) {
            $this->error('Échec d\'envoi : '.$e->getMessage());
            $this->line('Classe : '.$e::class);

            return self::FAILURE;
        }

        $this->info("Email de test envoyé vers {$email} (selon le mailer « {$mailer} »).");
        if ($mailer === 'log') {
            $this->warn('MAIL_MAILER=log : aucun SMTP réel — vérifier storage/logs/laravel.log.');
        }

        return self::SUCCESS;
    }
}
