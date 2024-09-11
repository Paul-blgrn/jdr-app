<?php

use App\Models\Board;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Validator;

use function Pest\Laravel\withoutExceptionHandling;

// ------------------------
//   POSITIVE TEST (CAN)
// ------------------------

it('can leave a board successfully', function () {
    // Create four Users
    $users = User::factory(4)->create();
    // Create one Board
    $board = Board::factory()->create();

    $roleMaster = Role::factory()->create(['name' => 'master']);
    $rolePlayer = Role::factory()->create(['name' => 'player']);

    $permissionLeave = Permission::factory()->create(['name' => 'leave-board']);

    $rolePlayer->permissions()->attach($permissionLeave->id);

    // Attach the first user with role "master"
    $board->users()->attach($users->first()->id, ['role_id' => $roleMaster->id]);

    // Attach the remaining users with role "player"
    $users->skip(1)->each(function ($user) use ($board, $rolePlayer) {
        $board->users()->attach($user->id, ['role_id' => $rolePlayer->id]);
    });

    // User who will leave the board
    $userWhoLeave = $users->get(1);

    // Perform the request to detach the user from the board
    $response = $this->actingAs($userWhoLeave)
        ->delete("/api/board/{$board->id}/leave")
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
        'role_id' => $rolePlayer->id,
    ]);

    // Filter users to exclude the one who left and
    // check explicitly in database that others users are still attached to the board
    $users->filter(function (User $user) use ($userWhoLeave) {
        return $user->id !== $userWhoLeave->id;
    })->each(function (User $user) use ($board, $roleMaster, $rolePlayer) {
        // Check the role_id based on the role assigned
        $expectedRoleId = $user->id === $board->users->first()->id ? $roleMaster->id : $rolePlayer->id;

        $this->assertDatabaseHas('board_user', [
            'board_id'=> $board->id,
            'user_id'=> $user->id,
            'role_id' => $expectedRoleId,
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

    $roleMaster = Role::factory()->create(['name' => 'master']);
    $rolePlayer = Role::factory()->create(['name' => 'player']);

    $permissionLeave = Permission::factory()->create(['name' => 'leave-board']);

    $rolePlayer->permissions()->attach($permissionLeave->id);

    // Attach the first user with role "master"
    $board->users()->attach($master->id, ['role_id' => $roleMaster->id]);
    // Attach the second user with role "player"
    $board->users()->attach($userWhoLeave->id, ['role_id' => $rolePlayer->id]);

    // Simulate the user's attempt to leave the board
    $response = $this->actingAs($userWhoLeave)
        ->delete("/api/board/{$board->id}/leave")
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
        'role_id' => $roleMaster->id,
    ]);

    // Count the number of users on the board
    // we expect only one user in this case
    expect($board->users)->toHaveCount(1);
});

// ------------------------
//  NEGATIVE TEST (CANNOT)
// ------------------------

it('returns 404 if board that user try to leave does not exist', function () {
    $user = User::factory()->create();
    $invalidBoardId = 9999;

    // simulate an user trying to leave a non-existent board
    $response = $this->actingAs($user)
        ->delete("/api/board/{$invalidBoardId}/leave");

    $response->assertStatus(404);

    // Check JSON response content
    $response->assertJson([
        'response' => [
            'status_title' => 'Not found',
            'status_message' => 'Board not found.',
            'status_code' => 404,
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

it('cannot leave a board if it will be empty', function () {
    // Create one User
    $user = User::factory()->create();
    // Create one Board
    $board = Board::factory()->create();

    $rolePlayer = Role::factory()->create(['name' => 'player']);

    $permissionLeave = Permission::factory()->create(['name' => 'leave-board']);

    $rolePlayer->permissions()->attach($permissionLeave->id);

    // Attach user to the board with role "player" in this case
    // because an user with the role "master"  will not be able to leave the board in any case
    $board->users()->attach($user->id, ['role_id' => $rolePlayer->id]);

    // simulate the user trying to leave the board and failing because it would make it empty
    $response = $this->actingAs($user)
        ->delete("/api/board/{$board->id}/leave")
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
        'role_id' => $rolePlayer->id,
    ]);
});

it('cannot leave a board if user is not a member', function () {
    // Create one User (which is a member of the board)
    $user = User::factory()->create();
    // Create another User (which is not a member of the board)
    $notAMember = User::factory()->create();
    // Create one Board
    $board = Board::factory()->create();

    $rolePlayer = Role::factory()->create(['name' => 'player']);

    $permissionLeave = Permission::factory()->create(['name' => 'leave-board']);

    $rolePlayer->permissions()->attach($permissionLeave->id);

    // Attach $user to the board with role "master"
    $board->users()->attach($user->id, ['role_id' => $rolePlayer->id]);

    // simulate an user trying to leave a board which it's not a member
    $response = $this->actingAs($notAMember)
        ->delete("/api/board/{$board->id}/leave");

    $response->assertStatus(404);

    // Check JSON response content
    $response->assertJson([
        'response' => [
            'status_title' => 'Not found',
            'status_message' => 'Board not found.',
            'status_code' => 404,
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

it('cannot leave a board if user have role master', function () {
    // Create one User (who will try to leave the board)
    $userToLeave = User::factory()->create();
    // Create one User (who will stay in the board)
    $user = User::factory()->create();
    // Create one Board
    $board = Board::factory()->create();

    $roleMaster = Role::factory()->create(['name' => 'master']);
    $rolePlayer = Role::factory()->create(['name' => 'player']);

    $permissionLeave = Permission::factory()->create(['name' => 'leave-board']);

    $rolePlayer->permissions()->attach($permissionLeave->id);

    // Attach $userToLeave to the board with role "master"
    $board->users()->attach($userToLeave->id, ['role_id' => $roleMaster->id]);
    // Attach $user to the board with role "player"
    $board->users()->attach($user->id, ['role_id' => $rolePlayer->id]);

    // simulate an attempt to leave the board while having the role "master"
    // we return a 403 error in this case
    $response = $this->actingAs($userToLeave)
        ->delete("/api/board/{$board->id}/leave")
        ->assertStatus(403);

    // Check JSON response content
    $response->assertJson([
        'response' => [
            'status_title' => 'Forbidden',
            'status_message' => 'You do not have the required board role.',
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
        'role_id' => $roleMaster->id,
    ]);

    // We expect 2 user on the board because the master of the board can't leave it
    expect($board->users)->toHaveCount(2);
});
