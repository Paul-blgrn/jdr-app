<?php

use App\Models\Board;
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

    // Attach $master to the board with role master
    $board->users()->attach($master->id, ['role' => 'master']);

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
    // Create 1 user (master)
    $user = User::factory()->create();
    // Create 3 users (players)
    $users = User::factory(3)->create();
    // Create another user (the one who will try to join the full board)
    $userToJoin = User::factory()->create();

    // Create a Board with capacity for 4 users
    $board = Board::factory()->create([
        'name' => 'table pleine',
        'description' => 'la table est pleine et doit exclure toute personne qui essaye de la rejoindre',
        'code' => 'fulltable',
        'capacity' => 4,
    ]);

    // Attach users to the Board with their roles
    $board->users()->attach($user, ['role'=> 'master']);
    $board->users()->attach($users, ['role'=> 'player']);

    // Simulate the user's attempt to join the board
    $response = $this->actingAs($userToJoin)
        ->post("/api/boards/join",
            [
                "code" => $board->code,
            ])
        ->assertStatus(403);

    // Refresh the board model
    $board->refresh();

    // Check that the response is in JSON and contains the expected data
    $response->assertJson([
        'response' => [
            'status_title' => 'No permission',
            'status_message' => 'User cannot join à full board.',
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
    $board->users()->each(function (User $users) {
        $role = $users->pivot->role;
        if ($users->id == 1) {
            expect($role)->toBe('master');
        } else {
            expect($role)->toBe('player');
        }
    });
});
