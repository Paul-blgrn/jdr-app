<?php

use App\Models\Board;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

use function Pest\Laravel\withoutExceptionHandling;

// ------------------------
//   POSITIVE TEST (CAN)
// ------------------------

test('master can update a board', function () {
    // Create one master and one board
    $master = User::factory()->create();
    $board = Board::factory()->create();

    // Create roles Master
    $roleUser = Role::factory()->create(['name' => 'user',]);
    $roleMaster = Role::factory()->create(['name' => 'master',]);

    // Create permission
    $permission = Permission::factory()->create(['name' => 'update-board']);

    // Attach permission to the role
    $roleMaster->permissions()->attach($permission->id);

    // Attach to the $master
    $master->roles()->attach($roleUser);

    // Attach master to the board with role "master
    $board->users()->attach($master->id, ['role_id' => $roleMaster->id]);

    // Simulate the master updating the board
    $response = $this->actingAs($master)
        ->put("/api/board/{$board->id}/update", [
            'name' => 'Updated Test Board',
            'description' => 'This is an updated test board.',
            'capacity' => 6,
        ]);

    // We expect a status code 200 (OK)
    $response->assertStatus(200);

    // Check JSON response content
    $response->assertJson([
        'response' => [
            'status_title' => 'Success',
            'status_message' => 'Board updated successfully.',
            'status_code' => 200,
        ]
    ]);

    // Explicitly check that the board was updated in the database
    $this->assertDatabaseHas('boards', [
        'id' => $board->id,
        'name' => 'Updated Test Board',
        'description' => 'This is an updated test board.',
        'capacity' => 6,
    ]);
});

// ------------------------
//  NEGATIVE TEST (CANNOT)
// ------------------------

test('user cannot update a board', function () {
    // Create two Users and one Board
    $users = User::factory(2)->create();
    $board = Board::factory()->create([
        'name' => 'Original Board',
        'description' => 'This is the original board.',
        'capacity' => 4,
    ]);

    // Create roles Master
    $roleUser = Role::factory()->create(['name' => 'user']);
    $roleMaster = Role::factory()->create(['name' => 'master']);
    $rolePlayer = Role::factory()->create(['name' => 'player']);

    // Create permission
    $updatePermission = Permission::factory()->create(['name' => 'update-board']);

    // Attach permission to the role
    $roleMaster->permissions()->attach($updatePermission->id);

    // attacher un role global à l'utilisateur
    $users->first()->roles()->attach($roleUser);
    $users->last()->roles()->attach($roleUser);


    // Attach users to the board with their roles
    $board->users()->attach($users->first()->id, ['role_id' => $roleMaster->id]);
    $board->users()->attach($users->last()->id, ['role_id' => $rolePlayer->id]);

    $response = $this->actingAs($users->last())
        ->put("/api/board/{$board->id}/update", [
            'name' => 'Updated Board',
            'description' => 'I am the player and i try to update your board.',
            'capacity' => 6,
        ]);

    // We expect a status code 403 (FORBIDDEN)
    $response->assertStatus(403);

    // Check JSON response content
    $response->assertJson([
        'response' => [
            'status_title' => 'Forbidden',
            'status_message' => 'You do not have the required board role.',
            'status_code' => 403,
        ]
    ]);

    // Explicitly check that the board was not updated in the database
    $this->assertDatabaseHas('boards', [
        'id' => $board->id,
        'name' => 'Original Board',
        'description' => 'This is the original board.',
        'capacity' => 4,
    ]);
});

test('the master cannot update a board with invalid data', function () {
    // Create one Master and one Board
    $master = User::factory()->create();
    $board = Board::factory()->create();

    $roleUser = Role::factory()->create(['name' => 'user']);
    $roleMaster = Role::factory()->create(['name' => 'master']);
    $permission = Permission::factory()->create(['name' => 'update-board']);

    // Attach permission to the role
    $roleMaster->permissions()->attach($permission->id);

    // Attach to the $master
    $master->roles()->attach($roleUser);

    // Attach master to the board with role "master
    $board->users()->attach($master->id, ['role_id' => $roleMaster->id]);

    // Vérifiez que l'utilisateur a le bon rôle dans la table pivot
    $this->assertDatabaseHas('board_user', [
        'board_id' => $board->id,
        'user_id' => $master->id,
        'role_id' => $roleMaster->id,
    ]);

    $response = $this->actingAs($master)
        ->put("/api/board/{$board->id}/update", [
            'name'=> 'az',
            'description'=> 'er',
            'capacity'=> 1,
        ]);

    // We expect a status code 422 (Validation Error)
    $response->assertStatus(422);

    // Explicitly check that the board was not updated in the database
    $this->assertDatabaseHas('boards', [
        'id' => $board->id,
        'name' => $board->name,
        'description' => $board->description,
        'capacity' => $board->capacity,
    ]);
});

it('cannot update a board with too short name', function () {
    $user = User::factory()->create();

})->todo();

it('cannot update a board with duplicated name', function () {

})->todo();

it('cannot update a board with too short description', function () {

})->todo();

it('cannot update a board with too low capacity', function () {

})->todo();

it('cannot update a board with too long name', function () {

})->todo();

it('cannot update a board with too long description', function () {

})->todo();

it('cannot update a board with too high capacity', function () {

})->todo();
