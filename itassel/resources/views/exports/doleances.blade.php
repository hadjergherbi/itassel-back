<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Export doléances — ITASSEL</title>
  <style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #222; }
    h1 { font-size: 14px; margin: 0 0 4px; }
    h2 { font-size: 12px; margin: 0 0 12px; font-weight: normal; }
    h3 { font-size: 11px; margin: 0 0 6px; }
    .meta { margin-bottom: 12px; }
    .meta p { margin: 2px 0; }
    table.donnees { width: 100%; border-collapse: collapse; }
    table.donnees th, table.donnees td { border: 1px solid #444; padding: 4px 6px; text-align: left; }
    table.donnees th { background: #e6e6e6; }
    tfoot td { font-weight: bold; }
    .footer { margin-top: 16px; font-size: 9px; color: #444; }
    table.chiffres { width: 100%; border-collapse: collapse; margin: 0 0 14px; }
    table.chiffres td { border: 1px solid #ccc; text-align: center; padding: 8px 6px; width: 25%; }
    table.chiffres .nombre { font-size: 14px; font-weight: bold; color: #006b3f; }
    table.chiffres .etiquette { font-size: 8px; color: #444; }
    table.grille { width: 100%; border-collapse: collapse; }
    table.grille > tbody > tr > td { border: none; vertical-align: top; width: 50%; padding: 6px 8px 12px 0; }
    table.legende { width: 100%; border-collapse: collapse; margin-top: 4px; }
    table.legende td { border: none; font-size: 8px; padding: 1px 4px 1px 0; }
    .vide { font-size: 9px; color: #6b7280; }
    .saut { page-break-before: always; }
  </style>
</head>
<body>
  @if (!empty($synthese))
    <h1>Ministère des Sports — ITASSEL</h1>
    <h2>Synthèse</h2>
    <div class="meta">
      <p>Service : {{ $service }}</p>
      <p>Période : {{ $date_debut }} → {{ $date_fin }}</p>
    </div>

    <table class="chiffres">
      <tr>
        <td>
          <div class="nombre">{{ $total }}</div>
          <div class="etiquette">Total</div>
        </td>
        <td>
          <div class="nombre">{{ $resolues }}</div>
          <div class="etiquette">Résolues</div>
        </td>
        <td>
          <div class="nombre">{{ $en_cours }}</div>
          <div class="etiquette">En cours</div>
        </td>
        <td>
          <div class="nombre">{{ number_format((float) $taux_resolution, 1, ',', ' ') }} %</div>
          <div class="etiquette">Taux de résolution</div>
        </td>
      </tr>
    </table>

    <table class="grille">
      @foreach (array_chunk($graphiques, 2) as $ligne)
        <tr>
          @foreach ($ligne as $graphique)
            <td>
              <h3>{{ $graphique['titre'] }}</h3>
              @if (!empty($graphique['image']))
                <img src="{{ $graphique['image'] }}" alt="" width="160" height="160">
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
          @if (count($ligne) === 1)
            <td></td>
          @endif
        </tr>
      @endforeach
    </table>
  @endif

  <div class="{{ !empty($synthese) ? 'saut' : '' }}">
    <h1>Ministère des Sports — ITASSEL</h1>
    <h2>Export des doléances</h2>

    <div class="meta">
      <p>Service : {{ $service }}</p>
      <p>Période : {{ $date_debut }} → {{ $date_fin }}</p>
      <p>Natures incluses : {{ $natures ? implode(', ', $natures) : 'Toutes' }}</p>
      <p>Généré le {{ $genere_le }} par {{ $agent }}</p>
    </div>

    <table class="donnees">
      <thead>
        <tr>
          <th>Référence</th>
          <th>Demandeur</th>
          <th>Nature</th>
          <th>Wilaya</th>
          <th>Date de dépôt</th>
          <th>Statut</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($doleances as $doleance)
          <tr>
            <td>{{ $doleance->reference }}</td>
            <td>{{ trim($doleance->prenom.' '.$doleance->nom) }}</td>
            <td>{{ $doleance->nature?->libelle }}</td>
            <td>{{ $doleance->wilaya }}</td>
            <td>{{ optional($doleance->date_depot)->format('d/m/Y') }}</td>
            <td>{{ $doleance->statut?->libelle }}</td>
          </tr>
        @endforeach
      </tbody>
      <tfoot>
        <tr>
          <td colspan="6">Total : {{ $doleances->count() }} doléance(s)</td>
        </tr>
      </tfoot>
    </table>
  </div>

  <div class="footer">
    Document interne — ITASSEL — page <script type="text/php">
      if (isset($pdf)) {
          $pdf->page_text(40, 560, 'Document interne — ITASSEL — page {PAGE_NUM} / {PAGE_COUNT}', null, 8);
      }
    </script>
  </div>
</body>
</html>
