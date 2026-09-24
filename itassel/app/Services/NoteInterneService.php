<?php

namespace App\Services;

use App\Mail\NotificationInterneMail;
use App\Models\Doleance;
use App\Models\NoteInterne;
use App\Models\NotificationApp;
use App\Models\Utilisateur;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class NoteInterneService
{
    /** @var array<string, true> */
    private static array $envoisAfterResponse = [];

    public static function mentionnables(Doleance $doleance, Utilisateur $acteur, string $recherche = ''): Collection
    {
        $recherche = mb_strtolower(trim($recherche));

        return Utilisateur::query()
            ->with('service')
            ->where('actif', true)
            ->where('id_utilisateur', '!=', $acteur->id_utilisateur)
            ->where(function ($q) use ($doleance) {
                $q->where('role', 'super_admin')
                    ->orWhere(function ($q) use ($doleance) {
                        $q->where('role', 'admin_service')
                            ->where('id_service', $doleance->id_service);
                    });
            })
            ->when($recherche !== '', function ($q) use ($recherche) {
                $terme = '%'.$recherche.'%';
                $q->where(function ($q) use ($terme) {
                    $q->whereRaw('LOWER(nom) LIKE ?', [$terme])
                        ->orWhereRaw('LOWER(prenom) LIKE ?', [$terme]);
                });
            })
            ->orderBy('nom')
            ->orderBy('prenom')
            ->limit(8)
            ->get();
    }

    public static function idsMentionnables(Doleance $doleance, Utilisateur $acteur): array
    {
        return static::mentionnables($doleance, $acteur)->pluck('id_utilisateur')->all();
    }

    public static function validerMentions(Doleance $doleance, Utilisateur $acteur, array $ids): void
    {
        $autorises = array_map('intval', static::idsMentionnables($doleance, $acteur));

        foreach ($ids as $id) {
            if (! in_array((int) $id, $autorises, true)) {
                throw ValidationException::withMessages([
                    'mentions' => ['Cette personne ne peut pas être mentionnée sur ce dossier.'],
                ]);
            }
        }
    }

    /**
     * @param  list<int>  $idsMentions
     */
    public static function creer(
        Request $request,
        Doleance $doleance,
        Utilisateur $auteur,
        string $contenu,
        array $idsMentions,
        bool $notifierEmail,
        ?string $etiquette,
    ): NoteInterne {
        static::validerMentions($doleance, $auteur, $idsMentions);

        $note = NoteInterne::create([
            'contenu'       => $contenu,
            'date_creation' => now(),
            'id_doleance'   => $doleance->id_doleance,
            'id_auteur'     => $auteur->id_utilisateur,
            'etiquette'     => $etiquette,
        ]);

        static::attacherEtNotifier($request, $doleance, $note, $auteur, $idsMentions, $notifierEmail);

        JournalService::action($request, $auteur, 'note_interne', $doleance->reference, $doleance);

        return $note->fresh(['auteur.service', 'mentions', 'epingleePar']);
    }

    /**
     * @param  list<int>|null  $idsMentions
     */
    public static function modifier(
        Request $request,
        Doleance $doleance,
        NoteInterne $note,
        Utilisateur $acteur,
        string $contenu,
        ?array $idsMentions,
        mixed $etiquette,
        bool $etiquetteFournie,
    ): NoteInterne {
        $note->contenu = $contenu;
        $note->modifiee_le = now();
        if ($etiquetteFournie) {
            $note->etiquette = $etiquette;
        }
        $note->save();

        if ($idsMentions !== null) {
            static::validerMentions($doleance, $acteur, $idsMentions);
            $deja = $note->mentions()->pluck('utilisateurs.id_utilisateur')->map(fn ($id) => (int) $id)->all();
            $nouveaux = array_values(array_diff(array_map('intval', $idsMentions), $deja));

            $note->mentions()->sync($idsMentions);
            static::notifierMentions($request, $doleance, $note, $acteur, $nouveaux, false);
        }

        JournalService::action(
            $request,
            $acteur,
            'modification_note',
            $doleance->reference.' — note '.$note->id_note,
            $doleance,
        );

        return $note->fresh(['auteur.service', 'mentions', 'epingleePar']);
    }

    public static function basculerEpinglage(Request $request, Doleance $doleance, NoteInterne $note, Utilisateur $acteur): NoteInterne
    {
        if ($note->epinglee) {
            $note->epinglee = false;
            $note->epinglee_le = null;
            $note->id_epinglee_par = null;
            $note->save();
        } else {
            $max = (int) config('itassel.notes.max_epinglees', 3);
            $deja = NoteInterne::where('id_doleance', $doleance->id_doleance)
                ->where('epinglee', true)
                ->count();

            if ($deja >= $max) {
                throw ValidationException::withMessages([
                    'epinglee' => [$max.' notes au maximum peuvent être épinglées.'],
                ]);
            }

            $note->epinglee = true;
            $note->epinglee_le = now();
            $note->id_epinglee_par = $acteur->id_utilisateur;
            $note->save();
        }

        JournalService::action(
            $request,
            $acteur,
            'epinglage_note',
            $doleance->reference.' — note '.$note->id_note,
            $doleance,
        );

        return $note->fresh(['auteur.service', 'mentions', 'epingleePar']);
    }

    /**
     * @param  list<int>  $ids
     */
    private static function attacherEtNotifier(
        Request $request,
        Doleance $doleance,
        NoteInterne $note,
        Utilisateur $auteur,
        array $ids,
        bool $notifierEmail,
    ): void {
        foreach ($ids as $id) {
            $note->mentions()->attach($id, ['notifie_email' => $notifierEmail]);
        }

        static::notifierMentions($request, $doleance, $note, $auteur, $ids, $notifierEmail);
    }

    /**
     * @param  list<int>  $ids
     */
    private static function notifierMentions(
        Request $request,
        Doleance $doleance,
        NoteInterne $note,
        Utilisateur $auteur,
        array $ids,
        bool $notifierEmail,
    ): void {
        if ($ids === []) {
            return;
        }

        $mentionnes = Utilisateur::query()
            ->whereIn('id_utilisateur', $ids)
            ->where('actif', true)
            ->get()
            ->keyBy('id_utilisateur');

        $extrait = static::extrait($note->contenu);
        $titre = $auteur->nomComplet().' vous a mentionné';

        foreach ($ids as $id) {
            if ((int) $id === (int) $auteur->id_utilisateur) {
                continue;
            }

            $cible = $mentionnes->get($id);
            if (! $cible) {
                continue;
            }

            NotificationApp::create([
                'id_utilisateur' => $cible->id_utilisateur,
                'evenement'      => 'mention_note',
                'titre'          => mb_substr($titre, 0, 150),
                'message'        => $extrait,
                'id_doleance'    => $doleance->id_doleance,
                'id_note'        => $note->id_note,
            ]);

            JournalService::action(
                $request,
                $auteur,
                'mention_note',
                $doleance->reference.' — '.$cible->nomComplet(),
                $doleance,
            );

            if ($notifierEmail) {
                static::envoyerEmailMention($cible, $doleance, $auteur, $extrait, $titre);
            }
        }
    }

    private static function envoyerEmailMention(
        Utilisateur $cible,
        Doleance $doleance,
        Utilisateur $auteur,
        string $extrait,
        string $titre,
    ): void {
        $mail = new NotificationInterneMail($doleance, $titre, $extrait);
        $mail->lienDossier = rtrim((string) config('itassel.frontend_url'), '/').'/admin/doleances/'.$doleance->reference;
        $cle = $cible->id_utilisateur.':'.$doleance->id_doleance.':'.hash('sha256', $extrait.$titre);

        dispatch(function () use ($cible, $mail, $cle) {
            if (isset(self::$envoisAfterResponse[$cle])) {
                return;
            }
            self::$envoisAfterResponse[$cle] = true;

            try {
                Mail::to($cible->email)->send($mail);
            } catch (\Throwable $e) {
                Log::error('Échec envoi mail mention de note', [
                    'id_utilisateur' => $cible->id_utilisateur,
                    'erreur'         => $e->getMessage(),
                ]);
            }
        })->afterResponse();
    }

    public static function extraire(string $contenu): string
    {
        return static::extrait($contenu);
    }

    private static function extrait(string $contenu): string
    {
        $texte = trim($contenu);

        return mb_strlen($texte) > 150 ? mb_substr($texte, 0, 147).'…' : $texte;
    }

    public static function mentionnableVersApi(Utilisateur $utilisateur): array
    {
        return [
            'id_utilisateur' => $utilisateur->id_utilisateur,
            'nom'            => $utilisateur->nom,
            'prenom'         => $utilisateur->prenom,
            'initiales'      => $utilisateur->initiales(),
            'libelle_role'   => $utilisateur->libelleRoleAffiche(),
            'service'        => $utilisateur->service?->nom_service,
        ];
    }
}
