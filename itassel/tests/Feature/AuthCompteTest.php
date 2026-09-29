<?php

namespace Tests\Feature;

use App\Mail\InvitationCompteMail;
use App\Mail\ReinitialisationMotDePasseMail;
use App\Models\JetonMotDePasse;
use App\Models\Journal;
use App\Services\JetonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\ItasselHelpers;
use Tests\TestCase;

class AuthCompteTest extends TestCase
{
    use ItasselHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerReferentiels();
        Mail::fake();
    }

    public function test_connexion_reussie_et_echec_sont_journalises(): void
    {
        $admin = $this->superAdmin(['email' => 'login@itassel.test']);

        $this->postJson('/api/admin/login', [
            'email' => 'login@itassel.test',
            'mot_de_passe' => 'Itassel2026!',
        ])->assertOk()->assertJsonStructure(['token', 'utilisateur']);

        $this->assertSame('succes', Journal::where('action', 'connexion')->latest('id_journal')->value('resultat'));
        $this->assertSame('Session ouverte', Journal::where('action', 'connexion')->latest('id_journal')->value('detail'));
        $this->assertNotNull($admin->fresh()->derniere_connexion);

        $this->postJson('/api/admin/login', [
            'email' => 'login@itassel.test',
            'mot_de_passe' => 'mauvais',
        ])->assertStatus(422);

        $echec = Journal::where('action', 'connexion_echec')->latest('id_journal')->first();
        $this->assertNotNull($echec);
        $this->assertSame('echec', $echec->resultat);
        $this->assertStringContainsString('Email saisi :', (string) $echec->detail);
        $this->assertStringContainsString('***', (string) $echec->detail);
        $this->assertStringNotContainsString('mauvais', (string) $echec->detail);
    }

    public function test_un_invite_ne_peut_pas_se_connecter(): void
    {
        $this->superAdmin([
            'email' => 'invite@itassel.test',
            'mot_de_passe_defini_le' => null,
        ]);

        $this->postJson('/api/admin/login', [
            'email' => 'invite@itassel.test',
            'mot_de_passe' => 'Itassel2026!',
        ])->assertStatus(422)->assertJson(['message' => 'Email ou mot de passe incorrect.']);

        $this->assertSame('connexion_echec', Journal::latest('id_journal')->value('action'));
        $this->assertStringContainsString('Email saisi :', (string) Journal::latest('id_journal')->value('detail'));
    }

    public function test_le_lien_d_invitation_definit_le_mot_de_passe_une_fois(): void
    {
        $cible = $this->superAdmin(['email' => 'nouveau@itassel.test', 'mot_de_passe_defini_le' => null]);
        $jeton = JetonService::emettre($cible, 'invitation');

        $this->postJson('/api/admin/mot-de-passe/definir', [
            'jeton' => $jeton,
            'mot_de_passe' => 'NouveauPass1',
            'mot_de_passe_confirmation' => 'NouveauPass1',
        ])->assertOk();

        $this->assertNotNull($cible->fresh()->mot_de_passe_defini_le);

        $this->postJson('/api/admin/mot-de-passe/definir', [
            'jeton' => $jeton,
            'mot_de_passe' => 'AutrePass12',
            'mot_de_passe_confirmation' => 'AutrePass12',
        ])->assertStatus(422)->assertJsonFragment(['Lien invalide ou expiré.']);
    }

    public function test_jeton_expire_utilise_ou_inconnu_est_refuse(): void
    {
        $cible = $this->superAdmin(['mot_de_passe_defini_le' => null]);
        $jeton = JetonService::emettre($cible, 'invitation');
        JetonMotDePasse::where('jeton_hash', hash('sha256', $jeton))->update(['expire_le' => now()->subMinute()]);

        $this->postJson('/api/admin/mot-de-passe/definir', [
            'jeton' => $jeton,
            'mot_de_passe' => 'NouveauPass1',
            'mot_de_passe_confirmation' => 'NouveauPass1',
        ])->assertStatus(422);

        $this->postJson('/api/admin/mot-de-passe/definir', [
            'jeton' => 'inconnu'.str_repeat('a', 50),
            'mot_de_passe' => 'NouveauPass1',
            'mot_de_passe_confirmation' => 'NouveauPass1',
        ])->assertStatus(422);
    }

    public function test_renvoi_d_invitation_invalide_l_ancien_lien(): void
    {
        $acteur = $this->superAdmin();
        $cible = $this->adminService(null, ['mot_de_passe_defini_le' => null]);
        $ancien = JetonService::emettre($cible, 'invitation');

        $this->connecter($acteur)
            ->postJson("/api/admin/utilisateurs/{$cible->id_utilisateur}/renvoyer-invitation")
            ->assertOk();

        $this->postJson('/api/admin/mot-de-passe/definir', [
            'jeton' => $ancien,
            'mot_de_passe' => 'NouveauPass1',
            'mot_de_passe_confirmation' => 'NouveauPass1',
        ])->assertStatus(422);
    }

    public function test_reinitialisation_admin_revoque_les_sessions(): void
    {
        $acteur = $this->superAdmin();
        $cible = $this->adminService();
        $tokenActeur = $acteur->createToken('a')->plainTextToken;
        $token = $cible->createToken('t')->plainTextToken;

        $this->withToken($tokenActeur)
            ->postJson("/api/admin/utilisateurs/{$cible->id_utilisateur}/reinitialiser-mot-de-passe")
            ->assertOk();

        $this->assertSame(0, $cible->fresh()->tokens()->count());
        Mail::assertSent(ReinitialisationMotDePasseMail::class);
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->withToken($token)->getJson('/api/admin/me')->assertStatus(401);
    }

    public function test_mot_de_passe_oublie_repond_identiquement(): void
    {
        $connu = $this->postJson('/api/admin/mot-de-passe-oublie', ['email' => $this->superAdmin()->email]);
        $inconnu = $this->postJson('/api/admin/mot-de-passe-oublie', ['email' => 'absent@itassel.test']);

        $connu->assertOk();
        $inconnu->assertOk();
        $this->assertSame($connu->json('message'), $inconnu->json('message'));
    }

    public function test_aucun_email_ne_contient_de_mot_de_passe(): void
    {
        $this->connecter($this->superAdmin())
            ->postJson('/api/admin/utilisateurs', [
                'nom' => 'Test',
                'prenom' => 'Invite',
                'email' => 'invite.mail@itassel.test',
                'role' => 'super_admin',
            ])->assertCreated();

        Mail::assertSent(InvitationCompteMail::class, function (InvitationCompteMail $mail) {
            $html = $mail->render();

            return ! str_contains($html, 'Itassel2026!') && ! str_contains(strtolower($html), 'mot_de_passe');
        });
    }
}
