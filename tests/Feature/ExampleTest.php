<?php

test('the career portal returns a successful response', function () {
    $response = $this->get('/karier');

    $response->assertStatus(200);
});

test('the login page returns a successful response', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

