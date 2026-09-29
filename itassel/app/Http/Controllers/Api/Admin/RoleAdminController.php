<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\ErreurValidation;
use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Services\RoleService;
use Illuminate\Http\Request;

class RoleAdminController extends Controller
{
    public function index()
    {
        $roles = Role::with('permissions')->withCount('utilisateurs')->orderBy('id_role')->get();

        return response()->json($roles->map(fn (Role $role) => [
            'id_role' => $role->id_role,
            'code' => $role->code,
            'libelle' => $role->libelle,
            'description' => $role->description,
            'utilisateurs' => $role->utilisateurs_count,
            'permissions' => $role->permissions->pluck('code')->values(),
        ]));
    }

    public function permissions()
    {
        $verrouillees = config('itassel.permissions_verrouillees.super_admin', []);
        $catalogue = config('itassel.permissions', []);
        $enBase = Permission::orderBy('groupe')->orderBy('code')->get()->groupBy('groupe');

        $groupes = $enBase->map(function ($permissions, $groupe) use ($verrouillees, $catalogue) {
            return [
                'groupe' => $groupe,
                'permissions' => $permissions->map(fn (Permission $p) => [
                    'code' => $p->code,
                    'libelle' => $p->libelle,
                    'verrouillee' => in_array($p->code, $verrouillees, true),
                    'defaults' => $catalogue[$p->code]['defaults'] ?? [],
                ])->values(),
            ];
        })->values();

        return response()->json($groupes);
    }

    public function mettreAJourPermissions(Request $request, string $code)
    {
        $role = Role::where('code', $code)->first();
        if (! $role) {
            return response()->json(['message' => 'Rôle introuvable.'], 404);
        }

        $data = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string'],
        ]);

        try {
            $role = RoleService::mettreAJourPermissions($role, $data['permissions'], $request->user(), $request);
        } catch (ErreurValidation $e) {
            return $e->toResponse();
        }

        return response()->json([
            'message' => 'Permissions mises à jour.',
            'permissions' => $role->permissions->pluck('code')->values(),
        ]);
    }
}
