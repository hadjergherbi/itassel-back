<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reaffectation extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_reaffectation';

    protected $fillable = [
        'etat', 'motif', 'date_demande', 'date_decision',
        'id_doleance', 'id_demandeur', 'id_decideur',
        'id_service_propose', 'id_service_destination',
    ];

    protected $casts = [
        'date_demande' => 'datetime',
        'date_decision' => 'datetime',
    ];

    public function doleance()
    {
        return $this->belongsTo(Doleance::class, 'id_doleance', 'id_doleance');
    }

    public function demandeur()
    {
        return $this->belongsTo(Utilisateur::class, 'id_demandeur', 'id_utilisateur');
    }

    public function decideur()
    {
        return $this->belongsTo(Utilisateur::class, 'id_decideur', 'id_utilisateur');
    }

    public function servicePropose()
    {
        return $this->belongsTo(Service::class, 'id_service_propose', 'id_service');
    }

    public function serviceDestination()
    {
        return $this->belongsTo(Service::class, 'id_service_destination', 'id_service');
    }
}
