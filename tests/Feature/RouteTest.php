<?php

test('example', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});

it('show homepage', function () {
    $this->get('/')
    ->assertStatus(200);
});

it('show login page', function () {
    $this->get('/login')
    ->assertStatus(200);
});

it('show user tables', function () {
    $this->get('/tables')
    ->assertStatus(200);
});
