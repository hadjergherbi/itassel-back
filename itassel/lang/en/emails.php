<?php

return [
    'pied' => 'This email was sent automatically, please do not reply. Your data is used only to process your request.',
    'confirmation_depot' => [
        'sujet' => 'ITASSEL — Your complaint :reference has been registered',
        'salutation' => 'Hello :prenom :nom,',
        'intro' => 'Your complaint has been registered and forwarded to the relevant department.',
        'reference_label' => 'Your tracking reference',
        'objet' => 'Subject',
        'suite' => 'Keep this reference. To view your file, open “Track my request”: a verification code will be sent to this address.',
    ],
    'code_verification' => [
        'sujet' => 'ITASSEL — Your verification code',
        'salutation' => 'Hello,',
        'intro' => 'Here is your code to view file :reference:',
        'validite' => 'This code is valid for :minutes minutes and can be used only once.',
        'ignore' => 'If you did not request this, you can ignore this email.',
    ],
    'changement_statut' => [
        'sujet' => 'ITASSEL — Update on your file :reference',
        'salutation' => 'Hello :prenom :nom,',
        'intro' => 'The status of your file :reference has changed.',
        'statut_label' => 'New status',
        'consulter' => 'You can view your file at any time:',
        'bouton' => 'Track my request',
    ],
    'demande_complement' => [
        'sujet' => 'ITASSEL — Information requested for your file :reference',
        'salutation' => 'Hello :prenom :nom,',
        'intro' => 'The department handling your file :reference needs additional information to continue reviewing your request.',
        'question_label' => 'Question from the department',
        'piece' => 'Supporting document requested',
        'piece_aide' => 'This file is required to reply (PDF, JPG or PNG, 5 MB maximum).',
        'consigne' => 'To reply, open the link below (your reference is already filled in):',
        'bouton' => 'Reply to the request',
        'code' => 'A verification code will be emailed to you so you can access your file.',
    ],
    'complement_annule' => [
        'sujet' => 'ITASSEL — Your file :reference: information no longer required',
        'salutation' => 'Hello :prenom :nom,',
        'intro' => 'The information requested for your file :reference is no longer needed. No action is expected from you: processing continues.',
        'bouton' => 'Track my request',
    ],
    'reponse_service' => [
        'sujet' => 'ITASSEL — Department reply for your file :reference',
        'salutation' => 'Hello :prenom :nom,',
        'intro' => 'The department has replied to your file :reference:',
        'historique' => 'This reply is also available in your file history:',
        'bouton' => 'Track my request',
    ],
    'accuse_complement' => [
        'sujet' => 'ITASSEL — Your reply for file :reference has been received',
        'salutation' => 'Hello :prenom :nom,',
        'intro' => 'Your reply to the additional-information request for file :reference has been received. The department will continue reviewing your file.',
        'suite' => 'You will be informed by email of the follow-up given to your request.',
    ],
];
