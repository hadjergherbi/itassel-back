<?php

namespace App\Console\Commands;

use App\Models\Service;
use App\Models\Utilisateur;
use App\Services\JournalService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class NettoyerComptes extends Command
{
    protected $signature = 'itassel:nettoyer-comptes
        {--super=nour.belkacem@itassel.dz : Email du super administrateur à conserver}
        {--service=amine.kaddour@itassel.dz : Email de l\'administrateur de service à conserver}
        {--dry-run : Affiche le plan sans rien écrire}
        {--force : Exécute sans confirmation}';

    protected $description = 'Ne conserve que deux comptes administrateurs actifs et retire les autres (désactivation, soft delete si jamais utilisés).';

    public function handle(): int
    {
        try {
            $contexte = $this->preparer(
                (string) $this->option('super'),
                (string) $this->option('service'),
            );
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        /** @var Utilisateur $super */
        $super = $contexte['super'];
        /** @var Utilisateur $service */
        $service = $contexte['service'];
        $plan = $contexte['plan'];

        $this->line('Comptes conservés :');
        $this->table(['Email', 'Rôle'], [
            [$super->email, 'super_admin'],
            [$service->email, 'admin_service'],
        ]);

        if ($plan === []) {
            $this->info('Rien à modifier.');
            $this->afficherResume(2, 0, 0);

            return self::SUCCESS;
        }

        $this->line('Comptes concernés :');
        $this->table(
            ['Email', 'Rôle', 'Action'],
            array_map(fn (array $ligne) => [
                $ligne['email'],
                $ligne['role'],
                $ligne['action'],
            ], $plan)
        );

        if ($this->option('dry-run')) {
            $this->info('Simulation : aucune modification.');
            $this->afficherResume(2, $this->compter($plan, 'desactive'), $this->compter($plan, 'soft'));

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Confirmer le retrait de ces comptes ?')) {
            $this->warn('Opération annulée. Aucune modification.');

            return self::SUCCESS;
        }

        try {
            $appliques = DB::transaction(fn () => $this->appliquer(
                (string) $this->option('super'),
                (string) $this->option('service'),
            ));
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Nettoyage terminé.');
        $this->afficherResume(2, $appliques['desactives'], $appliques['soft']);

        return self::SUCCESS;
    }

    /**
     * Vrai si retirer ces identifiants ne laisserait aucun super administrateur actif.
     *
     * @param  list<int>  $idsARetirer
     */
    public static function retireraitLeDernierSuperAdmin(int $idGarde, array $idsARetirer): bool
    {
        if (in_array($idGarde, $idsARetirer, true)) {
            return true;
        }

        return ! Utilisateur::query()
            ->where('role', 'super_admin')
            ->where('actif', true)
            ->whereNotIn('id_utilisateur', $idsARetirer)
            ->exists();
    }

    /**
     * @return array{super: Utilisateur, service: Utilisateur, plan: list<array{email: string, role: string, action: string, desactive: bool, soft: bool}>}
     */
    private function preparer(string $emailSuper, string $emailService): array
    {
        [$super, $service] = $this->gardes($emailSuper, $emailService);
        $plan = $this->plan($super, $service);
        $this->verifierDernierSuper($super, $plan);

        return compact('super', 'service', 'plan');
    }

    /**
     * @return array{desactives: int, soft: int}
     */
    private function appliquer(string $emailSuper, string $emailService): array
    {
        [$super, $service] = $this->gardes($emailSuper, $emailService, true);
        $comptes = $this->autresComptes($super, $service, true);
        $plan = [];
        foreach ($comptes as $compte) {
            $ligne = $this->ligne($compte);
            if ($ligne !== null) {
                $plan[] = $ligne;
            }
        }
        $this->verifierDernierSuper($super, $plan);

        $desactives = 0;
        $soft = 0;
        $requete = Request::create('/', 'GET', server: ['REMOTE_ADDR' => '127.0.0.1']);

        foreach ($comptes as $compte) {
            $ligne = $this->ligne($compte);
            if ($ligne === null) {
                continue;
            }

            $lie = $this->estLie($compte->id_utilisateur);
            $etaitActif = (bool) $compte->actif;

            Service::where('id_responsable', $compte->id_utilisateur)
                ->update(['id_responsable' => null]);
            $compte->tokens()->delete();

            if ($etaitActif) {
                $compte->actif = false;
            }

            $action = 'desactivation_utilisateur';
            $detail = "Compte retiré ({$compte->email}) : désactivé";

            if (! $lie) {
                $compte->save();
                $compte->delete();
                $soft++;
                $action = 'suppression_utilisateur';
                $detail = "Compte retiré ({$compte->email}) : désactivé, soft delete";
            } else {
                $compte->save();
                if ($etaitActif) {
                    $desactives++;
                }
            }

            JournalService::action($requete, $super, $action, $detail, $compte);
        }

        return ['desactives' => $desactives, 'soft' => $soft];
    }

    /**
     * @return array{0: Utilisateur, 1: Utilisateur}
     */
    private function gardes(string $emailSuper, string $emailService, bool $verrou = false): array
    {
        $emailSuper = mb_strtolower(trim($emailSuper));
        $emailService = mb_strtolower(trim($emailService));

        if ($emailSuper === '' || $emailService === '') {
            throw new RuntimeException('Les emails des comptes à conserver sont obligatoires.');
        }

        if ($emailSuper === $emailService) {
            throw new RuntimeException('Les deux comptes à conserver doivent être distincts.');
        }

        $super = $this->trouver($emailSuper, $verrou);
        $service = $this->trouver($emailService, $verrou);

        $this->verifierGarde($super, $emailSuper, 'super_admin', 'super administrateur');
        $this->verifierGarde($service, $emailService, 'admin_service', 'administrateur de service');

        return [$super, $service];
    }

    private function trouver(string $email, bool $verrou): ?Utilisateur
    {
        $requete = Utilisateur::withTrashed()->whereRaw('LOWER(email) = ?', [$email]);
        if ($verrou) {
            $requete->lockForUpdate();
        }

        return $requete->first();
    }

    private function verifierGarde(?Utilisateur $compte, string $email, string $role, string $libelle): void
    {
        if (! $compte) {
            throw new RuntimeException("Le {$libelle} « {$email} » est introuvable. Aucune modification.");
        }

        if (! $compte->actif || $compte->trashed()) {
            throw new RuntimeException("Le {$libelle} « {$email} » n'est pas actif. Aucune modification.");
        }

        if ($compte->role !== $role) {
            throw new RuntimeException("Le {$libelle} « {$email} » n'a pas le rôle {$role}. Aucune modification.");
        }
    }

    /**
     * @param  list<array{desactive: bool, soft: bool}>  $plan
     */
    private function verifierDernierSuper(Utilisateur $super, array $plan): void
    {
        $ids = [];
        foreach ($plan as $ligne) {
            if (($ligne['role'] ?? '') === 'super_admin' && ! empty($ligne['desactive'])) {
                $ids[] = (int) $ligne['id'];
            }
        }

        if (self::retireraitLeDernierSuperAdmin($super->id_utilisateur, $ids)) {
            throw new RuntimeException('Impossible de retirer le dernier Super administrateur actif. Aucune modification.');
        }
    }

    /**
     * @return list<array{id: int, email: string, role: string, action: string, desactive: bool, soft: bool}>
     */
    private function plan(Utilisateur $super, Utilisateur $service): array
    {
        $plan = [];
        foreach ($this->autresComptes($super, $service) as $compte) {
            $ligne = $this->ligne($compte);
            if ($ligne !== null) {
                $plan[] = $ligne;
            }
        }

        return $plan;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Utilisateur>
     */
    private function autresComptes(Utilisateur $super, Utilisateur $service, bool $verrou = false)
    {
        $requete = Utilisateur::query()
            ->whereIn('role', ['super_admin', 'admin_service'])
            ->whereNotIn('id_utilisateur', [$super->id_utilisateur, $service->id_utilisateur])
            ->orderBy('email');

        if ($verrou) {
            $requete->lockForUpdate();
        }

        return $requete->get();
    }

    /**
     * @return array{id: int, email: string, role: string, action: string, desactive: bool, soft: bool}|null
     */
    private function ligne(Utilisateur $compte): ?array
    {
        $etaitActif = (bool) $compte->actif;
        $responsable = Service::where('id_responsable', $compte->id_utilisateur)->exists();
        $jetons = $compte->tokens()->exists();
        $lie = $this->estLie($compte->id_utilisateur);
        $soft = ! $lie;
        $desactive = $etaitActif && $lie;

        if (! $desactive && ! $responsable && ! $jetons && ! $soft) {
            return null;
        }

        $morceaux = [];
        if ($etaitActif) {
            $morceaux[] = 'désactivé';
        }
        if ($responsable) {
            $morceaux[] = 'responsable de service retiré';
        }
        if ($jetons) {
            $morceaux[] = 'jetons révoqués';
        }
        if ($soft) {
            $morceaux[] = 'soft delete';
        }

        return [
            'id'        => $compte->id_utilisateur,
            'email'     => $compte->email,
            'role'      => $compte->role,
            'action'    => implode(', ', $morceaux),
            'desactive' => $desactive,
            'soft'      => $soft,
        ];
    }

    private function estLie(int $id): bool
    {
        $present = fn (string $table, string $colonne) => DB::table($table)->where($colonne, $id)->exists();

        return $present('doleances', 'id_responsable')
            || $present('services', 'id_responsable')
            || $present('reaffectations', 'id_demandeur')
            || $present('reaffectations', 'id_decideur')
            || $present('jetons_mot_de_passe', 'id_utilisateur')
            || $present('jetons_mot_de_passe', 'id_createur')
            || $present('notifications_app', 'id_utilisateur')
            || $present('notes_internes', 'id_auteur')
            || $present('notes_internes', 'id_epinglee_par')
            || $present('note_mentions', 'id_utilisateur')
            || $present('complements', 'id_auteur')
            || $present('complements', 'id_annule_par')
            || $present('reponses', 'id_auteur')
            || $present('historiques', 'id_utilisateur')
            || $present('journaux', 'id_utilisateur')
            || DB::table('journaux')
                ->where('cible_type', Utilisateur::class)
                ->where('cible_id', $id)
                ->exists()
            || DB::table('personal_access_tokens')
                ->where('tokenable_id', $id)
                ->where('tokenable_type', Utilisateur::class)
                ->exists();
    }

    /**
     * @param  list<array{desactive: bool, soft: bool}>  $plan
     */
    private function compter(array $plan, string $cle): int
    {
        return count(array_filter($plan, fn (array $ligne) => ! empty($ligne[$cle])));
    }

    private function afficherResume(int $gardes, int $desactives, int $soft): void
    {
        $this->line("Gardés : {$gardes}");
        $this->line("Désactivés : {$desactives}");
        $this->line("Supprimés (soft delete) : {$soft}");
    }
}
