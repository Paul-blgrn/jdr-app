<?php

namespace App\Policies;

use App\Models\Board;
use App\Models\Role;
use App\Models\User;

class BoardPolicy
{
    public static function isGlobal()
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     * @param  \App\Models\User  $user
     * @param  \App\Models\Board  $board
     * @return bool
     */
    public function view_board(User $user, Board $board): bool
    {
        return $user->hasRolePermission('view-board');
    }

    /**
     * Determine whether the user can update the model.
     * @param  \App\Models\User  $user
     * @param  \App\Models\Board  $board
     * @return bool
     */
    public function update_board(User $user, Board $board): bool
    {
        return $user->hasRolePermission('update-board');
    }

    /**
     * Determine if the user can delete the board.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Board  $board
     * @return bool
     */
    public function delete_board(User $user, Board $board): bool
    {
        return $user->hasRolePermission('delete-board');
    }


    // PlayerBoard Policy

    /**
     * Determine whether the user can view the model.
     * @param  \App\Models\User  $user
     * @param  \App\Models\Board  $board
     * @return bool
     */
    public function view_playerBoard(User $user, Board $board): bool
    {
        return $user->hasRolePermission('view-player-board');
    }

    /**
     * Determine whether the user can create models.
     * @param  \App\Models\User  $user
     * @param  \App\Models\Board  $board
     * @return bool
     */
    public function join_board(User $user): bool
    {
        return $user->hasRolePermission('join-board');
    }

    /**
     * Determine whether the user can delete the model.
     * @param  \App\Models\User  $user
     * @param  \App\Models\Board  $board
     * @return bool
     */
    public function leave_board(User $user, Board $board): bool
    {
        return $user->hasRolePermission('leave-board');
    }
}
