<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reponses', function (Blueprint $table) {
            $table->id('id_reponse');
            $table->text('contenu');
            $table->timestamp('date_publication')->useCurrent();
            $table->foreignId('id_doleance')->constrained('doleances', 'id_doleance')->cascadeOnDelete();
            $table->foreignId('id_auteur')->constrained('utilisateurs', 'id_utilisateur');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reponses');
    }
};
