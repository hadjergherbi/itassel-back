<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>{{ $titre_document }} — ITASSEL</title>
  <style>
    @font-face {
      font-family: 'IBM Plex Sans';
      src: url('{{ \App\Support\ExportPdf::police('IBMPlexSans-Regular.ttf') }}') format('truetype');
      font-weight: 400;
      font-style: normal;
    }
    @font-face {
      font-family: 'IBM Plex Sans';
      src: url('{{ \App\Support\ExportPdf::police('IBMPlexSans-Medium.ttf') }}') format('truetype');
      font-weight: 500;
      font-style: normal;
    }
    @font-face {
      font-family: 'IBM Plex Sans';
      src: url('{{ \App\Support\ExportPdf::police('IBMPlexSans-SemiBold.ttf') }}') format('truetype');
      font-weight: 600;
      font-style: normal;
    }
    @font-face {
      font-family: 'IBM Plex Mono';
      src: url('{{ \App\Support\ExportPdf::police('IBMPlexMono-Regular.ttf') }}') format('truetype');
      font-weight: 400;
      font-style: normal;
    }

    @page { size: A4 landscape; margin: 22mm 16mm 16mm 16mm; }

    body {
      font-family: 'IBM Plex Sans', 'DejaVu Sans', sans-serif;
      font-size: 10pt;
      color: #12201A;
      line-height: 1.35;
      margin: 0;
    }

    .entete {
      position: fixed;
      top: -16mm;
      left: 0;
      right: 0;
    }
    table.bandeau { width: 100%; border-collapse: collapse; }
    table.bandeau td { border: none; vertical-align: top; padding: 0; }
    table.bandeau td.gauche { width: 62%; }
    table.bandeau td.droite { width: 38%; text-align: right; }
    .etat {
      font-size: 7pt;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      color: #5F6F66;
      margin: 0 0 2px;
    }
    .ministere { font-size: 11pt; font-weight: 600; color: #12201A; margin: 0; }
    .doc-titre { font-size: 11pt; font-weight: 600; color: #12201A; margin: 0 0 2px; }
    .doc-ref { font-size: 8.5pt; color: #5F6F66; margin: 0; letter-spacing: 0.04em; }
    .logo { height: 28px; }

    table.filet { width: 100%; border-collapse: collapse; margin-top: 6px; }
    table.filet td { padding: 0; height: 2px; font-size: 0; line-height: 0; }
    table.filet td.vert { width: 94%; background: #006B3F; }
    table.filet td.rouge { width: 6%; background: #C8102E; }

    h3.section {
      font-size: 13pt;
      font-weight: 600;
      color: #12201A;
      margin: 0 0 8px;
    }

    .params {
      background: #F5F7FA;
      padding: 8px 12px 6px;
      margin: 0 0 12px;
    }
    .params-titre {
      font-size: 8pt;
      font-weight: 600;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: #5F6F66;
      margin: 0 0 5px;
    }
    table.params-table { width: 100%; border-collapse: collapse; }
    table.params-table td { border: none; padding: 1px 8px 1px 0; font-size: 9.5pt; vertical-align: top; }
    table.params-table td.cle { width: 22%; color: #5F6F66; }
    table.params-table td.val { color: #12201A; }

    table.kpi { width: auto; border-collapse: collapse; margin: 0 0 14px; }
    table.kpi td { border: none; padding: 0 20px 0 0; vertical-align: top; white-space: nowrap; }
    table.kpi td.sep {
      width: 1px;
      padding: 2px 16px 2px 0;
      border: none;
      border-left: 1px solid #D5DDD8;
    }
    table.kpi .nombre { font-size: 18pt; font-weight: 600; color: #12201A; line-height: 1.1; }
    table.kpi .etiquette { font-size: 8.5pt; color: #5F6F66; margin-top: 2px; }

    table.grille { width: 100%; border-collapse: collapse; }
    table.grille > tbody > tr > td {
      border: none;
      vertical-align: top;
      width: 50%;
      padding: 0 16px 10px 0;
    }

    table.barres { width: 100%; border-collapse: collapse; }
    table.barres td { border: none; padding: 3px 6px 3px 0; vertical-align: middle; font-size: 8.5pt; }
    td.barre-libelle { width: 34%; color: #12201A; }
    td.barre-piste-cell { width: 44%; }
    td.barre-val { width: 10%; text-align: right; color: #12201A; }
    td.barre-pct { width: 12%; text-align: right; color: #5F6F66; }
    table.piste { width: 100%; border-collapse: collapse; }
    table.piste td { padding: 0; height: 6px; font-size: 0; line-height: 0; }

    p.vide { font-size: 9pt; color: #5F6F66; margin: 4px 0 0; }

    table.donnees { width: 100%; border-collapse: collapse; }
    table.donnees thead { display: table-header-group; }
    table.donnees th {
      border: none;
      border-bottom: 1px solid #12201A;
      padding: 4px 6px 5px 0;
      text-align: left;
      font-size: 8pt;
      font-weight: 500;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: #5F6F66;
      background: transparent;
    }
    table.donnees td {
      border: none;
      border-bottom: 1px solid #E3E8E5;
      padding: 7px 6px 7px 0;
      font-size: 9pt;
      vertical-align: top;
      color: #12201A;
    }
    table.donnees tbody tr.alterne td { background: #FAFBFA; }
    table.donnees td.date { font-variant-numeric: tabular-nums; white-space: nowrap; }
    table.donnees td.ip { font-family: 'IBM Plex Mono', 'DejaVu Sans Mono', monospace; font-size: 8pt; }
    table.donnees td.resultat { color: #5F6F66; }
    table.donnees td.resultat.echec { color: #B42318; }
    table.donnees tfoot td {
      border: none;
      border-top: 1px solid #12201A;
      padding: 7px 0 0;
      text-align: right;
      font-size: 9pt;
      color: #5F6F66;
    }

    .saut { page-break-before: always; }
  </style>
</head>
<body>
  <div class="entete">
    <table class="bandeau">
      <tr>
        <td class="gauche">
          @if ($logo = \App\Support\ExportPdf::logo())
            <img class="logo" src="file://{{ $logo }}" alt="">
          @endif
          <p class="etat">République Algérienne Démocratique et Populaire</p>
          <p class="ministere">Ministère des Sports</p>
        </td>
        <td class="droite">
          <p class="doc-titre">{{ $titre_document }}</p>
          <p class="doc-ref">{{ $reference }}</p>
        </td>
      </tr>
    </table>
    <table class="filet">
      <tr>
        <td class="vert"></td>
        <td class="rouge"></td>
      </tr>
    </table>
  </div>

  @yield('contenu')

  <script type="text/php">
    if (isset($pdf)) {
        $h = $pdf->get_height();
        $w = $pdf->get_width();
        $y = $h - 32;
        $gris = array(0.373, 0.435, 0.400);
        $pdf->page_text(45, $y, 'Document interne — ITASSEL · Généré le {{ $genere_le }} par {{ $agent }}', null, 7.5, $gris);
        $pdf->page_text($w - 92, $y, 'Page {PAGE_NUM} / {PAGE_COUNT}', null, 7.5, $gris);
    }
  </script>
</body>
</html>
