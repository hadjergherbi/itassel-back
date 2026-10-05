<?php

namespace Tests\Feature;

use App\Mail\ConfirmationDepotMail;
use App\Models\Doleance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\ItasselHelpers;
use Tests\TestCase;

class EmailsLangueTest extends TestCase
{
    use ItasselHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerReferentiels();
        Mail::fake();
    }

    public function test_depot_en_anglais_a_un_sujet_anglais(): void
    {
        $this->postJson('/api/doleances', $this->champsDepotPublic([
            'langue' => 'en',
        ]))->assertCreated();

        $this->assertSame('en', Doleance::first()->langue);
        Mail::assertSent(ConfirmationDepotMail::class, function (ConfirmationDepotMail $mail) {
            return str_contains($mail->envelope()->subject, 'Your complaint');
        });
    }

    public function test_depot_en_arabe_est_rtl(): void
    {
        $this->postJson('/api/doleances', $this->champsDepotPublic([
            'langue' => 'ar',
        ]))->assertCreated();

        Mail::assertSent(ConfirmationDepotMail::class, function (ConfirmationDepotMail $mail) {
            return str_contains($mail->render(), 'dir="rtl"');
        });
    }

    public function test_langue_absente_reste_en_francais(): void
    {
        $this->postJson('/api/doleances', $this->champsDepotPublic())->assertCreated();

        $this->assertSame('fr', Doleance::first()->langue);
        Mail::assertSent(ConfirmationDepotMail::class, function (ConfirmationDepotMail $mail) {
            return str_contains($mail->envelope()->subject, 'Votre doléance');
        });
    }

    public function test_langue_invalide_renvoie_422(): void
    {
        $this->postJson('/api/doleances', $this->champsDepotPublic([
            'langue' => 'de',
        ]))->assertStatus(422)->assertJsonValidationErrors(['langue']);
    }

    public function test_les_trois_fichiers_de_langue_ont_les_memes_cles(): void
    {
        $fr = $this->cles(require lang_path('fr/emails.php'));
        $en = $this->cles(require lang_path('en/emails.php'));
        $ar = $this->cles(require lang_path('ar/emails.php'));

        $this->assertSame($fr, $en);
        $this->assertSame($fr, $ar);
    }

    /**
     * @param  array<string, mixed>  $tableau
     * @return list<string>
     */
    private function cles(array $tableau, string $prefixe = ''): array
    {
        $cles = [];
        foreach ($tableau as $cle => $valeur) {
            $chemin = $prefixe === '' ? (string) $cle : $prefixe.'.'.$cle;
            if (is_array($valeur)) {
                $cles = array_merge($cles, $this->cles($valeur, $chemin));
            } else {
                $cles[] = $chemin;
            }
        }
        sort($cles);

        return $cles;
    }
}
