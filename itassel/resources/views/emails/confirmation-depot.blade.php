@extends('emails.layout')

@section('content')
  <p>{{ __('emails.confirmation_depot.salutation', ['prenom' => $doleance->prenom, 'nom' => $doleance->nom]) }}</p>

  <p>{{ __('emails.confirmation_depot.intro') }}</p>

  <p style="margin:20px 0 6px;font-size:12px;color:#6B7280;text-transform:uppercase;letter-spacing:1px;">
    {{ __('emails.confirmation_depot.reference_label') }}
  </p>
  <p style="margin:0 0 20px;font-size:26px;font-weight:bold;color:#006B3F;letter-spacing:1px;">
    {{ $doleance->reference }}
  </p>

  <p><strong>{{ __('emails.confirmation_depot.objet') }} :</strong> {{ $doleance->objet }}</p>

  <p>{{ __('emails.confirmation_depot.suite') }}</p>
@endsection
