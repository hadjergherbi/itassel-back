<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parametres_notification', function (Blueprint $table) {
            $table->id('id_parametre_notification');
            $table->string('evenement', 40);
            $table->string('destinataire', 30);
            $table->boolean('canal_email')->default(false);
            $table->boolean('canal_app')->default(false);
            $table->boolean('modifiable')->default(true);
            $table->timestamps();
            $table->unique(['evenement', 'destinataire']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parametres_notification');
    }
};
