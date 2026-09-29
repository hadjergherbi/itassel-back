<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Qualite extends Model
{
    use HasFactory;

    public const LIBELLES = [
        'association',
        'entreprise ou institution',
        'athlète',
        'jeune homme / jeune femme',
        'Employé dans le secteur',
        'djalia',
    ];

    protected $primaryKey = 'id_qualite';

    protected $fillable = ['libelle', 'selectionnable', 'ordre'];

    protected $casts = [
        'selectionnable' => 'boolean',
    ];

    public function doleances()
    {
        return $this->hasMany(Doleance::class, 'id_qualite', 'id_qualite');
    }

    public function scopeSelectionnables($query)
    {
        return $query->where('selectionnable', true)
            ->orderBy('ordre')
            ->orderBy('id_qualite');
    }

    public static function synchroniserReferentiel(): void
    {
        foreach (self::LIBELLES as $index => $libelle) {
            $existante = static::query()
                ->whereRaw('LOWER(TRIM(libelle)) = ?', [mb_strtolower(trim($libelle))])
                ->first();

            $attributs = [
                'libelle' => $libelle,
                'selectionnable' => true,
                'ordre' => ($index + 1) * 10,
            ];

            if ($existante) {
                $existante->fill($attributs)->save();
            } else {
                static::create($attributs);
            }
        }

        $officiels = array_map(
            fn (string $libelle) => mb_strtolower(trim($libelle)),
            self::LIBELLES
        );

        static::query()->each(function (self $qualite) use ($officiels) {
            if (in_array(mb_strtolower(trim($qualite->libelle)), $officiels, true)) {
                return;
            }

            if ($qualite->doleances()->exists()) {
                $qualite->update(['selectionnable' => false]);
            } else {
                $qualite->delete();
            }
        });
    }
}
