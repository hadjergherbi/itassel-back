<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Le responsable d'un service : un utilisateur actif de ce même service (ou aucun)
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->foreignId('id_responsable')->nullable()
                ->after('nom_service')
                ->constrained('utilisateurs', 'id_utilisateur');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_responsable');
        });
    }
};
