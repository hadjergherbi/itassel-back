<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('statuts')->where('code', 'resolue')->update(['libelle' => 'Résolue']);
        DB::table('statuts')->where('code', 'non_retenue')->update(['libelle' => 'Non retenue']);
    }

    public function down(): void
    {
        DB::table('statuts')->where('code', 'resolue')->update(['libelle' => 'Résolu']);
        DB::table('statuts')->where('code', 'non_retenue')->update(['libelle' => 'Non fondée']);
    }
};
