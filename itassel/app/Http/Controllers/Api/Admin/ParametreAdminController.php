<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ModeleMessage;
use App\Models\Nature;
use App\Models\ParametreNotification;
use App\Models\Qualite;
use App\Models\Statut;
use App\Services\JournalService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ParametreAdminController extends Controller
{
    public function natures()
    {
        return response()->json(Nature::orderBy('libelle')->get());
    }

    public function creerNature(Request $request)
    {
        $data = $request->validate([
            'libelle' => ['required', 'string', 'max:80', 'unique:natures,libelle'],
            'famille' => ['required', 'in:reclamation,demande'],
        ]);

        $nature = Nature::create($data);
        JournalService::action($request, $request->user(), 'creation_nature', $nature->libelle, $nature);

        return response()->json(['message' => 'Nature créée.', 'nature' => $nature], 201);
    }

    public function modifierNature(Request $request, Nature $nature)
    {
        $data = $request->validate([
            'libelle' => ['sometimes', 'string', 'max:80', Rule::unique('natures', 'libelle')->ignore($nature->id_nature, 'id_nature')],
            'famille' => ['sometimes', 'in:reclamation,demande'],
        ]);

        if (isset($data['famille']) && $data['famille'] !== $nature->famille) {
            $restreintes = collect(config('itassel.issues', []))
                ->filter(fn ($meta) => count($meta['familles'] ?? []) === 1)
                ->keys();

            $bloque = $nature->doleances()
                ->whereHas('statut', fn ($q) => $q->whereIn('code', $restreintes))
                ->exists();

            if ($bloque) {
                return response()->json([
                    'message' => 'Des dossiers de cette nature sont classés dans une issue liée à la famille actuelle.',
                    'code'    => 'parametre_utilise',
                ], 409);
            }
        }

        $nature->update($data);
        JournalService::action($request, $request->user(), 'modification_nature', $nature->libelle, $nature);

        return response()->json(['message' => 'Nature mise à jour.', 'nature' => $nature]);
    }

    public function supprimerNature(Request $request, Nature $nature)
    {
        if ($nature->doleances()->exists()) {
            return response()->json([
                'message' => 'Cette nature est utilisée par des doléances.',
                'code'    => 'parametre_utilise',
            ], 409);
        }

        $libelle = $nature->libelle;
        $nature->delete();
        JournalService::action($request, $request->user(), 'suppression_nature', $libelle);

        return response()->json(['message' => 'Nature supprimée.']);
    }

    public function qualites()
    {
        return response()->json(Qualite::orderBy('libelle')->get());
    }

    public function creerQualite(Request $request)
    {
        $data = $request->validate([
            'libelle' => ['required', 'string', 'max:80', 'unique:qualites,libelle'],
        ]);

        $qualite = Qualite::create($data);
        JournalService::action($request, $request->user(), 'creation_qualite', $qualite->libelle, $qualite);

        return response()->json(['message' => 'Qualité créée.', 'qualite' => $qualite], 201);
    }

    public function modifierQualite(Request $request, Qualite $qualite)
    {
        $data = $request->validate([
            'libelle' => ['required', 'string', 'max:80', Rule::unique('qualites', 'libelle')->ignore($qualite->id_qualite, 'id_qualite')],
        ]);

        $qualite->update($data);
        JournalService::action($request, $request->user(), 'modification_qualite', $qualite->libelle, $qualite);

        return response()->json(['message' => 'Qualité mise à jour.', 'qualite' => $qualite]);
    }

    public function supprimerQualite(Request $request, Qualite $qualite)
    {
        if ($qualite->doleances()->exists()) {
            return response()->json([
                'message' => 'Cette qualité est utilisée par des doléances.',
                'code'    => 'parametre_utilise',
            ], 409);
        }

        $libelle = $qualite->libelle;
        $qualite->delete();
        JournalService::action($request, $request->user(), 'suppression_qualite', $libelle);

        return response()->json(['message' => 'Qualité supprimée.']);
    }

    public function modeles(Request $request)
    {
        $query = ModeleMessage::orderBy('titre');
        if ($request->filled('type_usage')) {
            $query->where('type_usage', $request->input('type_usage'));
        }

        return response()->json($query->get(['id_modele', 'titre', 'contenu', 'type_usage']));
    }

    public function creerModele(Request $request)
    {
        $data = $request->validate([
            'titre'      => ['required', 'string', 'max:120'],
            'contenu'    => ['required', 'string', 'max:5000'],
            'type_usage' => ['required', 'in:reponse,conclusion,complement'],
        ]);

        $modele = ModeleMessage::create($data);
        JournalService::action($request, $request->user(), 'creation_modele_message', $modele->titre, $modele);

        return response()->json(['message' => 'Modèle créé.', 'modele' => $modele], 201);
    }

    public function modifierModele(Request $request, ModeleMessage $modele)
    {
        $data = $request->validate([
            'titre'      => ['sometimes', 'string', 'max:120'],
            'contenu'    => ['sometimes', 'string', 'max:5000'],
            'type_usage' => ['sometimes', 'in:reponse,conclusion,complement'],
        ]);

        $modele->update($data);
        JournalService::action($request, $request->user(), 'modification_modele_message', $modele->titre, $modele);

        return response()->json(['message' => 'Modèle mis à jour.', 'modele' => $modele]);
    }

    public function supprimerModele(Request $request, ModeleMessage $modele)
    {
        $titre = $modele->titre;
        $modele->delete();
        JournalService::action($request, $request->user(), 'suppression_modele_message', $titre);

        return response()->json(['message' => 'Modèle supprimé.']);
    }

    public function statuts()
    {
        return response()->json(
            Statut::orderBy('ordre')->orderBy('id_statut')->get()
        );
    }

    public function modifierStatut(Request $request, Statut $statut)
    {
        $data = $request->validate([
            'libelle'          => ['sometimes', 'string', 'max:80'],
            'couleur'          => ['sometimes', 'string', 'max:30'],
            'message_citoyen'  => ['nullable', 'string', 'max:255'],
        ]);

        $statut->update($data);
        JournalService::action($request, $request->user(), 'modification_statut', $statut->libelle, $statut);

        return response()->json(['message' => 'Statut mis à jour.', 'statut' => $statut]);
    }

    public function notifications()
    {
        return response()->json(
            ParametreNotification::orderBy('evenement')->orderBy('destinataire')->get()
        );
    }

    public function modifierNotifications(Request $request)
    {
        $data = $request->validate([
            '*'               => ['required', 'array'],
            '*.evenement'     => ['required', 'string'],
            '*.destinataire'  => ['required', 'string'],
            '*.canal_email'   => ['required', 'boolean'],
            '*.canal_app'     => ['required', 'boolean'],
        ]);

        foreach ($data as $ligne) {
            $parametre = ParametreNotification::where('evenement', $ligne['evenement'])
                ->where('destinataire', $ligne['destinataire'])
                ->first();

            if (! $parametre) {
                return response()->json([
                    'message' => 'Paramètre introuvable.',
                    'errors'  => ['evenement' => ['Ligne inconnue : '.$ligne['evenement'].'/'.$ligne['destinataire']]],
                ], 422);
            }

            $canalApp = $ligne['destinataire'] === 'demandeur' ? false : $ligne['canal_app'];

            if (! $parametre->modifiable
                && ((bool) $parametre->canal_email !== (bool) $ligne['canal_email']
                    || (bool) $parametre->canal_app !== (bool) $canalApp)
            ) {
                return response()->json([
                    'message' => 'Ce paramètre n\'est pas modifiable.',
                    'errors'  => ['modifiable' => ['Ce paramètre n\'est pas modifiable.']],
                ], 422);
            }

            if ($parametre->modifiable) {
                $parametre->update([
                    'canal_email' => $ligne['canal_email'],
                    'canal_app'   => $canalApp,
                ]);
            }
        }

        JournalService::action($request, $request->user(), 'modification_notifications', 'Matrice des notifications');

        return response()->json([
            'message' => 'Notifications mises à jour.',
            'notifications' => ParametreNotification::orderBy('evenement')->get(),
        ]);
    }
}
