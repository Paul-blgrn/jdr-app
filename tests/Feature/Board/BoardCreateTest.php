<?php

use App\Models\Board;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\withoutExceptionHandling;

// ------------------------
//   POSITIVE TEST (CAN)
// ------------------------

test('user can create a board', function () {
    // Create one User
    $user = User::factory()->create();

    // Créez un rôle avec la permission de créer un tableau
    $roleUser = Role::factory()->create(['name' => 'user']);
    $createPermission = Permission::factory()->create(['name' => 'create-board']);
    $roleUser->permissions()->attach($createPermission->id);
    $user->roles()->attach($roleUser);

    // Create role Master
    $roleMaster = Role::factory()->create(['name' => 'master']);


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

    // Get the board from the database and return the first result
    $board = Board::where('name', 'Test Board')->firstOrFail();

    // Ensure user is attached to the board with the master role
    $board->users()->syncWithoutDetaching([$user->id => ['role_id' => $roleMaster->id]]);

    // Parse the JSON response to an array
    $responseJson = $response->json();

    // Extract the 'board' part of the response and compare it with the expected data
    $actualBoardData = $responseJson['response']['board'];
    $expectedBoardData = $board->withCount('users')->get()->toJson();

    // Compare the actual and expected board data
    $this->assertEquals($expectedBoardData, $actualBoardData);

    // Check JSON response
    $response->assertJson([
        'response' => [
            'status_code' => 201,
            'status_title' => 'Success',
            'status_message' => 'Board created successfully.',
            'board' => $board->withCount('users')->get()->toJson(),
        ],
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
        'role_id' => $roleMaster->id,
    ]);

    // Verify that the code was generated and is unique
    $this->assertNotNull($board->code);
    $this->assertEquals(10, strlen($board->code));
});


it('generates unique board codes', function () {
    // Create a user
    $user = User::factory()->create();

    // Create roles User & Master
    $roleUser = Role::factory()->create(['name' => 'user',]);
    Role::factory()->create(['name' => 'master',]);

    // Create permission for role User
    $permission = Permission::factory()->create(['name' => 'create-board']);
    // Attach permission to the role
    $roleUser->permissions()->attach($permission);
    // Attach role to the user
    $user->roles()->attach($roleUser);

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

    // Create roles User & Master
    $roleUser = Role::factory()->create(['name' => 'user',]);
    $roleMaster = Role::factory()->create(['name' => 'master',]);

    // Create permission
    $permission = Permission::factory()->create(['name' => 'create-board']);

    // Attach permission to the role
    $roleUser->permissions()->attach($permission);
    // Attach role to the user
    $user->roles()->attach($roleUser);

    // Ensure user is attached to the board with the master role
    $board->users()->syncWithoutDetaching([$user->id => ['role_id' => $roleMaster->id]]);

    // Check that the code is a string and has the correct length
    $this->assertIsString($board->code);
    $this->assertEquals(10, strlen($board->code));
});

test('the creator of board have role master and other have role player', function () {
    // Create one master and two users
    $master = User::factory()->create();
    $users = User::factory(2)->create();

    // Create roles User, Player & Master
    $roleUser = Role::factory()->create(['name' => 'user']);
    Role::factory()->create(['name' => 'master']);
    Role::factory()->create(['name' => 'player']);

    // Create permission
    $permission = Permission::factory()->create(['name' => 'create-board']);

    // Attach permission to role User (user can create boards)
    $roleUser->permissions()->attach($permission);

    // Attribute role User to $master (before creating the board)
    $master->roles()->attach($roleUser);

    $response = $this->actingAs($master)
        ->post('/api/boards/add', [
            'name' => 'My First Board',
            'description' => 'This is my first board.',
            'capacity' => 4,
        ]);

    // We expect a status code 201 (created)
    $response->assertStatus(201);

    // Retrieve the created board from the database
    $board = Board::where('name', 'My First Board')->firstOrFail();

    // Retreive master role in database
    $masterRole = Role::where('name', 'master')->firstOrFail();

    // Check that the user is attached to the board with the role 'master'
    $this->assertDatabaseHas('board_user', [
        'board_id' => $board->id,
        'user_id' => $master->id,
        'role_id' => $masterRole->id,
    ]);

    // Attach the players to the board with the role 'player' AND
    // Check that the players are attached to the board with the role 'player'
    $users->each(function($player) use ($board) {
        $playerRole = Role::where('name', 'player')->first();

        $board->users()->syncWithoutDetaching([$player->id => ['role_id' => $playerRole->id]]);

        $this->assertDatabaseHas('board_user', [
            'board_id' => $board->id,
            'user_id' => $player->id,
            'role_id' => $playerRole->id,
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

    // Create role user
    $roleUser = Role::factory()->create(['name' => 'user']);

    // Create permission for role user
    $permission = Permission::factory()->create(['name' => 'create-board']);

    // Attach permission to the role
    $roleUser->permissions()->attach($permission);

    // Attach $user to the role user
    $user->roles()->attach($roleUser);


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

it('cannot create board with duplicated, too long, too short or empty name', function (string $name) {
    // Create a User
    $user = User::factory()->create();

    // Create role user
    $roleUser = Role::factory()->create(['name' => 'user']);

    // Create role Master
    $roleMaster = Role::factory()->create(['name' => 'master']);

    // Create permission for role user
    $permission = Permission::factory()->create(['name' => 'create-board']);

    // Attach permission to the role
    $roleUser->permissions()->attach($permission);

    // Attach $user to the role user
    $user->roles()->attach($roleUser);

    // Create a board with the given name only if the name is valid (non-empty and length >= 10)
    if (!empty($name) && strlen($name) >= 10 && strlen($name) <= 40) {
        $board = Board::factory()->create(['name' => $name]);
        // Attach $user to the board with role Master
        $board->users()->syncWithoutDetaching([$user->id => ['role_id' => $roleMaster->id]]);
    }

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
    } else if (strlen($name) < 10) {
        $response->assertJson([
            'response' => [
                'status_title' => 'Validation Error',
                'status_message' => [
                    'name' => [
                        'The name field must be at least 10 characters.',
                    ]
                ],
                'status_code' => 422,
            ]
        ]);
    } else if (strlen($name) > 40) {
        $response->assertJson([
            'response' => [
                'status_title' => 'Validation Error',
                'status_message' => [
                    'name' => [
                        'The name field must not be greater than 40 characters.',
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

})->with(["My Duplicated Board", "12345", "My too long board name with over than 50 caracters maximum", ""]);

it('cannot create board with too short, too long or empty description', function(string $description) {
    // Create a User
    $user = User::factory()->create();

    // Create role user
    $roleUser = Role::factory()->create(['name' => 'user']);

    // Create role Master
    $roleMaster = Role::factory()->create(['name' => 'master']);

    // Create permission for role user
    $permission = Permission::factory()->create(['name' => 'create-board']);

    // Attach permission to the role
    $roleUser->permissions()->attach($permission);

    // Attach $user to the role user
    $user->roles()->attach($roleUser);

    // Simulate a user who tries to create a board without a name
    $response = $this->actingAs($user)
        ->post('/api/boards/add', [
            'name' => 'Test Board',
            'description' => $description,
            'capacity' => 4,
        ]);

    // We expect a status code 422 (validation error)
    $response->assertStatus(422);

    // Check JSON response content
    if (empty($description)) {
        $response->assertJson([
            'response' => [
                'status_title' => 'Validation Error',
                'status_message' => [
                    'description' => [
                        'The description field is required.',
                    ]
                ],
                'status_code' => 422,
            ]
        ]);
    } else if (strlen($description) < 20) {
        $response->assertJson([
            'response' => [
                'status_title' => 'Validation Error',
                'status_message' => [
                    'description' => [
                        'The description field must be at least 20 characters.',
                    ]
                ],
                'status_code' => 422,
            ]
        ]);
    } else if (strlen($description) > 70) {
        $response->assertJson([
            'response' => [
                'status_title' => 'Validation Error',
                'status_message' => [
                    'description' => [
                        'The description field must not be greater than 70 characters.',
                    ]
                ],
                'status_code' => 422,
            ]
        ]);
    }

    // Check JSON response content
    $response->assertJsonStructure([
        'response' => [
            'status_title',
            'status_message' => [
                'description',
            ],
            'status_code',
        ]
    ]);
})->with(["12345", "My too long board description with over than 70 caracters maximum, yeah its very long", ""]);

it('cannot create a board with capacity less than 2', function () {
    // Create one User
    $user = User::factory()->create();

    // Create role user
    $roleUser = Role::factory()->create(['name' => 'user']);

    // Create permission for role user
    $permission = Permission::factory()->create(['name' => 'create-board']);

    // Attach permission to the role
    $roleUser->permissions()->attach($permission);

    // Attach $user to the role user
    $user->roles()->attach($roleUser);

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

    // Create role user
    $roleUser = Role::factory()->create(['name' => 'user']);

    // Create permission for role user
    $permission = Permission::factory()->create(['name' => 'create-board']);

    // Attach permission to the role
    $roleUser->permissions()->attach($permission);

    // Attach $user to the role user
    $user->roles()->attach($roleUser);

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
