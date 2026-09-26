<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Nature extends Model
{
    use HasFactory;

    public const TOUTES_NATURES = 'Toutes natures';

    protected $primaryKey = 'id_nature';

    public static function estToutesNatures(?string $libelle): bool
    {
        return is_string($libelle)
            && mb_strtolower(trim($libelle)) === mb_strtolower(self::TOUTES_NATURES);
    }

    protected $fillable = ['libelle', 'famille'];

    public function doleances()
    {
        return $this->hasMany(Doleance::class, 'id_nature', 'id_nature');
    }
}
