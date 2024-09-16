<?php

use App\Models\Board;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

it('fails with incorrect role or permission', function($roleName, $permissionName, $expectedMessage) {
    $user = User::factory()->create();
    $board = Board::factory()->create();

    $role = Role::factory()->create(['name' => $roleName]);
    $permission = Permission::factory()->create(['name' => $permissionName]);

    // Attachez les permissions au rôle
    $role->permissions()->attach($permission->id);
    $user->roles()->attach($role);

    // Assignez l'utilisateur au tableau
    $board->users()->attach($user->id, ['role_id' => $role->id]);

    // Essayez de supprimer le tableau
    $response = $this->actingAs($user)
        ->delete("/api/board/{$board->id}/delete")
        ->assertStatus(403); // Devrait échouer avec un code 403

    // Vérifiez le contenu de la réponse JSON
    $response->assertJson([
        'response' => [
            'status_code' => 403,
            'status_title' => 'Forbidden',
            'status_message' => $expectedMessage,
        ]
    ]);
})->with([
    ['user', 'delete-board', 'You do not have the required board role.'],
    ['master', 'delete-board222', 'Your role does not have the required permissions.']
]);


test('user with correct role and permission can access board', function () {
    // Create users
    $master = User::factory()->create();
    $user = User::factory()->create();

    // Create a board
    $board = Board::factory()->create();

    // Create roles
    $roleUser = Role::factory()->create(['name' => 'user']);
    $roleMaster = Role::factory()->create(['name' => 'master']);
    $rolePlayer = Role::factory()->create(['name' => 'player']);

    // Create permissions
    $permissionView = Permission::factory()->create(['name' => 'view-board']);

    // Attach permissions to roles
    $roleMaster->permissions()->attach($permissionView->id);
    $rolePlayer->permissions()->attach($permissionView->id);

    // Attach global roles to users
    $master->roles()->attach($roleUser->id);
    $user->roles()->attach($roleUser->id);

    // Attach board-specific roles to users in the context of the board
    $board->users()->attach($master->id, ['role_id' => $roleMaster->id]);
    $board->users()->attach($user->id, ['role_id' => $rolePlayer->id]);

    // Verify that the master user has the correct role in the board_user pivot table
    $this->assertDatabaseHas('board_user', [
        'board_id' => $board->id,
        'user_id' => $master->id,
        'role_id' => $roleMaster->id,
    ]);

    // Verify that the user has the correct role in the board_user pivot table
    $this->assertDatabaseHas('board_user', [
        'board_id' => $board->id,
        'user_id' => $user->id,
        'role_id' => $rolePlayer->id,
    ]);

    // Attempt to access a route that requires the 'view-board' permission with player role
    $response = $this->actingAs($user)
        ->get("/api/board/{$board->id}");

    // We expect a status code 200 (OK) if the user has the correct role and permission
    $response->assertStatus(200);

    // Attempt to access a route that requires the 'view-board' permission with master role
    $response = $this->actingAs($master)
        ->get("/api/board/{$board->id}");

    // We expect a status code 200 (OK) if the user has the correct role and permission
    $response->assertStatus(200);
});

test('user with global and board roles and permission', function () {
    // Create a user, roles, a board, and permissions
    $user = User::factory()->create();
    $board = Board::factory()->create();

    $globalRole = Role::factory()->create(['name' => 'user']);
    $boardRole = Role::factory()->create(['name' => 'master']);

    $globalPermission = Permission::factory()->create(['name' => 'create-board']);
    $boardPermission = Permission::factory()->create(['name' => 'update-board']);

    // Attach permissions to roles
    $globalRole->permissions()->attach($globalPermission->id);
    $boardRole->permissions()->attach($boardPermission->id);

    // Attach roles to the user
    $user->roles()->attach($globalRole->id);
    $board->users()->attach($user->id, ['role_id' => $boardRole->id]);

    // Verify the user has the correct global role
    $this->assertDatabaseHas('role_user', [
        'user_id' => $user->id,
        'role_id' => $globalRole->id,
    ]);

    // Verify the user has the correct board role
    $this->assertDatabaseHas('board_user', [
        'board_id' => $board->id,
        'user_id' => $user->id,
        'role_id' => $boardRole->id,
    ]);

    // Attempt to create a new board (global permission)
    $response = $this->actingAs($user)
        ->post('/api/boards/add', [
            'name' => 'New Board Name',
            'description' => 'A new board description',
            'capacity' => 5,
        ]);

    // We expect a status code 201 (Created) if the user has the correct global permission
    $response->assertStatus(201);

    // Attempt to update the existing board (board permission)
    $response = $this->actingAs($user)
        ->put("/api/board/{$board->id}/update", [
            'name' => 'Updated Name',
            'description' => 'Updated Description Board',
            'capacity' => 10,
        ]);

    // We expect a status code 200 (OK) if the user has the correct board permission
    $response->assertStatus(200);
});


