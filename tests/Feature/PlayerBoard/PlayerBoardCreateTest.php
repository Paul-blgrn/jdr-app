<?php

use App\Models\Board;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Validator;

use function Pest\Laravel\withoutExceptionHandling;

// ------------------------
//   POSITIVE TEST (CAN)
// ------------------------

it('can join a board with right code', function () {
    // Create one User with role master
    $master = User::factory()->create();
    // Create one User with role player
    $user = User::factory()->create();
    // Create one Board
    $board = Board::factory()->create();

    // Create role user, master and player
    $roleUser = Role::factory()->create(['name' => 'user']);
    $roleMaster = Role::factory()->create(['name' => 'master']);
    $rolePlayer = Role::factory()->create(['name' => 'player']);

    // Create permission for role user
    $userPermissions = ['create-board', 'join-board'];
    $createdUserPermissions = [];
    foreach ($userPermissions as $permissionName) {
        $createdUserPermissions[] = Permission::factory()->create([
            'name' => $permissionName,
        ]);
    }
    // Attach permission to the role
    $roleUser->permissions()->sync(collect($createdUserPermissions)->pluck('id'));
    // Attach $user to the role user
    $user->roles()->attach($roleUser);

    // create permission for master
    $masterPermissions = ['view-board', 'update-board', 'delete-board'];
    $createdMasterPermissions = [];
    foreach ($masterPermissions as $permissionName) {
        $createdMasterPermissions[] = Permission::factory()->create([
            'name' => $permissionName,
        ]);
    }
    $roleMaster->permissions()->sync(collect($createdMasterPermissions)->pluck('id'));
    // Attach $master to the board with role master
    $board->users()->attach($master->id, ['role_id' => $roleMaster->id]);

    // try to join a board with the code $code
    $response = $this->actingAs($user)
        ->post("/api/boards/join",
            [
                "code" => $board->code,
            ])
        ->assertStatus(201);

    // Check that the response is in JSON and contains the expected data
    $response->assertJson([
        'response' => [
            'status_title' => 'Success',
            'status_message' => 'User joined the board successfully.',
            'status_code' => 201,
        ]
    ]);

    // Check JSON response structure
    $response->assertJsonStructure([
        'response' => [
            'status_title',
            'status_message',
            'status_code',
        ]
    ]);

    // Check that the user has been added to the board
    $this->assertDatabaseHas("board_user", [
        "board_id"=> $board->id,
        "user_id" => $user->id,
        "role_id" => $rolePlayer->id,
    ]);

    // Ensure that the board contains 2 users
    expect($board->users)->toHaveCount(2);
    // Ensure that the user $user has the board $board
    expect($user->boards->contains($board))->toBeTrue();
});

// ------------------------
//  NEGATIVE TEST (CANNOT)
// ------------------------

it('cannot join boards with wrong or empty invite code', function (string $code) {
    // Create one user
    $user = User::factory()->create();

    $roleUser = Role::factory()->create(['name' => 'user']);
    $permissionJoin = Permission::factory()->create(['name' => 'join-board']);

    $roleUser->permissions()->attach($permissionJoin->id);

    $user->roles()->attach($roleUser->id);

    // Simulate user login and send a code
    $response = $this->actingAs($user)
        ->post("/api/boards/join",
            [
                'code' => $code,
            ]);

    // Check response status to indicate validation error
    $response->assertStatus(422);

    $request = Request::create('/api/boards/join', 'POST', ['code'  => $code]);
    $validator = Validator::make($request->all(), [
        'code' => 'required|string',
    ]);

    // Check that the response is in JSON and contains the expected data
    if (empty($code)) {
        $response->assertJson([
            'response' => [
                'status_code' => 422,
                'status_title' => 'Validation Error',
                'status_message' => $validator->errors()->toArray(),
            ]
        ]);
    } else {
        $response->assertJson([
            'response' => [
                'status_code' => 422,
                'status_title' => 'Validation Error',
                'status_message' => 'The code is invalid or does not exist.',
            ]
        ]);
    }

    // Check JSON response structure
    $response->assertJsonStructure([
        'response' => [
            'status_code',
            'status_title',
            'status_message',
        ]
    ]);

    // Reload user to ensure relationships are updated
    $user->refresh();

    // Verify that the user has not joined any boards
    expect($user->boards)->toHaveCount(0);

})->with(["12345", "bonjour", ""]);

it('cannot join a full board', function () {
    // Création des utilisateurs et des rôles
    $master = User::factory()->create();
    $users = User::factory(3)->create();
    $userToJoin = User::factory()->create();

    $board = Board::factory()->create([
        'name' => 'table pleine',
        'description' => 'la table est pleine et doit exclure toute personne qui essaye de la rejoindre',
        'code' => 'fulltable',
        'capacity' => 4,
    ]);

    $roleUser = Role::factory()->create(['name' => 'user']);
    $roleMaster = Role::factory()->create(['name' => 'master']);
    $rolePlayer = Role::factory()->create(['name' => 'player']);

    // Création of permissions
    $viewPermission = Permission::factory()->create(['name' => 'view-board']);
    $leavePermission = Permission::factory()->create(['name' => 'leave-board']);
    $updatePermission = Permission::factory()->create(['name' => 'update-board']);
    $deletePermission = Permission::factory()->create(['name' => 'delete-board']);
    $createPermission = Permission::factory()->create(['name' => 'create-board']);
    $joinPermission = Permission::factory()->create(['name' => 'join-board']);

    // Attach permissions to roles
    $roleUser->permissions()->attach([$createPermission->id, $joinPermission->id]);
    $roleMaster->permissions()->attach([$updatePermission->id, $deletePermission->id, $viewPermission->id]);
    $rolePlayer->permissions()->attach([$viewPermission->id, $leavePermission->id]);

    // Attach roles to users
    $userToJoin->roles()->attach($roleUser);
    $master->roles()->attach($roleUser);
    $users->each(function ($user) use ($roleUser) {
        $user->roles()->attach($roleUser);
    });

    // Attach roles to board users
    $board->users()->attach($master->id, ['role_id' => $roleMaster->id]);
    $users->each(function ($user) use ($board, $rolePlayer) {
        $board->users()->attach($user->id, ['role_id' => $rolePlayer->id]);
    });

    // Simulate the user's attempt to join the board
    $response = $this->actingAs($userToJoin)
        ->post("/api/boards/join", ["code" => $board->code])
        ->assertStatus(403);

    // Refresh the board model
    $board->refresh();

    // Check that the response is in JSON and contains the expected data
    $response->assertJson([
        'response' => [
            'status_title' => 'No permission',
            'status_message' => 'User cannot join a full board.',
            'status_code' => 403,
        ]
    ]);

    // Check JSON response structure
    $response->assertJsonStructure([
        'response' => [
            'status_title',
            'status_message',
            'status_code',
        ]
    ]);

    // Check the number of users on the board has not changed
    expect($board->users)->toHaveCount(4);

    // Check that the user who tried to join is not in the pivot table
    $this->assertDatabaseMissing('board_user', [
        'board_id' => $board->id,
        'user_id' => $userToJoin->id,
    ]);

    // Check user roles on the board
    $board->users->each(function (User $user) use ($master) {
        // Get specific role from pivot table 'board_user'
        $roleID = $user->pivot->role_id;
        $role = Role::find($roleID);

        // verify that the role exists
        if ($role) {
            $roleName = $role->name;

            // Check if the role is what we expect
            if ($user->id == $master->id) {
                expect($roleName)->toBe('master');
            } else {
                expect($roleName)->toBe('player');
            }
        } else {
            // If the role does not exist, fail the test
            $this->fail('Role not found for user ID ' . $user->id);
        }
    });
});
