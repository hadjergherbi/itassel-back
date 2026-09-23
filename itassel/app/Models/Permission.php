<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $primaryKey = 'id_permission';

    protected $fillable = ['code', 'libelle', 'groupe', 'description'];

    public function roles()
    {
        return $this->belongsToMany(
            Role::class,
            'role_permission',
            'id_permission',
            'id_role'
        );
    }
}
