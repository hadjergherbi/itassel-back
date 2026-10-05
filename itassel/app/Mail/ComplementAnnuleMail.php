<?php

namespace App\Mail;

use App\Mail\Concerns\MailCitoyenLocalise;
use App\Models\Doleance;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ComplementAnnuleMail extends Mailable
{
    use MailCitoyenLocalise, Queueable, SerializesModels;

    public string $lienSuivi;

    public function __construct(public Doleance $doleance)
    {
        $base = rtrim((string) (config('itassel.frontend_url') ?: 'http://localhost:5173'), '/');
        $this->lienSuivi = $base.'/suivre?reference='.urlencode($doleance->reference);
        $this->appliquerLangue($doleance->langue ?? 'fr');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->sujetTraduit('emails.complement_annule.sujet', [
                'reference' => $this->doleance->reference,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.complement-annule');
    }
}
