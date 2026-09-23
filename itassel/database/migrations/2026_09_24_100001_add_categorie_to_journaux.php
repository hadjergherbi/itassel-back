<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journaux', function (Blueprint $table) {
            $table->string('categorie', 20)->nullable()->after('action');
            $table->string('cible_type', 40)->nullable();
            $table->unsignedBigInteger('cible_id')->nullable();
            $table->index('categorie');
            $table->index('date_action');
            $table->index('action');
            $table->index('resultat');
            $table->index(['cible_type', 'cible_id']);
        });

        DB::table('journaux')->whereIn('action', ['connexion', 'deconnexion'])->update(['categorie' => 'connexion']);
        DB::table('journaux')->where('action', 'export_csv')->update(['categorie' => 'export']);
    }

    public function down(): void
    {
        Schema::table('journaux', function (Blueprint $table) {
            $table->dropIndex(['categorie']);
            $table->dropIndex(['date_action']);
            $table->dropIndex(['action']);
            $table->dropIndex(['resultat']);
            $table->dropIndex(['cible_type', 'cible_id']);
            $table->dropColumn(['categorie', 'cible_type', 'cible_id']);
        });
    }
};
