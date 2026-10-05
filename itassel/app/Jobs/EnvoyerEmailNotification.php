<?php

namespace App\Jobs;

use App\Models\NotificationItassel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EnvoyerEmailNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public string $destinataire,
        public Mailable $mail,
        public int $idNotification,
        public string $reference,
    ) {}

    public function handle(): void
    {
        Mail::to($this->destinataire)->send($this->mail);

        NotificationItassel::whereKey($this->idNotification)->update([
            'etat_envoi' => 'transmis',
            'date_envoi' => now(),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        NotificationItassel::whereKey($this->idNotification)->update([
            'etat_envoi' => 'non_transmis',
            'date_envoi' => null,
        ]);

        Log::error('Échec d\'envoi email', [
            'reference' => $this->reference,
            'notification' => $this->idNotification,
            'exception' => $exception::class,
        ]);
    }
}
