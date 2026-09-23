<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $primaryKey = 'id_role';

    protected $fillable = ['code', 'libelle', 'description'];

    public function permissions()
    {
        return $this->belongsToMany(
            Permission::class,
            'role_permission',
            'id_role',
            'id_permission'
        );
    }

    public function utilisateurs()
    {
        return $this->hasMany(Utilisateur::class, 'role', 'code');
    }
}
