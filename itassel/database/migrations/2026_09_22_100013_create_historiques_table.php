<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historiques', function (Blueprint $table) {
            $table->id('id_evenement');
            $table->timestamp('date_evenement')->useCurrent();
            $table->string('type_evenement', 40); // depot, affectation, changement_statut, complement, reaffectation...
            $table->text('detail')->nullable();
            $table->boolean('visible_demandeur')->default(false);
            $table->foreignId('id_doleance')->constrained('doleances', 'id_doleance')->cascadeOnDelete();
            $table->foreignId('id_utilisateur')->nullable()
                ->constrained('utilisateurs', 'id_utilisateur'); // NULL : demandeur ou système
            $table->foreignId('id_statut_avant')->nullable()->constrained('statuts', 'id_statut');
            $table->foreignId('id_statut_apres')->nullable()->constrained('statuts', 'id_statut');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historiques');
    }
};
