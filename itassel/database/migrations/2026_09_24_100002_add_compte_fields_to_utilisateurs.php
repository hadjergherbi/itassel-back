<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('utilisateurs', function (Blueprint $table) {
            $table->timestamp('derniere_connexion')->nullable();
            $table->timestamp('mot_de_passe_defini_le')->nullable();
            $table->timestamp('invitation_envoyee_le')->nullable();
        });

        DB::table('utilisateurs')->whereNull('mot_de_passe_defini_le')->update([
            'mot_de_passe_defini_le' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('utilisateurs', function (Blueprint $table) {
            $table->dropColumn(['derniere_connexion', 'mot_de_passe_defini_le', 'invitation_envoyee_le']);
        });
    }
};
