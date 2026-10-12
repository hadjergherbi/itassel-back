<?php

namespace Tests\Feature;

use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ItasselHelpers;
use Tests\TestCase;

class ContratServicesUtilisateurTest extends TestCase
{
    use ItasselHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerReferentiels();
    }

    public function test_le_contrat_conserve_le_service_principal_et_ajoute_la_liste(): void
    {
        $sport = $this->serviceUsuel();
        $jeunesse = Service::where('nom_service', 'Jeunesse')->firstOrFail();
        $admin = $this->adminService($sport, ['email' => 'contrat.multi@itassel.test']);
        $admin->services()->syncWithoutDetaching([$jeunesse->id_service]);
        $admin->oublierIdsServices();

        $super = $this->superAdmin(['email' => 'contrat.super@itassel.test']);
        $this->connecter($super);

        $ligne = collect($this->getJson('/api/admin/utilisateurs?service='.$jeunesse->id_service)->assertOk()->json('data'))
            ->firstWhere('id_utilisateur', $admin->id_utilisateur);

        $this->assertSame($sport->id_service, $ligne['service']['id_service']);
        $this->assertSame('Sport', $ligne['service']['nom_service']);
        $services = collect($ligne['services'])->keyBy('id_service');
        $this->assertTrue($services[$sport->id_service]['principal']);
        $this->assertFalse($services[$jeunesse->id_service]['principal']);

        $me = $this->connecter($admin)->getJson('/api/admin/me')->assertOk()->json();
        $this->assertSame($sport->id_service, $me['service']['id_service']);
        $this->assertCount(2, $me['services']);

        $moi = $this->connecter($super)->getJson('/api/admin/me')->assertOk()->json();
        $this->assertNull($moi['service']);
        $this->assertSame([], $moi['services']);

        $admin->actif = false;
        $admin->save();

        $desactives = collect($this->connecter($super)
            ->getJson('/api/admin/utilisateurs?etat=desactive')
            ->assertOk()
            ->json('data'));
        $this->assertTrue($desactives->contains('id_utilisateur', $admin->id_utilisateur));
        $this->assertFalse($desactives->contains('id_utilisateur', $super->id_utilisateur));
    }
}
