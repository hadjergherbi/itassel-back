@extends('emails.layout')

@section('content')
  <p>Bonjour {{ $utilisateur->prenom }},</p>

  <p>
    Une réinitialisation de mot de passe a été demandée pour votre compte ITASSEL.
    Ce lien expire après {{ (int) config('itassel.jetons.reinitialisation_minutes', 60) }} minutes.
  </p>

  <p style="margin:20px 0;">
    <a href="{{ $lien }}"
       style="display:inline-block;background:#00984A;color:#FFFFFF;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:bold;">
      Choisir un nouveau mot de passe
    </a>
  </p>

  <p style="word-break:break-all;font-size:13px;color:#4B5563;">
    {{ $lien }}
  </p>

  <p>
    Si vous n'êtes pas à l'origine de cette demande, ignorez ce message :
    votre mot de passe actuel reste inchangé.
  </p>
@endsection
