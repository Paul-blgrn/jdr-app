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
    $board = Board::factory()->hasAttached(User::factory(3))->create();

    $response = $this->actingAs($board->users->first())
        ->get('/api/board/'. $board->id)
        ->assertStatus(200);

    $data = $response->json();
    //dd($data);

    // Vérifier que $data n'est pas vide
    expect($data)->not->toBeEmpty();

    // Vérifier que $data contient le bon nombre de users
    //dd($data['users']);
    expect($data['users'])->toHaveCount($board->users->count());

    // $board->users()->each(function (User $user) use ($data) {
    //     $users = $data[0]['users'];
    //     dd($users);

    // });
    // expect($data[0]['users'][0]['name'])->toBe($board->users->first()->name);
})->todo();

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
    expect($data[0]['users'])->toHaveCount($board->users->count());

    $pluckedData = collect($data)->pluck('id')->toArray();
    $pluckedUser = $user->boards->pluck('id')->toArray();
    // Vérifier les IDS
    expect($pluckedData)->toEqual($pluckedUser);

    // Vérifier que les détails du board sont corrects
    expect($data[0]['name'])->toEqual($board->name);
    expect($data[0]['description'])->toEqual($board->description);
    expect($data[0]['capacity'])->toEqual($board->capacity);
    expect($data[0]['users'][0]['name'])->toBe($board->users->first()->name);
})->todo();

it("can leave a board", function () {
    // Créer 3 utilisateurs
    $users = User::factory(3)->create();
    // Créer un Board et attacher les utilisateurs au board
    $board = Board::factory()->create();

    // $board->users()->attach($users[0], ['role' => 'master']);
    // $board->users()->attach([$users[1], $users[2]], ['role' => 'player']);
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
})->todo();

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
})->todo();

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
})->todo();

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
})->todo();

it("cannot leave a board if user have role master", function () {
    // withoutExceptionHandling();

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
});
