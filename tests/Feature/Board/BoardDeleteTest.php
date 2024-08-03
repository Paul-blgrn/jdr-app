<?php
use App\Models\Board;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

use function Pest\Laravel\withoutExceptionHandling;

// ------------------------
//   POSITIVE TEST (CAN)
// ------------------------

it('can delete a board if have role master', function() {
    // Créez un utilisateur master, un utilisateur et un tableau
    $master = User::factory()->create();
    $user = User::factory()->create();
    $board = Board::factory()->create();

    // Créez des rôles Player et Master
    $roleMaster = Role::factory()->create(['name' => 'master']);
    $rolePlayer = Role::factory()->create(['name' => 'player']);
    $roleUser = Role::factory()->create(['name' => 'user']);

    // Créez des permissions
    $viewPermission = Permission::factory()->create(['name' => 'view-board']);
    $leavePermission = Permission::factory()->create(['name' => 'leave-board']);
    $updatePermission = Permission::factory()->create(['name' => 'update-board']);
    $deletePermission = Permission::factory()->create(['name' => 'delete-board']);
    $createPermission = Permission::factory()->create(['name' => 'create-board']);
    $joinPermission = Permission::factory()->create(['name' => 'join-board']);

    // Attachez les permissions aux rôles
    $roleUser->permissions()->syncWithoutDetaching([$createPermission->id, $joinPermission->id]);
    $roleMaster->permissions()->syncWithoutDetaching([$deletePermission->id, $updatePermission->id, $viewPermission->id]);
    $rolePlayer->permissions()->syncWithoutDetaching([$viewPermission->id, $leavePermission->id]);

    // Assignez le rôle global user au master et au user
    $master->roles()->attach($roleMaster);
    $user->roles()->attach($roleUser);

    // Attachez l'utilisateur master au tableau et assignez-lui le rôle master
    $board->users()->attach($master->id, ['role_id' => $roleMaster->id]);
    // Attachez l'utilisateur user au tableau et assignez-lui le rôle player
    $board->users()->attach($user->id, ['role_id' => $rolePlayer->id]);

    // Simulez l'action de suppression du tableau
    $response = $this->actingAs($master)
        ->delete("/api/board/{$board->id}/delete")
        ->assertStatus(200);

    // Vérifiez le contenu de la réponse JSON
    $response->assertJson([
        'response' => [
            'status_title' => 'Success',
            'status_message' => 'The board has been deleted successfully.',
            'status_code' => 200,
        ]
    ]);

    // Vérifiez la structure de la réponse JSON
    $response->assertJsonStructure([
        'response' => [
            'status_title',
            'status_message',
            'status_code',
        ]
    ]);

    // Assurez-vous que le tableau est absent de la base de données
    $this->assertDatabaseMissing('boards', [
        'id' => $board->id
    ]);

    // Assurez-vous que les relations board_user sont absentes de la base de données
    $this->assertDatabaseMissing('board_user', [
        'board_id' => $board->id,
    ]);

    // Assurez-vous que les utilisateurs ne sont pas supprimés de la base de données
    $this->assertDatabaseHas('users', ['id' => $master->id]);
    $this->assertDatabaseHas('users', ['id' => $user->id]);

    // Assurez-vous que les utilisateurs sont supprimés de la table board_user mais existent toujours dans la table users
    $this->assertDatabaseMissing('board_user', ['user_id' => $master->id]);
    $this->assertDatabaseMissing('board_user', ['user_id' => $user->id]);
});

// ------------------------
//  NEGATIVE TEST (CANNOT)
// ------------------------

it('cannot delete à board if have role player', function () {
    // Create one master, one user and one board
    $master = User::factory()->create();
    $user = User::factory()->create();
    $board = Board::factory()->create();

    // Create roles Player & Master
    $roleMaster = Role::factory()->create(['name' => 'master',]);
    $rolePlayer = Role::factory()->create(['name' => 'player',]);

    // Create permission
    $permission = Permission::factory()->create(['name' => 'delete-board']);

    // Attribute role master to $master
    $master->roles()->attach($roleMaster);
    // Attach permission to role Master
    $roleMaster->permissions()->attach($permission);

    // Attribute role player to $user
    $user->roles()->attach($rolePlayer);

    // Attach the user $master to the Board and assign him the role master
    $board->users()->attach($master->id, ['role_id' => $roleMaster->id]);
    // Attach the user $user to the Board and assign him the role player
    $board->users()->attach($user->id, ['role_id' => $rolePlayer->id]);

    // Simulate user login as $user and try to delete the board
    // Return status code 403 (forbidden for $user)
    $response = $this->actingAs($user)
        ->delete("/api/board/{$board->id}/delete")
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

    // Reload the board with its users to ensure the relationship is up-to-date
    $board->refresh();

    // We explicitly check in the database that the board still exists
    $this->assertDatabaseHas('boards', [
        'id' => $board->id,
    ]);

    // We explicitly check in the database that the users are still in the board
    $board->users()->each(function (User $user) use ($board) {
        $this->assertDatabaseHas('board_user', [
            'board_id' => $board->id,
            'user_id' => $user->id,
        ]);
    });

    // Check that there are still two users in the board loaded in relation
    expect($board->users)->toHaveCount(2);
});

it('cannot delete a board if unauthenticated', function () {
    // Create one master and one board
    $master = User::factory()->create();
    $board = Board::factory()->create();

    // Create role Master
    $roleMaster = Role::factory()->create(['name' => 'master',]);
    // Create permission
    $permission = Permission::factory()->create(['name' => 'delete-board']);
    // Attribute role master to $master
    $master->roles()->attach($roleMaster);
    // Attach permission to role Master
    $roleMaster->permissions()->attach($permission);

    // Attach master to the board with role "master"
    $board->users()->attach($master->id, ['role_id' => $roleMaster->id]);

    // Simulate an unauthenticated user trying to delete the board
    $response = $this->delete("/api/board/{$board->id}/delete");

    // We expect a status code 302 (redirect)
    $response->assertStatus(302);

    // We explicitly check in the database that the board still exists
    $this->assertDatabaseHas('boards', [
        'id' => $board->id,
    ]);
});
