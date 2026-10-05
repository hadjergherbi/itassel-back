@extends('emails.layout')

@section('content')
  <p>{{ __('emails.code_verification.salutation') }}</p>

  <p>{{ __('emails.code_verification.intro', ['reference' => $reference]) }}</p>

  <p style="margin:20px 0;font-size:32px;font-weight:bold;color:#006B3F;letter-spacing:8px;text-align:center;">
    {{ substr($code, 0, 3) }} {{ substr($code, 3, 3) }}
  </p>

  <p>{{ __('emails.code_verification.validite', ['minutes' => $dureeMinutes]) }}</p>

  <p style="color:#6B7280;">{{ __('emails.code_verification.ignore') }}</p>
@endsection
