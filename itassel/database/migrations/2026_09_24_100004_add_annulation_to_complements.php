<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complements', function (Blueprint $table) {
            $table->foreignId('id_annule_par')->nullable()
                ->constrained('utilisateurs', 'id_utilisateur')
                ->nullOnDelete();
            $table->timestamp('date_annulation')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('complements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_annule_par');
            $table->dropColumn('date_annulation');
        });
    }
};
