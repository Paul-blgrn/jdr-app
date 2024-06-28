<?php

use App\Models\Board;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\get;
use function Pest\Laravel\withoutExceptionHandling;

it("sent a 404 error when accessing a board that do not exist", function () {
    // Create one user
    $user = User::factory()->make();

    // the user tries to access a board that does not exist, a 404 error is returned
    actingAs($user)
    ->get("/api/board/1")
    ->assertStatus(404);

    // Same case here but with different uri
    actingAs($user)
    ->get("/api/board/bonjour")
    ->assertStatus(404);
});

it("can join a board with right code", function () {
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

it("cannot join boards with wrong or empty invite code", function (string $code) {
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
                'status_message' => $validator->errors()->getMessages(),
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
            'status_title',
            'status_message',
            'status_code',
        ]
    ]);

    // Reload user to ensure relationships are updated
    $user->refresh();

    // Verify that the user has not joined any boards
    expect($user->boards)->toHaveCount(0);

})->with(["12345", "bonjour", ""]);

it("cannot join a full board", function () {
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

it("displays all the user boards and does not send back other players boards", function () {
    // Create one user and associate 3 Boards with him
    $user = User::factory()->hasAttached(Board::factory(3))->create();

    // Simulate the connection of $user and ensure the response 200 on "/api/boards"
    $response = $this
        ->actingAs($user)
        ->get('/api/boards')
        ->assertStatus(200);

    // Check JSON response structure
    $response->assertJsonStructure([
        '*' => [
            'id',
            'name',
            'description',
            'capacity',
            'code',
            'created_at',
            'updated_at',
            'users_count',
            'pivot' => [
                'user_id',
                'board_id',
                'role',
            ],
        ],
    ]);

    // Get response content in JSON
    $data = $response->json();

    // Check that the response is not empty
    expect($data)->not->toBeEmpty();

    // Check that $data contains the right number of boards
    expect($data)->toHaveCount($user->boards->count());

    $pluckedData = collect($data)->pluck('id')->toArray();
    $pluckedUser = $user->boards->pluck('id')->toArray();
    // Check IDS
    expect($pluckedData)->toEqual($pluckedUser);

    // Verify that the response contains the correct information
    $user->boards->each(function (Board $board) use ($data) {
        // Get the first element of $data
        $boardData = collect($data)->firstWhere('id', $board->id);

        // Check that the board details are correct
        expect($boardData['name'])->toBe($board->name);
        expect($boardData['description'])->toBe($board->description);
        expect($boardData['capacity'])->toBe($board->capacity);

        // Check that there is only one user
        expect($boardData['users_count'])->toEqual(1);
    });
});

it('displays all users associated with a board', function () {
    // Create three Users
    $users = User::factory(3)->create();
    // Create one Board
    $board = Board::factory()->create();

    // Attach the first user to the board and assign him the role "master"
    $board->users()->attach($users->first()->id, ['role' => 'master']);
    // Attach the remaining users with role "player"
    $users->skip(1)->each(function ($user) use ($board) {
        $board->users()->attach($user->id, ['role' => 'player']);
    });

    // Select one user
    $user = $users->get(1);

    // Simulate $user connection and try to display board information
    // In this case we expect a status code 200
    $response = $this->actingAs($user)
        ->get('/api/board/'. $board->id)
        ->assertStatus(200);

    // Check JSON response structure
    $response->assertJsonStructure([
        'users' => [
            '*' => [
                'id',
                'name',
                'email',
                'created_at',
                'updated_at',
                'pivot' => [
                    'board_id',
                    'user_id',
                    'role',
                ],
            ],
        ],
    ]);

    // Transform response to JSON
    $data = $response->json();

    // Check that $data is not empty
    expect($data)->not->toBeEmpty();

    // Check that $data contains the correct number of users
    expect($data['users'])->toHaveCount(3);

    // Collecting IDs from $data['users']
    $pluckedData = collect($data['users'])->pluck('id')->toArray();
    // Collecting IDs from $board->users
    $pluckedUser = $board->users->pluck('id')->toArray();
    // compare user IDs associated with a board
    expect($pluckedData)->toEqual($pluckedUser);

    // Create a collection of user roles mapped by user IDs from the JSON response data
    $userRoles = collect($data['users'])->mapWithKeys(function ($user) {
        return [$user['id'] => $user['pivot']['role']];
    });

    // test users roles
    expect($userRoles[$users->first()->id])->toBe('master');

    // Skip the first element in the collection $userRoles and iterate over the remaining elements.
    $userRoles->skip(1)->each(function ($role) {
        // Assert that each $role is equal to 'player'.
        expect($role)->toBe('player');
    });

    foreach ($data['users'] as $userData) {
        $originalUser = $users->firstWhere('id', $userData['id']);
        expect($userData['name'])->toBe($originalUser->name);
    }
});

it('displays a board with details', function () {
    // Create two Users
    $users = User::factory(2)->create();
    // Create one Board
    $board = Board::factory()->create();

    // Attach first user with role "master"
    $board->users()->attach($users->first()->id, ["role"=> "master"]);
    // Attach last user with role "player"
    $board->users()->attach($users->last()->id, ["role"=> "player"]);


    $response = $this->actingAs($users->first())
        ->get("/api/board/". $board->id)
        ->assertStatus(200);

    // Check JSON response structure
    $response->assertJsonStructure([
        'id',
        'name',
        'description',
        'capacity',
        'code',
        'created_at',
        'updated_at',
        'users' => [
            '*' => [
                'id',
                'name',
                'email',
                'created_at',
                'updated_at',
                'pivot' => [
                    'board_id',
                    'user_id',
                    'role',
                ],
            ],
        ],
    ]);

    // Transform response to JSON
    $data = $response->json();

    // Check that the response is not empty
    expect($data)->not->toBeEmpty();

    // Check that $data contains the correct number of users
    expect($data['users'])->toHaveCount(2);

    // Collecting IDs from $data['users']
    $pluckedData = collect($data['users'])->pluck('id')->toArray();
    // Collecting IDs from $board->users
    $pluckedUser = $board->users()->pluck('user_id')->toArray();
    // compare user IDs associated with a board
    expect($pluckedData)->toEqual($pluckedUser);

    // Check that the board details are corrects
    expect($data['name'])->toEqual($board->name);
    expect($data['description'])->toEqual($board->description);
    expect($data['capacity'])->toEqual($board->capacity);

    // Iterate over each user data in the $data['users'] array returned from the API response
    foreach ($data['users'] as $userData) {
        // Retrieve the original user model from the $users collection by matching IDs
        $originalUsers = $users->firstWhere('id', $userData['id']);
        // Assert that the name of the user in the API response matches the name of the original user
        expect($userData['name'])->toBe($originalUsers->name);

        // Retrieve the expected role of the user in the board from the database using the pivot table relationship
        $expectedRole = $board->users()->where('user_id', $userData['id'])->first()->pivot->role;
        // Assert that the role of the user in the API response matches the expected role from the database
        expect($userData['pivot']['role'])->toBe($expectedRole);
    }
});

it("can leave a board successfully", function () {
    // Create four Users
    $users = User::factory(4)->create();
    // Create one Board
    $board = Board::factory()->create();

    // Attach the first user with role "master"
    $board->users()->attach($users->first()->id, ['role' => 'master']);

    // Attach the remaining users with role "player"
    $users->skip(1)->each(function ($user) use ($board) {
        $board->users()->attach($user->id, ['role' => 'player']);
    });

    // User who will leave the board
    $userWhoLeave = $users->get(1);

    // Perform the request to detach the user from the board
    $response = $this->actingAs($userWhoLeave)
        ->delete("/api/board/leave/{$board->id}")
        ->assertStatus(200);

    // Check JSON response content
    $response->assertJson([
        'response' => [
            'status_title' => 'Success',
            'status_message' => 'The user have successfully left the board.',
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

    // Explicitly check in database that the user has been detached from the Board
    $this->assertDatabaseMissing('board_user', [
        'board_id' => $board->id,
        'user_id' => $userWhoLeave->id,
    ]);

    // Filter users to exclude the one who left and
    // check explicitly in database that others users are still attached to the board
    $users->filter(function (User $user) use ($userWhoLeave) {
        return $user->id !== $userWhoLeave->id;
    })->each(function (User $user) use ($board) {
        $this->assertDatabaseHas('board_user', [
            'board_id'=> $board->id,
            'user_id'=> $user->id,
        ]);
    });

    // Reload the board with its users to ensure the relationship is up-to-date
    $board->refresh();

    // Check that the Board has the right number of users
    expect($board->users)->toHaveCount(3);
});

it('can leave a board successfully if other users remain', function () {
    // create one user (it will be the master of the board)
    $master = User::factory()->create();
    // create another user (it will be the player who leave the board)
    $userWhoLeave = User::factory()->create();
    // Create one board
    $board = Board::factory()->create();

    // Attach the first user with role "master"
    $board->users()->attach($master->id, ['role' => 'master']);
    // Attach the second user with role "player"
    $board->users()->attach($userWhoLeave->id, ['role' => 'player']);

    // Simulate the user's attempt to leave the board
    $response = $this->actingAs($userWhoLeave)
        ->delete('/api/board/leave/' . $board->id)
        ->assertStatus(200);

    // Check JSON response content
    $response->assertJson([
        'response' => [
            'status_title' => 'Success',
            'status_message' => 'The user have successfully left the board.',
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

    // Refresh the board model
    $board->refresh();

    // Check that user who left is no longer present in the board
    expect($board->users)->not()->toContain($userWhoLeave);

    // Explicitly check in the database that the user $master is still present
    $this->assertDatabaseHas('board_user', [
        'board_id' => $board->id,
        'user_id' => $master->id,
    ]);

    // Count the number of users on the board
    // we expect only one user in this case
    expect($board->users)->toHaveCount(1);
});

it('cannot leave a board if it will be empty', function () {
    // Create one User
    $user = User::factory()->create();
    // Create one Board
    $board = Board::factory()->create();

    // Attach user to the board with role "player" in this case
    // because an user with the role "master"  will not be able to leave the board in any case
    $board->users()->attach($user->id, ['role' => 'player']);

    // simulate the user trying to leave the board and failing because it would make it empty
    $response = $this->actingAs($user)
        ->delete("/api/board/leave/{$board->id}")
        ->assertStatus(403);

    // Check JSON response content
    $response->assertJson([
        'response' => [
            'status_title' => 'No permission',
            'status_message' => 'The user cannot leave a board if it becomes empty after leaving.',
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

    // Refresh the board model
    $board->refresh();

    // Retrieve the IDS of the board users and
    // Check that the other user is still on the board
    $pluckedBoard = $board->users->pluck('id')->toArray();
    expect($pluckedBoard)->toContain($user->id);

    // Explicitly check in the database that $user is still here
    $this->assertDatabaseHas('board_user', [
        'board_id' => $board->id,
        'user_id' => $user->id,
    ]);
});

it('returns 404 if board that user try to leave does not exist', function () {
    $user = User::factory()->create();
    $invalidBoardId = 9999;

    // simulate an user trying to leave a non-existent board
    $this->actingAs($user)
        ->delete("/api/board/leave/{$invalidBoardId}")
        ->assertStatus(404);
});

it("cannot leave a board if user is not a member", function () {
    // Create one User (which is a member of the board)
    $user = User::factory()->create();
    // Create another User (which is not a member of the board)
    $notAMember = User::factory()->create();
    // Create one Board
    $board = Board::factory()->create();

    // Attach $user to the board with role "master"
    $board->users()->attach($user->id, ['role' => 'master']);

    // simulate an user trying to leave a board which it's not a member
    $response = $this->actingAs($notAMember)
        ->delete("/api/board/leave/{$board->id}")
        ->assertStatus(403);

    // Check JSON response content
    $response->assertJson([
        'response' => [
            'status_title' => 'No permission',
            'status_message' => 'The user cannot leave a board if they are not a member.',
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
});

it("cannot leave a board if user have role master", function () {
    // Create one User (who will try to leave the board)
    $userToLeave = User::factory()->create();
    // Create one User (who will stay in the board)
    $user = User::factory()->create();
    // Create one Board
    $board = Board::factory()->create();

    // Attach $userToLeave to the board with role "master"
    $board->users()->attach($userToLeave->id, ["role" => "master"]);
    // Attach $user to the board with role "player"
    $board->users()->attach($user->id, ["role" => "player"]);

    // simulate an attempt to leave the board while having the role "master"
    // we return a 403 error in this case
    $response = $this->actingAs($userToLeave)
        ->delete("/api/board/leave/{$board->id}")
        ->assertStatus(403);

    // Check JSON response content
    $response->assertJson([
        'response' => [
            'status_title' => 'No permission',
            'status_message' => 'The user with role Master cannot leave a board.',
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

    // We explicitly check in the database that $userToLeave is still on the Board
    $this->assertDatabaseHas('board_user', [
        'board_id' => $board->id,
        'user_id' => $userToLeave->id,
    ]);

    // We expect 2 user on the board because the master of the board can't leave it
    expect($board->users)->toHaveCount(2);
});
