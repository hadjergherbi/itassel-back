<?php

namespace App\Services;

use App\Exceptions\ConflitMetier;
use App\Mail\ComplementAnnuleMail;
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
        array $options = [],
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

        $statutInfo = Statut::parCode(Statut::INFORMATION_DEMANDEE);
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

        NotificationDispatcher::emettre(
            'complement_demande',
            $doleance->fresh(),
            [
                'complement' => $complement,
                'titre'      => "Complément demandé — {$doleance->reference}",
                'texte'      => "Une information a été demandée au demandeur pour le dossier {$doleance->reference}.",
            ],
            $auteur,
            $evenement->id_evenement,
            $options,
        );

        return $complement;
    }

    public static function annuler(Complement $complement, Utilisateur $acteur, string $motif): Complement
    {
        $statutEnCours = Statut::parCode(Statut::EN_COURS);

        [$complement, $doleance, $idEvenementVisible] = DB::transaction(function () use ($complement, $acteur, $motif, $statutEnCours) {
            $complement = Complement::whereKey($complement->id_complement)
                ->lockForUpdate()
                ->firstOrFail();

            if ($complement->etat === 'recu') {
                throw new ConflitMetier('deja_repondu', 'Ce complément a déjà reçu une réponse.');
            }
            if ($complement->etat === 'annule') {
                throw new ConflitMetier('deja_annule', 'Ce complément est déjà annulé.');
            }
            if ($complement->etat === 'examine') {
                throw new ConflitMetier('etat_invalide', 'Ce complément a déjà été examiné.');
            }
            if ($complement->etat !== 'en_attente') {
                throw new ConflitMetier('etat_invalide', 'Ce complément ne peut pas être annulé.');
            }

            $doleance = Doleance::whereKey($complement->id_doleance)
                ->lockForUpdate()
                ->with('statut')
                ->firstOrFail();

            $statutAvant = $doleance->id_statut;

            $complement->update([
                'etat'             => 'annule',
                'motif_annulation' => $motif,
                'id_annule_par'    => $acteur->id_utilisateur,
                'date_annulation'  => now(),
            ]);

            $passeEnCours = $doleance->statut?->code === Statut::INFORMATION_DEMANDEE;
            if ($passeEnCours) {
                $doleance->update(['id_statut' => $statutEnCours->id_statut]);
            }

            $evenementVisible = Historique::create([
                'date_evenement'    => now(),
                'type_evenement'    => 'complement_annule',
                'detail'            => null,
                'visible_demandeur' => true,
                'id_doleance'       => $doleance->id_doleance,
                'id_utilisateur'    => $acteur->id_utilisateur,
                'id_statut_avant'   => $statutAvant,
                'id_statut_apres'   => $passeEnCours ? $statutEnCours->id_statut : $statutAvant,
            ]);

            Historique::create([
                'date_evenement'    => now(),
                'type_evenement'    => 'complement_annule_motif',
                'detail'            => $motif,
                'visible_demandeur' => false,
                'id_doleance'       => $doleance->id_doleance,
                'id_utilisateur'    => $acteur->id_utilisateur,
            ]);

            return [$complement->fresh(), $doleance->fresh(), $evenementVisible->id_evenement];
        });

        $emailEnvoye = NotificationService::envoyer(
            $doleance,
            'complement_annule',
            new ComplementAnnuleMail($doleance),
            $idEvenementVisible,
        );

        JournalService::ecrire(
            request(),
            $acteur->email,
            'complement_annule',
            'succes',
            $acteur,
            $doleance->reference,
        );

        $complement->setAttribute('email_envoye', $emailEnvoye);

        return $complement;
    }
}
