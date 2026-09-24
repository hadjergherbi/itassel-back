<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NoteInterne extends Model
{
    use HasFactory;

    protected $table = 'notes_internes';
    protected $primaryKey = 'id_note';

    protected $fillable = [
        'contenu', 'date_creation', 'id_doleance', 'id_auteur',
        'etiquette', 'epinglee', 'epinglee_le', 'id_epinglee_par', 'modifiee_le',
    ];

    protected $casts = [
        'date_creation' => 'datetime',
        'epinglee'      => 'boolean',
        'epinglee_le'   => 'datetime',
        'modifiee_le'   => 'datetime',
    ];

    public function doleance()
    {
        return $this->belongsTo(Doleance::class, 'id_doleance', 'id_doleance');
    }

    public function auteur()
    {
        return $this->belongsTo(Utilisateur::class, 'id_auteur', 'id_utilisateur')->withTrashed();
    }

    public function epingleePar()
    {
        return $this->belongsTo(Utilisateur::class, 'id_epinglee_par', 'id_utilisateur')->withTrashed();
    }

    public function mentions()
    {
        return $this->belongsToMany(
            Utilisateur::class,
            'note_mentions',
            'id_note',
            'id_utilisateur',
        )->withPivot('notifie_email')->withTimestamps()->withTrashed();
    }

    public function dateReference()
    {
        return $this->created_at ?? $this->date_creation;
    }

    public function modifiableJusqua(): ?\Illuminate\Support\Carbon
    {
        $debut = $this->dateReference();
        if (! $debut) {
            return null;
        }

        return $debut->copy()->addMinutes((int) config('itassel.notes.modification_minutes', 15));
    }

    public function estEncoreModifiable(): bool
    {
        $limite = $this->modifiableJusqua();

        return $limite !== null && now()->lte($limite);
    }

    public function versApi(Utilisateur $lecteur): array
    {
        $auteur = $this->auteur;
        $estAuteur = $auteur && (int) $auteur->id_utilisateur === (int) $lecteur->id_utilisateur;
        $limite = $estAuteur && $this->estEncoreModifiable() ? $this->modifiableJusqua() : null;

        return [
            'id_note'            => $this->id_note,
            'contenu'            => $this->contenu,
            'etiquette'          => $this->etiquette,
            'epinglee'           => (bool) $this->epinglee,
            'epinglee_le'        => $this->epinglee_le,
            'epinglee_par'       => $this->epingleePar
                ? $this->epingleePar->only(['prenom', 'nom'])
                : null,
            'created_at'         => $this->dateReference(),
            'date_creation'      => $this->date_creation ?? $this->dateReference(),
            'modifiee_le'        => $this->modifiee_le,
            'modifiable_jusqu_a' => $limite,
            'est_auteur'         => (bool) $estAuteur,
            'auteur'             => $auteur ? [
                'id_utilisateur' => $auteur->id_utilisateur,
                'nom'            => $auteur->nom,
                'prenom'         => $auteur->prenom,
                'initiales'      => $auteur->initiales(),
                'libelle_role'   => $auteur->libelleRoleAffiche(),
            ] : null,
            'mentions'           => $this->mentions
                ->map(fn (Utilisateur $u) => $u->only(['id_utilisateur', 'nom', 'prenom']))
                ->values(),
        ];
    }
}
