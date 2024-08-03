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
        $board = $request->route('board'); // Board ID dans la route
        $roles = array_map('strtolower', $roles);

        $boardId = $board instanceof Board ? $board->id : $board;

        if ($boardId) {
            // Vérifiez le rôle spécifique au board
            $boardUserRole = $user->boards()->where('board_id', $boardId)->first()?->pivot->role_id;

            if (!$boardUserRole) {
                return $this->forbiddenResponse('You do not have a role on this board.');
            }

            $role = Role::find($boardUserRole);

            if (!$role || !in_array(strtolower($role->name), $roles)) {
                return $this->forbiddenResponse('You do not have the required board role.');
            }

            $permissions = $role->permissions->pluck('name')->toArray();
            $policyClass = $this->getPolicyClassFor(Board::class);

            if ($this->checkPolicyPermissions($permissions, $user, $policyClass, $boardId, true)) {
                return $next($request);
            }
        } else {
            // Verify global roles
            $role = $user->roles()->whereIn('name', $roles)->first();

            if (!$role) {
                return $this->forbiddenResponse('You do not have one of the required global roles.');
            }

            $permissions = $role->permissions->pluck('name')->toArray();
            $policyClass = $this->getPolicyClassFor(User::class);

            if ($this->checkPolicyPermissions($permissions, $user, $policyClass, null,  false)) {
                return $next($request);
            }
        }

        return $this->forbiddenResponse('Your role does not have the required permissions.');
    }

    protected function checkPolicyPermissions(array $permissions, $user, $policyClass, $modelId = null, $isBoard = false)
    {
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
                return $this->forbiddenResponse('Policy Class or Method Name nor found.');
            }
        }

        return count($validPermissions) === count($permissions);
    }

    protected function getPolicyClassFor($modelClass)
    {
        return Gate::getPolicyFor($modelClass);
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
            // 'view-user' => 'viewUser',
            // 'create-user' => 'createUser',
            // 'update-user' => 'updateUser',
            // 'delete-user' => 'deleteUser',
        ];

        return $isBoard ? ($boardMapping[$permissionName] ?? false) : ($globalMapping[$permissionName] ?? false);
    }

    protected function forbiddenResponse($message)
    {
        return response()->json([
            'response' => [
                'status_code' => 403,
                'status_title' => 'Forbidden',
                'status_message' => $message,
            ],
        ], 403);
    }

}
