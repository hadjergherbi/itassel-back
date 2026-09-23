<?php

namespace App\Mail;

use App\Models\Doleance;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccuseComplementMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Doleance $doleance)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "ITASSEL — Votre réponse pour le dossier {$this->doleance->reference} a bien été reçue",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.accuse-complement');
    }
}
