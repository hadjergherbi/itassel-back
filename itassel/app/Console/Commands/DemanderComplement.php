<?php

namespace App\Console\Commands;

use App\Models\Doleance;
use App\Models\NotificationItassel;
use App\Models\Utilisateur;
use App\Services\ComplementService;
use Illuminate\Console\Command;
use RuntimeException;

class DemanderComplement extends Command
{
    protected $signature = 'itassel:demander-complement
        {reference : Référence du dossier, ex. ITS-2026-1737}
        {question : Question posée au demandeur}
        {--piece= : Pièce justificative exigée (la rend obligatoire)}
        {--auteur= : Email de l\'administrateur (par défaut : le premier utilisateur actif)}';

    protected $description = "Demande un complément d'information au demandeur et lui envoie un email (test, en attendant le back-office).";

    public function handle(): int
    {
        $reference = strtoupper(trim($this->argument('reference')));
        $doleance = Doleance::where('reference', $reference)->first();

        if (! $doleance) {
            $this->error("Aucune doléance avec la référence {$reference}.");

            return self::FAILURE;
        }

        $auteur = $this->option('auteur')
            ? Utilisateur::where('email', $this->option('auteur'))->first()
            : Utilisateur::where('actif', true)->orderBy('id_utilisateur')->first();

        if (! $auteur) {
            $this->error("Aucun utilisateur trouvé pour jouer le rôle de l'administrateur. Créez-en un dans la table utilisateurs.");

            return self::FAILURE;
        }

        try {
            $complement = ComplementService::demander(
                $doleance,
                $auteur,
                $this->argument('question'),
                $this->option('piece'),
            );
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $notification = NotificationItassel::where('id_doleance', $doleance->id_doleance)
            ->where('type_notification', 'complement_demande')
            ->latest('id_notification')
            ->first();

        $this->info("Complément n° {$complement->id_complement} créé pour {$reference}.");
        $this->line('Statut du dossier : Information demandée.');
        $this->line('Pièce exigée : '.($complement->piece_exigee ? $complement->description_piece : 'non'));
        $this->line("Email à {$doleance->email} : ".($notification?->etat_envoi === 'transmis'
            ? 'transmis'
            : 'NON transmis (voir storage/logs/laravel.log)'));

        return self::SUCCESS;
    }
}
