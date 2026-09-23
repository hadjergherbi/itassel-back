<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NoteInterne extends Model
{
    use HasFactory;

    protected $table = 'notes_internes';
    protected $primaryKey = 'id_note';

    protected $fillable = ['contenu', 'date_creation', 'id_doleance', 'id_auteur'];

    protected $casts = ['date_creation' => 'datetime'];

    public function doleance()
    {
        return $this->belongsTo(Doleance::class, 'id_doleance', 'id_doleance');
    }

    public function auteur()
    {
        return $this->belongsTo(Utilisateur::class, 'id_auteur', 'id_utilisateur')->withTrashed();
    }
}
