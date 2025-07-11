<?php

// Test that the application returns a successful response (redirects to /app)
test('the application returns a successful response', function () {
    $response = $this->get('/');

    $response->assertStatus(302)->assertRedirect('/app');
});

// Test Vue.js application route
test('vue application route works', function () {
    $response = $this->get('/app');
    
    $response->assertStatus(200);
    $response->assertSee('league-simulation'); // Check for Vue component tag instead
});
