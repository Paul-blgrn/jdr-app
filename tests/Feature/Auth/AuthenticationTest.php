<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\withoutExceptionHandling;

test('users can authenticate using the login screen', function () {
    withoutExceptionHandling();
    $user = User::factory()->create();

    $response = $this->post('/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();

    $response->assertStatus(200);

    $response->assertJsonStructure([
        'message',
        'user' => [
            'id',
            'name',
            'email',
        ],
    ]);
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/auth/logout');

    $this->assertGuest();
    $response->assertStatus(200);

    $response->assertJson([
        'message' => 'Logged out successfully.',
    ]);
});

it('create an authentication token when login', function () {
    // create one test user
    $user = User::factory()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => Hash::make('Str0ngP@ssw0rd!'),
    ]);

    // Loggin in as Test user
    $response = $this->postJson('/auth/login', [
        'email' => 'test@example.com',
        'password' => 'Str0ngP@ssw0rd!',
    ]);

    // Check status and structure in the response
    $response->assertStatus(200)
        ->assertJsonStructure([
            'user' => [
                'id',
                'name',
                'email',
            ],
            'token',
        ]);

    // Check if token ha been generated
    $this->assertNotNull($response->json('token'));
});

it('deletes the current token on logout', function () {
    // create one test user
    $user = User::factory()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => Hash::make('Str0ngP@ssw0rd!'),
    ]);

    // create a token for the user
    $user->createToken('auth_token')->plainTextToken;

    // Check if the token is created
    $this->assertNotEmpty($user->tokens);

    // Disconnect the user
    $response = $this->actingAs($user)->postJson('/auth/logout');

    // Check if token is deleted
    $this->assertEmpty($user->fresh()->tokens);

    $response->assertStatus(200);
    $response->assertJson([
        'message' => 'Logged out successfully.',
    ]);
});

it('deletes all user tokens on logout', function () {
    // create one test user
    $user = User::factory()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => Hash::make('password123'),
    ]);

    // Generate multiple auth tokens
    $user->createToken('auth_token_1')->plainTextToken;
    $user->createToken('auth_token_2')->plainTextToken;
    $user->createToken('auth_token_3')->plainTextToken;

    // Check that tokens is created
    $this->assertCount(3, $user->tokens);

    // Disconnect the user
    $response = $this->actingAs($user)->postJson('/auth/logout');

    // Check if tokens are deleted
    $this->assertEmpty($user->fresh()->tokens);

    $response->assertStatus(200);
    $response->assertJson([
        'message' => 'Logged out successfully.',
    ]);
});

it('can access with a valid token', function () {
    // Create a user
    $user = User::factory()->create();

    // Generate a token for the user
    $token = $user->createToken('Test Token')->plainTextToken;

    // Make a request to an API route that requires authentication
    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $token,
    ])->getJson('/api/user');

    // Check if the request was successful
    $response->assertStatus(200);
});

it('validates a valid token', function () {
    withoutExceptionHandling();
    $user = User::factory()->create();
    $token = $user->createToken('auth_token')->plainTextToken;

    $this->actingAs($user);

    $response = $this->withHeaders([
        'Authorization' => "Bearer $token",
    ])->get('/auth/check');

    $response->assertStatus(200);
});

it('rejects an invalid token', function () {
    $user = User::factory()->create();
    $invalidToken = 'invalid_token';

    $this->actingAs($user);

    $response = $this->withHeaders([
        'Authorization' => "Bearer $invalidToken",
    ])->get('/auth/check');

    $response->assertStatus(401);
});

it('cannot have token if unauthenticated', function () {
    $request = Request::create('/', 'GET');
    $response = (new AuthenticatedSessionController)->checkAuth($request);

    expect($response->getContent())->toEqual(json_encode(['authenticated' => false]));
    expect($response->getStatusCode())->toEqual(200);
});

