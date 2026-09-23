<?php

namespace App\Mail;

use App\Models\Doleance;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotificationInterneMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $lienDossier;

    public function __construct(
        public Doleance $doleance,
        public string $titre,
        public string $texte,
    ) {
        $base = config('app.frontend_url') ?: 'http://localhost:5173';
        $this->lienDossier = rtrim($base, '/').'/admin/doleances/'.$this->doleance->reference;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "ITASSEL — {$this->titre}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.notification-interne');
    }
}
