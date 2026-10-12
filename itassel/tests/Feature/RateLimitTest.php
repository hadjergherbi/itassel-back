<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\ItasselHelpers;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use ItasselHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerReferentiels();
        Cache::flush();
    }

    public function test_epuiser_le_login_ne_bloque_pas_le_mot_de_passe(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/admin/login', [
                'email' => 'personne@itassel.test',
                'mot_de_passe' => 'mauvais',
            ])->assertStatus(422);
        }

        $this->postJson('/api/admin/login', [
            'email' => 'personne@itassel.test',
            'mot_de_passe' => 'mauvais',
        ])->assertStatus(429);

        $this->postJson('/api/admin/mot-de-passe-oublie', [
            'email' => 'personne@itassel.test',
        ])->assertOk();
    }

    public function test_epuiser_le_jeton_formulaire_ne_bloque_pas_le_suivi(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->getJson('/api/formulaire/jeton')->assertOk();
        }

        $this->getJson('/api/formulaire/jeton')->assertStatus(429);
        $this->getJson('/api/suivi/dossier')->assertStatus(401);
    }
}
