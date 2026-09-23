<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Utilisateur extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'utilisateurs';
    protected $primaryKey = 'id_utilisateur';

    protected $fillable = [
        'nom', 'prenom', 'email', 'mot_de_passe', 'role', 'actif', 'id_service',
    ];

    protected $hidden = ['mot_de_passe', 'remember_token'];

    protected $casts = ['actif' => 'boolean'];

    // Laravel attend "password" : on pointe vers la colonne réelle "mot_de_passe".
    public function getAuthPassword()
    {
        return $this->mot_de_passe;
    }

    public function service()
    {
        return $this->belongsTo(Service::class, 'id_service', 'id_service');
    }

    public function servicesResponsable()
    {
        return $this->hasMany(Service::class, 'id_responsable', 'id_utilisateur');
    }

    public function doleancesResponsable()
    {
        return $this->hasMany(Doleance::class, 'id_responsable', 'id_utilisateur');
    }

    public function estSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }
}
