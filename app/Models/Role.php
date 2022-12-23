<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function createRole($data)
    {
        return self::create($data);
    }

    public function updateRole($data,$id)
    {
        $role = self::find($id);
        $role->update($data);
        return $role;
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
