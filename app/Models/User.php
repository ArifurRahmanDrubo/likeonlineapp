<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\CustomRole;
use App\Models\CustomPermission;
use Laravel\Sanctum\HasApiTokens;

use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;


class User extends Authenticatable
{

    use HasApiTokens, HasFactory, Notifiable;


    protected $fillable = [
        'name',
        'email',
        'password',
        'mobile',
        'otp',
        'status'

    ];


    protected $attributes = ['otp' => '0'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'remember_token',
        'otp',
    ];
    public function customer()
    {
        return $this->hasOne(Customer::class);
    }
    public function employee()
    {
        return $this->hasOne(Employee::class);
    }
    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }
    public function userloginhistory()
    {
        return $this->hasMany(UserLoginHistory::class);
    }
    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(CustomRole::class, 'role_id');
    }


    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(CustomPermission::class, 'user_has_permissions', 'user_id', 'permission_id');
    }
    public function hasPermission(string $permission, string $type = 'read'): bool
    {
        // Check if the user is a super admin
        if ($this->role && $this->role->name === 'super admin') {
            return true;
        }
        // Fetch the permissions assigned to the user's role
        $rolePermissions = $this->role ? $this->role->permissions : collect();

        // Check if the user has the required permission
        foreach ($rolePermissions as $perm) {
            if ($perm->name === $permission) {
                if ($perm->type === 'full') {
                    return true;
                } elseif ($perm->type === 'write' && ($type === 'write' || $type === 'read')) {
                    return true;
                } elseif ($perm->type === 'read' && $type === 'read') {
                    return true;
                }
            }
        }
        return false;
    }
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($user) {
            // Delete associated user profile
            if ($user->profile) {
                $user->profile()->delete();
            }

            // Delete all login history records
            if ($user->userloginhistory) {
                $user->userloginhistory()->delete();
            }


            if ($user->role) {
                $user->role->permissions()->detach();
                $permissions = CustomPermission::whereDoesntHave('roles')->get();
                foreach ($permissions as $permission) {
                    $permission->delete();
                }
            }
            if ($user->role) {
                // Count the users associated with this role, excluding the current user
                if ($user->role->users()->where('id', '!=', $user->id)->count() === 0) {
                    $user->role()->dissociate();
                    $roles = CustomRole::whereDoesntHave('users')->get();
                    foreach ($roles as $role) {
                        $role->delete();
                    }
                }
            }
        });
    }
}
