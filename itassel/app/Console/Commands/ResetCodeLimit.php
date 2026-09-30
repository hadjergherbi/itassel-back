<?php

namespace App\Console\Commands;

use App\Models\Doleance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ResetCodeLimit extends Command
{
    protected $signature = 'itassel:reset-code-limit
        {reference : Référence du dossier, ex. ITS-2026-000001}';

    protected $description = 'Remet à zéro le compteur de demandes de code (local uniquement).';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('Cette commande est réservée à APP_ENV=local.');

            return self::FAILURE;
        }

        $reference = strtoupper(trim((string) $this->argument('reference')));
        $doleance = Doleance::where('reference', $reference)->first();

        if (! $doleance) {
            $this->error("Aucune doléance avec la référence {$reference}.");

            return self::FAILURE;
        }

        $cle = "suivi:demandes:{$doleance->id_doleance}";
        $avant = (int) Cache::get($cle, 0);
        Cache::forget($cle);

        $this->info("Compteur remis à zéro pour {$reference} (était {$avant}).");
        $this->line("Clé cache : {$cle}");

        return self::SUCCESS;
    }
}
