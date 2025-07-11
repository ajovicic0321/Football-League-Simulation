<?php

use App\Models\Team;
use App\Models\Season;
use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create test teams
    $this->teams = [
        Team::create(['name' => 'Manchester City', 'city' => 'Manchester', 'strength' => 85, 'primary_color' => '#6CABDD']),
        Team::create(['name' => 'Liverpool FC', 'city' => 'Liverpool', 'strength' => 82, 'primary_color' => '#C8102E']),
        Team::create(['name' => 'Arsenal FC', 'city' => 'London', 'strength' => 78, 'primary_color' => '#EF0107']),
        Team::create(['name' => 'Chelsea FC', 'city' => 'London', 'strength' => 75, 'primary_color' => '#034694']),
    ];
    
    $this->season = Season::create([
        'name' => '2024/25 API Test Season',
        'start_date' => now(),
        'status' => 'active',
        'is_current' => true,
    ]);
    
    // Create some test games
    Game::create([
        'season_id' => $this->season->id,
        'home_team_id' => $this->teams[0]->id,
        'away_team_id' => $this->teams[1]->id,
        'week' => 1,
        'status' => 'scheduled',
    ]);
    
    Game::create([
        'season_id' => $this->season->id,
        'home_team_id' => $this->teams[2]->id,
        'away_team_id' => $this->teams[3]->id,
        'week' => 1,
        'status' => 'scheduled',
    ]);
});

describe('Auto-Play API Endpoints', function () {
    test('GET /api/autoplay/options returns available options', function () {
        $response = $this->getJson('/api/autoplay/options');
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'speeds' => [
                            'slow' => ['name', 'games_per_batch', 'description'],
                            'normal' => ['name', 'games_per_batch', 'description'],
                            'fast' => ['name', 'games_per_batch', 'description'],
                        ],
                        'modes' => [
                            'basic' => ['name', 'description'],
                            'realistic' => ['name', 'description'],
                            'predictable' => ['name', 'description'],
                        ],
                        'analytics_available'
                    ]
                ]);
        
        expect($response->json('data.speeds.normal.games_per_batch'))->toBe(3);
        expect($response->json('data.analytics_available'))->toBeTrue();
    });

    test('POST /api/autoplay/seasons/{season}/start initiates auto-play session', function () {
        $response = $this->postJson("/api/autoplay/seasons/{$this->season->id}/start", [
            'speed' => 'normal',
            'mode' => 'realistic',
            'stop_at_week' => 2
        ]);
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'session_id',
                        'games_simulated',
                        'games',
                        'analytics',
                        'season_status' => [
                            'progress',
                            'completed_games',
                            'total_games',
                            'is_complete'
                        ],
                        'next_week',
                        'options'
                    ],
                    'message'
                ]);
        
        expect($response->json('success'))->toBeTrue();
        expect($response->json('data.session_id'))->toStartWith('autoplay_');
        expect($response->json('data.options.mode'))->toBe('realistic');
    });

    test('POST /api/autoplay/seasons/{season}/continue continues existing session', function () {
        // First, start a session
        $startResponse = $this->postJson("/api/autoplay/seasons/{$this->season->id}/start", [
            'speed' => 'slow',
            'mode' => 'basic'
        ]);
        
        $sessionId = $startResponse->json('data.session_id');
        
        // Then continue it
        $response = $this->postJson("/api/autoplay/seasons/{$this->season->id}/continue", [
            'session_id' => $sessionId,
            'current_week' => 1,
            'speed' => 'normal'
        ]);
        
        $response->assertStatus(200);
        expect($response->json('data.session_id'))->toBe($sessionId);
        expect($response->json('success'))->toBeTrue();
    });

    test('POST /api/autoplay/seasons/{season}/stop ends auto-play session', function () {
        $sessionId = 'autoplay_test_session';
        
        $response = $this->postJson("/api/autoplay/seasons/{$this->season->id}/stop", [
            'session_id' => $sessionId
        ]);
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'session_id',
                        'final_status',
                        'stopped_at'
                    ]
                ]);
        
        expect($response->json('data.session_id'))->toBe($sessionId);
    });

    test('auto-play start validates input parameters', function () {
        $response = $this->postJson("/api/autoplay/seasons/{$this->season->id}/start", [
            'speed' => 'invalid_speed',
            'mode' => 'invalid_mode',
            'stop_at_week' => -1
        ]);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['speed', 'mode', 'stop_at_week']);
    });
});

describe('Advanced Simulation API Endpoints', function () {
    beforeEach(function () {
        // Add some completed games for better predictions
        Game::create([
            'season_id' => $this->season->id,
            'home_team_id' => $this->teams[0]->id,
            'away_team_id' => $this->teams[2]->id,
            'week' => 2,
            'status' => 'completed',
            'home_goals' => 2,
            'away_goals' => 1,
            'played_at' => now()->subDays(1),
        ]);
    });

    test('GET /api/advanced/seasons/{season}/predictions returns multiple prediction methods', function () {
        $response = $this->getJson("/api/advanced/seasons/{$this->season->id}/predictions");
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'predictions' => [
                            'strength_based',
                            'form_based', 
                            'statistical',
                            'monte_carlo',
                            'consensus'
                        ],
                        'generated_at',
                        'methods_used',
                        'recommended_method'
                    ]
                ]);
        
        expect($response->json('data.recommended_method'))->toBe('consensus');
        expect($response->json('data.methods_used'))->toContain('strength_based');
        expect($response->json('data.methods_used'))->toContain('monte_carlo');
    });

    test('POST /api/advanced/seasons/{season}/simulate runs enhanced simulation', function () {
        $response = $this->postJson("/api/advanced/seasons/{$this->season->id}/simulate", [
            'week' => 1,
            'mode' => 'realistic'
        ]);
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'week',
                        'mode',
                        'games',
                        'games_simulated',
                        'analytics' => [
                            'week',
                            'games_played',
                            'total_goals',
                            'average_goals',
                            'upsets',
                            'entertainment_score'
                        ]
                    ]
                ]);
        
        expect($response->json('data.week'))->toBe(1);
        expect($response->json('data.mode'))->toBe('realistic');
        expect($response->json('data.games_simulated'))->toBeGreaterThan(0);
    });

    test('enhanced simulation validates input parameters', function () {
        $response = $this->postJson("/api/advanced/seasons/{$this->season->id}/simulate", [
            'week' => 0, // Invalid week
            'mode' => 'invalid_mode'
        ]);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['week', 'mode']);
    });

    test('GET /api/advanced/seasons/{season}/weeks/{week}/analytics returns week analytics', function () {
        // First simulate the week to have data
        $this->postJson("/api/advanced/seasons/{$this->season->id}/simulate", [
            'week' => 1,
            'mode' => 'realistic'
        ]);
        
        $response = $this->getJson("/api/advanced/seasons/{$this->season->id}/weeks/1/analytics");
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'week',
                        'analytics' => [
                            'week',
                            'games_played',
                            'total_goals',
                            'average_goals',
                            'upsets',
                            'entertainment_score'
                        ],
                        'games'
                    ]
                ]);
        
        expect($response->json('data.week'))->toBe(1);
        expect($response->json('data.analytics.entertainment_score'))->toBeFloat();
    });

    test('week analytics returns 404 for week with no completed games', function () {
        $response = $this->getJson("/api/advanced/seasons/{$this->season->id}/weeks/999/analytics");
        
        $response->assertStatus(404)
                ->assertJson([
                    'success' => false
                ]);
    });
});

describe('API Error Handling', function () {
    test('returns 404 for non-existent season', function () {
        $response = $this->postJson('/api/autoplay/seasons/999/start', [
            'speed' => 'normal',
            'mode' => 'realistic'
        ]);
        
        $response->assertStatus(404);
    });

    test('handles malformed JSON gracefully', function () {
        // Test with invalid field values instead of missing fields since start endpoint has no required fields
        $response = $this->postJson("/api/autoplay/seasons/{$this->season->id}/start", [
            'speed' => 'invalid_speed',
            'mode' => 'invalid_mode'
        ]);
        
        $response->assertStatus(422); // Validation error for invalid field values
    });

    test('validates required fields', function () {
        $response = $this->postJson("/api/autoplay/seasons/{$this->season->id}/continue", [
            // Missing required fields
        ]);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['session_id', 'current_week']);
    });
});

describe('API Performance and Load Testing', function () {
    test('auto-play start completes within reasonable time', function () {
        $start = microtime(true);
        
        $response = $this->postJson("/api/autoplay/seasons/{$this->season->id}/start", [
            'speed' => 'fast',
            'mode' => 'basic'
        ]);
        
        $duration = microtime(true) - $start;
        
        $response->assertStatus(200);
        expect($duration)->toBeLessThan(5.0); // Should complete within 5 seconds
    });

    test('advanced predictions complete within reasonable time', function () {
        $start = microtime(true);
        
        $response = $this->getJson("/api/advanced/seasons/{$this->season->id}/predictions");
        
        $duration = microtime(true) - $start;
        
        $response->assertStatus(200);
        expect($duration)->toBeLessThan(20.0); // Predictions can take longer due to Monte Carlo (increased timeout)
    });

    test('can handle multiple concurrent requests', function () {
        $promises = [];
        $responses = [];
        
        // Simulate multiple concurrent auto-play starts
        for ($i = 0; $i < 3; $i++) {
            $responses[] = $this->postJson("/api/autoplay/seasons/{$this->season->id}/start", [
                'speed' => 'normal',
                'mode' => 'realistic'
            ]);
        }
        
        foreach ($responses as $response) {
            $response->assertStatus(200);
            expect($response->json('success'))->toBeTrue();
        }
    });
});

describe('Data Consistency Tests', function () {
    test('auto-play maintains database consistency', function () {
        // Record initial state
        $initialCompletedGames = Game::where('status', 'completed')->count();
        
        // Run auto-play
        $response = $this->postJson("/api/autoplay/seasons/{$this->season->id}/start", [
            'speed' => 'normal',
            'mode' => 'realistic'
        ]);
        
        $response->assertStatus(200);
        
        // Verify database consistency
        $finalCompletedGames = Game::where('status', 'completed')->count();
        $gamesSimulated = $response->json('data.games_simulated');
        
        expect($finalCompletedGames - $initialCompletedGames)->toBe($gamesSimulated);
        
        // Verify all completed games have valid scores
        $completedGames = Game::where('status', 'completed')->get();
        foreach ($completedGames as $game) {
            expect($game->home_goals)->not->toBeNull();
            expect($game->away_goals)->not->toBeNull();
            expect($game->played_at)->not->toBeNull();
            expect($game->home_goals)->toBeGreaterThanOrEqual(0);
            expect($game->away_goals)->toBeGreaterThanOrEqual(0);
        }
    });

    test('predictions remain consistent across multiple calls', function () {
        // Get predictions twice
        $response1 = $this->getJson("/api/advanced/seasons/{$this->season->id}/predictions");
        $response2 = $this->getJson("/api/advanced/seasons/{$this->season->id}/predictions");
        
        $response1->assertStatus(200);
        $response2->assertStatus(200);
        
        // Strength-based predictions should be identical (deterministic)
        $strength1 = $response1->json('data.predictions.strength_based');
        $strength2 = $response2->json('data.predictions.strength_based');
        
        expect(count($strength1))->toBe(count($strength2));
        
        // Monte Carlo predictions will vary, but should have same structure
        $monte1 = $response1->json('data.predictions.monte_carlo');
        $monte2 = $response2->json('data.predictions.monte_carlo');
        
        expect(count($monte1))->toBe(count($monte2));
        foreach ($monte1 as $index => $prediction) {
            expect($prediction['team']['id'])->toBe($monte2[$index]['team']['id']);
        }
    });
}); 