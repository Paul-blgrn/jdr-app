<?php

namespace App\Http\Middleware;

use App\Models\Board;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Policies\GlobalPolicy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class CheckUserRoleAndPermission
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();
        $board = $request->route('board'); // Board ID in API route
        $roles = array_map('strtolower', $roles);

        $boardId = $board instanceof Board ? $board->id : $board;

        if ($boardId) {
            // get the board in database
            $findBoard = $user->boards()->where('board_id', $boardId)->first();
            // check if board exist
            if (!$findBoard) {
                return $this->returnError(
                    'Not found',
                    'Board not found or user not associated with this board.',
                    404
                );
            }

            // get the user role
            $boardUserRole = $findBoard->pivot->role_id;

            // check if user have role on board
            if (!$boardUserRole) {
                return $this->returnError(
                    'Forbidden',
                    'You do not have a role on this board.',
                    403,
                );
            }

            $role = Role::find($boardUserRole);

            if (!$role || !in_array(strtolower($role->name), $roles)) {
                return $this->returnError(
                    'Forbidden',
                    'You do not have the required board role.',
                    403,
                );
            }

            $permissions = $role->permissions->pluck('name')->toArray();
            $policyClass = $this->getPolicyClassFor(Board::class);

            if ($this->checkPolicyPermissions(
            $permissions,
            $user,
            $policyClass,
            $boardId,
            true
            ))
            {
                return $next($request);
            }
        }
        else {
            // Verify global roles
            $role = $user->roles()->whereIn('name', $roles)->first();

            if (!$role) {
                return $this->returnError(
                    'Forbidden',
                    'You do not have one of the required global roles.',
                    403,
                );
            }

            $permissions = $role->permissions->pluck('name')->toArray();
            $policyClass = $this->getPolicyClassFor(User::class);

            if ($this->checkPolicyPermissions(
                $permissions,
                $user,
                $policyClass,
                null,
                false))
            {
                return $next($request);
            }
        }

            return $this->returnError(
                'Forbidden',
                'Your role does not have the required permissions.',
                403,
            );
    }

    protected function checkPolicyPermissions(
        array $permissions,
        $user,
        $policyClass,
        $modelId = null,
        $isBoard = false
    ){
        $validPermissions = [];

        foreach ($permissions as $permissionName) {
            $methodName = $this->getPolicyMethodName($permissionName, $isBoard);

            if (!$methodName) {
                continue;
            }

            if ($policyClass && method_exists($policyClass, $methodName)) {
                if ($modelId) {
                    $model = Board::find($modelId);
                    $gateAllows = Gate::forUser($user)->allows($methodName, $model);

                    if ($gateAllows) {
                        $validPermissions[] = $permissionName;
                    }
                } else {
                    $gateAllows = Gate::forUser($user)->allows($methodName, $user);

                    if ($gateAllows) {
                        $validPermissions[] = $permissionName;
                    }
                }
            } else {

                return $this->returnError(
                    'Forbidden',
                    'Policy Class or Method Name nor found.',
                    403,
                );
            }
        }

        return count($validPermissions) === count($permissions);
    }

    protected function getPolicyMethodName($permissionName, $isBoard = false)
    {

        $boardMapping = [
            'view-board' => 'viewBoard',
            'update-board' => 'updateBoard',
            'delete-board' => 'deleteBoard',
            'view-player-board' => 'viewPlayerBoard',
            'leave-board' => 'leaveBoard',
        ];

        $globalMapping = [
            'create-board' => 'createBoard',
            'join-board' => 'joinBoard',
            'create-template' => 'createTemplate',
        ];

        return $isBoard ? ($boardMapping[$permissionName] ?? false) : ($globalMapping[$permissionName] ?? false);
    }

    protected function getPolicyClassFor($modelClass)
    {
        return Gate::getPolicyFor($modelClass);
    }

    protected function returnError($title, $message, $status) {
        return response()->json([
            'response' => [
                'status_code' => $status,
                'status_title' => $title,
                'status_message' => $message,
            ],
        ], $status);
    }

}
