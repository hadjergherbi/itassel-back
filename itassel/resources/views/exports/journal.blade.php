@extends('exports._layout')

@section('contenu')
  <div class="params">
    <p class="params-titre">Paramètres de l'export</p>
    <table class="params-table">
      <tr>
        <td class="cle">Période</td>
        <td class="val">{{ $filtres_lisibles['periode'] }}</td>
      </tr>
      <tr>
        <td class="cle">Catégorie</td>
        <td class="val">{{ $filtres_lisibles['categorie'] }}</td>
      </tr>
      <tr>
        <td class="cle">Utilisateur</td>
        <td class="val">{{ $filtres_lisibles['utilisateur'] }}</td>
      </tr>
      <tr>
        <td class="cle">Résultat</td>
        <td class="val">{{ $filtres_lisibles['resultat'] }}</td>
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
        <div class="nombre">{{ $echecs_connexion }}</div>
        <div class="etiquette">Échecs de connexion</div>
      </td>
      <td class="sep">
        <div class="nombre">{{ $actions_sensibles }}</div>
        <div class="etiquette">Actions sensibles</div>
      </td>
    </tr>
  </table>

  <table class="grille">
    <tr>
      <td>
        @foreach (array_slice($graphiques, 0, 2) as $graphique)
          @include('exports._graphique', ['graphique' => $graphique])
        @endforeach
      </td>
      <td>
        @foreach (array_slice($graphiques, 2) as $graphique)
          @include('exports._graphique', ['graphique' => $graphique])
        @endforeach
      </td>
    </tr>
  </table>

  <div class="saut">
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
        @foreach ($lignes as $index => $journal)
          <tr class="{{ $index % 2 === 1 ? 'alterne' : '' }}">
            <td class="date">{{ optional($journal->date_action)->format('d/m/Y H:i') }}</td>
            <td>{{ $journal->utilisateur ? trim($journal->utilisateur->prenom.' '.$journal->utilisateur->nom) : $journal->compte }}</td>
            <td>{{ $libelles[$journal->action] ?? $journal->action }}</td>
            <td>{{ $journal->detail }}</td>
            <td class="ip">{{ $journal->adresse_ip }}</td>
            <td class="resultat {{ $journal->resultat === 'echec' ? 'echec' : '' }}">
              {{ $journal->resultat === 'echec' ? '● Échec' : '● Succès' }}
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endsection
