<?php

namespace App\Mail;

use App\Mail\Concerns\MailCitoyenLocalise;
use App\Models\Doleance;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ConfirmationDepotMail extends Mailable
{
    use MailCitoyenLocalise, Queueable, SerializesModels;

    public function __construct(public Doleance $doleance)
    {
        $this->appliquerLangue($doleance->langue ?? 'fr');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->sujetTraduit('emails.confirmation_depot.sujet', [
                'reference' => $this->doleance->reference,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.confirmation-depot');
    }
}
