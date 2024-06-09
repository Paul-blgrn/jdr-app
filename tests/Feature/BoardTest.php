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
