<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParametreNotification extends Model
{
    protected $table = 'parametres_notification';
    protected $primaryKey = 'id_parametre_notification';

    protected $fillable = [
        'evenement', 'destinataire', 'canal_email', 'canal_app', 'modifiable',
    ];

    protected $casts = [
        'canal_email' => 'boolean',
        'canal_app'   => 'boolean',
        'modifiable'  => 'boolean',
    ];
}
