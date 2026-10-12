<?php

namespace App\Models;

use App\Support\Libelle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Nature extends Model
{
    use HasFactory;

    public const TOUTES_NATURES = 'Toutes natures';

    protected $primaryKey = 'id_nature';

    public static function estToutesNatures(?string $libelle): bool
    {
        return Libelle::equivalents($libelle, (string) config('itassel.systeme.nature', self::TOUTES_NATURES));
    }

    protected $fillable = ['libelle', 'famille'];

    public function scopePubliques($query)
    {
        return $query->whereRaw('LOWER(TRIM(libelle)) != ?', [mb_strtolower(self::TOUTES_NATURES)]);
    }

    public function doleances()
    {
        return $this->hasMany(Doleance::class, 'id_nature', 'id_nature');
    }
}
