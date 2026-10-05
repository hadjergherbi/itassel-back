@extends('exports._layout')

@section('contenu')
  <div class="params">
    <p class="params-titre">Statistiques du tableau de bord</p>
    <table class="params-table">
      <tr>
        <td class="cle">Période</td>
        <td class="val">{{ $periode['libelle'] }} ({{ $periode['date_debut'] }} → {{ $periode['date_fin'] }})</td>
      </tr>
      <tr>
        <td class="cle">Généré le</td>
        <td class="val">{{ $genere_le }}</td>
      </tr>
      <tr>
        <td class="cle">Agent</td>
        <td class="val">{{ $agent }}</td>
      </tr>
    </table>
  </div>

  <table class="kpi">
    <tr>
      <td>
        <div class="nombre">{{ $indicateurs['total'] }}</div>
        <div class="etiquette">Total</div>
      </td>
      <td class="sep">
        <div class="nombre">{{ $indicateurs['nouvelles'] }}</div>
        <div class="etiquette">Nouvelles</div>
      </td>
      <td class="sep">
        <div class="nombre">{{ $indicateurs['en_cours'] }}</div>
        <div class="etiquette">En cours</div>
      </td>
      <td class="sep">
        <div class="nombre">{{ $indicateurs['resolues'] }}</div>
        <div class="etiquette">Résolues</div>
      </td>
    </tr>
  </table>
  <table class="kpi">
    <tr>
      <td>
        <div class="nombre">{{ number_format((float) $taux_resolution, 1, ',', ' ') }} %</div>
        <div class="etiquette">Taux de résolution</div>
      </td>
      <td class="sep">
        <div class="nombre">{{ $indicateurs['delai_moyen_jours'] === null ? '—' : number_format((float) $indicateurs['delai_moyen_jours'], 1, ',', ' ') }}</div>
        <div class="etiquette">Délai moyen (jours)</div>
      </td>
    </tr>
  </table>

  <h3 class="section">Par service</h3>
  <table class="donnees">
    <thead>
      <tr>
        <th>Service</th>
        <th>Total</th>
        <th>Nouvelles</th>
        <th>En cours</th>
        <th>Résolues</th>
        <th>Autres</th>
        <th>À traiter</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($par_service as $index => $service)
        <tr class="{{ $index % 2 === 1 ? 'alterne' : '' }}">
          <td>{{ $service['nom_service'] }}</td>
          <td>{{ $service['total'] }}</td>
          <td>{{ $service['nouvelles'] }}</td>
          <td>{{ $service['en_cours'] }}</td>
          <td>{{ $service['resolues'] }}</td>
          <td>{{ $service['autres'] }}</td>
          <td>{{ $service['a_traiter'] }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>

  <table class="grille" style="margin-top:16px;">
    @foreach (array_chunk($graphiques, 2) as $ligne)
      <tr>
        @foreach ($ligne as $graphique)
          <td>@include('exports._graphique', ['graphique' => $graphique])</td>
        @endforeach
        @if (count($ligne) === 1)
          <td></td>
        @endif
      </tr>
    @endforeach
  </table>

  <h3 class="section">Évolution par mois</h3>
  <table class="donnees">
    <thead>
      <tr>
        <th>Mois</th>
        <th>Dépôts</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($par_mois as $index => $mois)
        <tr class="{{ $index % 2 === 1 ? 'alterne' : '' }}">
          <td>{{ $mois['libelle'] }} {{ $mois['mois'] }}</td>
          <td>{{ $mois['total'] }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>

  <h3 class="section">Issues</h3>
  <table class="donnees">
    <thead>
      <tr>
        <th>Issue</th>
        <th>Nombre</th>
      </tr>
    </thead>
    <tbody>
      @foreach (['resolue', 'reponse_apportee', 'hors_competence', 'non_retenue', 'double'] as $index => $code)
        <tr class="{{ $index % 2 === 1 ? 'alterne' : '' }}">
          <td>{{ $issues_libelles[$code] ?? $code }}</td>
          <td>{{ $issues[$code] ?? 0 }}</td>
        </tr>
      @endforeach
      <tr>
        <td>Dossiers ouverts</td>
        <td>{{ $issues['ouverts'] ?? 0 }}</td>
      </tr>
      <tr class="alterne">
        <td>Terminés (y compris à reclasser)</td>
        <td>{{ $issues['termines_total'] ?? 0 }}</td>
      </tr>
    </tbody>
  </table>
@endsection
