<?php

$issues = ['resolue', 'reponse_apportee', 'hors_competence', 'non_retenue', 'double'];

return [

    'frontend_url' => env('ITASSEL_FRONTEND_URL', env('FRONTEND_URL', 'http://localhost:5173')),

    'jetons' => [
        'invitation_heures'         => 72,
        'reinitialisation_minutes'  => 60,
    ],

    'transitions' => [
        'nouvelle'             => array_merge(['en_cours'], $issues),
        'en_cours'             => array_merge(['information_demandee'], $issues),
        'information_demandee' => ['en_cours'],
        'resolue'              => [],
        'reponse_apportee'     => [],
        'hors_competence'      => [],
        'non_retenue'          => [],
        'double'               => [],
        'non_fondee'           => [],
        'cloturee'             => [],
    ],

    'statuts_conclusion' => $issues,

    'issues' => [
        'resolue'          => ['familles' => ['reclamation'], 'exige' => 'message'],
        'reponse_apportee' => ['familles' => ['demande'], 'exige' => 'message'],
        'non_retenue'      => ['familles' => ['reclamation'], 'exige' => 'message'],
        'hors_competence'  => ['familles' => ['reclamation', 'demande'], 'exige' => 'message'],
        'double'           => ['familles' => ['reclamation', 'demande'], 'exige' => 'reference_initiale'],
    ],

    'reclassement' => [
        'non_fondee' => ['hors_competence', 'non_retenue'],
        'resolue'    => ['reponse_apportee'],
        'cloturee'   => null,
    ],

    'journal' => [
        'categories' => ['connexion', 'doleance', 'affectation', 'utilisateur', 'parametre', 'export'],
        'actions'    => [
            'connexion'                    => ['Connexion', 'connexion'],
            'deconnexion'                  => ['Déconnexion', 'connexion'],
            'changement_statut'            => ['Changement de statut', 'doleance'],
            'reponse'                      => ['Réponse au demandeur', 'doleance'],
            'note_interne'                 => ['Note interne', 'doleance'],
            'complement_demande'           => ['Demande de complément', 'doleance'],
            'complement_annule'            => ['Annulation de complément', 'doleance'],
            'complement_examine'           => ['Examen de complément', 'doleance'],
            'reclassement'                 => ['Reclassement', 'doleance'],
            'reaffectation_demande'        => ['Demande de réaffectation', 'affectation'],
            'reaffectation_acceptee'       => ['Réaffectation acceptée', 'affectation'],
            'reaffectation_refusee'        => ['Réaffectation refusée', 'affectation'],
            'reaffectation_annulee'        => ['Réaffectation annulée', 'affectation'],
            'reaffectation'                => ['Réaffectation', 'affectation'],
            'designation_responsable'      => ['Désignation du responsable', 'affectation'],
            'creation_utilisateur'         => ['Création d\'utilisateur', 'utilisateur'],
            'modification_utilisateur'     => ['Modification d\'utilisateur', 'utilisateur'],
            'activation_utilisateur'       => ['Activation d\'utilisateur', 'utilisateur'],
            'desactivation_utilisateur'    => ['Désactivation d\'utilisateur', 'utilisateur'],
            'suppression_utilisateur'      => ['Suppression d\'utilisateur', 'utilisateur'],
            'invitation_envoyee'           => ['Invitation envoyée', 'utilisateur'],
            'reinitialisation_mot_de_passe' => ['Réinitialisation du mot de passe', 'utilisateur'],
            'mot_de_passe_defini'          => ['Mot de passe défini', 'utilisateur'],
            'modification_permissions_role' => ['Modification des permissions', 'utilisateur'],
            'creation_service'             => ['Création de service', 'parametre'],
            'modification_service'         => ['Modification de service', 'parametre'],
            'creation_nature'              => ['Création de nature', 'parametre'],
            'modification_nature'          => ['Modification de nature', 'parametre'],
            'suppression_nature'           => ['Suppression de nature', 'parametre'],
            'creation_qualite'             => ['Création de qualité', 'parametre'],
            'modification_qualite'         => ['Modification de qualité', 'parametre'],
            'suppression_qualite'          => ['Suppression de qualité', 'parametre'],
            'creation_modele_message'      => ['Création de modèle', 'parametre'],
            'modification_modele_message'  => ['Modification de modèle', 'parametre'],
            'suppression_modele_message'   => ['Suppression de modèle', 'parametre'],
            'modification_statut'          => ['Modification de statut', 'parametre'],
            'modification_notifications'   => ['Modification des notifications', 'parametre'],
            'export_csv'                   => ['Export CSV', 'export'],
            'export_journal'               => ['Export du journal', 'export'],
        ],
    ],

    'permissions' => [
        'tableau_de_bord.voir'     => ['libelle' => 'Voir le tableau de bord', 'groupe' => 'tableau_de_bord', 'defaults' => ['super_admin', 'admin_service']],
        'tableau_de_bord.global'   => ['libelle' => 'Vue globale du tableau de bord', 'groupe' => 'tableau_de_bord', 'defaults' => ['super_admin']],
        'doleances.voir'           => ['libelle' => 'Voir les doléances', 'groupe' => 'doleances', 'defaults' => ['super_admin', 'admin_service']],
        'doleances.exporter'       => ['libelle' => 'Exporter les doléances', 'groupe' => 'doleances', 'defaults' => ['super_admin', 'admin_service']],
        'doleances.changer_statut' => ['libelle' => 'Changer le statut', 'groupe' => 'doleances', 'defaults' => ['super_admin', 'admin_service']],
        'doleances.repondre'       => ['libelle' => 'Répondre au demandeur', 'groupe' => 'doleances', 'defaults' => ['super_admin', 'admin_service']],
        'doleances.notes'          => ['libelle' => 'Ajouter une note interne', 'groupe' => 'doleances', 'defaults' => ['super_admin', 'admin_service']],
        'doleances.reclasser'      => ['libelle' => 'Reclasser une doléance', 'groupe' => 'doleances', 'defaults' => ['super_admin']],
        'complements.demander'     => ['libelle' => 'Demander un complément', 'groupe' => 'complements', 'defaults' => ['super_admin', 'admin_service']],
        'complements.annuler'      => ['libelle' => 'Annuler un complément', 'groupe' => 'complements', 'defaults' => ['super_admin', 'admin_service']],
        'complements.examiner'     => ['libelle' => 'Examiner un complément', 'groupe' => 'complements', 'defaults' => ['super_admin', 'admin_service']],
        'reaffectations.demander'  => ['libelle' => 'Demander une réaffectation', 'groupe' => 'reaffectations', 'defaults' => ['admin_service']],
        'reaffectations.decider'   => ['libelle' => 'Décider une réaffectation', 'groupe' => 'reaffectations', 'defaults' => ['super_admin']],
        'reaffectations.directe'   => ['libelle' => 'Réaffecter directement', 'groupe' => 'reaffectations', 'defaults' => ['super_admin']],
        'notifications.renvoyer'   => ['libelle' => 'Renvoyer un email', 'groupe' => 'notifications', 'defaults' => ['super_admin', 'admin_service']],
        'services.gerer'           => ['libelle' => 'Gérer les services', 'groupe' => 'administration', 'defaults' => ['super_admin']],
        'utilisateurs.gerer'       => ['libelle' => 'Gérer les utilisateurs', 'groupe' => 'administration', 'defaults' => ['super_admin']],
        'roles.gerer'              => ['libelle' => 'Gérer les rôles', 'groupe' => 'administration', 'defaults' => ['super_admin']],
        'parametres.gerer'         => ['libelle' => 'Gérer les paramètres', 'groupe' => 'administration', 'defaults' => ['super_admin']],
        'journal.voir'             => ['libelle' => 'Consulter le journal', 'groupe' => 'journal', 'defaults' => ['super_admin']],
        'journal.exporter'         => ['libelle' => 'Exporter le journal', 'groupe' => 'journal', 'defaults' => ['super_admin']],
    ],

    'permissions_verrouillees' => [
        'super_admin' => ['roles.gerer', 'utilisateurs.gerer'],
    ],

    'notifications' => [
        'defauts' => [
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
        ],
    ],

];
