<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * etat_envoi était un enum MySQL (transmis, non_transmis).
 * « en_attente » = ligne créée, job pas encore abouti.
 * Sur SQLite, l'enum est un CHECK inline : la migration recrée la table pour l'élargir.
 * La contrainte ck_not_date autorise une date nulle pour non_transmis et en_attente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->elargirEnumSqlite("check (\"etat_envoi\" in ('en_attente', 'transmis', 'non_transmis'))");

            return;
        }

        DB::statement('ALTER TABLE notifications_itassel DROP CONSTRAINT ck_not_date');
        DB::statement("ALTER TABLE notifications_itassel MODIFY etat_envoi ENUM('en_attente', 'transmis', 'non_transmis') NOT NULL DEFAULT 'en_attente'");
        DB::statement("
            ALTER TABLE notifications_itassel
            ADD CONSTRAINT ck_not_date CHECK (etat_envoi IN ('non_transmis', 'en_attente') OR date_envoi IS NOT NULL)
        ");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::table('notifications_itassel')->where('etat_envoi', 'en_attente')->update([
            'etat_envoi' => 'non_transmis',
            'date_envoi' => null,
        ]);

        DB::statement('ALTER TABLE notifications_itassel DROP CONSTRAINT ck_not_date');
        DB::statement("ALTER TABLE notifications_itassel MODIFY etat_envoi ENUM('transmis', 'non_transmis') NOT NULL");
        DB::statement("
            ALTER TABLE notifications_itassel
            ADD CONSTRAINT ck_not_date CHECK (etat_envoi = 'non_transmis' OR date_envoi IS NOT NULL)
        ");
    }

    /**
     * Le CHECK d'un enum Laravel est inline dans la colonne : il faut recréer la table.
     */
    private function elargirEnumSqlite(string $check): void
    {
        $definition = DB::selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'notifications_itassel'");
        $sql = preg_replace(
            '/check \\("etat_envoi" in \\([^)]+\\)\\)/',
            $check,
            (string) $definition->sql,
            1
        );

        DB::statement('PRAGMA foreign_keys = OFF');
        DB::statement('ALTER TABLE notifications_itassel RENAME TO notifications_itassel_ancien_enum');
        DB::statement($sql);
        DB::statement('INSERT INTO notifications_itassel SELECT * FROM notifications_itassel_ancien_enum');
        DB::statement('DROP TABLE notifications_itassel_ancien_enum');
        DB::statement('PRAGMA foreign_keys = ON');
    }
};
