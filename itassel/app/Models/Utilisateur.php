<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

class Utilisateur extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    public const DELETED_AT = 'supprime_le';

    /** @var list<int>|null */
    private ?array $idsServicesMemo = null;

    protected $table = 'utilisateurs';

    protected $primaryKey = 'id_utilisateur';

    protected $fillable = [
        'nom', 'prenom', 'email', 'actif', 'id_service',
        'derniere_connexion', 'connexion_precedente',
        'mot_de_passe_defini_le', 'invitation_envoyee_le',
    ];

    protected $hidden = ['mot_de_passe', 'remember_token'];

    protected $casts = [
        'actif' => 'boolean',
        'derniere_connexion' => 'datetime',
        'connexion_precedente' => 'datetime',
        'mot_de_passe_defini_le' => 'datetime',
        'invitation_envoyee_le' => 'datetime',
        'supprime_le' => 'datetime',
    ];

    public function getAuthPassword()
    {
        return $this->mot_de_passe;
    }

    protected static function booted(): void
    {
        static::saved(function (Utilisateur $utilisateur) {
            $utilisateur->assurerServicePrincipalDansPivot();
        });
    }

    public function service()
    {
        return $this->belongsTo(Service::class, 'id_service', 'id_service');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'utilisateur_service', 'id_utilisateur', 'id_service')
            ->withPivot('created_at');
    }

    /**
     * Identifiants des services gérés, principal compris. Mémorisé pour la requête en cours.
     *
     * @return list<int>
     */
    public function idsServices(): array
    {
        if ($this->idsServicesMemo !== null) {
            return $this->idsServicesMemo;
        }

        $ids = $this->relationLoaded('services')
            ? $this->services->pluck('id_service')->all()
            : $this->services()->pluck('services.id_service')->all();

        $ids = array_map('intval', $ids);

        if ($this->id_service) {
            $ids[] = (int) $this->id_service;
        }

        return $this->idsServicesMemo = array_values(array_unique($ids));
    }

    public function gereLeService(int $id): bool
    {
        return in_array($id, $this->idsServices(), true);
    }

    public function oublierIdsServices(): void
    {
        $this->idsServicesMemo = null;
        $this->unsetRelation('services');
    }

    /**
     * @return list<array{id_service: int, nom_service: string, principal: bool}>
     */
    public function servicesPourApi(): array
    {
        $this->loadMissing('services');
        $principal = $this->id_service ? (int) $this->id_service : null;

        return $this->services
            ->sortBy(fn (Service $service) => [
                $principal !== null && (int) $service->id_service === $principal ? 0 : 1,
                $service->nom_service,
            ])
            ->map(fn (Service $service) => [
                'id_service' => $service->id_service,
                'nom_service' => $service->nom_service,
                'principal' => $principal !== null && (int) $service->id_service === $principal,
            ])
            ->values()
            ->all();
    }

    /**
     * Invariant : un admin de service a son service principal dans le pivot ;
     * un super administrateur n'a aucun rattachement.
     */
    public function assurerServicePrincipalDansPivot(): void
    {
        if ($this->role === 'super_admin') {
            $this->services()->detach(null, false);
            $this->oublierIdsServices();

            return;
        }

        if ($this->role === 'admin_service' && $this->id_service) {
            $deja = $this->services()->where('services.id_service', $this->id_service)->exists();
            if (! $deja) {
                $this->services()->attach($this->id_service, ['created_at' => now()], false);
            }
        }

        $this->oublierIdsServices();
    }

    public static function remplirPivotDepuisPrincipal(): void
    {
        $maintenant = now();

        foreach (DB::table('utilisateurs')->where('role', 'admin_service')->whereNotNull('id_service')->get(['id_utilisateur', 'id_service']) as $ligne) {
            DB::table('utilisateur_service')->insertOrIgnore([
                'id_utilisateur' => $ligne->id_utilisateur,
                'id_service' => $ligne->id_service,
                'created_at' => $maintenant,
            ]);
        }
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

    public function initiales(): string
    {
        return mb_strtoupper(
            mb_substr((string) $this->prenom, 0, 1).mb_substr((string) $this->nom, 0, 1)
        );
    }

    public function libelleRoleAffiche(): string
    {
        if ($this->estSuperAdmin()) {
            return 'Super administrateur';
        }

        $service = $this->relationLoaded('service')
            ? $this->service?->nom_service
            : $this->service()->value('nom_service');

        return $service ? 'Administrateur · '.$service : 'Administrateur de service';
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
