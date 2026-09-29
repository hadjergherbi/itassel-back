<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PieceJointe extends Model
{
    use HasFactory;

    protected $table = 'pieces_jointes';

    protected $primaryKey = 'id_piece';

    protected $fillable = [
        'nom_fichier', 'type', 'taille', 'chemin', 'origine',
        'id_doleance', 'id_complement',
    ];

    public function doleance()
    {
        return $this->belongsTo(Doleance::class, 'id_doleance', 'id_doleance');
    }

    public function complement()
    {
        return $this->belongsTo(Complement::class, 'id_complement', 'id_complement');
    }
}
