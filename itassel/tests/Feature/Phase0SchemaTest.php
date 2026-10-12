<?php

namespace Tests\Feature;

use App\Models\Nature;
use App\Models\Role;
use App\Models\Statut;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Phase0SchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_migrations_s_executent(): void
    {
        $this->assertSame('mysql', Schema::getConnection()->getDriverName());
        $this->assertTrue(str_ends_with(Schema::getConnection()->getDatabaseName(), '_test'));
        $this->assertTrue(Schema::hasTable('utilisateurs'));
        $this->assertTrue(Schema::hasColumn('statuts', 'code'));
        $this->assertTrue(Schema::hasColumn('statuts', 'message_citoyen'));
        $this->assertTrue(Schema::hasColumn('natures', 'famille'));
        $this->assertTrue(Schema::hasTable('roles'));
        $this->assertTrue(Schema::hasTable('jetons_mot_de_passe'));
        $this->assertTrue(Schema::hasTable('notifications_app'));
    }

    public function test_le_seeder_principal_fonctionne(): void
    {
        $this->seed();

        $this->assertTrue(Statut::where('code', 'reponse_apportee')->exists());
        $this->assertTrue(Nature::where('libelle', 'Signalement')->exists());
        $this->assertTrue(Role::where('code', 'super_admin')->exists());
    }
}
