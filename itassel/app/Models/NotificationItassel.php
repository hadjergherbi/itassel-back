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

    public static function typeDestinataire(string $destinataire, ?string $emailDemandeur, iterable $emailsSuperAdmin): string
    {
        $email = mb_strtolower(trim($destinataire));
        $demandeur = mb_strtolower(mb_substr((string) $emailDemandeur, 0, 100));

        if ($email !== '' && $email === $demandeur) {
            return 'demandeur';
        }

        $superAdmins = collect($emailsSuperAdmin)->map(fn ($e) => mb_strtolower(trim((string) $e)));

        if ($superAdmins->contains($email)) {
            return 'super_admin';
        }

        return 'responsable';
    }

    public function versApi(?string $emailDemandeur = null, iterable $emailsSuperAdmin = []): array
    {
        return [
            'id_notification'    => $this->id_notification,
            'type_notification'  => $this->type_notification,
            'destinataire'       => $this->destinataire,
            'destinataire_type'  => static::typeDestinataire(
                (string) $this->destinataire,
                $emailDemandeur ?? $this->doleance?->email,
                $emailsSuperAdmin,
            ),
            'etat_envoi'         => $this->etat_envoi,
            'date_envoi'         => $this->date_envoi,
        ];
    }
}
