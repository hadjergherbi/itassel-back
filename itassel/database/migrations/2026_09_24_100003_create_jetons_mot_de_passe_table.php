<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jetons_mot_de_passe', function (Blueprint $table) {
            $table->id('id_jeton');
            $table->foreignId('id_utilisateur')
                ->constrained('utilisateurs', 'id_utilisateur')
                ->cascadeOnDelete();
            $table->enum('type', ['invitation', 'reinitialisation']);
            $table->char('jeton_hash', 64)->unique();
            $table->dateTime('expire_le');
            $table->timestamp('utilise_le')->nullable();
            $table->foreignId('id_createur')->nullable()
                ->constrained('utilisateurs', 'id_utilisateur')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jetons_mot_de_passe');
    }
};
