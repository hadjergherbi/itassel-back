<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * type_usage est déjà un string(40) (pas un enum).
 * Normalise les anciennes libellés éventuels et l'ancien code « conclusion ».
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('modeles_message')->where('type_usage', 'Réponse')->update(['type_usage' => 'reponse']);
        DB::table('modeles_message')->where('type_usage', 'Complément')->update(['type_usage' => 'complement']);
        DB::table('modeles_message')->where('type_usage', 'conclusion')->update(['type_usage' => 'autre']);
    }

    public function down(): void
    {
        DB::table('modeles_message')->where('type_usage', 'reponse')->update(['type_usage' => 'Réponse']);
        DB::table('modeles_message')->where('type_usage', 'complement')->update(['type_usage' => 'Complément']);
        DB::table('modeles_message')->where('type_usage', 'autre')->update(['type_usage' => 'conclusion']);
    }
};
