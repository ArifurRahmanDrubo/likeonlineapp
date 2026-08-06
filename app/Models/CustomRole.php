<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CustomRole extends Model
{
  protected $guarded = []; 
    public function users()
    {
        return $this->hasMany(User::class, 'role_id');
    }
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(CustomPermission::class, 'role_has_permissions', 'role_id', 'permission_id');
    }
}
