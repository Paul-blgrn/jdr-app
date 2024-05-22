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
        ->get('/boards');
    $response->assertOk();
});

it("sent a 404 error when accessing a board that do not exist", function () {

    $user = User::factory()->make();

    actingAs($user)
    ->get("/table/1")
    ->assertStatus(404);

    actingAs($user)
    ->get("/table/bonjour")
    ->assertStatus(404);
});

it("can display one specific board", function () {

    $user = User::factory()->create();
    $board = Board::factory()->create();

    $this->actingAs($user);

    get("/board/{$board->id}")
        ->assertOk()
        ->assertSee($board->name)
        ->assertSee($board->description)
        ->assertSee($board->capacity)
        ->assertSee($board->code);
});

it("cannot join boards without invite code", function () {
    $user = User::factory()->create();
    $board = Board::factory()->create();

    $this->actingAs($user);

    get("/boards/join/")
        ->assertOk();
});
