<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Doleance extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_doleance';

    protected $fillable = [
        'reference', 'nom', 'prenom', 'email', 'telephone', 'wilaya',
        'objet', 'description', 'date_depot',
        'id_service', 'id_statut', 'id_nature', 'id_qualite',
        'id_responsable', 'id_doleance_initial', 'organisme_competent',
    ];

    protected $casts = ['date_depot' => 'datetime'];

    public function service()
    {
        return $this->belongsTo(Service::class, 'id_service', 'id_service');
    }

    public function statut()
    {
        return $this->belongsTo(Statut::class, 'id_statut', 'id_statut');
    }

    public function nature()
    {
        return $this->belongsTo(Nature::class, 'id_nature', 'id_nature');
    }

    public function qualite()
    {
        return $this->belongsTo(Qualite::class, 'id_qualite', 'id_qualite');
    }

    public function responsable()
    {
        return $this->belongsTo(Utilisateur::class, 'id_responsable', 'id_utilisateur');
    }

    public function doleanceInitiale()
    {
        return $this->belongsTo(Doleance::class, 'id_doleance_initial', 'id_doleance');
    }

    public function copies()
    {
        return $this->hasMany(Doleance::class, 'id_doleance_initial', 'id_doleance');
    }

    public function piecesJointes()
    {
        return $this->hasMany(PieceJointe::class, 'id_doleance', 'id_doleance');
    }

    public function complements()
    {
        return $this->hasMany(Complement::class, 'id_doleance', 'id_doleance');
    }

    public function reaffectations()
    {
        return $this->hasMany(Reaffectation::class, 'id_doleance', 'id_doleance');
    }

    public function reponses()
    {
        return $this->hasMany(Reponse::class, 'id_doleance', 'id_doleance');
    }

    public function notesInternes()
    {
        return $this->hasMany(NoteInterne::class, 'id_doleance', 'id_doleance');
    }

    public function historique()
    {
        return $this->hasMany(Historique::class, 'id_doleance', 'id_doleance')->orderBy('date_evenement');
    }

    public function notifications()
    {
        return $this->hasMany(NotificationItassel::class, 'id_doleance', 'id_doleance');
    }

    public function codesVerification()
    {
        return $this->hasMany(CodeVerification::class, 'id_doleance', 'id_doleance');
    }

    public function transitionsAutorisees()
    {
        $this->loadMissing(['statut', 'nature']);
        $famille = $this->nature?->famille ?? 'reclamation';

        return Statut::autorisesDepuis($this->statut?->code)
            ->filter(function (Statut $statut) use ($famille) {
                if (! Statut::estIssue($statut->code)) {
                    return true;
                }

                $familles = config("itassel.issues.{$statut->code}.familles", ['reclamation', 'demande']);

                return in_array($famille, $familles, true);
            })
            ->values();
    }

    public function complementAExaminer(): bool
    {
        return $this->complements()->where('etat', 'recu')->exists();
    }

    public function emailResponsableInterne(): ?string
    {
        $this->loadMissing(['responsable', 'service.responsable']);

        return $this->responsable?->email
            ?? $this->service?->responsable?->email;
    }
}
