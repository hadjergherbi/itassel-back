<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Export doléances — ITASSEL</title>
  <style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #222; }
    h1 { font-size: 14px; margin: 0 0 4px; }
    h2 { font-size: 12px; margin: 0 0 12px; font-weight: normal; }
    .meta { margin-bottom: 12px; }
    .meta p { margin: 2px 0; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #444; padding: 4px 6px; text-align: left; }
    th { background: #e6e6e6; }
    tfoot td { font-weight: bold; }
    .footer { margin-top: 16px; font-size: 9px; color: #444; }
  </style>
</head>
<body>
  <h1>Ministère des Sports — ITASSEL</h1>
  <h2>Export des doléances</h2>

  <div class="meta">
    <p>Service : {{ $service }}</p>
    <p>Période : {{ $date_debut }} → {{ $date_fin }}</p>
    <p>Natures incluses : {{ $natures ? implode(', ', $natures) : 'Toutes' }}</p>
    <p>Généré le {{ $genere_le }} par {{ $agent }}</p>
  </div>

  <table>
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

  <div class="footer">
    Document interne — ITASSEL — page <script type="text/php">
      if (isset($pdf)) {
          $pdf->page_text(40, 560, 'Document interne — ITASSEL — page {PAGE_NUM} / {PAGE_COUNT}', null, 8);
      }
    </script>
  </div>
</body>
</html>
