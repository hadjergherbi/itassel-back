<?php

use App\Models\Qualite;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qualites', function (Blueprint $table) {
            $table->boolean('selectionnable')->default(true);
            $table->unsignedSmallInteger('ordre')->default(0);
        });

        Qualite::synchroniserReferentiel();
    }

    public function down(): void
    {
        Schema::table('qualites', function (Blueprint $table) {
            $table->dropColumn(['selectionnable', 'ordre']);
        });
    }
};
