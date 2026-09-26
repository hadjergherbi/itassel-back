<h3 class="section">{{ $graphique['titre'] }}</h3>
@if (empty($graphique['legende']))
  <p class="vide">Aucune donnée</p>
@else
  <table class="barres">
    @foreach ($graphique['legende'] as $part)
      @php $pct = max(0, min(100, (float) $part['pourcentage'])); @endphp
      <tr>
        <td class="barre-libelle">{{ $part['libelle'] }}</td>
        <td class="barre-piste-cell">
          <table class="piste">
            <tr>
              @if ($pct > 0)
                <td style="width: {{ $pct }}%; height: 6px; background: {{ $part['couleur'] }};"></td>
              @endif
              @if ($pct < 100)
                <td style="width: {{ 100 - $pct }}%; height: 6px; background: #E6ECE9;"></td>
              @endif
            </tr>
          </table>
        </td>
        <td class="barre-val">{{ $part['valeur'] }}</td>
        <td class="barre-pct">{{ number_format($pct, 1, ',', ' ') }} %</td>
      </tr>
    @endforeach
  </table>
@endif
