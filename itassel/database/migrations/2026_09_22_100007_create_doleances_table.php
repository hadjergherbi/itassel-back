<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doleances', function (Blueprint $table) {
            $table->id('id_doleance');
            $table->string('reference', 20)->unique();
            $table->string('nom', 60);
            $table->string('prenom', 60);
            $table->string('email', 120);
            $table->string('telephone', 20);
            $table->string('wilaya', 40);
            $table->string('objet', 200);
            $table->text('description');
            $table->timestamp('date_depot')->useCurrent();
            $table->foreignId('id_service')->constrained('services', 'id_service');
            $table->foreignId('id_statut')->constrained('statuts', 'id_statut');
            $table->foreignId('id_nature')->constrained('natures', 'id_nature');
            $table->foreignId('id_qualite')->constrained('qualites', 'id_qualite');
            $table->foreignId('id_responsable')->nullable()
                ->constrained('utilisateurs', 'id_utilisateur'); // un seul responsable par dossier
            $table->foreignId('id_doleance_initial')->nullable()
                ->constrained('doleances', 'id_doleance'); // pour une « double doléance »
            $table->timestamps();
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::unprepared("
                CREATE TRIGGER trg_doleance_initiale
                BEFORE UPDATE ON doleances
                FOR EACH ROW
                WHEN NEW.id_doleance_initial IS NOT NULL
                     AND NEW.id_doleance_initial = NEW.id_doleance
                BEGIN
                    SELECT RAISE(ABORT, 'Une doléance ne peut pas être son propre dossier initial');
                END
            ");
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::unprepared('
                CREATE TRIGGER trg_doleance_initiale BEFORE UPDATE ON doleances
                FOR EACH ROW
                BEGIN
                    IF NEW.id_doleance_initial IS NOT NULL AND NEW.id_doleance_initial = NEW.id_doleance THEN
                        SIGNAL SQLSTATE \'45000\'
                        SET MESSAGE_TEXT = \'Une doléance ne peut pas être son propre dossier initial\';
                    END IF;
                END
            ');
        }
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_doleance_initiale');
        Schema::dropIfExists('doleances');
    }
};
