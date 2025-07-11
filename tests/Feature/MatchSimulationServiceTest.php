<?php

use App\Models\Team;
use App\Models\Season;
use App\Models\Game;
use App\Services\MatchSimulationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('match simulation produces valid results', function () {
    $service = new MatchSimulationService();
    
    $homeTeam = Team::create([
        'name' => 'Strong Team',
        'city' => 'Strong City',
        'strength' => 85,
    ]);

    $awayTeam = Team::create([
        'name' => 'Weak Team',
        'city' => 'Weak City',
        'strength' => 65,
    ]);

    $result = $service->simulateMatch($homeTeam, $awayTeam);

    expect($result)->toHaveKeys(['home_goals', 'away_goals']);
    expect($result['home_goals'])->toBeInt();
    expect($result['away_goals'])->toBeInt();
    expect($result['home_goals'])->toBeGreaterThanOrEqual(0);
    expect($result['away_goals'])->toBeGreaterThanOrEqual(0);
    expect($result['home_goals'])->toBeLessThanOrEqual(6);
    expect($result['away_goals'])->toBeLessThanOrEqual(6);
});

test('stronger teams tend to score more goals', function () {
    $service = new MatchSimulationService();
    
    $strongTeam = Team::create([
        'name' => 'Strong Team',
        'city' => 'Strong City',
        'strength' => 95,
    ]);

    $weakTeam = Team::create([
        'name' => 'Weak Team',
        'city' => 'Weak City',
        'strength' => 45,
    ]);

    // Run multiple simulations to test tendency
    $strongTeamGoals = 0;
    $weakTeamGoals = 0;
    $simulations = 50;

    for ($i = 0; $i < $simulations; $i++) {
        $result = $service->simulateMatch($strongTeam, $weakTeam);
        $strongTeamGoals += $result['home_goals'];
        $weakTeamGoals += $result['away_goals'];
    }

    $strongAverage = $strongTeamGoals / $simulations;
    $weakAverage = $weakTeamGoals / $simulations;

    expect($strongAverage)->toBeGreaterThan($weakAverage);
});

test('home advantage is applied correctly', function () {
    $service = new MatchSimulationService();
    
    $team1 = Team::create([
        'name' => 'Team 1',
        'city' => 'City 1',
        'strength' => 75,
    ]);

    $team2 = Team::create([
        'name' => 'Team 2',
        'city' => 'City 2',
        'strength' => 75,
    ]);

    // Run multiple simulations to test home advantage
    $homeWins = 0;
    $awayWins = 0;
    $draws = 0;
    $simulations = 100;

    for ($i = 0; $i < $simulations; $i++) {
        $result = $service->simulateMatch($team1, $team2);
        
        if ($result['home_goals'] > $result['away_goals']) {
            $homeWins++;
        } elseif ($result['home_goals'] < $result['away_goals']) {
            $awayWins++;
        } else {
            $draws++;
        }
    }

    // Home team should win more often due to home advantage
    expect($homeWins)->toBeGreaterThan($awayWins);
});

test('can play a game and update database', function () {
    $service = new MatchSimulationService();

    $homeTeam = Team::create([
        'name' => 'Home Team',
        'city' => 'Home City',
        'strength' => 80,
    ]);

    $awayTeam = Team::create([
        'name' => 'Away Team',
        'city' => 'Away City',
        'strength' => 70,
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

    $result = $service->playGame($game);

    expect($result->isCompleted())->toBeTrue();
    expect($result->home_goals)->not->toBeNull();
    expect($result->away_goals)->not->toBeNull();
    expect($result->played_at)->not->toBeNull();
});

test('cannot play already completed game', function () {
    $service = new MatchSimulationService();

    $homeTeam = Team::create([
        'name' => 'Home Team',
        'city' => 'Home City',
        'strength' => 80,
    ]);

    $awayTeam = Team::create([
        'name' => 'Away Team',
        'city' => 'Away City',
        'strength' => 70,
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
        'status' => 'completed',
        'home_goals' => 2,
        'away_goals' => 1,
    ]);

    expect(fn() => $service->playGame($game))
        ->toThrow(InvalidArgumentException::class, 'Game is already completed');
});

test('can play all games in a week', function () {
    $service = new MatchSimulationService();

    // Create 4 teams
    $teams = [];
    for ($i = 1; $i <= 4; $i++) {
        $teams[] = Team::create([
            'name' => "Team $i",
            'city' => "City $i",
            'strength' => 70 + $i * 5,
        ]);
    }

    $season = Season::create([
        'name' => 'Test Season',
        'start_date' => now(),
        'status' => 'active',
        'is_current' => true,
    ]);

    // Create games for week 1
    Game::create([
        'season_id' => $season->id,
        'home_team_id' => $teams[0]->id,
        'away_team_id' => $teams[1]->id,
        'week' => 1,
        'status' => 'scheduled',
    ]);

    Game::create([
        'season_id' => $season->id,
        'home_team_id' => $teams[2]->id,
        'away_team_id' => $teams[3]->id,
        'week' => 1,
        'status' => 'scheduled',
    ]);

    $results = $service->playWeek($season, 1);

    expect($results)->toHaveCount(2);
    foreach ($results as $result) {
        expect($result->isCompleted())->toBeTrue();
    }
});

test('can predict final table', function () {
    $service = new MatchSimulationService();

    // Create teams
    $teams = [];
    for ($i = 1; $i <= 4; $i++) {
        $teams[] = Team::create([
            'name' => "Team $i",
            'city' => "City $i",
            'strength' => 70 + $i * 5,
        ]);
    }

    $season = Season::create([
        'name' => 'Test Season',
        'start_date' => now(),
        'status' => 'active',
        'is_current' => true,
    ]);

    // Create some scheduled games
    Game::create([
        'season_id' => $season->id,
        'home_team_id' => $teams[0]->id,
        'away_team_id' => $teams[1]->id,
        'week' => 1,
        'status' => 'scheduled',
    ]);

    Game::create([
        'season_id' => $season->id,
        'home_team_id' => $teams[2]->id,
        'away_team_id' => $teams[3]->id,
        'week' => 1,
        'status' => 'scheduled',
    ]);

    $prediction = $service->predictFinalTable($season);

    expect($prediction)->toBeArray();
    expect($prediction)->toHaveCount(4);

    foreach ($prediction as $standing) {
        expect($standing)->toHaveKey('is_prediction');
        expect($standing['is_prediction'])->toBeTrue();
        expect($standing)->toHaveKeys([
            'team', 'position', 'played', 'won', 'drawn', 'lost',
            'goals_for', 'goals_against', 'goal_difference', 'points'
        ]);
    }
});