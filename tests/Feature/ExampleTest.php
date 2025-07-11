<?php

// Test that the application returns a successful response
test('the application returns a successful response', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});

// Test Vue.js application route
test('vue application route works', function () {
    $response = $this->get('/app');
    
    $response->assertStatus(200);
    $response->assertSee('Football League Simulation');
});
