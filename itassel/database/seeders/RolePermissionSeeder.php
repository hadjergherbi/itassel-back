<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['code' => 'super_admin', 'libelle' => 'Super administrateur', 'description' => 'Accès à l\'ensemble de la plateforme.'],
            ['code' => 'admin_service', 'libelle' => 'Administrateur de service', 'description' => 'Traite les doléances de son service.'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['code' => $role['code']], $role);
        }

        $catalogue = config('itassel.permissions', []);

        if ($catalogue === []) {
            $catalogue = $this->catalogueParDefaut();
        }

        foreach ($catalogue as $code => $meta) {
            Permission::updateOrCreate(
                ['code' => $code],
                [
                    'libelle' => $meta['libelle'],
                    'groupe'  => $meta['groupe'],
                ]
            );
        }

        $roles = Role::all()->keyBy('code');
        $permissions = Permission::all()->keyBy('code');

        foreach ($catalogue as $code => $meta) {
            $permission = $permissions[$code] ?? null;
            if (! $permission) {
                continue;
            }
            $defaults = $meta['defaults'] ?? [];
            foreach ($defaults as $roleCode) {
                $role = $roles[$roleCode] ?? null;
                if (! $role) {
                    continue;
                }
                DB::table('role_permission')->updateOrInsert(
                    ['id_role' => $role->id_role, 'id_permission' => $permission->id_permission],
                    []
                );
            }
        }
    }

    private function catalogueParDefaut(): array
    {
        $s = 'super_admin';
        $a = 'admin_service';

        return [
            'tableau_de_bord.voir'       => ['libelle' => 'Voir le tableau de bord', 'groupe' => 'tableau_de_bord', 'defaults' => [$s, $a]],
            'tableau_de_bord.global'     => ['libelle' => 'Vue globale du tableau de bord', 'groupe' => 'tableau_de_bord', 'defaults' => [$s]],
            'doleances.voir'             => ['libelle' => 'Voir les doléances', 'groupe' => 'doleances', 'defaults' => [$s, $a]],
            'doleances.exporter'         => ['libelle' => 'Exporter les doléances', 'groupe' => 'doleances', 'defaults' => [$s, $a]],
            'doleances.changer_statut'   => ['libelle' => 'Changer le statut', 'groupe' => 'doleances', 'defaults' => [$s, $a]],
            'doleances.repondre'         => ['libelle' => 'Répondre au demandeur', 'groupe' => 'doleances', 'defaults' => [$s, $a]],
            'doleances.notes'            => ['libelle' => 'Ajouter une note interne', 'groupe' => 'doleances', 'defaults' => [$s, $a]],
            'doleances.reclasser'        => ['libelle' => 'Reclasser une doléance', 'groupe' => 'doleances', 'defaults' => [$s]],
            'complements.demander'       => ['libelle' => 'Demander un complément', 'groupe' => 'complements', 'defaults' => [$s, $a]],
            'complements.annuler'        => ['libelle' => 'Annuler un complément', 'groupe' => 'complements', 'defaults' => [$s, $a]],
            'complements.examiner'       => ['libelle' => 'Examiner un complément', 'groupe' => 'complements', 'defaults' => [$s, $a]],
            'reaffectations.demander'    => ['libelle' => 'Demander une réaffectation', 'groupe' => 'reaffectations', 'defaults' => [$a]],
            'reaffectations.decider'     => ['libelle' => 'Décider une réaffectation', 'groupe' => 'reaffectations', 'defaults' => [$s]],
            'reaffectations.directe'     => ['libelle' => 'Réaffecter directement', 'groupe' => 'reaffectations', 'defaults' => [$s]],
            'notifications.renvoyer'     => ['libelle' => 'Renvoyer un email', 'groupe' => 'notifications', 'defaults' => [$s, $a]],
            'services.gerer'             => ['libelle' => 'Gérer les services', 'groupe' => 'administration', 'defaults' => [$s]],
            'utilisateurs.gerer'         => ['libelle' => 'Gérer les utilisateurs', 'groupe' => 'administration', 'defaults' => [$s]],
            'roles.gerer'                => ['libelle' => 'Gérer les rôles', 'groupe' => 'administration', 'defaults' => [$s]],
            'parametres.gerer'           => ['libelle' => 'Gérer les paramètres', 'groupe' => 'administration', 'defaults' => [$s]],
            'journal.voir'               => ['libelle' => 'Consulter le journal', 'groupe' => 'journal', 'defaults' => [$s]],
            'journal.exporter'           => ['libelle' => 'Exporter le journal', 'groupe' => 'journal', 'defaults' => [$s]],
        ];
    }
}
