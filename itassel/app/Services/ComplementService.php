<?php

namespace App\Services;

use App\Mail\DemandeComplementMail;
use App\Models\Complement;
use App\Models\Doleance;
use App\Models\Historique;
use App\Models\Statut;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ComplementService
{
    /**
     * Un administrateur demande un complément d'information au demandeur :
     * - crée le complément (en attente),
     * - passe le dossier au statut « Information demandée »,
     * - ajoute l'événement à l'historique (visible du demandeur),
     * - envoie l'email au demandeur (tracé dans notifications_itassel).
     *
     * Utilisé par la commande de test itassel:demander-complement, et à
     * réutiliser tel quel dans le futur contrôleur du back-office.
     */
    public static function demander(
        Doleance $doleance,
        Utilisateur $auteur,
        string $question,
        ?string $descriptionPiece = null,
    ): Complement {
        $dejaEnAttente = Complement::where('id_doleance', $doleance->id_doleance)
            ->where('etat', 'en_attente')
            ->exists();

        if ($dejaEnAttente) {
            throw new RuntimeException(
                'Une demande de complément est déjà en attente pour ce dossier : '
                .'le demandeur doit y répondre avant une nouvelle demande.'
            );
        }

        $statutInfo = Statut::where('libelle', 'Information demandée')->firstOrFail();
        $descriptionPiece = trim((string) $descriptionPiece) ?: null;

        [$complement, $evenement] = DB::transaction(function () use ($doleance, $auteur, $question, $descriptionPiece, $statutInfo) {
            $statutAvant = $doleance->id_statut;

            $complement = Complement::create([
                'question'          => $question,
                'piece_exigee'      => $descriptionPiece !== null,
                'description_piece' => $descriptionPiece,
                'etat'              => 'en_attente',
                'date_demande'      => now(),
                'id_doleance'       => $doleance->id_doleance,
                'id_auteur'         => $auteur->id_utilisateur,
            ]);

            $doleance->update(['id_statut' => $statutInfo->id_statut]);

            $evenement = Historique::create([
                'date_evenement'    => now(),
                'type_evenement'    => 'complement_demande',
                'detail'            => $question,
                'visible_demandeur' => true,
                'id_doleance'       => $doleance->id_doleance,
                'id_utilisateur'    => $auteur->id_utilisateur,
                'id_statut_avant'   => $statutAvant,
                'id_statut_apres'   => $statutInfo->id_statut,
            ]);

            return [$complement, $evenement];
        });

        NotificationService::envoyer(
            $doleance,
            'complement_demande',
            new DemandeComplementMail($doleance, $complement),
            $evenement->id_evenement,
        );

        return $complement;
    }
}
