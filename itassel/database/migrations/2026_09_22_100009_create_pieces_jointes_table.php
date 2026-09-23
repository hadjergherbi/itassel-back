<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pieces_jointes', function (Blueprint $table) {
            $table->id('id_piece');
            $table->string('nom_fichier', 200);
            $table->enum('type', ['pdf', 'jpg', 'png']);
            $table->unsignedInteger('taille'); // octets
            $table->string('chemin', 255);
            $table->string('origine', 50); // DEPOT_INITIAL ou COMPLEMENT
            $table->foreignId('id_doleance')->constrained('doleances', 'id_doleance')->cascadeOnDelete();
            $table->foreignId('id_complement')->nullable()
                ->constrained('complements', 'id_complement')->cascadeOnDelete();
            $table->timestamps();
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("
                ALTER TABLE pieces_jointes
                ADD CONSTRAINT ck_pj_taille CHECK (taille > 0 AND taille <= 5242880),
                ADD CONSTRAINT ck_pj_origine CHECK (
                    (origine = 'DEPOT_INITIAL' AND id_complement IS NULL) OR
                    (origine = 'COMPLEMENT' AND id_complement IS NOT NULL)
                )
            ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pieces_jointes');
    }
};
