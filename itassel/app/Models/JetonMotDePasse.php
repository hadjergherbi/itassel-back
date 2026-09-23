<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JetonMotDePasse extends Model
{
    protected $table = 'jetons_mot_de_passe';
    protected $primaryKey = 'id_jeton';

    protected $fillable = [
        'id_utilisateur', 'type', 'jeton_hash', 'expire_le', 'utilise_le', 'id_createur',
    ];

    protected $casts = [
        'expire_le'  => 'datetime',
        'utilise_le' => 'datetime',
    ];

    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, 'id_utilisateur', 'id_utilisateur');
    }

    public function createur()
    {
        return $this->belongsTo(Utilisateur::class, 'id_createur', 'id_utilisateur');
    }

    public function estUtilisable(): bool
    {
        return $this->utilise_le === null && $this->expire_le->isFuture();
    }
}
