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

class UtilisateurAdminController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'role'   => ['nullable', 'string', 'in:super_admin,admin_service'],
            'service'=> ['nullable', 'integer'],
            'actif'  => ['nullable', 'boolean'],
            'etat'   => ['nullable', 'string', 'in:invitation_en_attente,invitation_expiree,actif,desactive'],
            'q'      => ['nullable', 'string', 'max:100'],
        ]);

        $query = Utilisateur::with('service')->orderBy('nom');

        if (! empty($data['role'])) {
            $query->where('role', $data['role']);
        }
        if (! empty($data['service'])) {
            $query->where('id_service', $data['service']);
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

        $page = $query->paginate(25)->through(fn (Utilisateur $u) => $this->ligne($u));

        if (! empty($data['etat'])) {
            $filtres = collect($page->items())->filter(fn ($l) => $l['etat_compte'] === $data['etat'])->values();
            $page->setCollection($filtres);
        }

        return response()->json($page);
    }

    public function store(Request $request)
    {
        $data = $request->validate((new CreerUtilisateurRequest())->rules());

        $utilisateur = CompteService::creer($data, $request->user(), $request);

        return response()->json([
            'message'     => 'Utilisateur créé. Une invitation a été envoyée.',
            'utilisateur' => $this->detail($utilisateur),
        ], 201);
    }

    public function show(Utilisateur $utilisateur)
    {
        return response()->json($this->detail($utilisateur->load('service')));
    }

    public function update(Request $request, Utilisateur $utilisateur)
    {
        $data = $request->validate([
            'nom'        => ['sometimes', 'string', 'max:80'],
            'prenom'     => ['sometimes', 'string', 'max:80'],
            'email'      => ['sometimes', 'email', 'max:120', Rule::unique('utilisateurs', 'email')->ignore($utilisateur->id_utilisateur, 'id_utilisateur')],
            'id_service' => ['nullable', 'integer', 'exists:services,id_service'],
        ]);

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
            'id_remplacant' => ['nullable', 'integer', 'exists:utilisateurs,id_utilisateur'],
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
            'message'             => 'Compte désactivé.',
            'utilisateur'         => $this->detail($resultat['utilisateur']),
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
            'id'                  => $u->id_utilisateur,
            'id_utilisateur'      => $u->id_utilisateur,
            'nom'                 => $u->nom,
            'prenom'              => $u->prenom,
            'email'               => $u->email,
            'role'                => $u->role,
            'libelle_role'        => $u->libelleRole(),
            'service'             => $u->service
                ? ['id_service' => $u->service->id_service, 'nom_service' => $u->service->nom_service]
                : 'Tous les services',
            'actif'               => $u->actif,
            'etat_compte'         => $u->etatCompte(),
            'derniere_connexion'  => $u->derniere_connexion,
            'est_responsable'     => Service::where('id_responsable', $u->id_utilisateur)->exists(),
        ];
    }

    private function detail(Utilisateur $u): array
    {
        return $this->ligne($u);
    }
}
