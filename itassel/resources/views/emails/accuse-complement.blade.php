@extends('emails.layout')

@section('content')
  <p>Bonjour {{ $doleance->prenom }} {{ $doleance->nom }},</p>

  <p>
    Votre réponse à la demande de complément pour le dossier
    <strong>{{ $doleance->reference }}</strong> a bien été reçue.
    Le service concerné va poursuivre l'étude de votre dossier.
  </p>

  <p>Vous serez informé(e) par email de la suite donnée à votre demande.</p>
@endsection
