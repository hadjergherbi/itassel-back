<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Complement extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_complement';

    protected $fillable = [
        'question', 'piece_exigee', 'description_piece', 'reponse',
        'date_demande', 'date_reponse', 'etat', 'motif_annulation',
        'id_doleance', 'id_auteur', 'id_annule_par', 'date_annulation',
    ];

    protected $casts = [
        'date_demande' => 'datetime',
        'date_reponse' => 'datetime',
        'date_annulation' => 'datetime',
        'piece_exigee' => 'boolean',
    ];

    public function doleance()
    {
        return $this->belongsTo(Doleance::class, 'id_doleance', 'id_doleance');
    }

    public function auteur()
    {
        return $this->belongsTo(Utilisateur::class, 'id_auteur', 'id_utilisateur')->withTrashed();
    }

    public function piecesJointes()
    {
        return $this->hasMany(PieceJointe::class, 'id_complement', 'id_complement');
    }

    public function annulePar()
    {
        return $this->belongsTo(Utilisateur::class, 'id_annule_par', 'id_utilisateur')->withTrashed();
    }
}
