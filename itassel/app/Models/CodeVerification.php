<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class CodeVerification extends Model
{
    use HasFactory;

    protected $table = 'codes_verification';
    protected $primaryKey = 'id_code';

    protected $fillable = [
        'code_hash', 'date_creation', 'date_expiration',
        'nombre_essais', 'utilise', 'id_doleance',
    ];

    protected $casts = [
        'date_creation' => 'datetime',
        'date_expiration' => 'datetime',
        'utilise' => 'boolean',
    ];

    public function doleance()
    {
        return $this->belongsTo(Doleance::class, 'id_doleance', 'id_doleance');
    }

    public function estValide(): bool
    {
        return ! $this->utilise
            && $this->nombre_essais < (int) config('itassel.suivi.max_essais_code', 5)
            && $this->date_expiration->isFuture();
    }

    public function correspondA(string $codeEnClair): bool
    {
        return hash_equals($this->code_hash, hash('sha256', $codeEnClair));
    }
}
