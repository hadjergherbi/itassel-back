<?php

namespace App\Mail;

use App\Mail\Concerns\MailCitoyenLocalise;
use App\Models\Complement;
use App\Models\Doleance;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DemandeComplementMail extends Mailable
{
    use MailCitoyenLocalise, Queueable, SerializesModels;

    public string $lienSuivi;

    public function __construct(
        public Doleance $doleance,
        public Complement $complement,
    ) {
        $this->lienSuivi = $this->lienSuiviPour($doleance);
        $this->appliquerLangue($doleance->langue ?? 'fr');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->sujetTraduit('emails.demande_complement.sujet', [
                'reference' => $this->doleance->reference,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.demande-complement');
    }

    private function lienSuiviPour(Doleance $doleance): string
    {
        $base = rtrim((string) (config('itassel.frontend_url') ?: 'http://localhost:5173'), '/');

        return $base.'/suivre?reference='.urlencode($doleance->reference);
    }
}
