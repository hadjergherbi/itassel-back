<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications_app', function (Blueprint $table) {
            $table->id('id_notification_app');
            $table->foreignId('id_utilisateur')
                ->constrained('utilisateurs', 'id_utilisateur')
                ->cascadeOnDelete();
            $table->string('evenement', 40);
            $table->string('titre', 150);
            $table->text('message');
            $table->foreignId('id_doleance')->nullable()
                ->constrained('doleances', 'id_doleance')
                ->cascadeOnDelete();
            $table->timestamp('lue_le')->nullable();
            $table->timestamps();
            $table->index(['id_utilisateur', 'lue_le']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications_app');
    }
};
