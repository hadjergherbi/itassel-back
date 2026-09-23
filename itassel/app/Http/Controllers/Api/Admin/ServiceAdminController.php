<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\Statut;
use App\Models\Utilisateur;
use App\Services\JournalService;
use App\Services\ServiceResponsableService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServiceAdminController extends Controller
{
    public function index()
    {
        $codesOuverts = Statut::codesOuverts();
        $services = Service::with('responsable:id_utilisateur,nom,prenom,email')
            ->withCount([
                'doleances as total',
                'doleances as a_traiter' => fn ($q) => $q->whereHas('statut', fn ($s) => $s->where('code', Statut::NOUVELLE)),
                'doleances as ouvertes' => fn ($q) => $q->whereHas('statut', fn ($s) => $s->whereIn('code', $codesOuverts)),
            ])
            ->orderBy('nom_service')
            ->get();

        $alertes = $services->whereNull('id_responsable')->map(fn (Service $s) => [
            'id_service'  => $s->id_service,
            'nom'         => $s->nom_service,
            'nom_service' => $s->nom_service,
            'code'        => 'sans_responsable',
        ])->values();

        return response()->json([
            'services' => $services->map(fn (Service $s) => [
                'id'           => $s->id_service,
                'id_service'   => $s->id_service,
                'nom'          => $s->nom_service,
                'nom_service'  => $s->nom_service,
                'responsable'  => $s->responsable
                    ? $s->responsable->only(['id_utilisateur', 'nom', 'prenom', 'email'])
                    : null,
                'total'        => $s->total,
                'a_traiter'    => $s->a_traiter,
                'ouvertes'     => $s->ouvertes,
                'alertes'      => $s->id_responsable ? [] : [['code' => 'sans_responsable']],
            ])->values(),
            'alertes' => $alertes,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nom_service' => ['required', 'string', 'max:120', 'unique:services,nom_service'],
        ]);

        $service = Service::create($data);
        JournalService::action($request, $request->user(), 'creation_service', $service->nom_service, $service);

        return response()->json(['message' => 'Service créé.', 'service' => $service], 201);
    }

    public function update(Request $request, Service $service)
    {
        $data = $request->validate([
            'nom_service' => ['required', 'string', 'max:120', Rule::unique('services', 'nom_service')->ignore($service->id_service, 'id_service')],
        ]);

        $service->update($data);
        JournalService::action($request, $request->user(), 'modification_service', $service->nom_service, $service);

        return response()->json(['message' => 'Service mis à jour.', 'service' => $service]);
    }

    public function designerResponsable(Request $request, Service $service)
    {
        $data = $request->validate([
            'id_responsable' => ['nullable', 'integer', 'exists:utilisateurs,id_utilisateur'],
        ]);

        $responsable = isset($data['id_responsable'])
            ? Utilisateur::find($data['id_responsable'])
            : null;

        $service = ServiceResponsableService::designer($service, $responsable, $request->user(), $request);

        return response()->json(['message' => 'Responsable mis à jour.', 'service' => $service]);
    }

    public function responsablesPossibles(Service $service)
    {
        $utilisateurs = Utilisateur::where('id_service', $service->id_service)
            ->where('role', 'admin_service')
            ->where('actif', true)
            ->whereNotNull('mot_de_passe_defini_le')
            ->orderBy('nom')
            ->get(['id_utilisateur', 'nom', 'prenom', 'email']);

        return response()->json($utilisateurs);
    }
}
