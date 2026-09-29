<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CodeVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public string $reference,
        public int $dureeMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'ITASSEL — Votre code de vérification',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.code-verification');
    }
}
