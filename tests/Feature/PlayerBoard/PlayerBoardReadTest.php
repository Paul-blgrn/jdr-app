<?php

use App\Models\Board;
use App\Models\User;

use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\withoutExceptionHandling;

// ------------------------
//   POSITIVE TEST (CAN)
// ------------------------

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

// ------------------------
//  NEGATIVE TEST (CANNOT)
// ------------------------
