<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('statuts', function (Blueprint $table) {
            $table->string('message_citoyen', 255)->nullable();
            $table->boolean('selectionnable')->default(true);
            $table->unsignedSmallInteger('ordre')->default(0);
        });

        $nouveaux = [
            ['reponse_apportee', 'Réponse apportée', 'vert', 'Une réponse à votre demande est disponible.', true, 40],
            ['hors_competence', 'Hors compétence', 'gris', 'Votre demande relève d\'un autre organisme.', true, 50],
            ['non_retenue', 'Non fondée', 'rouge', 'Votre réclamation n\'a pas été retenue après examen.', true, 60],
        ];

        foreach ($nouveaux as [$code, $libelle, $couleur, $message, $selectionnable, $ordre]) {
            DB::table('statuts')->updateOrInsert(
                ['code' => $code],
                [
                    'libelle'          => $libelle,
                    'couleur'          => $couleur,
                    'message_citoyen'  => $message,
                    'selectionnable'   => $selectionnable,
                    'ordre'            => $ordre,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]
            );
        }

        $messages = [
            'resolue'          => ['Une réponse est disponible.', 30, true],
            'reponse_apportee' => ['Une réponse à votre demande est disponible.', 40, true],
            'hors_competence'  => ['Votre demande relève d\'un autre organisme.', 50, true],
            'non_retenue'      => ['Votre réclamation n\'a pas été retenue après examen.', 60, true],
            'double'           => ['Votre demande a déjà été enregistrée.', 70, true],
            'nouvelle'         => [null, 10, true],
            'en_cours'         => [null, 20, true],
            'information_demandee' => [null, 25, true],
        ];

        foreach ($messages as $code => [$message, $ordre, $selectionnable]) {
            DB::table('statuts')->where('code', $code)->update([
                'message_citoyen' => $message,
                'ordre'           => $ordre,
                'selectionnable'  => $selectionnable,
            ]);
        }

        DB::table('statuts')->where('code', 'non_fondee')->update([
            'libelle'         => 'Ancien classement — non fondée',
            'selectionnable'  => false,
            'ordre'           => 80,
        ]);

        DB::table('statuts')->where('code', 'cloturee')->update([
            'selectionnable' => false,
            'ordre'          => 90,
        ]);

        Schema::table('natures', function (Blueprint $table) {
            $table->enum('famille', ['reclamation', 'demande'])->default('reclamation');
        });

        DB::table('natures')->whereIn('libelle', ['Suggestion', "Demande d'information"])
            ->update(['famille' => 'demande']);

        Schema::table('doleances', function (Blueprint $table) {
            $table->string('organisme_competent', 150)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('doleances', function (Blueprint $table) {
            $table->dropColumn('organisme_competent');
        });

        Schema::table('natures', function (Blueprint $table) {
            $table->dropColumn('famille');
        });

        DB::table('statuts')->where('code', 'non_fondee')->update([
            'libelle'         => 'Doléance non fondée',
            'selectionnable'  => true,
        ]);

        DB::table('statuts')->where('code', 'cloturee')->update([
            'selectionnable' => true,
        ]);

        foreach (['reponse_apportee', 'hors_competence', 'non_retenue'] as $code) {
            $statut = DB::table('statuts')->where('code', $code)->first();
            if (! $statut) {
                continue;
            }
            $utilise = DB::table('doleances')->where('id_statut', $statut->id_statut)->exists();
            if (! $utilise) {
                DB::table('statuts')->where('code', $code)->delete();
            }
        }

        Schema::table('statuts', function (Blueprint $table) {
            $table->dropColumn(['message_citoyen', 'selectionnable', 'ordre']);
        });
    }
};
