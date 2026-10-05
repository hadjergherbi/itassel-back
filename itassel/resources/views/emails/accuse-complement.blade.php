@extends('emails.layout')

@section('content')
  <p>{{ __('emails.accuse_complement.salutation', ['prenom' => $doleance->prenom, 'nom' => $doleance->nom]) }}</p>

  <p>{{ __('emails.accuse_complement.intro', ['reference' => $doleance->reference]) }}</p>

  <p>{{ __('emails.accuse_complement.suite') }}</p>
@endsection
