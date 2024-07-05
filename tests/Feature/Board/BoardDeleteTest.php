<?php
use App\Models\Board;
use App\Models\User;

use function Pest\Laravel\withoutExceptionHandling;

// ------------------------
//   POSITIVE TEST (CAN)
// ------------------------

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

// ------------------------
//  NEGATIVE TEST (CANNOT)
// ------------------------

it('cannot delete à board if have role player', function () {
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

it('cannot delete a board if unauthenticated', function () {
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
