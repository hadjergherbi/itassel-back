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

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:80'],
            'prenom' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:120', 'unique:utilisateurs,email'],
            'role' => ['required', 'in:super_admin,admin_service'],
            'id_service' => ['nullable', ...Service::regleIdAssignable(), 'required_if:role,admin_service'],
        ];
    }
}
