<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ModeleMessage extends Model
{
    use HasFactory;

    protected $table = 'modeles_message';

    protected $primaryKey = 'id_modele';

    protected $fillable = ['titre', 'contenu', 'type_usage'];

    /** @var array<string, string> */
    private const ALIASES_USAGE = [
        'Réponse' => 'reponse',
        'Complément' => 'complement',
        'conclusion' => 'autre',
    ];

    /**
     * Convertit les anciennes libellés vers les codes config (Réponse, Complément, …).
     */
    public static function normaliserCodeUsage(string $valeur): string
    {
        return self::ALIASES_USAGE[$valeur] ?? $valeur;
    }

    /**
     * Valeurs à chercher en base pour un filtre usage (code + anciennes libellés).
     *
     * @return list<string>
     */
    public static function valeursUsagePourFiltre(string $brut): array
    {
        $code = self::normaliserCodeUsage($brut);
        $valeurs = [$brut, $code];

        foreach (self::ALIASES_USAGE as $ancien => $nouveau) {
            if ($nouveau === $code) {
                $valeurs[] = $ancien;
            }
        }

        return array_values(array_unique($valeurs));
    }

    /**
     * Accepte usage ou type_usage, puis normalise les anciennes valeurs.
     */
    public static function preparerUsageRequete(Request $request): void
    {
        $brut = $request->input('usage');
        if (! is_string($brut) || $brut === '') {
            $brut = $request->input('type_usage');
        }

        if (! is_string($brut)) {
            return;
        }

        $request->merge(['usage' => self::normaliserCodeUsage($brut)]);
    }

    /**
     * Codes d'usage dont la liste de statuts est vide (« autre ») ou contient $statut.
     *
     * @return list<string>
     */
    public static function usagesPourStatut(string $statut): array
    {
        $codes = [];
        foreach (config('itassel.messages_usages', []) as $code => $def) {
            $statuts = $def['statuts'] ?? [];
            if ($statuts === [] || in_array($statut, $statuts, true)) {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    /**
     * Filtre optionnel : usage / type_usage / statut.
     * Un usage inconnu ne provoque pas d'erreur : liste vide.
     */
    public static function appliquerFiltres(Builder $query, Request $request): Builder
    {
        $usage = $request->input('usage', $request->input('type_usage'));
        if (is_string($usage) && $usage !== '') {
            $query->whereIn('type_usage', self::valeursUsagePourFiltre($usage));
        }

        if ($request->filled('statut')) {
            $query->whereIn('type_usage', self::usagesPourStatut((string) $request->input('statut')));
        }

        return $query;
    }

    public function versApi(): array
    {
        $usages = config('itassel.messages_usages', []);
        $code = self::normaliserCodeUsage((string) $this->type_usage);

        return [
            'id' => $this->id_modele,
            'id_modele' => $this->id_modele,
            'titre' => $this->titre,
            'contenu' => $this->contenu,
            'usage' => $code,
            'usage_libelle' => $usages[$code]['libelle'] ?? $code,
        ];
    }
}
