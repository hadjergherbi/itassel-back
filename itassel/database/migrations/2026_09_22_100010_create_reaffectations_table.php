<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reaffectations', function (Blueprint $table) {
            $table->id('id_reaffectation');
            $table->enum('etat', ['en_attente', 'acceptee', 'refusee', 'annulee', 'sans_suite'])
                ->default('en_attente');
            $table->text('motif'); // motif donné par le demandeur
            $table->timestamp('date_demande')->useCurrent();
            $table->timestamp('date_decision')->nullable();
            $table->foreignId('id_doleance')->constrained('doleances', 'id_doleance')->cascadeOnDelete();
            $table->foreignId('id_demandeur')->constrained('utilisateurs', 'id_utilisateur');
            $table->foreignId('id_decideur')->nullable()->constrained('utilisateurs', 'id_utilisateur');
            $table->foreignId('id_service_propose')->constrained('services', 'id_service'); // suggéré
            $table->foreignId('id_service_destination')->nullable()
                ->constrained('services', 'id_service'); // choisi par le Super administrateur
            $table->timestamps();
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("
                ALTER TABLE reaffectations
                ADD CONSTRAINT ck_rea_traite CHECK (
                    (etat = 'en_attente' AND date_decision IS NULL) OR
                    (etat <> 'en_attente' AND date_decision IS NOT NULL)
                ),
                ADD CONSTRAINT ck_rea_decision CHECK (
                    etat NOT IN ('acceptee', 'refusee') OR id_decideur IS NOT NULL
                ),
                ADD CONSTRAINT ck_rea_dest CHECK (
                    etat <> 'acceptee' OR id_service_destination IS NOT NULL
                )
            ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reaffectations');
    }
};
