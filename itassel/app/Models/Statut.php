<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Statut extends Model
{
    use HasFactory;

    public const NOUVELLE = 'nouvelle';

    public const EN_COURS = 'en_cours';

    public const INFORMATION_DEMANDEE = 'information_demandee';

    public const RESOLUE = 'resolue';

    public const CLOTUREE = 'cloturee';

    public const NON_FONDEE = 'non_fondee';

    public const DOUBLE = 'double';

    public const REPONSE_APPORTEE = 'reponse_apportee';

    public const HORS_COMPETENCE = 'hors_competence';

    public const NON_RETENUE = 'non_retenue';

    protected $primaryKey = 'id_statut';

    protected $fillable = ['code', 'libelle', 'couleur', 'message_citoyen', 'selectionnable', 'ordre'];

    public static function parCode(string $code): self
    {
        return static::where('code', $code)->firstOrFail();
    }

    public static function codesIssues(): array
    {
        $issues = config('itassel.issues', []);

        return array_is_list($issues) ? $issues : array_keys($issues);
    }

    public static function codesFinaux(): array
    {
        return array_values(array_unique([
            ...static::codesIssues(),
            self::NON_FONDEE,
            self::CLOTUREE,
        ]));
    }

    public static function codesOuverts(): array
    {
        return [
            self::NOUVELLE,
            self::EN_COURS,
            self::INFORMATION_DEMANDEE,
        ];
    }

    public static function estIssue(?string $code): bool
    {
        return $code !== null && in_array($code, static::codesIssues(), true);
    }

    public static function estFinal(?string $code): bool
    {
        return $code !== null && in_array($code, static::codesFinaux(), true);
    }

    public static function autorisesDepuis(?string $code)
    {
        $codes = config('itassel.transitions')[$code] ?? [];

        if ($codes === []) {
            return static::query()->whereRaw('1 = 0')->get(['id_statut', 'code', 'libelle', 'couleur']);
        }

        return static::whereIn('code', $codes)
            ->where('selectionnable', true)
            ->orderBy('ordre')
            ->orderBy('id_statut')
            ->get(['id_statut', 'code', 'libelle', 'couleur', 'message_citoyen']);
    }

    public function versApi(): array
    {
        return $this->only(['id_statut', 'code', 'libelle', 'couleur']);
    }

    public function doleances()
    {
        return $this->hasMany(Doleance::class, 'id_statut', 'id_statut');
    }
}
