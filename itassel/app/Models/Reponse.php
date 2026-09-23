<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reponse extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_reponse';

    protected $fillable = ['contenu', 'date_publication', 'id_doleance', 'id_auteur'];

    protected $casts = ['date_publication' => 'datetime'];

    public function doleance()
    {
        return $this->belongsTo(Doleance::class, 'id_doleance', 'id_doleance');
    }

    public function auteur()
    {
        return $this->belongsTo(Utilisateur::class, 'id_auteur', 'id_utilisateur')->withTrashed();
    }
}
