@extends('emails.layout')

@php $bordure = app()->getLocale() === 'ar' ? 'border-right' : 'border-left'; @endphp

@section('content')
  <p>{{ __('emails.demande_complement.salutation', ['prenom' => $doleance->prenom, 'nom' => $doleance->nom]) }}</p>

  <p>{{ __('emails.demande_complement.intro', ['reference' => $doleance->reference]) }}</p>

  <p style="margin:20px 0 6px;font-size:12px;color:#6B7280;text-transform:uppercase;letter-spacing:1px;">
    {{ __('emails.demande_complement.question_label') }}
  </p>
  <p style="margin:0 0 16px;padding:12px 16px;background:#FAF5FF;{{ $bordure }}:4px solid #7C3AED;border-radius:4px;font-style:italic;">
    « {{ $complement->question }} »
  </p>

  @if ($complement->piece_exigee)
    <p>
      <strong>{{ __('emails.demande_complement.piece') }} :</strong> {{ $complement->description_piece }}<br>
      <span style="color:#6B7280;">{{ __('emails.demande_complement.piece_aide') }}</span>
    </p>
  @endif

  <p>{{ __('emails.demande_complement.consigne') }}</p>

  <p style="margin:20px 0;">
    <a href="{{ $lienSuivi }}"
       style="display:inline-block;background:#00984A;color:#FFFFFF;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:bold;">
      {{ __('emails.demande_complement.bouton') }}
    </a>
  </p>

  <p style="color:#6B7280;">{{ __('emails.demande_complement.code') }}</p>
@endsection
