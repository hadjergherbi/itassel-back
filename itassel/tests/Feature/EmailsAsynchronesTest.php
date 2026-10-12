<?php

namespace Tests\Feature;

use App\Jobs\EnvoyerEmailNotification;
use App\Mail\CodeVerificationMail;
use App\Mail\ConfirmationDepotMail;
use App\Models\NotificationItassel;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;
use Tests\Support\ItasselHelpers;
use Tests\TestCase;

class EmailsAsynchronesTest extends TestCase
{
    use ItasselHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerReferentiels();
    }

    public function test_le_depot_met_un_job_en_file(): void
    {
        Queue::fake();

        $this->postJson('/api/doleances', $this->champsDepotPublic())->assertCreated();

        Queue::assertPushed(EnvoyerEmailNotification::class);
        $this->assertSame('en_attente', NotificationItassel::query()->value('etat_envoi'));
    }

    public function test_un_echec_smtp_laisse_non_transmis_sans_bloquer_le_depot(): void
    {
        config([
            'mail.default' => 'echec',
            'mail.mailers.echec' => ['transport' => 'echec'],
        ]);
        Mail::extend('echec', fn () => new class implements TransportInterface
        {
            public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
            {
                throw new TransportException('smtp down');
            }

            public function __toString(): string
            {
                return 'echec';
            }
        });
        Mail::purge('echec');

        $this->postJson('/api/doleances', $this->champsDepotPublic())->assertCreated();

        $this->assertSame('non_transmis', NotificationItassel::query()->latest('id_notification')->value('etat_envoi'));
    }

    public function test_failed_marque_la_ligne_sans_journaliser_l_email(): void
    {
        $doleance = $this->doleance(['email' => 'secret.citoyen@example.test']);
        $ligne = NotificationItassel::create([
            'type_notification' => 'depot',
            'destinataire' => $doleance->email,
            'etat_envoi' => 'en_attente',
            'date_envoi' => null,
            'id_doleance' => $doleance->id_doleance,
        ]);

        $job = new EnvoyerEmailNotification(
            $doleance->email,
            new ConfirmationDepotMail($doleance),
            $ligne->id_notification,
            $doleance->reference,
        );
        $job->failed(new \RuntimeException('otp 123456 '.$doleance->email));

        $this->assertSame('non_transmis', $ligne->fresh()->etat_envoi);
        $this->assertNull($ligne->fresh()->date_envoi);
    }

    public function test_le_renvoi_manuel_met_la_ligne_a_jour(): void
    {
        Mail::fake();
        $doleance = $this->doleance();
        $ligne = NotificationItassel::create([
            'type_notification' => 'depot',
            'destinataire' => $doleance->email,
            'etat_envoi' => 'non_transmis',
            'date_envoi' => null,
            'id_doleance' => $doleance->id_doleance,
        ]);

        $this->connecter($this->superAdmin())
            ->postJson("/api/admin/notifications/{$ligne->id_notification}/renvoyer")
            ->assertOk()
            ->assertJsonPath('notification.etat_envoi', 'transmis');

        $this->assertSame('transmis', $ligne->fresh()->etat_envoi);
        $this->assertNotNull($ligne->fresh()->date_envoi);
    }

    public function test_un_echec_de_mise_en_file_renvoie_faux_sans_donnee_personnelle(): void
    {
        $doleance = $this->doleance(['email' => 'secret.citoyen@example.test']);
        $this->app->instance(\Illuminate\Contracts\Bus\Dispatcher::class, new class
        {
            public function dispatch(mixed $command): mixed
            {
                throw new \RuntimeException('file indisponible secret.citoyen@example.test');
            }
        });

        $messages = [];
        Log::listen(function ($event) use (&$messages) {
            $messages[] = $event->message.' '.json_encode($event->context);
        });

        $erreur = null;
        $misEnFile = NotificationService::envoyer(
            $doleance,
            'code_verification',
            new CodeVerificationMail('654321', $doleance->reference, 10, 'fr'),
            null,
            $erreur,
        );

        $this->assertFalse($misEnFile);
        $this->assertSame('mise en file impossible', $erreur);
        $this->assertSame('non_transmis', NotificationItassel::query()->value('etat_envoi'));
        $journal = implode("\n", $messages);
        $this->assertStringNotContainsString('secret.citoyen@example.test', $journal);
        $this->assertStringNotContainsString('654321', $journal);
        $this->assertStringNotContainsString($doleance->reference, $journal);
    }

    public function test_le_code_de_suivi_est_chiffre_dans_la_table_jobs(): void
    {
        config(['queue.default' => 'database']);
        $doleance = $this->doleance(['email' => 'secret.citoyen@example.test']);

        $this->assertTrue(NotificationService::envoyer(
            $doleance,
            'code_verification',
            new CodeVerificationMail('654321', $doleance->reference, 10, 'fr'),
        ));

        $payload = (string) DB::table('jobs')->value('payload');
        $this->assertNotSame('', $payload);
        $this->assertStringNotContainsString('654321', $payload);
        $this->assertStringNotContainsString('secret.citoyen@example.test', $payload);
    }
}
