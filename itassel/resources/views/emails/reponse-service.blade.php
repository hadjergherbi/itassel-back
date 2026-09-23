@extends('emails.layout')

@section('content')
  <p>Bonjour {{ $doleance->prenom }} {{ $doleance->nom }},</p>

  <p>Le service a répondu à votre dossier <strong>{{ $doleance->reference }}</strong> :</p>

  <p style="margin:16px 0;padding:12px 16px;background:#F5F7FA;border-left:4px solid #006B3F;border-radius:4px;">
    {!! nl2br(e($reponse->contenu)) !!}
  </p>

  <p>Cette réponse est aussi disponible dans l'historique de votre dossier :</p>

  <p style="margin:20px 0;">
    <a href="{{ $lienSuivi }}"
       style="display:inline-block;background:#00984A;color:#FFFFFF;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:bold;">
      Suivre ma demande
    </a>
  </p>
@endsection
