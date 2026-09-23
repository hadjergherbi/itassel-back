<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doleances', function (Blueprint $table) {
            $table->index(['id_service', 'id_statut']);
            $table->index('date_depot');
        });

        Schema::table('historiques', function (Blueprint $table) {
            $table->index(['id_doleance', 'type_evenement']);
        });
    }

    public function down(): void
    {
        Schema::table('doleances', function (Blueprint $table) {
            $table->dropIndex(['id_service', 'id_statut']);
            $table->dropIndex(['date_depot']);
        });

        Schema::table('historiques', function (Blueprint $table) {
            $table->dropIndex(['id_doleance', 'type_evenement']);
        });
    }
};
