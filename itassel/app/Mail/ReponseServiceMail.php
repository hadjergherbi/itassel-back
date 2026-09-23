<?php

namespace App\Mail;

use App\Models\Doleance;
use App\Models\Reponse;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReponseServiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $lienSuivi;

    public function __construct(
        public Doleance $doleance,
        public Reponse $reponse,
    ) {
        $base = config('app.frontend_url') ?: 'http://localhost:5173';
        $this->lienSuivi = rtrim($base, '/').'/suivre';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "ITASSEL — Réponse du service pour votre dossier {$this->doleance->reference}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.reponse-service');
    }
}
