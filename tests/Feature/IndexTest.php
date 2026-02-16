<?php

namespace Tests\Feature;

test('the home page returns a successful response', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
