<?php

use App\Models\Nature;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Nature::firstOrCreate(
            ['libelle' => Nature::TOUTES_NATURES],
            ['famille' => 'reclamation']
        );
    }

    public function down(): void
    {
        $nature = Nature::where('libelle', Nature::TOUTES_NATURES)->first();

        if ($nature && ! $nature->doleances()->exists()) {
            $nature->delete();
        }
    }
};
