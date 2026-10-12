<?php

namespace Tests\Feature;

use App\Support\ProxiesDeConfiance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ProxiesDeConfianceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TrustProxies::flushState();
        parent::tearDown();
    }

    public function test_seuls_les_proxys_listes_transmettent_ladresse_cliente(): void
    {
        Route::get('/api/test-ip', fn () => response()->json(['ip' => request()->ip()]));

        $sansConfiance = $this->call('GET', '/api/test-ip', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
        ]);
        $sansConfiance->assertOk()->assertJsonPath('ip', '10.0.0.1');

        TrustProxies::at(ProxiesDeConfiance::depuis('10.0.0.1'));

        $avecConfiance = $this->call('GET', '/api/test-ip', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
        ]);
        $avecConfiance->assertOk()->assertJsonPath('ip', '203.0.113.9');
    }

    public function test_letoile_est_acceptee_seulement_si_elle_est_explicite(): void
    {
        $this->assertNull(ProxiesDeConfiance::depuis(null));
        $this->assertNull(ProxiesDeConfiance::depuis(''));
        $this->assertSame('*', ProxiesDeConfiance::depuis('*'));
        $this->assertSame(['10.0.0.1', '10.0.0.2'], ProxiesDeConfiance::depuis('10.0.0.1, 10.0.0.2'));
    }
}
