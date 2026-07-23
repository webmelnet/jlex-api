<?php

namespace App\Services;

use Spatie\Permission\Models\Role;

class RoleService
{
    public function getAllRoles()
    {
        return Role::with('permissions')->get();
    }

    public function getRoleById($id)
    {
        return Role::with('permissions')->findOrFail($id);
    }
}