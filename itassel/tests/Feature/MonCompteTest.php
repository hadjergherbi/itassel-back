<?php

namespace Tests\Feature;

use App\Models\Journal;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ItasselHelpers;
use Tests\TestCase;

class MonCompteTest extends TestCase
{
    use RefreshDatabase, ItasselHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerReferentiels();
        Carbon::setTestNow(Carbon::parse('2026-09-24 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_deux_logins_renseignent_connexion_precedente(): void
    {
        $admin = $this->adminService(null, ['email' => 'compte@itassel.test']);

        $this->postJson('/api/admin/login', [
            'email' => 'compte@itassel.test',
            'mot_de_passe' => 'Itassel2026!',
        ])->assertOk();

        $premiere = $admin->fresh()->derniere_connexion;
        $this->assertNotNull($premiere);
        $this->assertNull($admin->fresh()->connexion_precedente);

        Carbon::setTestNow(Carbon::parse('2026-09-24 11:00:00'));

        $this->postJson('/api/admin/login', [
            'email' => 'compte@itassel.test',
            'mot_de_passe' => 'Itassel2026!',
        ])->assertOk();

        $admin = $admin->fresh();
        $this->assertEquals($premiere->toDateTimeString(), $admin->connexion_precedente->toDateTimeString());
        $this->assertTrue($admin->derniere_connexion->gt($admin->connexion_precedente));

        $token = $admin->createToken('me')->plainTextToken;
        $me = $this->withToken($token)->getJson('/api/admin/me')->assertOk()->json();
        $this->assertArrayHasKey('connexion_precedente', $me);
        $this->assertArrayHasKey('derniere_connexion', $me);
        $this->assertArrayHasKey('libelle_role', $me);
        $this->assertSame('compte@itassel.test', $me['email']);
    }

    public function test_changement_de_mot_de_passe(): void
    {
        $admin = $this->adminService(null, ['email' => 'mdp@itassel.test']);
        $tokenCourant = $admin->createToken('courant')->plainTextToken;
        $tokenAutre = $admin->createToken('autre')->plainTextToken;

        $this->withToken($tokenCourant)->putJson('/api/admin/mot-de-passe', [
            'mot_de_passe_actuel' => 'mauvais',
            'mot_de_passe' => 'NouveauPass1',
            'mot_de_passe_confirmation' => 'NouveauPass1',
        ])->assertStatus(422)->assertJsonValidationErrors(['mot_de_passe_actuel']);

        $this->withToken($tokenCourant)->putJson('/api/admin/mot-de-passe', [
            'mot_de_passe_actuel' => 'Itassel2026!',
            'mot_de_passe' => 'Itassel2026!',
            'mot_de_passe_confirmation' => 'Itassel2026!',
        ])->assertStatus(422)->assertJsonValidationErrors(['mot_de_passe']);

        $this->withToken($tokenCourant)->putJson('/api/admin/mot-de-passe', [
            'mot_de_passe_actuel' => 'Itassel2026!',
            'mot_de_passe' => 'NouveauPass1',
            'mot_de_passe_confirmation' => 'NouveauPass1',
        ])->assertOk()->assertJson(['message' => 'Mot de passe mis à jour.']);

        $this->assertSame(1, $admin->fresh()->tokens()->count());
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->withToken($tokenAutre)->getJson('/api/admin/me')->assertStatus(401);
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->withToken($tokenCourant)->getJson('/api/admin/me')->assertOk();
        $this->assertTrue(Journal::where('action', 'changement_mot_de_passe')->exists());
    }
}
