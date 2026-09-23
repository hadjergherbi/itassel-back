<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journaux', function (Blueprint $table) {
            $table->id('id_journal');
            $table->timestamp('date_action')->useCurrent();
            $table->string('compte', 120); // email utilisé, même si compte inconnu
            $table->string('action', 60); // connexion, export_csv, affectation, creation_utilisateur...
            $table->text('detail')->nullable();
            $table->string('adresse_ip', 45);
            $table->enum('resultat', ['succes', 'echec']);
            $table->foreignId('id_utilisateur')->nullable()
                ->constrained('utilisateurs', 'id_utilisateur')->nullOnDelete(); // NULL si compte inconnu
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journaux');
    }
};
