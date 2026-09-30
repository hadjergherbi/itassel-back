<?php

namespace Tests\Feature;

use App\Mail\CodeVerificationMail;
use App\Models\CodeVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\Support\ItasselHelpers;
use Tests\TestCase;

class DemanderCodeTest extends TestCase
{
    use ItasselHelpers, RefreshDatabase;

    private string $messageAttendu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerReferentiels();
        Mail::fake();

        $duree = (int) config('itassel.suivi.duree_validite_min', 10);
        $this->messageAttendu = 'Si cette référence existe, un code à usage unique a été envoyé '
            ."à l'adresse email du dossier. Il expire dans {$duree} minutes.";
    }

    public function test_reference_existante_envoie_un_code(): void
    {
        $doleance = $this->doleance(['email' => 'citoyen@example.test']);

        $reponse = $this->postJson('/api/suivi/demander-code', [
            'reference' => $doleance->reference,
        ])->assertOk();

        $this->assertSame($this->messageAttendu, $reponse->json('message'));
        Mail::assertSent(CodeVerificationMail::class, function (CodeVerificationMail $mail) use ($doleance) {
            return $mail->reference === $doleance->reference
                && $mail->hasTo('citoyen@example.test');
        });
        $this->assertSame(1, CodeVerification::where('id_doleance', $doleance->id_doleance)->count());
    }

    public function test_reference_inconnue_nenvoie_rien_mais_meme_reponse(): void
    {
        $reponse = $this->postJson('/api/suivi/demander-code', [
            'reference' => 'ITS-2099-999999',
        ])->assertOk();

        $this->assertSame($this->messageAttendu, $reponse->json('message'));
        Mail::assertNothingSent();
        $this->assertSame(0, CodeVerification::count());
    }

    public function test_apres_trois_demandes_aucun_nouvel_envoi_meme_reponse(): void
    {
        $doleance = $this->doleance(['email' => 'citoyen@example.test']);
        $cle = "suivi:demandes:{$doleance->id_doleance}";

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/suivi/demander-code', [
                'reference' => $doleance->reference,
            ])->assertOk();
        }

        Mail::assertSent(CodeVerificationMail::class, 3);
        $this->assertSame(3, (int) Cache::get($cle, 0));

        Mail::fake();

        $reponse = $this->postJson('/api/suivi/demander-code', [
            'reference' => $doleance->reference,
        ])->assertOk();

        $this->assertSame($this->messageAttendu, $reponse->json('message'));
        Mail::assertNothingSent();
        $this->assertSame(3, CodeVerification::where('id_doleance', $doleance->id_doleance)->count());
    }
}
