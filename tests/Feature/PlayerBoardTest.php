<?php

use App\Models\Board;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\get;
use function Pest\Laravel\withoutExceptionHandling;

it("sent a 404 error when accessing a board that do not exist", function () {
    $user = User::factory()->make();

    actingAs($user)
    ->get("/api/board/1")
    ->assertStatus(404);

    actingAs($user)
    ->get("/api/board/bonjour")
    ->assertStatus(404);
});

it("can join a board with right code", function () {
    $user = User::factory()->create();
    $board = Board::factory()->create();

    $this->actingAs($user)
        ->post("/api/boards/join",
            [
                "code" => $board->code,
            ])
        ->assertStatus(201);

    // Vérifier que l'utilisateur a bien été ajouté au board
    $this->assertDatabaseHas("board_user", [
        "board_id"=> $board->id,
        "user_id" => $user->id,
    ]);

    expect($user->boards)->toHaveCount(1);
    expect($user->boards->contains($board))->toBeTrue();
});

it("cannot join boards with wrong or empty invite code", function (string $code) {
    // Créer un utilisateur
    $user = User::factory()->create();

    // Simuler la connexion de l'utilisateur et envoyer un code
    $response = $this->actingAs($user)
        ->post("/api/boards/join",
            [
                'code' => $code,
            ]);

    // Vérifier le statut de la réponse pour indiquer une erreur de validation
    $response->assertStatus(422);

    // Recharger l'utilisateur pour s'assurer que les relations sont mises à jour
    $user->refresh();

    // Vérifier que l'utilisateur n'a pas rejoint de boards
    expect($user->boards)->toHaveCount(0);

})->with(["12345", "bonjour", ""]);

it('cannot join a full board', function () {
    // Créer 1 utilisateur (master)
    $user = User::factory()->create();
    // Créer 3 utilisateurs (players)
    $users = User::factory(3)->create();
    // Créer un autre utilisateur
    $userToJoin = User::factory()->create();

    // Créer un Board avec une capacité de 4
    $board = Board::factory()->create([
        'name' => 'table pleine',
        'description' => 'la table est pleine et doit exclure toute personne qui essaye de la rejoindre',
        'code' => 'fulltable',
        'capacity' => 4,
    ]);

    // Attacher les utilisateurs a la Board avec leur rôles
    $board->users()->attach($user, ['role'=> 'master']);
    $board->users()->attach($users, ['role'=> 'player']);

    // Simuler la tentative de rejoindre le board par l'utilisateur
    $this->actingAs($userToJoin)
        ->post("/api/boards/join",
            [
                "code" => $board->code,
            ])
        ->assertStatus(403);

    // Rafraîchir le modèle du board
    $board->refresh();

    // Vérifier le nombre d'utilisateurs sur le board n'a pas changé
    expect($board->users)->toHaveCount(4);

    // Vérifier que l'utilisateur qui a essayé de rejoindre n'est pas dans la table pivot
    $this->assertDatabaseMissing('board_user', [
        'board_id' => $board->id,
        'user_id' => $userToJoin->id,
    ]);

    // Vérifier les rôles des utilisateurs sur le board
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
    // Créer un utilisateur et lui associer 3 Boards
    $user = User::factory()->hasAttached(Board::factory(3))->create();

    // On simule la connexion de $user et on s'assure de la réponse 200 sur "/api/boards"
    $response = $this
        ->actingAs($user)
        ->get('/api/boards')
        ->assertStatus(200);

    // Récupérer le contenu de la réponse en JSON
    $data = $response->json();

    // Vérifier que la réponse n'est pas vide
    expect($data)->not->toBeEmpty();

    // Vérifier que $data contient le bon nombre de boards
    expect($data)->toHaveCount($user->boards->count());

    $pluckedData = collect($data)->pluck('id')->toArray();
    $pluckedUser = $user->boards->pluck('id')->toArray();
    // Vérifier les IDS
    expect($pluckedData)->toEqual($pluckedUser);

    // Vérifier que la réponse contient les informations exactes
    $user->boards->each(function (Board $board) use ($data) {
        // Récupérer le premier élément de $data
        $boardData = collect($data)->firstWhere('id', $board->id);

        // Vérifier que les détails du board sont corrects
        expect($boardData['name'])->toBe($board->name);
        expect($boardData['description'])->toBe($board->description);
        expect($boardData['capacity'])->toBe($board->capacity);

        // Vérifier qu'il n'y a qu'un seul utilisateur
        expect($boardData['users_count'])->toEqual(1);
    });
});

it('displays the information of all players associated with a board', function () {
    $users = User::factory(3)->create();
    $board = Board::factory()->create();

    $board->users()->attach($users[0]->id, ['role' => 'master']);
    $board->users()->attach([$users[1]->id, $users[2]->id], ['role' => 'player']);

    $response = $this->actingAs($board->users->first())
        ->get('/api/board/'. $board->id)
        ->assertStatus(200);

    $data = $response->json();

    // Vérifier que $data n'est pas vide
    expect($data)->not->toBeEmpty();


    // Vérifier que $data contient le bon nombre de users
    expect($data['users'])->toHaveCount(3);

    $pluckedData = collect($data['users'])->pluck('id')->toArray();
    $pluckedUser = $board->users->pluck('id')->toArray();
    // Vérifier les IDS
    expect($pluckedData)->toEqual($pluckedUser);

    $userRoles = collect($data['users'])->mapWithKeys(function ($user) {
        return [$user['id'] => $user['pivot']['role']];
    });

    expect($userRoles[$users[0]->id])->toBe('master');
    expect($userRoles[$users[1]->id])->toBe('player');
    expect($userRoles[$users[2]->id])->toBe('player');

    foreach ($data['users'] as $userData) {
        $originalUser = $users->firstWhere('id', $userData['id']);
        expect($userData['name'])->toBe($originalUser->name);
    }
});

it("displays a board with details", function () {

    $user = User::factory()->create();
    $board = Board::factory()->hasAttached($user)->create();

    $response = $this->actingAs($user)
        ->get("/api/board/". $board->id)
        ->assertStatus(200);

    $data = $response->json();

    // Vérifier que la réponse n'est pas vide
    expect($data)->not->toBeEmpty();

    // Vérifier que $data contient le bon nombre de users
    expect($data['users'])->toHaveCount(1);
    // dd($data);

    $pluckedData = collect($data['users'])->pluck('id')->toArray();
    $pluckedUser = $user->boards->pluck('id')->toArray();
    // Vérifier les IDS
    expect($pluckedData)->toEqual($pluckedUser);

    // Vérifier que les détails du board sont corrects
    expect($data['name'])->toEqual($board->name);
    expect($data['description'])->toEqual($board->description);
    expect($data['capacity'])->toEqual($board->capacity);
    //dd($data['users'][0]['name']);
    foreach ($data['users'] as $userData) {
        $originalUsers = $user->firstWhere('id', $userData['id']);
        expect($userData['name'])->toBe($originalUsers->name);
    }

    //expect($data['users'][0]['name'])->toBe($board->users->first()->name);
});

it("can leave a board successfully", function () {
    // Créer 3 utilisateurs
    $users = User::factory(3)->create();
    // Créer un Board et attacher les utilisateurs au board
    $board = Board::factory()->create();
    $board->users()->attach($users);

    // Utilisateur qui va quitter le board
    $userToLeave = $users->first();

    // Effectuer la requête pour détacher l'utilisateur du board
    $this->actingAs($userToLeave)
        ->post("/api/board/leave/{$board->id}")
        ->assertStatus(200);

    // Vérifier explicitement que l'utilisateur a bien été détaché du Board
    $this->assertDatabaseMissing('board_user', [
        'board_id' => $board->id,
        'user_id' => $userToLeave->id,
    ]);

    // Vérifier que les autres utilisateurs sont toujours attachés
    // a la board
    $users->skip(1)->each(function (User $user) use ($board) {
        $this->assertDatabaseHas('board_user', [
            'board_id'=> $board->id,
            'user_id'=> $user->id,
        ]);
    });

    // Vérifier que le Board à le bon nombre de users
    expect($board->users)->toHaveCount(2);
});

it('can leave a board successfully if other users remain', function () {
    // Créer un utilisateur
    $user = User::factory()->create();
    // Créer un autre utilisateur
    $anotherUser = User::factory()->create();

    // Créer un Board et attacher cet utilisateur
    $board = Board::factory()->create();
    $board->users()->attach([$user->id, $anotherUser->id]);

    // Simuler la tentative de quitter le board par l'utilisateur
    $this->actingAs($user)
        ->post('/api/board/leave/' . $board->id)
        ->assertStatus(200);

    // Rafraîchir le modèle du board
    $board->refresh();

    // Vérifier que l'utilisateur $user n'est plus présent
    // dans la board
    expect($board->users)->not()->toContain($user);

    // Vérifier explicitement dans la base de donnée
    // que l'utilisateur $anotherUser est encore présent
    $this->assertDatabaseHas('board_user', [
        'board_id' => $board->id,
        'user_id' => $anotherUser->id,
    ]);

    // Compter le nombre d'utilisateurs dans la board
    // nous attendons un seul utilisateur dans ce cas
    expect($board->users)->toHaveCount(1);
});

it('cannot leave a board if it will be empty', function () {
    // Créer un utilisateur
    $user = User::factory()->create();

    // Créer un Board et attacher cet utilisateur
    $board = Board::factory()->create();
    $board->users()->attach($user->id);

    // Utilisateur qui essaie de quitter le board et échoue car cela le rendrait vide
    $this->actingAs($user)
        ->post("/api/board/leave/{$board->id}")
        ->assertStatus(403);

    // Rafraîchir le modèle du board
    $board->refresh();

    // Récupérer les IDS des utilisateurs de la board et
    // Vérifier que l'autre utilisateur est toujours dans le board
    $pluckedBoard = $board->users->pluck('id')->toArray();
    expect($pluckedBoard)->toContain($user->id);

    // Vérifier explicitement dans la base de données que
    // $anotherUser est encore présent
    $this->assertDatabaseHas('board_user', [
        'board_id' => $board->id,
        'user_id' => $user->id,
    ]);
});

it('returns 404 if board that user try to leave does not exist', function () {
    $user = User::factory()->create();
    $invalidBoardId = 9999;

    // Utilisateur qui essaie de quitter un board inexistant
    $this->actingAs($user)
        ->post("/api/board/leave/{$invalidBoardId}")
        ->assertStatus(404);
});

it('cannot leave a board if user is not a member', function () {
    $user = User::factory()->create();
    $anotherUser = User::factory()->create();
    $board = Board::factory()->create();

    // Attacher un seul utilisateur au board
    $board->users()->attach($user->id);

    // L'autre utilisateur qui essaie de quitter le board auquel il n'appartient pas
    $this->actingAs($anotherUser)
        ->post("/api/board/leave/{$board->id}")
        ->assertStatus(403);
});

it("cannot leave a board if user have role master", function () {
    // créer deux utilisateurs et une board
    $userToLeave = User::factory()->create();
    $anotherUser = User::factory()->create();
    $board = Board::factory()->create();

    // Attacher les utilisateurs à la Board
    // Attribuer le role master à $user et le role player à $anotherUser
    $board->users()->attach($userToLeave->id, ["role" => "master"]);
    $board->users()->attach($anotherUser->id, ["role" => "player"]);

    $this->actingAs($userToLeave)
        ->post("/api/board/leave/{$board->id}")
        ->assertStatus(403);

    // On vérifie explicitement en base de donnée que
    // userToLeave soit encore sur sa Board
    $this->assertDatabaseHas('board_user', [
        'board_id' => $board->id,
        'user_id' => $userToLeave->id,
    ]);

    // On attend qu'il reste 2 utilisateurs
    // car le role "master" ne peut pas quitter son board
    expect($board->users)->toHaveCount(2);
});
