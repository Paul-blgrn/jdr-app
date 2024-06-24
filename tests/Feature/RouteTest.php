<?php

it('show homepage', function () {
    $this->get('/')
    ->assertStatus(200);
});

it('sent a 302 error when guests try to see boards', function () {
    $this->get('/api/boards')
    ->assertStatus(302);
});
