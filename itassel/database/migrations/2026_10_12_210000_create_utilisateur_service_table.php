<?php

use App\Models\Utilisateur;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utilisateur_service', function (Blueprint $table) {
            $table->foreignId('id_utilisateur')
                ->constrained('utilisateurs', 'id_utilisateur')
                ->cascadeOnDelete();
            $table->foreignId('id_service')
                ->constrained('services', 'id_service')
                ->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['id_utilisateur', 'id_service']);
            $table->index('id_service');
        });

        Utilisateur::remplirPivotDepuisPrincipal();
    }

    public function down(): void
    {
        Schema::dropIfExists('utilisateur_service');
    }
};
