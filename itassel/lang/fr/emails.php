<?php

return [
    'pied' => 'Cet email a été envoyé automatiquement, merci de ne pas y répondre. Vos données sont utilisées uniquement pour traiter votre demande.',
    'confirmation_depot' => [
        'sujet' => 'ITASSEL — Votre doléance :reference a bien été enregistrée',
        'salutation' => 'Bonjour :prenom :nom,',
        'intro' => 'Votre doléance a bien été enregistrée. Elle est transmise au service concerné.',
        'reference_label' => 'Votre référence de suivi',
        'objet' => 'Objet',
        'suite' => 'Conservez cette référence. Pour consulter votre dossier, rendez-vous sur « Suivre ma demande » : un code de vérification vous sera envoyé à cette adresse.',
    ],
    'code_verification' => [
        'sujet' => 'ITASSEL — Votre code de vérification',
        'salutation' => 'Bonjour,',
        'intro' => 'Voici votre code pour consulter le dossier :reference :',
        'validite' => 'Ce code est valable :minutes minutes et ne peut être utilisé qu\'une seule fois.',
        'ignore' => 'Si vous n\'êtes pas à l\'origine de cette demande, ignorez simplement cet email.',
    ],
    'changement_statut' => [
        'sujet' => 'ITASSEL — Mise à jour de votre dossier :reference',
        'salutation' => 'Bonjour :prenom :nom,',
        'intro' => 'Le statut de votre dossier :reference a changé.',
        'statut_label' => 'Nouveau statut',
        'consulter' => 'Vous pouvez consulter votre dossier à tout moment :',
        'bouton' => 'Suivre ma demande',
    ],
    'demande_complement' => [
        'sujet' => 'ITASSEL — Information demandée pour votre dossier :reference',
        'salutation' => 'Bonjour :prenom :nom,',
        'intro' => 'Le service qui traite votre dossier :reference a besoin d\'une information complémentaire pour poursuivre l\'étude de votre demande.',
        'question_label' => 'Question du service',
        'piece' => 'Pièce justificative demandée',
        'piece_aide' => 'Ce fichier est obligatoire pour répondre (PDF, JPG ou PNG, 5 Mo maximum).',
        'consigne' => 'Pour répondre, ouvrez le lien ci-dessous (votre référence est déjà renseignée) :',
        'bouton' => 'Répondre à la demande',
        'code' => 'Un code de vérification vous sera envoyé par email pour accéder à votre dossier.',
    ],
    'complement_annule' => [
        'sujet' => 'ITASSEL — Votre dossier :reference : information plus nécessaire',
        'salutation' => 'Bonjour :prenom :nom,',
        'intro' => 'L\'information demandée pour votre dossier :reference n\'est plus nécessaire. Aucune action n\'est attendue de votre part : le dossier poursuit son traitement.',
        'bouton' => 'Suivre ma demande',
    ],
    'reponse_service' => [
        'sujet' => 'ITASSEL — Réponse du service pour votre dossier :reference',
        'salutation' => 'Bonjour :prenom :nom,',
        'intro' => 'Le service a répondu à votre dossier :reference :',
        'historique' => 'Cette réponse est aussi disponible dans l\'historique de votre dossier :',
        'bouton' => 'Suivre ma demande',
    ],
    'accuse_complement' => [
        'sujet' => 'ITASSEL — Votre réponse pour le dossier :reference a bien été reçue',
        'salutation' => 'Bonjour :prenom :nom,',
        'intro' => 'Votre réponse à la demande de complément pour le dossier :reference a bien été reçue. Le service concerné va poursuivre l\'étude de votre dossier.',
        'suite' => 'Vous serez informé(e) par email de la suite donnée à votre demande.',
    ],
];
