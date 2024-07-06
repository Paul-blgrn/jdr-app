<?php

use App\Models\Board;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\withoutExceptionHandling;

// ------------------------
//   POSITIVE TEST (CAN)
// ------------------------

it('can create a board', function () {
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


it('generates unique board codes', function () {
    // Create a user
    $user = User::factory()->create();

    // Create the first and second Board and extract the code
    $boardResponse = $this->actingAs($user)
    ->post('/api/boards/add', [
        'name' => 'Board number One',
        'description' => 'Description for board one',
        'capacity' => 4,
    ])->assertStatus(201);

    $boardResponse = $this->actingAs($user)
    ->post('/api/boards/add', [
        'name' => 'Board number Two',
        'description' => 'Description for board two',
        'capacity' => 6,
    ])->assertStatus(201);

    // Retrieving boards from the response
    $boardContent = $boardResponse->getContent();
    $boardData = json_decode($boardContent, true);
    $boardFinalData = json_decode($boardData['response']['board'], true);

    // Extract codes from tables
    $board1Code = $boardFinalData[0]['code'];
    $board2Code = $boardFinalData[1]['code'];

    // Ensure codes are present and unique
    $this->assertIsString($board1Code);
    $this->assertIsString($board2Code);
    $this->assertNotEmpty($board1Code);
    $this->assertNotEmpty($board2Code);
    $this->assertNotEquals($board1Code, $board2Code);
});

test('board code format and length', function () {
    // Create one User
    $user = User::factory()->create();
    // Create a board
    $board = Board::factory()->create(['code' => $code = Str::random(10)]);
    // Attach user to the board with role "master"
    $board->users()->attach($user->id, ['role' => 'master']);

    // Check that the code is a string and has the correct length
    $this->assertIsString($board->code);
    $this->assertEquals(10, strlen($board->code));
});

test('the creator of board have role master and other have role player', function () {
    // Create one Users
    $master = User::factory()->create();

    // Simulate the creation of the board by the user
    $response = $this->actingAs($master)
        ->post('/api/boards/add', [
            'name' => 'Test Board',
            'description' => 'This is a test board.',
            'capacity' => 4,
        ]);

    // We expect a status code 201 (created)
    $response->assertStatus(201);

    // Retrieve the created board from the database
    $board = Board::where('name', 'Test Board')->first();

    // Check that the user is attached to the board with the role 'master'
    $this->assertDatabaseHas('board_user', [
        'board_id' => $board->id,
        'user_id' => $master->id,
        'role' => 'master',
    ]);

    // Add additional users and assign them as 'players'
    $players = User::factory(2)->create();

    // Attach the players to the board with the role 'player'
    $players->each(function($player) use ($board) {
        $board->users()->attach($player->id, ['role' => 'player']);
    });

    // Check that the players are attached to the board with the role 'player'
    $players->each(function($player) use ($board) {
        $this->assertDatabaseHas('board_user', [
            'board_id' => $board->id,
            'user_id' => $player->id,
            'role' => 'player',
        ]);
    });

});

// ------------------------
//  NEGATIVE TEST (CANNOT)
// ------------------------

it('cannot create a board if unauthenticated', function () {
    // Simulate an unauthenticated user trying to create a board
    $response = $this->post('/api/boards/add', [
        'name' => 'Test Board',
        'description' => 'This is a test board.',
        'capacity' => 4,
    ]);

    // We expect a status code 302 (redirect)
    $response->assertStatus(302);

    // Explicitly check that the board was not  created in the database
    $this->assertDatabaseMissing('boards', [
        'name' => 'Test Board',
        'description' => 'This is a test board.',
        'capacity' => 4,
    ]);
});

it('cannot create a board without name', function() {
    // Create one User
    $user = User::factory()->create();

    // Simulate a user who tries to create a board without a name
    $response = $this->actingAs($user)
        ->post('/api/boards/add', [
            'description' => 'This is a test board.',
            'capacity' => 4,
        ]);

    // We expect a status code 422 (validation error)
    $response->assertStatus(422);

    // Check JSON response content
    $response->assertJsonStructure([
        'response' => [
            'status_title',
            'status_message',
            'status_code',
        ]
    ]);
});

it('cannot create a board with too short name', function () {
    // Create one User
    $user = User::factory()->create();

    // Simulate the user trying to create a board with a short description
    $response = $this->actingAs($user)
        ->post('/api/boards/add',[
            'name' => 'Short',
            'description' => 'This is a test board with short name.',
            'capacity' => 4,
        ]);

    // We expect a status code 422 (validation error)
    $response->assertStatus(422);

    // Check JSON response content
    $response->assertJson([
        'response' => [
            'status_title' => 'Validation Error',
            'status_message' => [
                'name' => [
                    'The name field must be at least 10 characters.'
                ]
            ],
            'status_code' => 422,
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

it('cannot create board with duplicated or empty name', function (string $name) {
    // Create a User
    $user = User::factory()->create();

    // Create a board with a specific name
    Board::factory()->create(['name' => $name]);

    // Simulate the user trying to create another board with the same name
    $response = $this->actingAs($user)
        ->post('/api/boards/add', [
            'name' => $name,
            'description' => 'This is a duplicate board.',
            'capacity' => 4,
        ]);

    // We expect a status code 422 (Validation Error)
    $response->assertStatus(422);

    // Check JSON response content
    if (empty($name)) {
        $response->assertJson([
            'response' => [
                'status_title' => 'Validation Error',
                'status_message' => [
                    'name' => [
                        'The name field is required.',
                    ]
                ],
                'status_code' => 422,
            ]
        ]);
    } else {
        $response->assertJson([
            'response' => [
                'status_title' => 'Validation Error',
                'status_message' => [
                    'name' => [
                        'The name has already been taken.',
                    ]
                ],
                'status_code' => 422,
            ]
        ]);
    }

    // Check JSON response structure
    $response->assertJsonStructure([
        'response' => [
            'status_title',
            'status_message' => [
                'name',
            ],
            'status_code',
        ]
    ]);
})->with(["My Board", "12345", ""]);

it('cannot create a board without description', function() {
    // Create one User
    $user = User::factory()->create();

    // Simulate a user who tries to create a board without a name
    $response = $this->actingAs($user)
        ->post('/api/boards/add', [
            'name' => 'Test Board',
            'capacity' => 4,
        ]);

    // We expect a status code 422 (validation error)
    $response->assertStatus(422);

    // Check JSON response content
    $response->assertJsonStructure([
        'response' => [
            'status_title',
            'status_message',
            'status_code',
        ]
    ]);
});

it('cannot create a board with too short description', function () {
    // Create a User
    $user = User::factory()->create();

    // Simulate the user trying to create a board with a short description
    $response = $this->actingAs($user)
        ->post('/api/boards/add', [
            'name' => 'Test Board',
            'description' => 'Short',
            'capacity' => 4,
        ]);

    // We expect a status code 422 (validation error)
    $response->assertStatus(422);

    // Check JSON response content
    $response->assertJson([
        'response' => [
            'status_title' => 'Validation Error',
            'status_message' => [
                'description' => [
                    'The description field must be at least 20 characters.'
                ]
            ],
            'status_code' => 422,
        ]
    ]);

    // Check JSON response content
    $response->assertJsonStructure([
        'response' => [
            'status_title',
            'status_message' => [
                'description'
            ],
            'status_code',
        ]
    ]);

});

it('cannot create a board with capacity less than 2', function () {
    // Create one User
    $user = User::factory()->create();

    // Simulate a user who tries to create a board with invalid capacity
    $response = $this->actingAs($user)
        ->post('/api/boards/add', [
            'name' => 'Test Board',
            'description' => 'This is a test board.',
            'capacity' => 1,
        ]);

    // We expect a status code 422 (validation error)
    $response->assertStatus(422);

    // Check JSON response content
    $response->assertJson([
        'response' => [
            'status_title' => 'Validation Error',
            'status_message' => [
                'capacity' => [
                    'The capacity field must be at least 2.'
                ]
            ],
            'status_code' => 422,
        ]
    ]);

    // Check JSON response content
    $response->assertJsonStructure([
        'response' => [
            'status_title',
            'status_message',
            'status_code',
        ]
    ]);
});

it('cannot create a board with too high capacity', function() {
    // Create a User
    $user = User::factory()->create();

    // Simulate the user trying to create a board with a high capacity
    $response = $this->actingAs($user)
        ->post('/api/boards/add', [
            'name' => 'Test Board',
            'description' => 'This is a test board.',
            'capacity' => 100,
        ]);

    // We expect a status code 422 (validation error)
    $response->assertStatus(422);

    // Check JSON response content
    $response->assertJson([
        'response' => [
            'status_title' => 'Validation Error',
            'status_message' => [
                'capacity' => [
                    'The capacity field must not be greater than 20.'
                ]
            ],
            'status_code' => 422,
        ]
    ]);

    // Check JSON response content
    $response->assertJsonStructure([
        'response' => [
            'status_title',
            'status_message',
            'status_code',
        ]
    ]);
});
