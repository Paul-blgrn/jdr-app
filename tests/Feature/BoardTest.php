<?php

use App\Models\Board;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\withoutExceptionHandling;

it("display boards for logged users", function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/api/boards');
    $response->assertOk();
});

it("sent a 404 error when accessing a board that do not exist", function () {

    $user = User::factory()->make();

    actingAs($user)
    ->get("/api/board/1")
    ->assertStatus(404);

    actingAs($user)
    ->get("/api/board/bonjour")
    ->assertStatus(404);
});

it("can display one specific board", function () {

    $user = User::factory()->create();
    $board = Board::factory()->create();

    $this->actingAs($user);

    get("/api/board/{$board->id}")
        ->assertOk()
        ->assertSee($board->name)
        ->assertSee($board->description)
        ->assertSee($board->capacity)
        ->assertSee($board->code);
});

test("player can join a board with right code", function () {
    $user = User::factory()->create();
    $board = Board::factory()->create();

    $this->actingAs($user)
        ->post("/api/boards/join",
            [
                "code" => $board->code,
            ])
        ->assertStatus(201);

        expect($user->boards)->toHaveCount(1);
        expect($user->boards->contains($board))->toBeTrue();
});



it("can't join boards with wrong or empty invite code", function (string $code) {
    $user = User::factory()->create();

    $this->actingAs($user)->post("/api/boards/join",
        [
            "code"=> $code,
        ])
    ->assertStatus(404);

    expect($user->boards)->toHaveCount(0);
})->with(["12345", "bonjour", ""]);



it("can create board", function () {
    $user = User::factory()->create();
    $board = Board::factory()->create();
})->todo();

test("the creator of board have role master", function () {

})->todo();
