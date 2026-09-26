<?php

namespace Tests\Feature;

use App\Models\Doleance;
use App\Models\Journal;
use App\Models\PieceJointe;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ItasselHelpers;
use Tests\TestCase;

class PieceJointeApercuTest extends TestCase
{
    use RefreshDatabase, ItasselHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerReferentiels();
        Storage::fake('local');
        Cache::flush();
    }

    public function test_pdf_jpg_png_sont_servis_en_inline(): void
    {
        $admin = $this->superAdmin();
        $this->connecter($admin);

        foreach ([
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
        ] as $type => $mime) {
            $piece = $this->pieceSurDisque(['type' => $type, 'nom_fichier' => "justificatif.{$type}"]);

            $reponse = $this->get("/api/admin/pieces-jointes/{$piece->id_piece}/apercu")
                ->assertOk();

            $this->assertSame($mime, $reponse->headers->get('Content-Type'));
            $disposition = (string) $reponse->headers->get('Content-Disposition');
            $this->assertStringContainsString('inline', $disposition);
            $this->assertStringNotContainsString('attachment', $disposition);
            $this->assertSame('nosniff', $reponse->headers->get('X-Content-Type-Options'));
            $this->assertStringContainsString('no-store', (string) $reponse->headers->get('Cache-Control'));
            $this->assertStringContainsString('private', (string) $reponse->headers->get('Cache-Control'));
        }
    }

    public function test_une_piece_d_un_autre_service_renvoie_404(): void
    {
        $sport = Service::where('nom_service', 'Sport')->first() ?? Service::first();
        $autre = Service::where('id_service', '!=', $sport->id_service)->first();
        $this->assertNotNull($autre);

        $piece = $this->pieceSurDisque([
            'id_service' => $autre->id_service,
        ]);

        $this->connecter($this->adminService($sport))
            ->getJson("/api/admin/pieces-jointes/{$piece->id_piece}/apercu")
            ->assertStatus(404)
            ->assertJson(['message' => 'Pièce jointe introuvable.']);
    }

    public function test_fichier_absent_du_disque_renvoie_404(): void
    {
        $piece = $this->pieceSurDisque();
        Storage::disk('local')->delete($piece->chemin);

        $this->connecter($this->superAdmin())
            ->getJson("/api/admin/pieces-jointes/{$piece->id_piece}/apercu")
            ->assertStatus(404)
            ->assertJson(['message' => 'Le fichier est absent du serveur.']);
    }

    public function test_non_authentifie_renvoie_401(): void
    {
        $piece = $this->pieceSurDisque();

        $this->getJson("/api/admin/pieces-jointes/{$piece->id_piece}/apercu")
            ->assertStatus(401);
    }

    public function test_admin_service_peut_apercu_mais_pas_telecharger(): void
    {
        $sport = Service::where('nom_service', 'Sport')->first() ?? $this->serviceUsuel();
        $piece = $this->pieceSurDisque(['id_service' => $sport->id_service]);

        $this->connecter($this->adminService($sport))
            ->get("/api/admin/pieces-jointes/{$piece->id_piece}/apercu")
            ->assertOk();

        $this->getJson("/api/admin/pieces-jointes/{$piece->id_piece}/telecharger")
            ->assertForbidden();
    }

    public function test_telecharger_reste_en_attachment(): void
    {
        $piece = $this->pieceSurDisque(['nom_fichier' => 'rapport.pdf', 'type' => 'pdf']);

        $this->connecter($this->superAdmin());
        $reponse = $this->get("/api/admin/pieces-jointes/{$piece->id_piece}/telecharger")
            ->assertOk();

        $this->assertStringContainsString('attachment', (string) $reponse->headers->get('Content-Disposition'));
        $this->assertStringNotContainsString('inline', (string) $reponse->headers->get('Content-Disposition'));
        $this->assertSame('application/pdf', $reponse->headers->get('Content-Type'));
    }

    public function test_deux_apercus_rapproches_n_ecrivent_qu_une_entree_de_journal(): void
    {
        $admin = $this->superAdmin();
        $piece = $this->pieceSurDisque(['nom_fichier' => 'scan-identite.pdf']);

        $this->connecter($admin)
            ->get("/api/admin/pieces-jointes/{$piece->id_piece}/apercu")
            ->assertOk();
        $this->get("/api/admin/pieces-jointes/{$piece->id_piece}/apercu")
            ->assertOk();

        $lignes = Journal::where('action', 'consultation_piece_jointe')->get();
        $this->assertCount(1, $lignes);
        $this->assertSame($admin->id_utilisateur, $lignes->first()->id_utilisateur);
        $this->assertSame('scan-identite.pdf', $lignes->first()->detail);
        $this->assertSame(Doleance::class, $lignes->first()->cible_type);
        $this->assertSame($piece->id_doleance, (int) $lignes->first()->cible_id);
        $this->assertSame('doleance', $lignes->first()->categorie);
    }

    private function pieceSurDisque(array $attrs = []): PieceJointe
    {
        $doleance = $this->doleance(array_filter([
            'id_service' => $attrs['id_service'] ?? null,
        ]));
        unset($attrs['id_service']);

        $type = $attrs['type'] ?? 'pdf';
        $chemin = 'pieces-jointes/'.$doleance->id_doleance.'/'.uniqid('pj_', true).'.'.$type;
        Storage::disk('local')->put($chemin, 'contenu-test-'.$type);

        return PieceJointe::create(array_merge([
            'nom_fichier' => 'justificatif.'.$type,
            'type'        => $type,
            'taille'      => 20,
            'chemin'      => $chemin,
            'origine'     => 'DEPOT_INITIAL',
            'id_doleance' => $doleance->id_doleance,
        ], $attrs));
    }
}
