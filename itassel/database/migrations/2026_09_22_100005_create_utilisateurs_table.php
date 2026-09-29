<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utilisateurs', function (Blueprint $table) {
            $table->id('id_utilisateur');
            $table->string('nom', 60);
            $table->string('prenom', 60);
            $table->string('email', 120)->unique();
            $table->string('mot_de_passe', 255); // hash uniquement (Hash::make)
            $table->enum('role', ['admin_service', 'super_admin']);
            $table->boolean('actif')->default(true);
            $table->foreignId('id_service')->nullable()
                ->constrained('services', 'id_service'); // NULL pour le Super administrateur
            $table->rememberToken();
            $table->timestamps();
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("
                ALTER TABLE utilisateurs
                ADD CONSTRAINT ck_util_role
                CHECK (role = 'admin_service' OR id_service IS NULL)
            ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('utilisateurs');
    }
};
