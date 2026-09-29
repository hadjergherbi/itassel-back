@extends('emails.layout')

@section('content')
  <p>Bonjour {{ $doleance->prenom }} {{ $doleance->nom }},</p>

  <p>
    Le service qui traite votre dossier <strong>{{ $doleance->reference }}</strong>
    a besoin d'une information complémentaire pour poursuivre l'étude de votre demande.
  </p>

  <p style="margin:20px 0 6px;font-size:12px;color:#6B7280;text-transform:uppercase;letter-spacing:1px;">
    Question du service
  </p>
  <p style="margin:0 0 16px;padding:12px 16px;background:#FAF5FF;border-left:4px solid #7C3AED;border-radius:4px;font-style:italic;">
    « {{ $complement->question }} »
  </p>

  @if ($complement->piece_exigee)
    <p>
      <strong>Pièce justificative demandée :</strong> {{ $complement->description_piece }}<br>
      <span style="color:#6B7280;">Ce fichier est obligatoire pour répondre (PDF, JPG ou PNG, 5 Mo maximum).</span>
    </p>
  @endif

  <p>Pour répondre, ouvrez le lien ci-dessous (votre référence est déjà renseignée) :</p>

  <p style="margin:20px 0;">
    <a href="{{ $lienSuivi }}"
       style="display:inline-block;background:#00984A;color:#FFFFFF;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:bold;">
      Répondre à la demande
    </a>
  </p>

  <p style="color:#6B7280;">Un code de vérification vous sera envoyé par email pour accéder à votre dossier.</p>
@endsection
