<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modeles_message', function (Blueprint $table) {
            $table->id('id_modele');
            $table->string('titre', 100);
            $table->text('contenu');
            $table->string('type_usage', 40); // reponse, conclusion, complement...
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modeles_message');
    }
};
