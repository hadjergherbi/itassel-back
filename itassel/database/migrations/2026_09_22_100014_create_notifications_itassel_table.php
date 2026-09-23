<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Nommée "notifications_itassel" pour ne pas entrer en conflit avec la table
    // "notifications" que Laravel réserve à son propre système de notifications.
    public function up(): void
    {
        Schema::create('notifications_itassel', function (Blueprint $table) {
            $table->id('id_notification');
            $table->string('type_notification', 50); // depot, changement_statut, reponse, complement, code_verification...
            $table->string('destinataire', 100); // adresse email
            $table->enum('etat_envoi', ['transmis', 'non_transmis']);
            $table->timestamp('date_envoi')->nullable();
            $table->foreignId('id_doleance')->constrained('doleances', 'id_doleance')->cascadeOnDelete();
            $table->foreignId('id_evenement')->nullable()
                ->constrained('historiques', 'id_evenement')->nullOnDelete();
            $table->timestamps();
        });

        DB::statement("
            ALTER TABLE notifications_itassel
            ADD CONSTRAINT ck_not_date CHECK (etat_envoi = 'non_transmis' OR date_envoi IS NOT NULL)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications_itassel');
    }
};
