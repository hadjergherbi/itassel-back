<?php

namespace App\Http\Requests\Admin;

use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;

class CreerUtilisateurRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->replace(static::normaliserSaisie($this->all()));
    }

    /**
     * L'ancien champ id_service reste accepté : il devient une liste d'un seul service.
     * Si les deux sont envoyés, ids_services fait foi.
     *
     * @param  array<string, mixed>  $saisie
     * @return array<string, mixed>
     */
    public static function normaliserSaisie(array $saisie): array
    {
        if (($saisie['role'] ?? null) === 'super_admin') {
            unset($saisie['ids_services'], $saisie['id_service_principal'], $saisie['id_service']);

            return $saisie;
        }

        $listeFournie = array_key_exists('ids_services', $saisie) && is_array($saisie['ids_services']);
        if (! $listeFournie && array_key_exists('id_service', $saisie) && $saisie['id_service'] !== null && $saisie['id_service'] !== '') {
            $saisie['ids_services'] = [(int) $saisie['id_service']];
        }

        return $saisie;
    }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:80'],
            'prenom' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:120', 'unique:utilisateurs,email'],
            'role' => ['required', 'in:super_admin,admin_service'],
            'id_service' => ['nullable', ...Service::regleIdAssignable()],
            'ids_services' => ['required_if:role,admin_service', 'array', 'min:1', 'max:10'],
            'ids_services.*' => [...Service::regleIdAssignable(), 'distinct'],
            'id_service_principal' => ['nullable', 'integer'],
        ];
    }
}
