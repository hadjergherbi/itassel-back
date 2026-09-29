<?php

namespace App\Mail;

use App\Models\Doleance;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ConfirmationDepotMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Doleance $doleance) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "ITASSEL — Votre doléance {$this->doleance->reference} a bien été enregistrée",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.confirmation-depot');
    }
}
