@extends('emails.layout')

@section('content')
  <p>Bonjour {{ $doleance->prenom }} {{ $doleance->nom }},</p>

  <p>Votre doléance a bien été enregistrée. Elle est transmise au service concerné.</p>

  <p style="margin:20px 0 6px;font-size:12px;color:#6B7280;text-transform:uppercase;letter-spacing:1px;">
    Votre référence de suivi
  </p>
  <p style="margin:0 0 20px;font-size:26px;font-weight:bold;color:#006B3F;letter-spacing:1px;">
    {{ $doleance->reference }}
  </p>

  <p><strong>Objet :</strong> {{ $doleance->objet }}</p>

  <p>
    Conservez cette référence. Pour consulter votre dossier, rendez-vous sur
    « Suivre ma demande » : un code de vérification vous sera envoyé à cette adresse.
  </p>
@endsection
