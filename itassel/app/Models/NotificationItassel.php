<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationItassel extends Model
{
    use HasFactory;

    protected $table = 'notifications_itassel';
    protected $primaryKey = 'id_notification';

    protected $fillable = [
        'type_notification', 'destinataire', 'etat_envoi', 'date_envoi',
        'id_doleance', 'id_evenement',
    ];

    protected $casts = ['date_envoi' => 'datetime'];

    public function doleance()
    {
        return $this->belongsTo(Doleance::class, 'id_doleance', 'id_doleance');
    }

    public function evenement()
    {
        return $this->belongsTo(Historique::class, 'id_evenement', 'id_evenement');
    }
}
