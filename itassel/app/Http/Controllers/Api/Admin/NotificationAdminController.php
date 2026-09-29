<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ChangementStatutMail;
use App\Mail\ComplementAnnuleMail;
use App\Mail\ConfirmationDepotMail;
use App\Mail\DemandeComplementMail;
use App\Mail\ReponseServiceMail;
use App\Models\Complement;
use App\Models\NotificationItassel;
use App\Models\Reponse;
use App\Models\Utilisateur;
use App\Services\NotificationService;
use App\Support\Acces;
use Illuminate\Http\Request;
use Illuminate\Mail\Mailable;

class NotificationAdminController extends Controller
{
    /**
     * POST /api/admin/notifications/{id}/renvoyer
     */
    public function renvoyer(Request $request, int $id)
    {
        $notification = NotificationItassel::with(['doleance', 'evenement.statutApres'])->find($id);

        if (! $notification || ! Acces::doleancesVisibles($request->user())
            ->whereKey($notification->id_doleance)
            ->exists()) {
            return response()->json(['message' => 'Notification introuvable.'], 404);
        }

        if ($notification->etat_envoi === 'transmis') {
            return response()->json([
                'message' => 'Cet email a déjà été transmis.',
                'code' => 'deja_transmis',
            ], 409);
        }

        $mail = $this->reconstruireMail($notification);

        if (! $mail) {
            return response()->json([
                'message' => 'Le renvoi n\'est pas disponible pour ce type de notification.',
                'errors' => ['type_notification' => ['Le renvoi n\'est pas disponible pour ce type de notification.']],
            ], 422);
        }

        NotificationService::renvoyer($notification, $mail);
        $notification->refresh();

        $emailsSuperAdmin = Utilisateur::where('role', 'super_admin')->pluck('email');

        return response()->json([
            'message' => 'Email renvoyé.',
            'notification' => $notification->versApi($notification->doleance?->email, $emailsSuperAdmin),
        ]);
    }

    private function reconstruireMail(NotificationItassel $notification): ?Mailable
    {
        $doleance = $notification->doleance;
        $evenement = $notification->evenement;

        if (! $doleance) {
            return null;
        }

        return match ($notification->type_notification) {
            'depot' => new ConfirmationDepotMail($doleance),

            'changement_statut' => $evenement?->statutApres
                ? new ChangementStatutMail($doleance, $evenement->statutApres, $evenement->detail)
                : null,

            'complement_demande' => $this->mailComplement($doleance, $evenement?->date_evenement),

            'reponse' => $this->mailReponse($doleance, $evenement?->date_evenement),

            'complement_annule' => new ComplementAnnuleMail($doleance),

            default => null,
        };
    }

    private function mailComplement($doleance, $avant): ?DemandeComplementMail
    {
        $complement = Complement::where('id_doleance', $doleance->id_doleance)
            ->when($avant, fn ($q) => $q->where('date_demande', '<=', $avant))
            ->orderByDesc('date_demande')
            ->first();

        return $complement ? new DemandeComplementMail($doleance, $complement) : null;
    }

    private function mailReponse($doleance, $avant): ?ReponseServiceMail
    {
        $reponse = Reponse::where('id_doleance', $doleance->id_doleance)
            ->when($avant, fn ($q) => $q->where('date_publication', '<=', $avant))
            ->orderByDesc('date_publication')
            ->first();

        return $reponse ? new ReponseServiceMail($doleance, $reponse) : null;
    }
}
