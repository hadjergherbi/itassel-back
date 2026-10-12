<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class VerifierProductionTest extends TestCase
{
    use RefreshDatabase;

    public function test_une_exception_api_est_masquee_quand_debug_est_faux(): void
    {
        config(['app.debug' => false]);

        Route::get('/api/test-erreur-interne', function () {
            throw new RuntimeException('SELECT secret FROM users -- /var/www/html/app/Secret.php');
        });

        $reponse = $this->getJson('/api/test-erreur-interne')->assertStatus(500);

        $corps = $reponse->getContent();
        $this->assertSame('Une erreur interne est survenue.', $reponse->json('message'));
        $this->assertStringNotContainsString('Secret.php', $corps);
        $this->assertStringNotContainsString('SELECT', $corps);
        $this->assertStringNotContainsString('stack', mb_strtolower($corps));
    }

    public function test_debug_actif_en_production_fait_echouer_la_commande(): void
    {
        config([
            'app.env' => 'production',
            'app.debug' => true,
        ]);

        $this->artisan('itassel:verifier-production')->assertFailed();
    }

    public function test_environnement_local_fait_echouer_la_commande(): void
    {
        config([
            'app.env' => 'local',
            'app.debug' => false,
        ]);

        $this->artisan('itassel:verifier-production')->assertFailed();
    }

    public function test_compte_demo_actif_en_production_est_signale(): void
    {
        $this->seed();
        config([
            'app.env' => 'production',
            'app.debug' => false,
        ]);

        Artisan::call('itassel:verifier-production');
        $sortie = Artisan::output();

        $this->assertMatchesRegularExpression('/KO\s*\|\s*Aucun compte démo actif/', $sortie);
    }
}
