<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('codes_verification', function (Blueprint $table) {
            $table->id('id_code');
            $table->char('code_hash', 64); // empreinte SHA-256, jamais le code en clair
            $table->timestamp('date_creation')->useCurrent();
            // dateTime (pas timestamp) : MySQL refuse un 2e TIMESTAMP NOT NULL sans DEFAULT
            $table->dateTime('date_expiration'); // création + 10 minutes
            $table->tinyInteger('nombre_essais')->default(0); // 5 essais maximum
            $table->boolean('utilise')->default(false);
            $table->foreignId('id_doleance')->constrained('doleances', 'id_doleance')->cascadeOnDelete();
            $table->timestamps();
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("
                ALTER TABLE codes_verification
                ADD CONSTRAINT ck_code_essais CHECK (nombre_essais BETWEEN 0 AND 5),
                ADD CONSTRAINT ck_code_dates CHECK (date_expiration > date_creation)
            ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('codes_verification');
    }
};
