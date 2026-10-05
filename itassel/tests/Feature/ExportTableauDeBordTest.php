<?php

namespace Tests\Feature;

use App\Models\Journal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ItasselHelpers;
use Tests\TestCase;

class ExportTableauDeBordTest extends TestCase
{
    use ItasselHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerReferentiels();
    }

    public function test_le_super_admin_telecharge_un_pdf_journalise(): void
    {
        $admin = $this->superAdmin();
        $doleance = $this->doleance([
            'email' => 'citoyen.secret@example.test',
            'telephone' => '0551234567',
            'nom' => 'Benali',
            'prenom' => 'Karim',
        ]);

        $pdf = $this->connecter($admin)
            ->get('/api/admin/tableau-de-bord/export?periode=6m')
            ->assertOk();

        $this->assertStringContainsString('application/pdf', (string) $pdf->headers->get('Content-Type'));
        $contenu = $pdf->getContent();
        $this->assertStringStartsWith('%PDF', $contenu);
        $this->assertStringNotContainsString($doleance->email, $contenu);
        $this->assertStringNotContainsString($doleance->telephone, $contenu);
        $this->assertTrue(
            Journal::where('action', 'export_pdf')
                ->where('detail', 'like', 'Statistiques du tableau de bord — période 6m')
                ->exists()
        );
    }

    public function test_un_admin_de_service_recoit_403(): void
    {
        $this->connecter($this->adminService())
            ->getJson('/api/admin/tableau-de-bord/export')
            ->assertStatus(403);
    }

    public function test_sans_jeton_recoit_401(): void
    {
        $this->getJson('/api/admin/tableau-de-bord/export')->assertStatus(401);
    }

    public function test_une_periode_invalide_renvoie_422(): void
    {
        $this->connecter($this->superAdmin())
            ->getJson('/api/admin/tableau-de-bord/export?periode=semaine')
            ->assertStatus(422);
    }
}
