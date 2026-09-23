<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Qualite extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_qualite';

    protected $fillable = ['libelle'];

    public function doleances()
    {
        return $this->hasMany(Doleance::class, 'id_qualite', 'id_qualite');
    }
}
