<?php

namespace App\Policies;

use App\Models\User;

class GlobalPolicy
{
    public static function isGlobal()
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create_board(User $user): bool
    {
        return $user->hasRolePermission('create-board');
    }

    /**
     * Determine whether the user can create templates.
     */
    public function create_template(User $user): bool
    {
        return $user->hasRolePermission('create-template');
    }

}
