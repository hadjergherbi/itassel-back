<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Service extends Model
{
    use HasFactory;

    public const TOUS_LES_DOMAINES = 'Tous les domaines';

    protected $primaryKey = 'id_service';

    protected $fillable = ['nom_service', 'id_responsable'];

    public static function estTousLesDomaines(?string $nom): bool
    {
        return is_string($nom)
            && mb_strtolower(trim($nom)) === mb_strtolower(self::TOUS_LES_DOMAINES);
    }

    public function scopeAssignables($query)
    {
        return $query->whereRaw('LOWER(TRIM(nom_service)) != ?', [mb_strtolower(self::TOUS_LES_DOMAINES)]);
    }

    public static function regleIdAssignable(): array
    {
        return [
            'integer',
            Rule::exists('services', 'id_service')->where(
                fn ($q) => $q->whereRaw('LOWER(TRIM(nom_service)) != ?', [mb_strtolower(self::TOUS_LES_DOMAINES)])
            ),
        ];
    }

    public function responsable()
    {
        return $this->belongsTo(Utilisateur::class, 'id_responsable', 'id_utilisateur');
    }

    public function utilisateurs()
    {
        return $this->hasMany(Utilisateur::class, 'id_service', 'id_service');
    }

    public function doleances()
    {
        return $this->hasMany(Doleance::class, 'id_service', 'id_service');
    }
}
