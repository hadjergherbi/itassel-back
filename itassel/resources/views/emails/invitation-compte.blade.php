@extends('emails.layout')

@section('content')
  <p>Bonjour {{ $utilisateur->prenom }},</p>

  <p>
    Un compte administrateur ITASSEL a été créé pour vous.
    Cliquez sur le bouton ci-dessous pour définir votre mot de passe.
    Ce lien expire après {{ (int) config('itassel.jetons.invitation_heures', 72) }} heures.
  </p>

  <p style="margin:20px 0;">
    <a href="{{ $lien }}"
       style="display:inline-block;background:#00984A;color:#FFFFFF;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:bold;">
      Définir mon mot de passe
    </a>
  </p>
@endsection
