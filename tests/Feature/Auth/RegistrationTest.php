<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

test('new users can register', function () {
    Role::factory()->create(['name' => 'user']);
    $response = $this->post('/auth/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'Str0ngP@ssw0rd!',
        'password_confirmation' => 'Str0ngP@ssw0rd!',
    ]);

    $this->assertAuthenticated();
    $response->assertStatus(200);
});

it('fails to register with a weak password', function () {
    $response = $this->postJson('/auth/register', [
        'name' => 'Test User',
        'email' => 'testuser@example.com',
        'password' => 'weakpass',
        'password_confirmation' => 'weakpass',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

it('registers a user with a strong password', function () {
    Role::factory()->create(['name' => 'user']);
    $response = $this->postJson('/auth/register', [
        'name' => 'Test User',
        'email' => 'testuser@example.com',
        'password' => 'Str0ngP@ssw0rd!',
        'password_confirmation' => 'Str0ngP@ssw0rd!',
    ]);

    $response->assertStatus(200);

    $response->assertJsonStructure([
        'message',
        'user' => [
            'id',
            'name',
            'email',
        ],
        'token',
    ]);
});


it('registers a user and assign the user a role with a token', function () {
    // Fake events to prevent the actual event from firing
    Event::fake();

    Role::factory()->create(['name' => 'user']);

    $response = $this->post('/auth/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'Str0ngP@ssw0rd!',
        'password_confirmation' => 'Str0ngP@ssw0rd!',
    ]);

    // Assert that the response is successful and check its structure
    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'user' => [
                'id',
                'name',
                'email',
            ],
            'token',
        ]);

    // Get the user that was just created
    $user = User::where('email', 'test@example.com')->first();

    expect($user)->not->toBeNull();

    Event::assertDispatched(Registered::class);

    // Assert that the user was assigned the "user" role
    expect($user->roles->contains('name', 'user'))->toBeTrue();

    // Assert that the token was created
    $token = $user->tokens()->where('name', 'auth_token')->first();
    expect($token)->not->toBeNull();

    // Assert that the token is valid
    $responseToken = $response->json('token');
    Sanctum::actingAs($user, [], 'web');
    $this->withHeader('Authorization', "Bearer $responseToken")
        ->get('/api/boards')
        ->assertStatus(200);
});

