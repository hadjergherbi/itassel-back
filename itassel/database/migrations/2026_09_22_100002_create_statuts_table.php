<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statuts', function (Blueprint $table) {
            $table->id('id_statut');
            $table->string('libelle', 60)->unique();
            $table->string('couleur', 20);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statuts');
    }
};
