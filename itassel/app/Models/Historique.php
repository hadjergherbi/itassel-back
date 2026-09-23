<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Historique extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_evenement';

    protected $fillable = [
        'date_evenement', 'type_evenement', 'detail', 'visible_demandeur',
        'id_doleance', 'id_utilisateur', 'id_statut_avant', 'id_statut_apres',
    ];

    protected $casts = [
        'date_evenement' => 'datetime',
        'visible_demandeur' => 'boolean',
    ];

    public function doleance()
    {
        return $this->belongsTo(Doleance::class, 'id_doleance', 'id_doleance');
    }

    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, 'id_utilisateur', 'id_utilisateur')->withTrashed();
    }

    public function statutAvant()
    {
        return $this->belongsTo(Statut::class, 'id_statut_avant', 'id_statut');
    }

    public function statutApres()
    {
        return $this->belongsTo(Statut::class, 'id_statut_apres', 'id_statut');
    }

    public function notifications()
    {
        return $this->hasMany(NotificationItassel::class, 'id_evenement', 'id_evenement');
    }
}
