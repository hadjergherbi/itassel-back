<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationApp extends Model
{
    protected $table = 'notifications_app';
    protected $primaryKey = 'id_notification_app';

    protected $fillable = [
        'id_utilisateur', 'evenement', 'titre', 'message', 'id_doleance', 'lue_le',
    ];

    protected $casts = [
        'lue_le' => 'datetime',
    ];

    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, 'id_utilisateur', 'id_utilisateur');
    }

    public function doleance()
    {
        return $this->belongsTo(Doleance::class, 'id_doleance', 'id_doleance');
    }
}
