<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReclasserDoleanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_statut' => ['required', 'integer', 'exists:statuts,id_statut'],
            'motif'     => ['required', 'string', 'max:2000'],
        ];
    }
}
