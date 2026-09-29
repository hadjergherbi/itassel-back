<?php

namespace App\Mail;

use App\Models\Doleance;
use App\Models\Statut;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ChangementStatutMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $lienSuivi;

    public function __construct(
        public Doleance $doleance,
        public Statut $statut,
        public ?string $messageService = null,
    ) {
        $base = rtrim((string) (config('itassel.frontend_url') ?: 'http://localhost:5173'), '/');
        $this->lienSuivi = $base.'/suivre?reference='.urlencode($doleance->reference);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "ITASSEL — Mise à jour de votre dossier {$this->doleance->reference}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.changement-statut');
    }
}
