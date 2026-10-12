<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\ConflitMetier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreerUtilisateurRequest;
use App\Models\Service;
use App\Models\Utilisateur;
use App\Services\CompteService;
use App\Services\UtilisateurService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UtilisateurAdminController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'role' => ['nullable', 'string', 'in:super_admin,admin_service'],
            'service' => ['nullable', 'integer'],
            'actif' => ['nullable', 'boolean'],
            'etat' => ['nullable', 'string', 'in:invitation_en_attente,invitation_expiree,actif,desactive'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $query = Utilisateur::with(['service', 'services'])
            ->withExists('servicesResponsable as est_responsable')
            ->orderBy('nom');

        if (! empty($data['role'])) {
            $query->where('role', $data['role']);
        }
        if (! empty($data['service'])) {
            $query->whereHas('services', fn ($q) => $q->where('services.id_service', $data['service']));
        }
        if (array_key_exists('actif', $data) && $data['actif'] !== null) {
            $query->where('actif', $request->boolean('actif'));
        }
        if (! empty($data['q'])) {
            $texte = '%'.addcslashes($data['q'], '%_').'%';
            $query->where(function ($w) use ($texte) {
                $w->where('nom', 'like', $texte)
                    ->orWhere('prenom', 'like', $texte)
                    ->orWhere('email', 'like', $texte);
            });
        }
        if (! empty($data['etat'])) {
            $this->filtrerEtat($query, $data['etat']);
        }

        $page = $query->paginate(25)->through(fn (Utilisateur $u) => $this->ligne($u));

        return response()->json($page);
    }

    public function store(Request $request)
    {
        $request->merge(CreerUtilisateurRequest::normaliserSaisie($request->all()));
        $data = $request->validate((new CreerUtilisateurRequest)->rules());

        $utilisateur = CompteService::creer($data, $request->user(), $request);

        return response()->json([
            'message' => 'Utilisateur créé. Une invitation a été envoyée.',
            'utilisateur' => $this->detail($utilisateur),
        ], 201);
    }

    public function show(Utilisateur $utilisateur)
    {
        return response()->json($this->detail($utilisateur->load(['service', 'services'])));
    }

    public function update(Request $request, Utilisateur $utilisateur)
    {
        $request->merge(CreerUtilisateurRequest::normaliserSaisie($request->all()));
        $roleDemande = $request->input('role', $utilisateur->role);

        $regles = [
            'nom' => ['sometimes', 'string', 'max:80'],
            'prenom' => ['sometimes', 'string', 'max:80'],
            'email' => ['sometimes', 'email', 'max:120', Rule::unique('utilisateurs', 'email')->ignore($utilisateur->id_utilisateur, 'id_utilisateur')],
            'role' => ['sometimes', 'in:super_admin,admin_service'],
            'id_service' => ['sometimes', 'nullable', ...Service::regleIdAssignable()],
            'ids_services' => ['sometimes', 'array', 'min:1', 'max:10'],
            'ids_services.*' => [...Service::regleIdAssignable(), 'distinct'],
            'id_service_principal' => ['sometimes', 'nullable', 'integer'],
            'id_remplacant' => ['nullable', 'integer', Rule::exists('utilisateurs', 'id_utilisateur')->whereNull('supprime_le')],
        ];

        if ($roleDemande === 'admin_service' && $utilisateur->role !== 'admin_service') {
            $regles['ids_services'][] = 'required';
        }

        $data = $request->validate($regles);

        if ($roleDemande === 'admin_service'
            && array_key_exists('id_service', $data)
            && $data['id_service'] === null
            && empty($data['ids_services'])) {
            throw ValidationException::withMessages([
                'id_service' => ['Un administrateur de service doit être rattaché à un service.'],
            ]);
        }

        try {
            $utilisateur = UtilisateurService::modifier($utilisateur, $data, $request->user(), $request);
        } catch (ConflitMetier $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => $e->codeErreur], 409);
        }

        return response()->json(['message' => 'Utilisateur mis à jour.', 'utilisateur' => $this->detail($utilisateur)]);
    }

    public function activer(Request $request, Utilisateur $utilisateur)
    {
        $utilisateur = UtilisateurService::activer($utilisateur, $request->user(), $request);

        return response()->json(['message' => 'Compte activé.', 'utilisateur' => $this->detail($utilisateur)]);
    }

    public function desactiver(Request $request, Utilisateur $utilisateur)
    {
        $data = $request->validate([
            'id_remplacant' => ['nullable', 'integer', Rule::exists('utilisateurs', 'id_utilisateur')->whereNull('supprime_le')],
        ]);

        $remplacant = isset($data['id_remplacant'])
            ? Utilisateur::find($data['id_remplacant'])
            : null;

        try {
            $resultat = UtilisateurService::desactiver($utilisateur, $request->user(), $remplacant, $request);
        } catch (ConflitMetier $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => $e->codeErreur], 409);
        }

        return response()->json([
            'message' => 'Compte désactivé.',
            'utilisateur' => $this->detail($resultat['utilisateur']),
            'dossiers_transferes' => $resultat['dossiers_transferes'],
        ]);
    }

    public function impact(Request $request, Utilisateur $utilisateur)
    {
        return response()->json(UtilisateurService::impact($utilisateur, $request->user()));
    }

    public function renvoyerInvitation(Request $request, Utilisateur $utilisateur)
    {
        try {
            CompteService::renvoyerInvitation($utilisateur, $request->user(), $request);
        } catch (ConflitMetier $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => $e->codeErreur], 409);
        }

        return response()->json(['message' => 'Invitation renvoyée.']);
    }

    public function reinitialiserMotDePasse(Request $request, Utilisateur $utilisateur)
    {
        CompteService::reinitialiserMotDePasse($utilisateur, $request->user(), $request);

        return response()->json(['message' => 'Lien de réinitialisation envoyé.']);
    }

    public function destroy(Request $request, Utilisateur $utilisateur)
    {
        try {
            UtilisateurService::supprimer($utilisateur, $request->user(), $request);
        } catch (ConflitMetier $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => $e->codeErreur], 409);
        }

        return response()->json(['message' => 'Compte supprimé.']);
    }

    private function ligne(Utilisateur $u): array
    {
        return [
            'id' => $u->id_utilisateur,
            'id_utilisateur' => $u->id_utilisateur,
            'nom' => $u->nom,
            'prenom' => $u->prenom,
            'email' => $u->email,
            'role' => $u->role,
            'libelle_role' => $u->libelleRole(),
            'service' => $u->service
                ? ['id_service' => $u->service->id_service, 'nom_service' => $u->service->nom_service]
                : 'Tous les services',
            'services' => $u->servicesPourApi(),
            'actif' => $u->actif,
            'etat_compte' => $u->etatCompte(),
            'derniere_connexion' => $u->derniere_connexion,
            'est_responsable' => (bool) $u->est_responsable,
        ];
    }

    private function detail(Utilisateur $u): array
    {
        if (! array_key_exists('est_responsable', $u->getAttributes())) {
            $u->loadExists('servicesResponsable as est_responsable');
        }

        return $this->ligne($u);
    }

    private function filtrerEtat($query, string $etat): void
    {
        $seuil = now()->subHours((int) config('itassel.jetons.invitation_heures', 72));

        match ($etat) {
            'desactive' => $query->where('actif', false),
            'actif' => $query->where('actif', true)->whereNotNull('mot_de_passe_defini_le'),
            'invitation_expiree' => $query->where('actif', true)
                ->whereNull('mot_de_passe_defini_le')
                ->whereNotNull('invitation_envoyee_le')
                ->where('invitation_envoyee_le', '<=', $seuil),
            'invitation_en_attente' => $query->where('actif', true)
                ->whereNull('mot_de_passe_defini_le')
                ->where(function ($q) use ($seuil) {
                    $q->whereNull('invitation_envoyee_le')
                        ->orWhere('invitation_envoyee_le', '>', $seuil);
                }),
            default => null,
        };
    }
}
