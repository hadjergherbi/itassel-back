<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Journal extends Model
{
    use HasFactory;

    protected $table = 'journaux';
    protected $primaryKey = 'id_journal';

    protected $fillable = [
        'date_action', 'compte', 'action', 'categorie', 'detail', 'adresse_ip',
        'resultat', 'id_utilisateur', 'cible_type', 'cible_id',
    ];

    protected $casts = ['date_action' => 'datetime'];

    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, 'id_utilisateur', 'id_utilisateur')->withTrashed();
    }
}
