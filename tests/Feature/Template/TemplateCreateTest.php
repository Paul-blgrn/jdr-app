<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Template;

// ------------------------
//   POSITIVE TEST (CAN)
// ------------------------

it('can create a template with react-grid-layout', function () {
    // Create one User
    $user = User::factory()->create();

    // Create the template data
    $templateData = Template::factory()->make()->toArray();

    $roleUser = Role::factory()->create(['name' => 'user']);

    $permissionCreateTemplate = Permission::factory()->create(['name' => 'create-template']);

    $roleUser->permissions()->attach($permissionCreateTemplate->id);

    $user->roles()->attach($roleUser->id);

    // Send a POST request to create the template
    $response = $this->actingAs($user)
        ->postJson('/api/templates/add', $templateData)
        ->assertStatus(201);

    // Decode the JSON response
    $finalData = $response->json();

    // Assert the response contains the correct template type
    $this->assertEquals($templateData['type'], $finalData['response']['template']['type']);

    // Assert the response contains the correct template content
    $this->assertEquals($templateData['content'], $finalData['response']['template']['content']);

    // Assert that a template has been created in the database
    expect(Template::count())->toBe(1);

    // Assert that a template has been created in the database
    $this->assertDatabaseHas('templates', [
        'type' => $templateData['type'],
        'content' => $templateData['content'],
    ]);
});

// ------------------------
//  NEGATIVE TEST (CANNOT)
// ------------------------

it('cannot create a template without name', function () {

})->todo();

it('cannot create a template without content', function () {

})->todo();

it('cannot create a template without type', function () {

})->todo();
