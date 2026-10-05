<?php

namespace App\Mail;

use App\Mail\Concerns\MailCitoyenLocalise;
use App\Models\Doleance;
use App\Models\Reponse;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReponseServiceMail extends Mailable
{
    use MailCitoyenLocalise, Queueable, SerializesModels;

    public string $lienSuivi;

    public function __construct(
        public Doleance $doleance,
        public Reponse $reponse,
    ) {
        $base = rtrim((string) (config('itassel.frontend_url') ?: 'http://localhost:5173'), '/');
        $this->lienSuivi = $base.'/suivre?reference='.urlencode($doleance->reference);
        $this->appliquerLangue($doleance->langue ?? 'fr');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->sujetTraduit('emails.reponse_service.sujet', [
                'reference' => $this->doleance->reference,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.reponse-service');
    }
}
