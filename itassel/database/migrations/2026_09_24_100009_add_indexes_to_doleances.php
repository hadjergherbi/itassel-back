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
        // MariaDB retire l'index de clé étrangère devenu redondant
        // (préfixe gauche de l'index composite). On le recrée avant le drop.
        Schema::table('doleances', function (Blueprint $table) {
            $table->index('id_service', 'doleances_id_service_foreign');
        });

        Schema::table('doleances', function (Blueprint $table) {
            $table->dropIndex(['id_service', 'id_statut']);
            $table->dropIndex(['date_depot']);
        });

        Schema::table('historiques', function (Blueprint $table) {
            $table->index('id_doleance', 'historiques_id_doleance_foreign');
        });

        Schema::table('historiques', function (Blueprint $table) {
            $table->dropIndex(['id_doleance', 'type_evenement']);
        });
    }
};
