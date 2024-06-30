<?php

use App\Models\Board;
use App\Models\User;

use function Pest\Laravel\withoutExceptionHandling;


test("user with role player cannot delete à board", function () {
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
        ->delete("/api/board/delete/{$board->id}")
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
        ->delete("/api/board/delete/{$board->id}")
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

it('assign roles correctly when board created', function () {
})->todo();

test("the creator of board have role master", function () {

})->todo();
