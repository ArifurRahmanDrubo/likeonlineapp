<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CustomPermission extends Model
{
   protected $guarded = []; 

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(CustomRole::class, 'role_has_permissions', 'permission_id', 'role_id');
    }
    public function children(): HasMany
    {
        return $this->hasMany(CustomPermission::class, 'parent_id');
    }

    public function parent()
    {
        return $this->belongsTo(CustomPermission::class, 'parent_id');
    }
}
