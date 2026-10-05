@extends('emails.layout')

@php $bordure = app()->getLocale() === 'ar' ? 'border-right' : 'border-left'; @endphp

@section('content')
  <p>{{ __('emails.changement_statut.salutation', ['prenom' => $doleance->prenom, 'nom' => $doleance->nom]) }}</p>

  <p>{{ __('emails.changement_statut.intro', ['reference' => $doleance->reference]) }}</p>

  <p style="margin:20px 0 6px;font-size:12px;color:#6B7280;text-transform:uppercase;letter-spacing:1px;">
    {{ __('emails.changement_statut.statut_label') }}
  </p>
  <p style="margin:0 0 16px;font-size:20px;font-weight:bold;color:#006B3F;">
    {{ $statut->libelle }}
  </p>

  @if ($messageService)
    <p style="margin:0 0 16px;padding:12px 16px;background:#F5F7FA;{{ $bordure }}:4px solid #006B3F;border-radius:4px;">
      {!! nl2br(e($messageService)) !!}
    </p>
  @endif

  <p>{{ __('emails.changement_statut.consulter') }}</p>

  <p style="margin:20px 0;">
    <a href="{{ $lienSuivi }}"
       style="display:inline-block;background:#00984A;color:#FFFFFF;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:bold;">
      {{ __('emails.changement_statut.bouton') }}
    </a>
  </p>
@endsection
