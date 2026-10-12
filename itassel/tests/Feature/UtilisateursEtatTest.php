<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ItasselHelpers;
use Tests\TestCase;

class UtilisateursEtatTest extends TestCase
{
    use ItasselHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerReferentiels();
        $this->connecter($this->superAdmin());
    }

    public function test_le_filtre_etat_pagine_en_base(): void
    {
        $service = $this->serviceUsuel();
        for ($i = 0; $i < 30; $i++) {
            $this->adminService($service, ['nom' => sprintf('Actif%02d', $i)]);
        }
        $this->adminService($service, ['nom' => 'Inactif', 'actif' => false]);

        $reponse = $this->getJson('/api/admin/utilisateurs?etat=actif')->assertOk();

        $this->assertCount(25, $reponse->json('data'));
        $this->assertGreaterThan(25, $reponse->json('total'));
        $this->assertSame(25, $reponse->json('per_page'));
        foreach ($reponse->json('data') as $ligne) {
            $this->assertSame('actif', $ligne['etat_compte']);
            $this->assertIsBool($ligne['est_responsable']);
        }
        $this->assertFalse(collect($reponse->json('data'))->contains('nom', 'Inactif'));

        $expires = $this->adminService($service, ['nom' => 'Expire']);
        $expires->forceFill([
            'actif' => true,
            'mot_de_passe_defini_le' => null,
            'invitation_envoyee_le' => now()->subHours(80),
        ])->save();

        $attente = $this->adminService($service, ['nom' => 'Attente']);
        $attente->forceFill([
            'actif' => true,
            'mot_de_passe_defini_le' => null,
            'invitation_envoyee_le' => now()->subHours(2),
        ])->save();

        $expirees = $this->getJson('/api/admin/utilisateurs?etat=invitation_expiree')->assertOk();
        $this->assertTrue(collect($expirees->json('data'))->contains('nom', 'Expire'));
        $this->assertFalse(collect($expirees->json('data'))->contains('nom', 'Attente'));
        $this->assertSame(
            collect($expirees->json('data'))->count(),
            $expirees->json('total')
        );

        $enAttente = $this->getJson('/api/admin/utilisateurs?etat=invitation_en_attente')->assertOk();
        $this->assertTrue(collect($enAttente->json('data'))->contains('nom', 'Attente'));
        $this->assertFalse(collect($enAttente->json('data'))->contains('nom', 'Expire'));
    }

    public function test_un_admin_de_service_ne_peut_pas_perdre_son_service(): void
    {
        $admin = $this->adminService($this->serviceUsuel());

        $this->putJson("/api/admin/utilisateurs/{$admin->id_utilisateur}", [
            'id_service' => null,
        ])->assertStatus(422)->assertJsonValidationErrors('id_service');

        $this->assertNotNull($admin->fresh()->id_service);
    }

    public function test_un_remplacant_supprime_est_refuse(): void
    {
        $service = $this->serviceUsuel();
        $cible = $this->adminService($service);
        $remplacant = $this->adminService($service);
        $remplacant->delete();

        $this->postJson("/api/admin/utilisateurs/{$cible->id_utilisateur}/desactiver", [
            'id_remplacant' => $remplacant->id_utilisateur,
        ])->assertStatus(422)->assertJsonValidationErrors('id_remplacant');

        $this->assertTrue($cible->fresh()->actif);
    }
}
