<?php

namespace App\Mail;

use App\Models\Doleance;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ComplementAnnuleMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $lienSuivi;

    public function __construct(public Doleance $doleance)
    {
        $base = rtrim((string) (config('itassel.frontend_url') ?: 'http://localhost:5173'), '/');
        $this->lienSuivi = $base.'/suivre?reference='.urlencode($doleance->reference);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "ITASSEL — Votre dossier {$this->doleance->reference} : information plus nécessaire",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.complement-annule');
    }
}
