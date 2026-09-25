<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Export journal — ITASSEL</title>
  <style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #222; }
    h1 { font-size: 14px; margin: 0 0 4px; }
    h2 { font-size: 12px; margin: 0 0 12px; font-weight: normal; }
    h3 { font-size: 11px; margin: 0 0 6px; }
    .meta { margin-bottom: 12px; }
    .meta p { margin: 2px 0; }
    table.donnees { width: 100%; border-collapse: collapse; }
    table.donnees th, table.donnees td { border: 1px solid #444; padding: 3px 4px; text-align: left; vertical-align: top; }
    table.donnees th { background: #e6e6e6; }
    table.donnees thead { display: table-header-group; }
    tr.echec td { color: #b42318; }
    table.chiffres { width: 100%; border-collapse: collapse; margin: 0 0 14px; }
    table.chiffres td { border: 1px solid #ccc; text-align: center; padding: 8px 6px; width: 33%; }
    table.chiffres .nombre { font-size: 14px; font-weight: bold; color: #006b3f; }
    table.chiffres .etiquette { font-size: 8px; color: #444; }
    table.grille { width: 100%; border-collapse: collapse; }
    table.grille > tbody > tr > td { border: none; vertical-align: top; width: 33%; padding: 6px 8px 12px 0; }
    table.legende { width: 100%; border-collapse: collapse; margin-top: 4px; }
    table.legende td { border: none; font-size: 8px; padding: 1px 4px 1px 0; }
    .vide { font-size: 9px; color: #6b7280; }
    .saut { page-break-before: always; }
    .footer { margin-top: 12px; font-size: 8px; color: #444; }
  </style>
</head>
<body>
  <h1>Ministère des Sports — ITASSEL</h1>
  <h2>Synthèse du journal</h2>
  <div class="meta">
    <p>Période : {{ $filtres_lisibles['periode'] }}</p>
    <p>Catégorie : {{ $filtres_lisibles['categorie'] }}</p>
    <p>Utilisateur : {{ $filtres_lisibles['utilisateur'] }}</p>
    <p>Résultat : {{ $filtres_lisibles['resultat'] }}</p>
    <p>Généré le {{ $genere_le }} par {{ $agent }}</p>
  </div>

  <table class="chiffres">
    <tr>
      <td>
        <div class="nombre">{{ $total }}</div>
        <div class="etiquette">Total</div>
      </td>
      <td>
        <div class="nombre">{{ $echecs_connexion }}</div>
        <div class="etiquette">Échecs de connexion</div>
      </td>
      <td>
        <div class="nombre">{{ $actions_sensibles }}</div>
        <div class="etiquette">Actions sensibles</div>
      </td>
    </tr>
  </table>

  <table class="grille">
    <tr>
      @foreach ($graphiques as $graphique)
        <td>
          <h3>{{ $graphique['titre'] }}</h3>
          @if (!empty($graphique['image']))
            <img src="{{ $graphique['image'] }}" alt="" width="140" height="140">
          @elseif (empty($graphique['legende']))
            <p class="vide">Aucune donnée</p>
          @else
            <p class="vide">Graphique indisponible</p>
          @endif
          @if (!empty($graphique['legende']))
            <table class="legende">
              @foreach ($graphique['legende'] as $part)
                <tr>
                  <td style="width:10px;background:{{ $part['couleur'] }};"></td>
                  <td>{{ $part['libelle'] }}</td>
                  <td>{{ $part['valeur'] }}</td>
                  <td>{{ number_format((float) $part['pourcentage'], 1, ',', ' ') }} %</td>
                </tr>
              @endforeach
            </table>
          @endif
        </td>
      @endforeach
    </tr>
  </table>

  <div class="saut">
    <h1>Ministère des Sports — ITASSEL</h1>
    <h2>Journal des actions</h2>

    <table class="donnees">
      <thead>
        <tr>
          <th>Date</th>
          <th>Utilisateur</th>
          <th>Action</th>
          <th>Détail</th>
          <th>IP</th>
          <th>Résultat</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($lignes as $journal)
          <tr class="{{ $journal->resultat === 'echec' ? 'echec' : '' }}">
            <td>{{ optional($journal->date_action)->format('d/m/Y H:i') }}</td>
            <td>{{ $journal->utilisateur ? trim($journal->utilisateur->prenom.' '.$journal->utilisateur->nom) : $journal->compte }}</td>
            <td>{{ $libelles[$journal->action] ?? $journal->action }}</td>
            <td>{{ $journal->detail }}</td>
            <td>{{ $journal->adresse_ip }}</td>
            <td>{{ $journal->resultat === 'echec' ? 'Échec' : 'Succès' }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>

    <p class="footer">Généré le {{ $genere_le }} par {{ $agent }}</p>
  </div>

  <script type="text/php">
    if (isset($pdf)) {
        $pdf->page_text(700, 560, 'Page {PAGE_NUM} / {PAGE_COUNT}', null, 8);
    }
  </script>
</body>
</html>
