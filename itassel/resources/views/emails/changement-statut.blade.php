@extends('emails.layout')

@section('content')
  <p>Bonjour {{ $doleance->prenom }} {{ $doleance->nom }},</p>

  <p>Le statut de votre dossier <strong>{{ $doleance->reference }}</strong> a changé.</p>

  <p style="margin:20px 0 6px;font-size:12px;color:#6B7280;text-transform:uppercase;letter-spacing:1px;">
    Nouveau statut
  </p>
  <p style="margin:0 0 16px;font-size:20px;font-weight:bold;color:#006B3F;">
    {{ $statut->libelle }}
  </p>

  @if ($messageService)
    <p style="margin:0 0 16px;padding:12px 16px;background:#F5F7FA;border-left:4px solid #006B3F;border-radius:4px;">
      {!! nl2br(e($messageService)) !!}
    </p>
  @endif

  <p>Vous pouvez consulter votre dossier à tout moment :</p>

  <p style="margin:20px 0;">
    <a href="{{ $lienSuivi }}"
       style="display:inline-block;background:#00984A;color:#FFFFFF;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:bold;">
      Suivre ma demande
    </a>
  </p>
@endsection
