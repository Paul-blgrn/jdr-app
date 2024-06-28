<?php

use App\Models\Board;
use App\Models\User;

use function Pest\Laravel\actingAs;
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

it("can delete a board if user have role master", function() {
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


})->todo();

it("can create board", function () {
    $user = User::factory()->create();
    $board = Board::factory()->create();

    $this->actingAs($user)
        ->post("/api/boards/add",
            [

            ]);
})->todo();

it('assign roles correctly when board created', function () {
})->todo();

test("the creator of board have role master", function () {

})->todo();
