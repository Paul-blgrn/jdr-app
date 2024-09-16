<?php
use App\Models\User;
use App\Models\Template;

it('can edit template with react-grid-layout', function () {
    $user = User::factory()->create();

    $template = Template::factory()->create();
    $updateData = Template::factory()->make()->toArray();

    $response = $this->actingAs($user)
        ->putJson("/api/templates/{$template->id}", $updateData)
        ->assertStatus(200)
        ->assertJsonPath('data.type', $updateData['type']);

    expect($template->fresh()->content)->toBe($updateData['content']);
})->todo();

