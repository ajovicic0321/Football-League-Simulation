<?php

use App\Models\Team;
use App\Models\Season;
use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create test teams
    $this->teams = [
        Team::create(['name' => 'Manchester City', 'city' => 'Manchester', 'strength' => 85]),
        Team::create(['name' => 'Liverpool FC', 'city' => 'Liverpool', 'strength' => 82]),
    ];
    
    $this->season = Season::create([
        'name' => '2024/25 Test Season',
        'start_date' => now(),
        'status' => 'active',
        'is_current' => true,
    ]);
    
    // Create test games
    Game::create([
        'season_id' => $this->season->id,
        'home_team_id' => $this->teams[0]->id,
        'away_team_id' => $this->teams[1]->id,
        'week' => 1,
        'status' => 'completed',
        'home_goals' => 2,
        'away_goals' => 1,
        'played_at' => now(),
    ]);
    
    Game::create([
        'season_id' => $this->season->id,
        'home_team_id' => $this->teams[1]->id,
        'away_team_id' => $this->teams[0]->id,
        'week' => 2,
        'status' => 'scheduled',
    ]);
});

test('can reset season via API', function () {
    // Ensure we have completed games
    expect($this->season->games()->where('status', 'completed')->count())->toBe(1);
    
    $response = $this->putJson("/api/seasons/{$this->season->id}/reset");
    
    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Season reset successfully'
        ]);
    
    // Verify all games are reset
    $this->season->refresh();
    $allGames = $this->season->games()->get();
    
    foreach ($allGames as $game) {
        expect($game->status)->toBe('scheduled');
        expect($game->home_goals)->toBeNull();
        expect($game->away_goals)->toBeNull();
        expect($game->played_at)->toBeNull();
    }
    
    // Verify season status
    expect($this->season->fresh()->status)->toBe('active');
});

test('reset season returns proper response structure', function () {
    $response = $this->putJson("/api/seasons/{$this->season->id}/reset");
    
    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'data' => [
                'season' => [
                    'id',
                    'name',
                    'status'
                ],
                'statistics',
                'message'
            ],
            'message'
        ]);
});

test('reset season handles non-existent season', function () {
    $response = $this->putJson("/api/seasons/999/reset");
    
    $response->assertStatus(404);
});

test('can get current season', function () {
    $response = $this->getJson('/api/seasons/current');
    
    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'id' => $this->season->id,
                'name' => $this->season->name,
                'is_current' => true
            ]
        ]);
});

test('can get season table', function () {
    $response = $this->getJson("/api/seasons/{$this->season->id}/table");
    
    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'data' => [
                'table' => [
                    '*' => [
                        'position',
                        'team' => [
                            'id',
                            'name'
                        ],
                        'points',
                        'goals_for',
                        'goals_against',
                        'goal_difference'
                    ]
                ]
            ]
        ]);
}); 