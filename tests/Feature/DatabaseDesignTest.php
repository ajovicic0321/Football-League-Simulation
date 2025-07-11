<?php

use App\Models\Team;
use App\Models\Season;
use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Test that teams can be created and retrieved
test('teams can be created and retrieved', function () {
    $team = Team::create([
        'name' => 'Test FC',
        'city' => 'Test City',
        'strength' => 75,
        'primary_color' => '#FF0000',
        'secondary_color' => '#FFFFFF',
        'is_active' => true, // Explicitly set for now
    ]);

    expect($team->name)->toBe('Test FC');
    expect($team->strength)->toBe(75);
    expect($team->is_active)->toBeTrue();
});

// Test that seasons can be created
test('seasons can be created and set as current', function () {
    $season = Season::create([
        'name' => 'Test Season',
        'start_date' => now()->subDays(10),
        'status' => 'active',
        'is_current' => true,
    ]);

    expect($season->name)->toBe('Test Season');
    expect($season->is_current)->toBeTrue();
    expect($season->status)->toBe('active');
});

// Test that games can be created with team relationships
test('games can be created with team relationships', function () {
    $homeTeam = Team::create([
        'name' => 'Home Team',
        'city' => 'Home City',
        'strength' => 80,
    ]);

    $awayTeam = Team::create([
        'name' => 'Away Team',
        'city' => 'Away City',
        'strength' => 75,
    ]);

    $season = Season::create([
        'name' => 'Test Season',
        'start_date' => now(),
        'status' => 'active',
        'is_current' => true,
    ]);

    $game = Game::create([
        'season_id' => $season->id,
        'home_team_id' => $homeTeam->id,
        'away_team_id' => $awayTeam->id,
        'week' => 1,
        'status' => 'scheduled',
    ]);

    expect($game->homeTeam->name)->toBe('Home Team');
    expect($game->awayTeam->name)->toBe('Away Team');
    expect($game->season->name)->toBe('Test Season');
    expect($game->status)->toBe('scheduled');
});

// Test game completion and result calculation
test('games can be completed and results calculated', function () {
    $homeTeam = Team::create([
        'name' => 'Home Team 2',
        'city' => 'Home City',
        'strength' => 80,
    ]);

    $awayTeam = Team::create([
        'name' => 'Away Team 2',
        'city' => 'Away City',
        'strength' => 75,
    ]);

    $season = Season::create([
        'name' => 'Test Season',
        'start_date' => now(),
        'status' => 'active',
        'is_current' => true,
    ]);

    $game = Game::create([
        'season_id' => $season->id,
        'home_team_id' => $homeTeam->id,
        'away_team_id' => $awayTeam->id,
        'week' => 1,
        'status' => 'scheduled',
    ]);

    // Complete the game
    $game->completeGame(2, 1);

    expect($game->fresh()->isCompleted())->toBeTrue();
    expect($game->fresh()->home_goals)->toBe(2);
    expect($game->fresh()->away_goals)->toBe(1);
    expect($game->fresh()->getResultString())->toBe('2-1');
    expect($game->fresh()->getWinner()->id)->toBe($homeTeam->id);
});

// Test team statistics calculation
test('team statistics are calculated correctly', function () {
    $team1 = Team::create([
        'name' => 'Team 1',
        'city' => 'City 1',
        'strength' => 80,
    ]);

    $team2 = Team::create([
        'name' => 'Team 2',
        'city' => 'City 2',
        'strength' => 75,
    ]);

    $season = Season::create([
        'name' => 'Test Season',
        'start_date' => now(),
        'status' => 'active',
        'is_current' => true,
    ]);

    // Create and complete some games
    $game1 = Game::create([
        'season_id' => $season->id,
        'home_team_id' => $team1->id,
        'away_team_id' => $team2->id,
        'week' => 1,
    ]);
    $game1->completeGame(3, 1); // Team 1 wins

    $game2 = Game::create([
        'season_id' => $season->id,
        'home_team_id' => $team2->id,
        'away_team_id' => $team1->id,
        'week' => 2,
    ]);
    $game2->completeGame(1, 1); // Draw

    // Check Team 1 stats
    $stats = $team1->getStatsForSeason($season->id);
    expect($stats['played'])->toBe(2);
    expect($stats['won'])->toBe(1);
    expect($stats['drawn'])->toBe(1);
    expect($stats['lost'])->toBe(0);
    expect($stats['goals_for'])->toBe(4);
    expect($stats['goals_against'])->toBe(2);
    expect($stats['goal_difference'])->toBe(2);
    expect($stats['points'])->toBe(4); // 3 for win + 1 for draw
});

// Test league standings calculation
test('league standings are calculated correctly', function () {
    // Create test data instead of relying on seeded data
    $team1 = Team::create(['name' => 'Team A', 'city' => 'City A', 'strength' => 80]);
    $team2 = Team::create(['name' => 'Team B', 'city' => 'City B', 'strength' => 75]);
    $team3 = Team::create(['name' => 'Team C', 'city' => 'City C', 'strength' => 70]);
    $team4 = Team::create(['name' => 'Team D', 'city' => 'City D', 'strength' => 65]);

    $season = Season::create([
        'name' => 'Test Season Standings',
        'start_date' => now(),
        'status' => 'active',
        'is_current' => true,
    ]);

    $standings = $season->getStandings();
    expect($standings)->toBeArray();
    expect(count($standings))->toBe(4);

    // Check that standings have required fields
    foreach ($standings as $standing) {
        expect($standing)->toHaveKeys([
            'team', 'position', 'played', 'won', 'drawn', 'lost',
            'goals_for', 'goals_against', 'goal_difference', 'points'
        ]);
    }
}); 