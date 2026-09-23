<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Nature extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_nature';

    protected $fillable = ['libelle'];

    public function doleances()
    {
        return $this->hasMany(Doleance::class, 'id_nature', 'id_nature');
    }
}
