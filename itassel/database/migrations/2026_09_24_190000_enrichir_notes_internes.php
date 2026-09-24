<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notes_internes', function (Blueprint $table) {
            $table->enum('etiquette', ['information', 'a_verifier', 'urgent'])->nullable()->after('contenu');
            $table->boolean('epinglee')->default(false)->after('etiquette');
            $table->timestamp('epinglee_le')->nullable()->after('epinglee');
            $table->foreignId('id_epinglee_par')->nullable()->after('epinglee_le')
                ->constrained('utilisateurs', 'id_utilisateur')->nullOnDelete();
            $table->timestamp('modifiee_le')->nullable()->after('id_epinglee_par');
        });

        Schema::create('note_mentions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_note')
                ->constrained('notes_internes', 'id_note')
                ->cascadeOnDelete();
            $table->foreignId('id_utilisateur')
                ->constrained('utilisateurs', 'id_utilisateur')
                ->cascadeOnDelete();
            $table->boolean('notifie_email')->default(false);
            $table->timestamps();
            $table->unique(['id_note', 'id_utilisateur']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_mentions');

        Schema::table('notes_internes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_epinglee_par');
            $table->dropColumn(['etiquette', 'epinglee', 'epinglee_le', 'modifiee_le']);
        });
    }
};
