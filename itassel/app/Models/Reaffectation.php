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
        return $this->belongsTo(Utilisateur::class, 'id_demandeur', 'id_utilisateur')->withTrashed();
    }

    public function decideur()
    {
        return $this->belongsTo(Utilisateur::class, 'id_decideur', 'id_utilisateur')->withTrashed();
    }

    public function servicePropose()
    {
        return $this->belongsTo(Service::class, 'id_service_propose', 'id_service');
    }

    public function serviceDestination()
    {
        return $this->belongsTo(Service::class, 'id_service_destination', 'id_service');
    }

    public function versApi(): array
    {
        $this->loadMissing(['demandeur', 'decideur', 'servicePropose', 'serviceDestination']);

        return [
            'id_reaffectation' => $this->id_reaffectation,
            'etat' => $this->etat,
            'motif' => $this->motif,
            'date_demande' => $this->date_demande,
            'date_decision' => $this->date_decision,
            'demandeur' => $this->demandeur
                ? $this->demandeur->only(['id_utilisateur', 'nom', 'prenom'])
                : null,
            'decideur' => $this->decideur
                ? $this->decideur->only(['id_utilisateur', 'nom', 'prenom'])
                : null,
            'service_propose' => $this->servicePropose
                ? $this->servicePropose->only(['id_service', 'nom_service'])
                : null,
            'service_destination' => $this->serviceDestination
                ? $this->serviceDestination->only(['id_service', 'nom_service'])
                : null,
        ];
    }
}
