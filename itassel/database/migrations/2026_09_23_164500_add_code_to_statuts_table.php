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
            $table->string('code', 30)->nullable()->unique()->after('id_statut');
        });

        $correspondances = [
            'Nouvelle'                 => ['nouvelle', 'Nouvelle doléance'],
            'En cours de traitement'   => ['en_cours', 'En cours'],
            'Information demandée'     => ['information_demandee', 'Information demandée'],
            'Traitée'                  => ['resolue', 'Résolu'],
            'Clôturée'                 => ['cloturee', 'Clôturée'],
            'Non fondée'               => ['non_fondee', 'Doléance non fondée'],
            'Double doléance'          => ['double', 'Double doléance'],
        ];

        foreach ($correspondances as $ancienLibelle => [$code, $nouveauLibelle]) {
            DB::table('statuts')->where('libelle', $ancienLibelle)->update([
                'code'    => $code,
                'libelle' => $nouveauLibelle,
            ]);
        }

        Schema::table('statuts', function (Blueprint $table) {
            $table->string('code', 30)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        $retours = [
            'nouvelle'              => 'Nouvelle',
            'en_cours'              => 'En cours de traitement',
            'information_demandee'  => 'Information demandée',
            'resolue'               => 'Traitée',
            'cloturee'              => 'Clôturée',
            'non_fondee'            => 'Non fondée',
            'double'                => 'Double doléance',
        ];

        foreach ($retours as $code => $ancienLibelle) {
            DB::table('statuts')->where('code', $code)->update(['libelle' => $ancienLibelle]);
        }

        Schema::table('statuts', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};

