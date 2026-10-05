@extends('emails.layout')

@section('content')
  <p>{{ __('emails.complement_annule.salutation', ['prenom' => $doleance->prenom, 'nom' => $doleance->nom]) }}</p>

  <p>{{ __('emails.complement_annule.intro', ['reference' => $doleance->reference]) }}</p>

  <p style="margin:20px 0;">
    <a href="{{ $lienSuivi }}"
       style="display:inline-block;background:#00984A;color:#FFFFFF;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:bold;">
      {{ __('emails.complement_annule.bouton') }}
    </a>
  </p>
@endsection
