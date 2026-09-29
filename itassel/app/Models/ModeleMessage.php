<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModeleMessage extends Model
{
    use HasFactory;

    protected $table = 'modeles_message';

    protected $primaryKey = 'id_modele';

    protected $fillable = ['titre', 'contenu', 'type_usage'];
}
