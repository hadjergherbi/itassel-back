<?php

namespace App\Services;

use App\Models\Doleance;
use App\Models\Historique;
use App\Models\Service;
use App\Models\Statut;
use App\Models\Utilisateur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceResponsableService
{
    public static function designer(Service $service, ?Utilisateur $responsable, Utilisateur $acteur, Request $request): Service
    {
        if (Service::estTousLesDomaines($service->nom_service)) {
            throw ValidationException::withMessages([
                'id_service' => ['Ce domaine ne peut pas être géré par un administrateur de service.'],
            ]);
        }

        if ($responsable) {
            if (! $responsable->actif
                || $responsable->role !== 'admin_service'
                || (int) $responsable->id_service !== (int) $service->id_service
                || $responsable->mot_de_passe_defini_le === null
            ) {
                throw ValidationException::withMessages([
                    'id_responsable' => ['Le responsable doit être un administrateur actif du service, avec un mot de passe défini.'],
                ]);
            }
        }

        DB::transaction(function () use ($service, $responsable, $acteur) {
            $service->update(['id_responsable' => $responsable?->id_utilisateur]);

            if (! $responsable) {
                return;
            }

            $dossiers = Doleance::where('id_service', $service->id_service)
                ->whereNull('id_responsable')
                ->whereHas('statut', fn ($q) => $q->whereIn('code', Statut::codesOuverts()))
                ->get();

            foreach ($dossiers as $doleance) {
                $doleance->update(['id_responsable' => $responsable->id_utilisateur]);

                Historique::create([
                    'date_evenement'    => now(),
                    'type_evenement'    => 'changement_responsable',
                    'detail'            => "Responsable désigné : {$responsable->nomComplet()}",
                    'visible_demandeur' => false,
                    'id_doleance'       => $doleance->id_doleance,
                    'id_utilisateur'    => $acteur->id_utilisateur,
                ]);
            }
        });

        if ($responsable) {
            $dossiers = Doleance::where('id_service', $service->id_service)
                ->where('id_responsable', $responsable->id_utilisateur)
                ->whereHas('statut', fn ($q) => $q->whereIn('code', Statut::codesOuverts()))
                ->get();

            foreach ($dossiers as $doleance) {
                NotificationDispatcher::emettre(
                    'responsable_designe',
                    $doleance,
                    [
                        'utilisateur_designe' => $responsable,
                        'titre'               => "Dossier attribué — {$doleance->reference}",
                        'texte'               => "Vous êtes désormais responsable du dossier {$doleance->reference}.",
                    ],
                    $acteur,
                );
            }
        }

        JournalService::action(
            $request,
            $acteur,
            'designation_responsable',
            $service->nom_service.($responsable ? ' → '.$responsable->nomComplet() : ' (aucun)'),
            $service,
        );

        return $service->fresh('responsable');
    }
}
