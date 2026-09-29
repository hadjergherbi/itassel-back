<?php

namespace Tests\Support;

use App\Models\Doleance;
use App\Models\Nature;
use App\Models\Qualite;
use App\Models\Service;
use App\Models\Statut;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

trait ItasselHelpers
{
    protected function preparerReferentiels(): void
    {
        $this->seed();
    }

    protected function superAdmin(array $extra = []): Utilisateur
    {
        $utilisateur = new Utilisateur;
        $utilisateur->fill([
            'nom' => $extra['nom'] ?? 'Admin',
            'prenom' => $extra['prenom'] ?? 'Super',
            'email' => $extra['email'] ?? fake()->unique()->safeEmail(),
            'actif' => $extra['actif'] ?? true,
            'id_service' => null,
            'mot_de_passe_defini_le' => array_key_exists('mot_de_passe_defini_le', $extra)
                ? $extra['mot_de_passe_defini_le']
                : now(),
        ]);
        $utilisateur->role = 'super_admin';
        $utilisateur->mot_de_passe = Hash::make($extra['mot_de_passe'] ?? 'Itassel2026!');
        $utilisateur->save();

        return $utilisateur->fresh();
    }

    protected function adminService(?Service $service = null, array $extra = []): Utilisateur
    {
        $service ??= Service::assignables()->orderBy('id_service')->first()
            ?? Service::factory()->create();

        $utilisateur = new Utilisateur;
        $utilisateur->fill([
            'nom' => $extra['nom'] ?? 'Service',
            'prenom' => $extra['prenom'] ?? 'Admin',
            'email' => $extra['email'] ?? fake()->unique()->safeEmail(),
            'actif' => $extra['actif'] ?? true,
            'id_service' => $service->id_service,
            'mot_de_passe_defini_le' => array_key_exists('mot_de_passe_defini_le', $extra)
                ? $extra['mot_de_passe_defini_le']
                : now(),
        ]);
        $utilisateur->role = 'admin_service';
        $utilisateur->mot_de_passe = Hash::make($extra['mot_de_passe'] ?? 'Itassel2026!');
        $utilisateur->save();

        return $utilisateur->fresh();
    }

    protected function connecter(Utilisateur $utilisateur): static
    {
        Sanctum::actingAs($utilisateur);

        return $this;
    }

    protected function jetonFormulaireValide(int $ageSecondes = 4): string
    {
        return Crypt::encryptString((string) now()->subSeconds($ageSecondes)->timestamp);
    }

    protected function serviceUsuel(): Service
    {
        return Service::where('nom_service', 'Sport')->first()
            ?? Service::assignables()->orderBy('id_service')->firstOrFail();
    }

    protected function natureUsuelle(): Nature
    {
        return Nature::where('libelle', 'Réclamation')->first()
            ?? Nature::where('famille', 'reclamation')
                ->whereRaw('LOWER(TRIM(libelle)) != ?', [mb_strtolower(Nature::TOUTES_NATURES)])
                ->orderBy('id_nature')
                ->firstOrFail();
    }

    protected function champsDepotPublic(array $extra = []): array
    {
        return array_merge([
            'nom' => 'Citoyen',
            'prenom' => 'Test',
            'email' => 'citoyen@example.test',
            'telephone' => '0550000000',
            'wilaya' => 'Alger',
            'objet' => 'Objet de test',
            'description' => 'Description suffisamment longue.',
            'id_service' => $this->serviceUsuel()->id_service,
            'id_nature' => $this->natureUsuelle()->id_nature,
            'id_qualite' => Qualite::first()->id_qualite,
            'jeton_formulaire' => $this->jetonFormulaireValide(),
        ], $extra);
    }

    protected function doleance(array $attrs = []): Doleance
    {
        $code = $attrs['statut'] ?? Statut::NOUVELLE;
        unset($attrs['statut']);

        return Doleance::factory()->create(array_merge([
            'id_service' => $attrs['id_service'] ?? $this->serviceUsuel()->id_service,
            'id_statut' => Statut::parCode($code)->id_statut,
            'id_nature' => $attrs['id_nature'] ?? $this->natureUsuelle()->id_nature,
            'id_qualite' => $attrs['id_qualite'] ?? Qualite::first()->id_qualite,
        ], $attrs));
    }
}
