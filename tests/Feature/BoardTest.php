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
    withoutExceptionHandling();

    $user = User::factory()->create();
    $board = Board::factory()->create();

    $this->actingAs($user)
        ->post("/api/boards/join",
            [
                "code" => $board->code,
            ])
        ->assertStatus(201);
});

it("can't join boards without code", function () {
    withoutExceptionHandling();

    $user = User::factory()->create();
    $board = Board::factory()->create();

    $this->actingAs($user)
        ->post("/api/boards/join",
            [
                "code" => null,
            ])
        ->assertStatus(302);
})->todo();

it("can't join boards with wrong invite code", function () {
    $user = User::factory()->create();
    $board = Board::factory()->create();
    $fakeCode = "1234";

    $this->actingAs($user)
        ->post("/api/boards/join",
            [
                "code" => $fakeCode,
            ]);
    expect($board->code)->not()->toEqual($fakeCode);
});

test("the creator of board have role master", function () {

})->todo();
