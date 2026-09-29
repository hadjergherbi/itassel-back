<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complements', function (Blueprint $table) {
            $table->id('id_complement');
            $table->text('question');
            $table->boolean('piece_exigee')->default(false);
            $table->string('description_piece', 200)->nullable(); // ex. « plan du terrain »
            $table->text('reponse')->nullable();
            $table->timestamp('date_demande')->useCurrent();
            $table->timestamp('date_reponse')->nullable();
            $table->enum('etat', ['en_attente', 'recu', 'examine', 'annule'])->default('en_attente');
            $table->text('motif_annulation')->nullable();
            $table->foreignId('id_doleance')->constrained('doleances', 'id_doleance')->cascadeOnDelete();
            $table->foreignId('id_auteur')->constrained('utilisateurs', 'id_utilisateur'); // qui a demandé le complément
            $table->timestamps();
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("
                ALTER TABLE complements
                ADD CONSTRAINT ck_cmp_reponse CHECK (
                    (reponse IS NULL AND date_reponse IS NULL) OR
                    (reponse IS NOT NULL AND date_reponse IS NOT NULL)
                ),
                ADD CONSTRAINT ck_cmp_etat CHECK (
                    etat IN ('en_attente', 'annule') OR reponse IS NOT NULL
                ),
                ADD CONSTRAINT ck_cmp_motif CHECK (
                    etat <> 'annule' OR motif_annulation IS NOT NULL
                )
            ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('complements');
    }
};
