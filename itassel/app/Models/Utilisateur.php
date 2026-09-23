<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\HasApiTokens;

class Utilisateur extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    public const DELETED_AT = 'supprime_le';

    protected $table = 'utilisateurs';
    protected $primaryKey = 'id_utilisateur';

    protected $fillable = [
        'nom', 'prenom', 'email', 'actif', 'id_service',
        'derniere_connexion', 'mot_de_passe_defini_le', 'invitation_envoyee_le',
    ];

    protected $hidden = ['mot_de_passe', 'remember_token'];

    protected $casts = [
        'actif'                   => 'boolean',
        'derniere_connexion'      => 'datetime',
        'mot_de_passe_defini_le'  => 'datetime',
        'invitation_envoyee_le'   => 'datetime',
        'supprime_le'             => 'datetime',
    ];

    public function getAuthPassword()
    {
        return $this->mot_de_passe;
    }

    public function service()
    {
        return $this->belongsTo(Service::class, 'id_service', 'id_service');
    }

    public function roleModele()
    {
        return $this->belongsTo(Role::class, 'role', 'code');
    }

    public function notificationsApp()
    {
        return $this->hasMany(NotificationApp::class, 'id_utilisateur', 'id_utilisateur');
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

    public function nomComplet(): string
    {
        return trim($this->prenom.' '.$this->nom);
    }

    public function libelleRole(): string
    {
        return $this->roleModele?->libelle
            ?? ($this->role === 'super_admin' ? 'Super administrateur' : 'Administrateur de service');
    }

    public function etatCompte(): string
    {
        if (! $this->actif) {
            return 'desactive';
        }

        if ($this->mot_de_passe_defini_le === null) {
            $heures = (int) config('itassel.jetons.invitation_heures', 72);
            $envoyee = $this->invitation_envoyee_le;

            if ($envoyee && $envoyee->copy()->addHours($heures)->isPast()) {
                return 'invitation_expiree';
            }

            return 'invitation_en_attente';
        }

        return 'actif';
    }

    public function permissions(): array
    {
        $role = $this->role ?: 'admin_service';

        return Cache::remember("itassel:permissions:{$role}", 3600, function () use ($role) {
            $modele = Role::where('code', $role)->first();

            if (! $modele) {
                return [];
            }

            return $modele->permissions()->pluck('code')->all();
        });
    }

    public function peut(string $code): bool
    {
        return in_array($code, $this->permissions(), true);
    }

    public static function viderCachePermissions(?string $role = null): void
    {
        if ($role) {
            Cache::forget("itassel:permissions:{$role}");

            return;
        }

        foreach (['super_admin', 'admin_service'] as $code) {
            Cache::forget("itassel:permissions:{$code}");
        }
    }
}
