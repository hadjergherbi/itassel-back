<?php

namespace Tests\Feature;

use App\Models\Journal;
use App\Models\Service;
use App\Models\Statut;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Support\ItasselHelpers;
use Tests\TestCase;

class UtilisateurAffectationServicesTest extends TestCase
{
    use ItasselHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerReferentiels();
        Mail::fake();
        $this->connecter($this->superAdmin());
    }

    public function test_creation_avec_deux_services_et_principal(): void
    {
        [$sport, $jeunesse] = $this->deuxServices();

        $this->postJson('/api/admin/utilisateurs', [
            'nom' => 'Bensalem',
            'prenom' => 'Amel',
            'email' => 'amel.multi@itassel.test',
            'role' => 'admin_service',
            'ids_services' => [$jeunesse->id_service, $sport->id_service],
            'id_service_principal' => $sport->id_service,
        ])->assertCreated()
            ->assertJsonPath('utilisateur.service.id_service', $sport->id_service);

        $admin = Utilisateur::where('email', 'amel.multi@itassel.test')->firstOrFail();
        $this->assertSame($sport->id_service, $admin->id_service);
        $this->assertEqualsCanonicalizing(
            [$sport->id_service, $jeunesse->id_service],
            $this->idsPivot($admin)
        );

        $detail = Journal::where('action', 'creation_utilisateur')->latest('id_journal')->value('detail');
        $this->assertStringContainsString('Jeunesse', (string) $detail);
        $this->assertStringContainsString('Sport', (string) $detail);
    }

    public function test_lancien_champ_id_service_cree_un_seul_rattachement(): void
    {
        $sport = $this->serviceUsuel();

        $this->postJson('/api/admin/utilisateurs', [
            'nom' => 'Ancien',
            'prenom' => 'Client',
            'email' => 'ancien.client@itassel.test',
            'role' => 'admin_service',
            'id_service' => $sport->id_service,
        ])->assertCreated()
            ->assertJsonPath('utilisateur.service.id_service', $sport->id_service);

        $admin = Utilisateur::where('email', 'ancien.client@itassel.test')->firstOrFail();
        $this->assertSame([$sport->id_service], $this->idsPivot($admin));
    }

    public function test_modification_ajoute_et_retire_un_service(): void
    {
        [$sport, $jeunesse] = $this->deuxServices();
        $admin = $this->adminAvec($sport, $jeunesse);

        $this->putJson("/api/admin/utilisateurs/{$admin->id_utilisateur}", [
            'ids_services' => [$sport->id_service],
            'id_service_principal' => $sport->id_service,
        ])->assertOk()
            ->assertJsonPath('utilisateur.service.id_service', $sport->id_service);

        $this->assertSame([$sport->id_service], $this->idsPivot($admin->fresh()));

        $this->putJson("/api/admin/utilisateurs/{$admin->id_utilisateur}", [
            'ids_services' => [$jeunesse->id_service, $sport->id_service],
            'id_service_principal' => $jeunesse->id_service,
        ])->assertOk()
            ->assertJsonPath('utilisateur.service.id_service', $jeunesse->id_service);

        $this->assertEqualsCanonicalizing(
            [$sport->id_service, $jeunesse->id_service],
            $this->idsPivot($admin->fresh())
        );
        $this->assertSame($jeunesse->id_service, $admin->fresh()->id_service);

        $detail = Journal::where('action', 'modification_utilisateur')->latest('id_journal')->value('detail');
        $this->assertStringContainsString('Sport', (string) $detail);
        $this->assertStringContainsString('Jeunesse', (string) $detail);
        $this->assertStringContainsString('→', (string) $detail);
    }

    public function test_retrait_refuse_si_responsable_du_service_sans_remplacant(): void
    {
        [$sport, $jeunesse] = $this->deuxServices();
        $admin = $this->adminAvec($sport, $jeunesse);
        $jeunesse->update(['id_responsable' => $admin->id_utilisateur]);

        $this->putJson("/api/admin/utilisateurs/{$admin->id_utilisateur}", [
            'ids_services' => [$sport->id_service],
        ])->assertStatus(422)
            ->assertJsonValidationErrors('ids_services');

        $this->assertEqualsCanonicalizing(
            [$sport->id_service, $jeunesse->id_service],
            $this->idsPivot($admin->fresh())
        );
        $this->assertSame($admin->id_utilisateur, $jeunesse->fresh()->id_responsable);
    }

    public function test_retrait_refuse_si_dossiers_ouverts_sans_remplacant(): void
    {
        [$sport, $jeunesse] = $this->deuxServices();
        $admin = $this->adminAvec($sport, $jeunesse);
        $dossier = $this->doleance([
            'id_service' => $jeunesse->id_service,
            'id_responsable' => $admin->id_utilisateur,
            'statut' => Statut::EN_COURS,
        ]);

        $this->putJson("/api/admin/utilisateurs/{$admin->id_utilisateur}", [
            'ids_services' => [$sport->id_service],
        ])->assertStatus(422);

        $this->assertSame($admin->id_utilisateur, $dossier->fresh()->id_responsable);
        $this->assertContains($jeunesse->id_service, $this->idsPivot($admin->fresh()));
    }

    public function test_retrait_avec_remplacant_transfere_la_responsabilite(): void
    {
        [$sport, $jeunesse] = $this->deuxServices();
        $admin = $this->adminAvec($sport, $jeunesse);
        $jeunesse->update(['id_responsable' => $admin->id_utilisateur]);
        $dossier = $this->doleance([
            'id_service' => $jeunesse->id_service,
            'id_responsable' => $admin->id_utilisateur,
            'statut' => Statut::EN_COURS,
        ]);
        $remplacant = $this->adminService($jeunesse, ['email' => 'remplacant.jeunesse@itassel.test']);

        $this->putJson("/api/admin/utilisateurs/{$admin->id_utilisateur}", [
            'ids_services' => [$sport->id_service],
            'id_remplacant' => $remplacant->id_utilisateur,
        ])->assertOk();

        $this->assertSame([$sport->id_service], $this->idsPivot($admin->fresh()));
        $this->assertSame($remplacant->id_utilisateur, $jeunesse->fresh()->id_responsable);
        $this->assertSame($remplacant->id_utilisateur, $dossier->fresh()->id_responsable);
    }

    public function test_passer_super_admin_vide_le_pivot(): void
    {
        [$sport, $jeunesse] = $this->deuxServices();
        $admin = $this->adminAvec($sport, $jeunesse);

        $this->putJson("/api/admin/utilisateurs/{$admin->id_utilisateur}", [
            'role' => 'super_admin',
        ])->assertOk();

        $admin->refresh();
        $this->assertSame('super_admin', $admin->role);
        $this->assertNull($admin->id_service);
        $this->assertSame([], $this->idsPivot($admin));
    }

    public function test_devenir_admin_de_service_exige_une_liste(): void
    {
        $super = $this->superAdmin(['email' => 'devient.admin@itassel.test']);
        [$sport] = $this->deuxServices();

        $this->putJson("/api/admin/utilisateurs/{$super->id_utilisateur}", [
            'role' => 'admin_service',
        ])->assertStatus(422)->assertJsonValidationErrors('ids_services');

        $this->putJson("/api/admin/utilisateurs/{$super->id_utilisateur}", [
            'role' => 'admin_service',
            'id_service' => $sport->id_service,
        ])->assertOk()
            ->assertJsonPath('utilisateur.service.id_service', $sport->id_service);

        $super->refresh();
        $this->assertSame('admin_service', $super->role);
        $this->assertSame([$sport->id_service], $this->idsPivot($super));
    }

    public function test_le_principal_doit_appartenir_a_la_liste(): void
    {
        [$sport, $jeunesse] = $this->deuxServices();

        $this->postJson('/api/admin/utilisateurs', [
            'nom' => 'Principal',
            'prenom' => 'Hors',
            'email' => 'principal.hors@itassel.test',
            'role' => 'admin_service',
            'ids_services' => [$sport->id_service],
            'id_service_principal' => $jeunesse->id_service,
        ])->assertStatus(422)->assertJsonValidationErrors('id_service_principal');
    }

    /**
     * @return array{0: Service, 1: Service}
     */
    private function deuxServices(): array
    {
        return [
            $this->serviceUsuel(),
            Service::where('nom_service', 'Jeunesse')->firstOrFail(),
        ];
    }

    private function adminAvec(Service $principal, Service $autre): Utilisateur
    {
        $admin = $this->adminService($principal, ['email' => fake()->unique()->safeEmail()]);
        $admin->services()->sync([$principal->id_service, $autre->id_service]);
        $admin->oublierIdsServices();

        return $admin->fresh();
    }

    /**
     * @return list<int>
     */
    private function idsPivot(Utilisateur $admin): array
    {
        return DB::table('utilisateur_service')
            ->where('id_utilisateur', $admin->id_utilisateur)
            ->pluck('id_service')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
