<?php

namespace App\Mail;

use App\Mail\Concerns\MailCitoyenLocalise;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CodeVerificationMail extends Mailable
{
    use MailCitoyenLocalise, Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public string $reference,
        public int $dureeMinutes,
        public string $langue = 'fr',
    ) {
        $this->appliquerLangue($langue);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->sujetTraduit('emails.code_verification.sujet'),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.code-verification');
    }
}
