<?php

namespace App\Services;

use App\Exceptions\ConflitMetier;
use App\Models\Doleance;
use App\Models\Reaffectation;
use App\Models\Service;
use App\Models\Utilisateur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServiceSuppressionService
{
    public static function raisonBlocage(Service $service): ?string
    {
        $id = $service->id_service;

        if (Doleance::where('id_service', $id)->exists()) {
            return 'service_doleances';
        }

        if (Utilisateur::where('id_service', $id)->exists()) {
            return 'service_utilisateurs';
        }

        if (Reaffectation::where('id_service_propose', $id)->orWhere('id_service_destination', $id)->exists()) {
            return 'service_reaffectations';
        }

        return null;
    }

    public static function supprimer(Service $service, Utilisateur $acteur, Request $request): void
    {
        $code = static::raisonBlocage($service);
        if ($code !== null) {
            throw new ConflitMetier($code, static::message($code));
        }

        $nom = $service->nom_service;

        DB::transaction(function () use ($service) {
            Utilisateur::onlyTrashed()
                ->where('id_service', $service->id_service)
                ->update(['id_service' => null]);

            $service->id_responsable = null;
            $service->save();
            $service->delete();
        });

        JournalService::action($request, $acteur, 'suppression_service', $nom);
    }

    private static function message(string $code): string
    {
        return match ($code) {
            'service_doleances' => 'Ce service contient encore des doléances : il ne peut pas être supprimé.',
            'service_utilisateurs' => 'Des utilisateurs sont encore rattachés à ce service : réaffectez-les ou supprimez-les d\'abord.',
            'service_reaffectations' => 'Ce service apparaît dans l\'historique des réaffectations : il ne peut pas être supprimé.',
            default => 'Ce service ne peut pas être supprimé.',
        };
    }
}
