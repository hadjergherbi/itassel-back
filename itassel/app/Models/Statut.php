<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Statut extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_statut';

    protected $fillable = ['libelle', 'couleur'];

    public function doleances()
    {
        return $this->hasMany(Doleance::class, 'id_statut', 'id_statut');
    }
}
