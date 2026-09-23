@extends('emails.layout')

@section('content')
  <p>Bonjour {{ $doleance->prenom }} {{ $doleance->nom }},</p>

  <p>
    L'information demandée pour votre dossier <strong>{{ $doleance->reference }}</strong>
    n'est plus nécessaire. Aucune action n'est attendue de votre part :
    le dossier poursuit son traitement.
  </p>

  <p style="margin:20px 0;">
    <a href="{{ $lienSuivi }}"
       style="display:inline-block;background:#00984A;color:#FFFFFF;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:bold;">
      Suivre ma demande
    </a>
  </p>
@endsection
