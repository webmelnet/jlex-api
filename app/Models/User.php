<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's primary role, prioritizing Superadmin
     */
    public function getPrimaryRole()
    {
        // Ensure roles are loaded first
        if (!$this->relationLoaded('roles')) {
            $this->load('roles');
        }

        // If no roles, return null
        if ($this->roles->isEmpty()) {
            return null;
        }

        // Check session for user's role preference (only for role switching)
        $sessionRole = session('primary_role');
        if ($sessionRole && $sessionRole !== 'auto' && $this->roles->contains('name', $sessionRole)) {
            return $sessionRole;
        }

        // Always prioritize Superadmin if user has it (regardless of session)
        $superadminRole = $this->roles->firstWhere('name', 'Superadmin');
        if ($superadminRole) {
            return 'Superadmin';
        }

        // Define role priority order (customize as needed)
        $rolePriority = [
            'Admin',
            'Manager',
            'Cashier',
        ];

        // Find the highest priority role the user has
        foreach ($rolePriority as $roleName) {
            $role = $this->roles->firstWhere('name', $roleName);
            if ($role) {
                return $roleName;
            }
        }

        // If no priority roles found, return first available role
        $firstRole = $this->roles->first();
        return $firstRole ? $firstRole->name : null;
    }

    /**
     * Get the primary role of the user (updated to prioritize Superadmin)
     * This is called automatically when accessing $user->role
     * 
     * @return string|null
     */
    public function getRoleAttribute()
    {
        // Check if role has been explicitly set (for API responses)
        if (array_key_exists('role', $this->attributes)) {
            return $this->attributes['role'];
        }

        // Otherwise use getPrimaryRole for consistency
        return $this->getPrimaryRole();
    }

    /**
     * Set the role attribute (allows overriding the accessor)
     */
    public function setRoleAttribute($value)
    {
        $this->attributes['role'] = $value;
    }

    /**
     * Switch user's active role (for role switching functionality)
     */
    public function switchRole($roleName)
    {
        // Check if user has the role using direct collection search
        if ($this->roles->contains('name', $roleName)) {
            session(['primary_role' => $roleName]);
            return true;
        }

        return false;
    }

    /**
     * Reset to automatic role selection (prioritizes Superadmin)
     */
    public function resetRoleToAuto()
    {
        session(['primary_role' => 'auto']);
        return $this->getPrimaryRole();
    }

    /**
     * Check if user has a specific role (override Spatie's method if needed)
     */
    public function hasRole($roleName)
    {
        // Ensure roles are loaded
        if (!$this->relationLoaded('roles')) {
            $this->load('roles');
        }

        // Check if user has the role
        return $this->roles->contains('name', $roleName);
    }

    /**
     * Get all user roles for role switching
     */
    public function getAllRoles()
    {
        return $this->roles->pluck('name')->toArray();
    }

    /**
     * Check if user is currently acting as Superadmin
     */
    public function isActingAsSuperadmin()
    {
        return $this->getPrimaryRole() === 'Superadmin';
    }

    /**
     * Get all roles of the user as an array of role names (keep existing method)
     * 
     * @return array
     */
    public function getRolesListAttribute()
    {
        return $this->roles->pluck('name')->toArray();
    }


    public function hasAnyRole($roles)
    {
        if (!$this->relationLoaded('roles')) {
            $this->load('roles');
        }

        $roles = is_array($roles) ? $roles : [$roles];
        $userRoles = $this->roles->pluck('name')->toArray();

        return !empty(array_intersect($roles, $userRoles));
    }
}