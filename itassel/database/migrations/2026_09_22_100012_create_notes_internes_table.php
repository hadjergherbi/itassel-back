<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes_internes', function (Blueprint $table) {
            $table->id('id_note');
            $table->text('contenu');
            $table->timestamp('date_creation')->useCurrent();
            $table->foreignId('id_doleance')->constrained('doleances', 'id_doleance')->cascadeOnDelete();
            $table->foreignId('id_auteur')->constrained('utilisateurs', 'id_utilisateur');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes_internes');
    }
};
