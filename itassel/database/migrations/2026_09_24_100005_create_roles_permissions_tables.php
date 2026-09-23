<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id('id_role');
            $table->string('code', 30)->unique();
            $table->string('libelle');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id('id_permission');
            $table->string('code', 60)->unique();
            $table->string('libelle');
            $table->string('groupe', 40);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('role_permission', function (Blueprint $table) {
            $table->foreignId('id_role')->constrained('roles', 'id_role')->cascadeOnDelete();
            $table->foreignId('id_permission')->constrained('permissions', 'id_permission')->cascadeOnDelete();
            $table->primary(['id_role', 'id_permission']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permission');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
