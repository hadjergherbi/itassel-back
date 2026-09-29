<?php

namespace Database\Seeders;

use App\Models\ParametreNotification;
use Illuminate\Database\Seeder;

/**
 * Paramètre les canaux e-mail / application pour chaque événement de notification.
 * Requis à l'installation pour que les alertes (dépôt, statut, complément, réaffectation) soient envoyées.
 */
class ParametreNotificationSeeder extends Seeder
{
    public function run(): void
    {
        $lignes = config('itassel.notifications.defauts', $this->defauts());

        foreach ($lignes as $ligne) {
            ParametreNotification::updateOrCreate(
                [
                    'evenement' => $ligne['evenement'],
                    'destinataire' => $ligne['destinataire'],
                ],
                [
                    'canal_email' => $ligne['canal_email'],
                    'canal_app' => $ligne['canal_app'],
                    'modifiable' => $ligne['modifiable'],
                ]
            );
        }
    }

    private function defauts(): array
    {
        return [
            ['evenement' => 'doleance_deposee', 'destinataire' => 'demandeur', 'canal_email' => true, 'canal_app' => false, 'modifiable' => false],
            ['evenement' => 'doleance_deposee', 'destinataire' => 'responsable', 'canal_email' => true, 'canal_app' => true, 'modifiable' => true],
            ['evenement' => 'doleance_deposee', 'destinataire' => 'admins_service', 'canal_email' => false, 'canal_app' => true, 'modifiable' => true],
            ['evenement' => 'doleance_deposee', 'destinataire' => 'super_admins', 'canal_email' => false, 'canal_app' => true, 'modifiable' => true],
            ['evenement' => 'changement_statut', 'destinataire' => 'demandeur', 'canal_email' => true, 'canal_app' => false, 'modifiable' => true],
            ['evenement' => 'changement_statut', 'destinataire' => 'responsable', 'canal_email' => false, 'canal_app' => true, 'modifiable' => true],
            ['evenement' => 'complement_demande', 'destinataire' => 'demandeur', 'canal_email' => true, 'canal_app' => false, 'modifiable' => false],
            ['evenement' => 'complement_demande', 'destinataire' => 'responsable', 'canal_email' => false, 'canal_app' => true, 'modifiable' => true],
            ['evenement' => 'complement_recu', 'destinataire' => 'responsable', 'canal_email' => true, 'canal_app' => true, 'modifiable' => true],
            ['evenement' => 'complement_recu', 'destinataire' => 'admins_service', 'canal_email' => false, 'canal_app' => true, 'modifiable' => true],
            ['evenement' => 'complement_annule', 'destinataire' => 'demandeur', 'canal_email' => true, 'canal_app' => false, 'modifiable' => false],
            ['evenement' => 'reponse_publiee', 'destinataire' => 'demandeur', 'canal_email' => true, 'canal_app' => false, 'modifiable' => true],
            ['evenement' => 'reponse_publiee', 'destinataire' => 'responsable', 'canal_email' => false, 'canal_app' => true, 'modifiable' => true],
            ['evenement' => 'reaffectation_demandee', 'destinataire' => 'super_admins', 'canal_email' => true, 'canal_app' => true, 'modifiable' => true],
            ['evenement' => 'reaffectation_decidee', 'destinataire' => 'demandeur_reaffectation', 'canal_email' => true, 'canal_app' => true, 'modifiable' => true],
            ['evenement' => 'doleance_reaffectee', 'destinataire' => 'responsable', 'canal_email' => true, 'canal_app' => true, 'modifiable' => true],
            ['evenement' => 'doleance_reaffectee', 'destinataire' => 'admins_service', 'canal_email' => false, 'canal_app' => true, 'modifiable' => true],
            ['evenement' => 'responsable_designe', 'destinataire' => 'utilisateur_designe', 'canal_email' => true, 'canal_app' => true, 'modifiable' => true],
        ];
    }
}
