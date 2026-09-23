@extends('emails.layout')

@section('content')
  <p>Bonjour,</p>

  <p>Voici votre code pour consulter le dossier <strong>{{ $reference }}</strong> :</p>

  <p style="margin:20px 0;font-size:32px;font-weight:bold;color:#006B3F;letter-spacing:8px;text-align:center;">
    {{ substr($code, 0, 3) }} {{ substr($code, 3, 3) }}
  </p>

  <p>
    Ce code est valable <strong>{{ $dureeMinutes }} minutes</strong> et ne peut être utilisé qu'une seule fois.
  </p>

  <p style="color:#6B7280;">
    Si vous n'êtes pas à l'origine de cette demande, ignorez simplement cet email.
  </p>
@endsection
