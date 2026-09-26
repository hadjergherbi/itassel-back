@extends('exports._layout')

@section('contenu')
  @if (!empty($synthese))
    <div class="params">
      <p class="params-titre">Paramètres de l'export</p>
      <table class="params-table">
        <tr>
          <td class="cle">Service</td>
          <td class="val">{{ $service }}</td>
        </tr>
        <tr>
          <td class="cle">Période</td>
          <td class="val">{{ $date_debut }} → {{ $date_fin }}</td>
        </tr>
        <tr>
          <td class="cle">Natures</td>
          <td class="val">{{ $natures ? implode(', ', $natures) : 'Toutes' }}</td>
        </tr>
      </table>
    </div>

    <table class="kpi">
      <tr>
        <td>
          <div class="nombre">{{ $total }}</div>
          <div class="etiquette">Total</div>
        </td>
        <td class="sep">
          <div class="nombre">{{ $resolues }}</div>
          <div class="etiquette">Résolues</div>
        </td>
        <td class="sep">
          <div class="nombre">{{ $en_cours }}</div>
          <div class="etiquette">En cours</div>
        </td>
        <td class="sep">
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
              @include('exports._graphique', ['graphique' => $graphique])
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
    @if (empty($synthese))
      <div class="params">
        <p class="params-titre">Paramètres de l'export</p>
        <table class="params-table">
          <tr>
            <td class="cle">Service</td>
            <td class="val">{{ $service }}</td>
          </tr>
          <tr>
            <td class="cle">Période</td>
            <td class="val">{{ $date_debut }} → {{ $date_fin }}</td>
          </tr>
          <tr>
            <td class="cle">Natures</td>
            <td class="val">{{ $natures ? implode(', ', $natures) : 'Toutes' }}</td>
          </tr>
        </table>
      </div>
    @endif

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
        @foreach ($doleances as $index => $doleance)
          <tr class="{{ $index % 2 === 1 ? 'alterne' : '' }}">
            <td>{{ $doleance->reference }}</td>
            <td>{{ trim($doleance->prenom.' '.$doleance->nom) }}</td>
            <td>{{ $doleance->nature?->libelle }}</td>
            <td>{{ $doleance->wilaya }}</td>
            <td class="date">{{ optional($doleance->date_depot)->format('d/m/Y') }}</td>
            <td>{{ $doleance->statut?->libelle }}</td>
          </tr>
        @endforeach
      </tbody>
      <tfoot>
        <tr>
          <td colspan="6">{{ $doleances->count() }} doléance(s)</td>
        </tr>
      </tfoot>
    </table>
  </div>
@endsection
