@extends('emails.layout')

@php $bordure = app()->getLocale() === 'ar' ? 'border-right' : 'border-left'; @endphp

@section('content')
  <p>{{ __('emails.reponse_service.salutation', ['prenom' => $doleance->prenom, 'nom' => $doleance->nom]) }}</p>

  <p>{{ __('emails.reponse_service.intro', ['reference' => $doleance->reference]) }}</p>

  <p style="margin:16px 0;padding:12px 16px;background:#F5F7FA;{{ $bordure }}:4px solid #006B3F;border-radius:4px;">
    {!! nl2br(e($reponse->contenu)) !!}
  </p>

  <p>{{ __('emails.reponse_service.historique') }}</p>

  <p style="margin:20px 0;">
    <a href="{{ $lienSuivi }}"
       style="display:inline-block;background:#00984A;color:#FFFFFF;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:bold;">
      {{ __('emails.reponse_service.bouton') }}
    </a>
  </p>
@endsection
