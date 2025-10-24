<?php

it('show homepage', function () {
    $this->get('/')
    ->assertStatus(200);
});

it('show login page', function () {
    $this->post('/auth/login')
    ->assertStatus(302);
});

it('sent a 302 error when guests try to see boards', function () {
    $this->get('/api/boards')
    ->assertStatus(302);
});

it('sent a 302 error when guests try to see templates', function () {
    $this->get('/api/templates')
    ->assertStatus(302);
});

