<?php

use App\Models\Board;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\withoutExceptionHandling;

it("can create board", function () {
    $user = User::factory()->create();
    $board = Board::factory()->create();

    $this->actingAs($user)
        ->post("/api/boards/add",
            [

            ]);
})->todo();

test("the creator of board have role master", function () {

})->todo();

test("user with role player cannot delete à board", function () {
    // Créer deux Utilisateurs et une Board
    $user = User::factory()->create();
    $master = User::factory()->create();
    $board = Board::factory()->create();

    // Attacher l'utilisateur $master à la Board et lui attribuer le role master
    $board->users()->attach($master->id, ["role" => "master"]);
    // Attacher l'utilisateur $user à la Board et lui attribuer le role player
    $board->users()->attach($user->id, ["role" => "player"]);

    $response = $this->actingAs($user)
        ->delete("/api/board/delete/{$board->id}")
        ->assertStatus(403);

    // Vérifier le contenu de la réponse JSON
    $response->assertJson([
        'response' => [
            'status_title' => 'No permission',
            'status_message' => 'The user with role Player cannot delete a board.',
            'status_code' => 403,
        ]
    ]);

    // Vérifier la structure de la réponse JSON
    $response->assertJsonStructure([
        'response' => [
            'status_title',
            'status_message',
            'status_code',
        ]
    ]);
});

it("can delete a board if user have role master", function() {
    // créer un utilisateur et une board
    $user = User::factory()->create();
    $board = Board::factory()->create();

    // Attacher l'utilisateur à la Board et lui attribuer le role master
    $board->users()->attach($user->id, ["role" => "master"]);

    $response = $this->actingAs($user)
        ->delete("/api/board/delete/{$board->id}")
        ->assertStatus(200);
})->todo();
