@extends('emails.layout')

@section('content')
  <p style="margin:0 0 8px;font-size:12px;color:#6B7280;text-transform:uppercase;letter-spacing:1px;">
    Notification interne
  </p>
  <p style="margin:0 0 16px;font-size:18px;font-weight:bold;color:#006B3F;">
    {{ $titre }}
  </p>

  <p>Dossier <strong>{{ $doleance->reference }}</strong></p>

  <p style="margin:0 0 16px;padding:12px 16px;background:#F5F7FA;border-left:4px solid #006B3F;border-radius:4px;">
    {!! nl2br(e($texte)) !!}
  </p>

  <p style="margin:20px 0;">
    <a href="{{ $lienDossier }}"
       style="display:inline-block;background:#00984A;color:#FFFFFF;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:bold;">
      Ouvrir le dossier
    </a>
  </p>
@endsection
