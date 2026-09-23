<?php

namespace App\Mail;

use App\Models\Utilisateur;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReinitialisationMotDePasseMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $lien;

    public function __construct(public Utilisateur $utilisateur, string $jeton)
    {
        $base = rtrim((string) config('itassel.frontend_url'), '/');
        $this->lien = $base.'/definir-mot-de-passe?jeton='.$jeton;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'ITASSEL — Réinitialisation du mot de passe');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.reinitialisation-mot-de-passe');
    }
}
