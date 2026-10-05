<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doleances', function (Blueprint $table) {
            $table->string('langue', 2)->default('fr');
        });
    }

    public function down(): void
    {
        Schema::table('doleances', function (Blueprint $table) {
            $table->dropColumn('langue');
        });
    }
};
