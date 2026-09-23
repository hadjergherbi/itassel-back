<?php

namespace App\Services;

use App\Exceptions\ErreurValidation;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Utilisateur;
use Illuminate\Http\Request;

class RoleService
{
    public static function mettreAJourPermissions(Role $role, array $codes, Utilisateur $acteur, Request $request): Role
    {
        $catalogue = array_keys(config('itassel.permissions', []));
        $inconnus = array_values(array_diff($codes, $catalogue));

        if ($inconnus !== []) {
            throw new ErreurValidation('permissions', 'Permission inconnue : '.$inconnus[0].'.');
        }

        if ($role->code === 'super_admin') {
            $verrouillees = config('itassel.permissions_verrouillees.super_admin', []);
            $manquantes = array_values(array_diff($verrouillees, $codes));
            if ($manquantes !== []) {
                throw new ErreurValidation(
                    'permissions',
                    'La permission « '.$manquantes[0].' » ne peut pas être retirée au Super administrateur.',
                    'permission_verrouillee',
                );
            }
        }

        $actuelles = $role->permissions()->pluck('code')->all();
        $ajoutees = array_values(array_diff($codes, $actuelles));
        $retirees = array_values(array_diff($actuelles, $codes));

        $ids = Permission::whereIn('code', $codes)->pluck('id_permission');
        $role->permissions()->sync($ids);
        Utilisateur::viderCachePermissions($role->code);

        $detail = trim($role->libelle
            .' — +'.implode(', ', $ajoutees)
            .' −'.implode(', ', $retirees));

        JournalService::action($request, $acteur, 'modification_permissions_role', $detail, $role);

        return $role->fresh('permissions');
    }
}
