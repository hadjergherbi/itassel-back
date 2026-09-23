<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_service';

    protected $fillable = ['nom_service', 'id_responsable'];

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
