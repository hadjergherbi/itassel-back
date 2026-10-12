<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\ConflitMetier;
use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\Statut;
use App\Models\Utilisateur;
use App\Services\JournalService;
use App\Services\ServiceResponsableService;
use App\Services\ServiceSuppressionService;
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
                'utilisateurs',
            ])
            ->orderBy('nom_service')
            ->get();

        $alertes = $services
            ->whereNull('id_responsable')
            ->reject(fn (Service $s) => Service::estTousLesDomaines($s->nom_service))
            ->map(fn (Service $s) => [
                'id_service' => $s->id_service,
                'nom' => $s->nom_service,
                'nom_service' => $s->nom_service,
                'code' => 'sans_responsable',
            ])->values();

        return response()->json([
            'services' => $services->map(function (Service $s) {
                $raison = $this->raisonBlocageListe($s);

                return [
                    'id' => $s->id_service,
                    'id_service' => $s->id_service,
                    'nom' => $s->nom_service,
                    'nom_service' => $s->nom_service,
                    'responsable' => $s->responsable
                        ? $s->responsable->only(['id_utilisateur', 'nom', 'prenom', 'email'])
                        : null,
                    'total' => $s->total,
                    'a_traiter' => $s->a_traiter,
                    'ouvertes' => $s->ouvertes,
                    'supprimable' => $raison === null,
                    'raison_blocage' => $raison,
                    'assignable' => ! Service::estTousLesDomaines($s->nom_service),
                    'alertes' => ($s->id_responsable || Service::estTousLesDomaines($s->nom_service))
                        ? []
                        : [['code' => 'sans_responsable']],
                ];
            })->values(),
            'alertes' => $alertes,
        ]);
    }

    public function store(Request $request)
    {
        if (Service::estTousLesDomaines($request->input('nom_service'))) {
            throw new ConflitMetier('enregistrement_systeme', 'Ce libellé est réservé à un enregistrement système.');
        }

        $data = $request->validate([
            'nom_service' => ['required', 'string', 'max:120', 'unique:services,nom_service'],
        ]);

        $service = Service::create($data);
        JournalService::action($request, $request->user(), 'creation_service', $service->nom_service, $service);

        return response()->json(['message' => 'Service créé.', 'service' => $service], 201);
    }

    public function destroy(Request $request, Service $service)
    {
        if (! $request->user()->can('delete', $service)) {
            return response()->json([
                'message' => 'Seul le Super administrateur peut supprimer un service.',
                'code' => 'non_autorise',
            ], 403);
        }

        if (Service::estTousLesDomaines($service->nom_service)) {
            throw new ConflitMetier('enregistrement_systeme', 'Cet enregistrement système ne peut pas être supprimé.');
        }

        try {
            ServiceSuppressionService::supprimer($service, $request->user(), $request);
        } catch (ConflitMetier $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => $e->codeErreur], 409);
        }

        return response()->json(['message' => 'Service supprimé.']);
    }

    public function update(Request $request, Service $service)
    {
        $data = $request->validate([
            'nom_service' => ['required', 'string', 'max:120', Rule::unique('services', 'nom_service')->ignore($service->id_service, 'id_service')],
        ]);

        if (Service::estTousLesDomaines($service->nom_service) && $data['nom_service'] !== $service->nom_service) {
            throw new ConflitMetier('enregistrement_systeme', 'Cet enregistrement système ne peut pas être renommé.');
        }

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

    /**
     * Réutilise les compteurs déjà chargés (doléances, utilisateurs) avant une requête complémentaire.
     */
    private function raisonBlocageListe(Service $service): ?string
    {
        if ((int) $service->total > 0) {
            return 'service_doleances';
        }

        if ((int) $service->utilisateurs_count > 0) {
            return 'service_utilisateurs';
        }

        return ServiceSuppressionService::raisonBlocage($service);
    }

    public function responsablesPossibles(Service $service)
    {
        $utilisateurs = Utilisateur::query()
            ->where('role', 'admin_service')
            ->where('actif', true)
            ->whereNotNull('mot_de_passe_defini_le')
            ->whereHas('services', fn ($q) => $q->where('services.id_service', $service->id_service))
            ->orderBy('nom')
            ->get(['id_utilisateur', 'nom', 'prenom', 'email']);

        return response()->json($utilisateurs);
    }
}
