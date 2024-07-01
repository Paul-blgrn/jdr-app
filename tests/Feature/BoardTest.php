<?php

use App\Models\Board;
use App\Models\User;

use Illuminate\Support\Str;

use function Pest\Laravel\withoutExceptionHandling;

test('user can create a board', function () {
    // Create one User
    $user = User::factory()->create();

    // Simulate a user who creates a board with data
    $response = $this->actingAs($user)
        ->post("/api/boards/add", [
            'name' => 'Test Board',
            'description' => 'This is a test board.',
            'capacity' => 4,
        ]);

    // We expect a status code 201 (created)
    $response->assertStatus(201);

    // Explicitly check that the board was created in the database
    $this->assertDatabaseHas('boards', [
        'name' => 'Test Board',
        'description' => 'This is a test board.',
        'capacity' => 4,
    ]);

    // get the board in database and return the first result
    $board = Board::where('name', 'Test Board')->first();

    // Check JSON response content
    $response->assertJson([
        'response' => [
            'status_code' =>201,
            'status_title' => 'Success',
            'status_message' => 'Board created successfully.',
            'board' => $board->withCount('users')->get()->toJson(),
        ]
    ]);

    // Check JSON response structure
    $response->assertJsonStructure([
        'response' => [
            'status_code',
            'status_title',
            'status_message',
            'board',
        ]
    ]);

    // Count users in the board
    expect($user->boards)->toHaveCount(1);

    // Explicitly check that the user was attached to the board
    $this->assertDatabaseHas('board_user', [
        'board_id' => $board->id,
        'user_id' => $user->id,
    ]);

    // Verify that the code was generated and is unique
    $this->assertNotNull($board->code);
    $this->assertEquals(10, strlen($board->code));
});

test('board code is unique', function () {
    // Create one User
    $user = User::factory()->create();

    // Create multiple Boards
    $board1 = Board::factory()->create(['code' => Str::random(10)]);
    $board2 = Board::factory()->create(['code' => Str::random(10)]);

    // Check that the codes are unique
    $this->assertNotEquals($board1->code, $board2->code);
});

test('the creator of board have role master and other have role player', function () {
    // Create one Users
    $master = User::factory()->create();

    // Simulate the creation of the board by the user
    $response = $this->actingAs($master)
        ->post('/api/boards/add', [
            'name' => 'Test Board',
            'description' => 'This is a test board.',
            'capacity' => 4,
        ]);

    // We expect a status code 201 (created)
    $response->assertStatus(201);

    // Retrieve the created board from the database
    $board = Board::where('name', 'Test Board')->first();

    // Check that the user is attached to the board with the role 'master'
    $this->assertDatabaseHas('board_user', [
        'board_id' => $board->id,
        'user_id' => $master->id,
        'role' => 'master',
    ]);

    // Add additional users and assign them as 'players'
    $players = User::factory(2)->create();

    // Attach the players to the board with the role 'player'
    $players->each(function($player) use ($board) {
        $board->users()->attach($player->id, ['role' => 'player']);
    });

    // Check that the players are attached to the board with the role 'player'
    $players->each(function($player) use ($board) {
        $this->assertDatabaseHas('board_user', [
            'board_id' => $board->id,
            'user_id' => $player->id,
            'role' => 'player',
        ]);
    });

});

test('unauthenticated user cannot create a board', function () {
    // Simulate an unauthenticated user trying to create a board
    $response = $this->post('/api/boards/add', [
        'name' => 'Test Board',
        'description' => 'This is a test board.',
        'capacity' => 4,
    ]);

    // We expect a status code 302 (redirect)
    $response->assertStatus(302);

    // Explicitly check that the board was not  created in the database
    $this->assertDatabaseMissing('boards', [
        'name' => 'Test Board',
        'description' => 'This is a test board.',
        'capacity' => 4,
    ]);
});

it('cannot create a board without name', function() {
    // Create one User
    $user = User::factory()->create();

    // Simulate a user who tries to create a board without a name
    $response = $this->actingAs($user)
        ->post('/api/boards/add', [
            'description' => 'This is a test board.',
            'capacity' => 4,
        ]);

    // We expect a status code 422 (validation error)
    $response->assertStatus(422);

    // Check JSON response content
    $response->assertJsonStructure([
        'response' => [
            'status_title',
            'status_message',
            'status_code',
        ]
    ]);
});

it('cannot create a board without description', function() {
    // Create one User
    $user = User::factory()->create();

    // Simulate a user who tries to create a board without a name
    $response = $this->actingAs($user)
        ->post('/api/boards/add', [
            'name' => 'Test Board',
            'capacity' => 4,
        ]);

    // We expect a status code 422 (validation error)
    $response->assertStatus(422);

    // Check JSON response content
    $response->assertJsonStructure([
        'response' => [
            'status_title',
            'status_message',
            'status_code',
        ]
    ]);
});

it('cannot create a board with capacity less than 2', function () {
    // Create one User
    $user = User::factory()->create();

    // Simulate a user who tries to create a board with invalid capacity
    $response = $this->actingAs($user)
        ->post('/api/boards/add', [
            'name' => 'Test Board',
            'description' => 'This is a test board.',
            'capacity' => 1,
        ]);

    // We expect a status code 422 (validation error)
    $response->assertStatus(422);

    // Check JSON response content
    $response->assertJsonStructure([
        'response' => [
            'status_title',
            'status_message',
            'status_code',
        ]
    ]);
});

it('can delete a board if have role master', function() {
    // Create one master, one user and one board
    $master = User::factory()->create();
    $user = User::factory()->create();
    $board = Board::factory()->create();

    // Attach the user $master to the Board and assign him the role master
    $board->users()->attach($master->id, ["role" => "master"]);
    // Attach the user $user to the Board and assign him the role player
    $board->users()->attach($user->id, ["role" => "player"]);

    $response = $this->actingAs($master)
        ->delete("/api/board/{$board->id}/delete")
        ->assertStatus(200);

    // Check JSON response content
    $response->assertJson([
        'response' => [
            'status_title' => 'Success',
            'status_message' => 'The board ha been deleted successfully.',
            'status_code' => 200,
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

    $this->assertDatabaseMissing('boards', [
        'id' => $board->id
    ]);

    $this->assertDatabaseMissing('board_user', [
        'board_id' => $board->id,
    ]);

    $board->users->each(function($user) {
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    });

});

test('unauthenticated user cannot delete a board', function () {
    // Create one master and one board
    $master = User::factory()->create();
    $board = Board::factory()->create();
    // Attach master to the board with role "master"
    $board->users()->attach($master->id, ["role" => "master"]);

    // Simulate an unauthenticated user trying to delete the board
    $response = $this->delete("/api/board/{$board->id}/delete");

    // We expect a status code 302 (redirect)
    $response->assertStatus(302);

    // We explicitly check in the database that the board still exists
    $this->assertDatabaseHas('boards', [
        'id' => $board->id,
    ]);
});

test('user with role player cannot delete à board', function () {
    // Create one master, one user and one board
    $master = User::factory()->create();
    $user = User::factory()->create();
    $board = Board::factory()->create();

    // Attach the user $master to the Board and assign him the role master
    $board->users()->attach($master->id, ["role" => "master"]);
    // Attach the user $user to the Board and assign him the role player
    $board->users()->attach($user->id, ["role" => "player"]);

    // Simulate user login as $user and try to delete the board
    // Return status code 403 (forbidden for $user)
    $response = $this->actingAs($user)
        ->delete("/api/board/{$board->id}/delete")
        ->assertStatus(403);

    // Check JSON response content
    $response->assertJson([
        'response' => [
            'status_title' => 'No permission',
            'status_message' => 'The user with role Player cannot delete a board.',
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

    // Reload the board with its users to ensure the relationship is up-to-date
    $board->refresh();

    // We explicitly check in the database that the board still exists
    $this->assertDatabaseHas('boards', [
        'id' => $board->id,
    ]);

    // We explicitly check in the database that the users are still in the board
    $board->users()->each(function (User $user) use ($board) {
        $this->assertDatabaseHas('board_user', [
            'board_id' => $board->id,
            'user_id' => $user->id,
        ]);
    });

    // Check that there are still two users in the board loaded in relation
    expect($board->users)->toHaveCount(2);
});

test('master can update a board', function () {
    // Create one master and one board
    $master = User::factory()->create();
    $board = Board::factory()->create();
    // Attach master to the board with role "master
    $board->users()->attach($master->id, ["role" => "master"]);

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

test('user cannot update a board', function () {
    // Create two Users and one Board
    $users = User::factory(2)->create();
    $board = Board::factory()->create([
        'name' => 'Original Board',
        'description' => 'This is the original board.',
        'capacity' => 4,
    ]);

    // Attach users to the board with their roles
    $board->users()->attach($users->first()->id, ['role' => 'master']);
    $board->users()->attach($users->last()->id, ['role' => 'player']);

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
            'status_title' => 'No permission',
            'status_message' => 'Player cannot update the board.',
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

it('cannot update a board with invalid data', function () {
    // Create two Users ans one Board
    $users = User::factory(2)->create();
    $board = Board::factory()->create();

    // Attach users to the board with their roles
    $board->users()->attach($users->first()->id, ['role' => 'master']);
    $board->users()->attach($users->last()->id, ['role' => 'player']);

    // Attempt to update the board with invalid data
    $response = $this->actingAs($users->first())
        ->put("/api/board/{$board->id}/update", [
            'name' => '',
            'description' => '',
            'capacity' => 1,
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
