<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications_app', function (Blueprint $table) {
            $table->foreignId('id_note')->nullable()->after('id_doleance')
                ->constrained('notes_internes', 'id_note')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('notifications_app', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_note');
        });
    }
};
