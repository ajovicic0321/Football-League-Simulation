<?php

use App\Models\Team;
use App\Models\Season;
use App\Models\Game;
use App\Services\LeagueService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('can create season with teams', function () {
    $service = new LeagueService();

    // Create 4 teams
    $teams = [];
    for ($i = 1; $i <= 4; $i++) {
        $teams[] = Team::create([
            'name' => "Team $i",
            'city' => "City $i",
            'strength' => 70 + $i * 5,
        ]);
    }

    $teamIds = collect($teams)->pluck('id')->toArray();
    $season = $service->createSeason('Test Season 2024/25', $teamIds);

    expect($season->name)->toBe('Test Season 2024/25');
    expect($season->status)->toBe('upcoming');
    expect($season->is_current)->toBeFalse();
    
    // Check that all fixtures are created (4 teams = 12 games in double round-robin)
    $gamesCount = $season->games()->count();
    expect($gamesCount)->toBe(12); // 4 teams: (4*3) * 2 rounds = 12 games
});

test('can create season with all active teams if no teams specified', function () {
    $service = new LeagueService();

    // Create 3 active teams and 1 inactive
    Team::create(['name' => 'Active Team 1', 'city' => 'City 1', 'strength' => 75, 'is_active' => true]);
    Team::create(['name' => 'Active Team 2', 'city' => 'City 2', 'strength' => 80, 'is_active' => true]);
    Team::create(['name' => 'Active Team 3', 'city' => 'City 3', 'strength' => 70, 'is_active' => true]);
    Team::create(['name' => 'Inactive Team', 'city' => 'City 4', 'strength' => 65, 'is_active' => false]);

    $season = $service->createSeason('Auto Season');

    // Should create fixtures for 3 active teams only (6 games total)
    $gamesCount = $season->games()->count();
    expect($gamesCount)->toBe(6); // 3 teams: (3*2) * 2 rounds = 6 games
});

test('cannot create season with less than 2 teams', function () {
    $service = new LeagueService();

    $team = Team::create(['name' => 'Lonely Team', 'city' => 'Lonely City', 'strength' => 75]);

    expect(fn() => $service->createSeason('Invalid Season', [$team->id]))
        ->toThrow(InvalidArgumentException::class, 'At least 2 teams are required to create a season');
});

test('can start season and set as current', function () {
    $service = new LeagueService();

    // Create teams and season
    $teams = [];
    for ($i = 1; $i <= 4; $i++) {
        $teams[] = Team::create([
            'name' => "Team $i",
            'city' => "City $i",
            'strength' => 70 + $i * 5,
        ]);
    }

    $teamIds = collect($teams)->pluck('id')->toArray();
    $season = $service->createSeason('Test Season', $teamIds);

    // Start the season
    $activeSeason = $service->startSeason($season);

    expect($activeSeason->status)->toBe('active');
    expect($activeSeason->is_current)->toBeTrue();
    
    // Verify it's the current season
    $currentSeason = Season::getCurrent();
    expect($currentSeason->id)->toBe($activeSeason->id);
});

test('starting new season deactivates previous current season', function () {
    $service = new LeagueService();

    // Create teams
    $teams = [];
    for ($i = 1; $i <= 4; $i++) {
        $teams[] = Team::create([
            'name' => "Team $i",
            'city' => "City $i",
            'strength' => 70 + $i * 5,
        ]);
    }
    $teamIds = collect($teams)->pluck('id')->toArray();

    // Create and start first season
    $season1 = $service->createSeason('Season 1', $teamIds);
    $service->startSeason($season1);

    // Create and start second season
    $season2 = $service->createSeason('Season 2', $teamIds);
    $service->startSeason($season2);

    // Check that first season is no longer current
    expect($season1->fresh()->is_current)->toBeFalse();
    expect($season2->fresh()->is_current)->toBeTrue();
});

test('can get league table', function () {
    $service = new LeagueService();

    // Create teams
    $teams = [];
    for ($i = 1; $i <= 4; $i++) {
        $teams[] = Team::create([
            'name' => "Team $i",
            'city' => "City $i",
            'strength' => 70 + $i * 5,
        ]);
    }

    $teamIds = collect($teams)->pluck('id')->toArray();
    $season = $service->createSeason('Test Season', $teamIds);
    $service->startSeason($season);

    $table = $service->getLeagueTable($season);

    expect($table)->toBeArray();
    expect($table)->toHaveCount(4);
    
    foreach ($table as $standing) {
        expect($standing)->toHaveKeys([
            'team', 'position', 'played', 'won', 'drawn', 'lost',
            'goals_for', 'goals_against', 'goal_difference', 'points'
        ]);
    }
});

test('can get week games', function () {
    $service = new LeagueService();

    // Create teams and season
    $teams = [];
    for ($i = 1; $i <= 4; $i++) {
        $teams[] = Team::create([
            'name' => "Team $i",
            'city' => "City $i",
            'strength' => 70 + $i * 5,
        ]);
    }

    $teamIds = collect($teams)->pluck('id')->toArray();
    $season = $service->createSeason('Test Season', $teamIds);

    $weekGames = $service->getWeekGames($season, 1);

    expect($weekGames)->not->toBeEmpty();
    foreach ($weekGames as $game) {
        expect($game->week)->toBe(1);
        expect($game->homeTeam)->not->toBeNull();
        expect($game->awayTeam)->not->toBeNull();
    }
});

test('can update game result', function () {
    $service = new LeagueService();

    $homeTeam = Team::create(['name' => 'Home Team', 'city' => 'Home City', 'strength' => 80]);
    $awayTeam = Team::create(['name' => 'Away Team', 'city' => 'Away City', 'strength' => 70]);
    
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

    $updatedGame = $service->updateGameResult($game, 3, 1);

    expect($updatedGame->home_goals)->toBe(3);
    expect($updatedGame->away_goals)->toBe(1);
    expect($updatedGame->isCompleted())->toBeTrue();
    expect($updatedGame->getResultString())->toBe('3-1');
});

test('cannot update game with negative goals', function () {
    $service = new LeagueService();

    $homeTeam = Team::create(['name' => 'Home Team', 'city' => 'Home City', 'strength' => 80]);
    $awayTeam = Team::create(['name' => 'Away Team', 'city' => 'Away City', 'strength' => 70]);
    
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

    expect(fn() => $service->updateGameResult($game, -1, 2))
        ->toThrow(InvalidArgumentException::class, 'Goals cannot be negative');
});

test('cannot update game with unrealistic goals', function () {
    $service = new LeagueService();

    $homeTeam = Team::create(['name' => 'Home Team', 'city' => 'Home City', 'strength' => 80]);
    $awayTeam = Team::create(['name' => 'Away Team', 'city' => 'Away City', 'strength' => 70]);
    
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

    expect(fn() => $service->updateGameResult($game, 25, 2))
        ->toThrow(InvalidArgumentException::class, 'Goals seem unrealistic (max 20)');
});

test('can reset game', function () {
    $service = new LeagueService();

    $homeTeam = Team::create(['name' => 'Home Team', 'city' => 'Home City', 'strength' => 80]);
    $awayTeam = Team::create(['name' => 'Away Team', 'city' => 'Away City', 'strength' => 70]);
    
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

    $resetGame = $service->resetGame($game);

    expect($resetGame->status)->toBe('scheduled');
    expect($resetGame->home_goals)->toBeNull();
    expect($resetGame->away_goals)->toBeNull();
    expect($resetGame->played_at)->toBeNull();
});

test('can get season statistics', function () {
    $service = new LeagueService();

    // Create teams and season
    $teams = [];
    for ($i = 1; $i <= 4; $i++) {
        $teams[] = Team::create([
            'name' => "Team $i",
            'city' => "City $i",
            'strength' => 70 + $i * 5,
        ]);
    }

    $teamIds = collect($teams)->pluck('id')->toArray();
    $season = $service->createSeason('Test Season', $teamIds);

    // Complete some games
    $games = $season->games()->take(3)->get();
    foreach ($games as $game) {
        $game->completeGame(2, 1);
    }

    $stats = $service->getSeasonStats($season);

    expect($stats)->toHaveKeys([
        'total_games', 'completed_games', 'remaining_games',
        'completion_percentage', 'current_week', 'total_weeks',
        'status', 'is_completed'
    ]);
    
    expect($stats['total_games'])->toBe(12);
    expect($stats['completed_games'])->toBe(3);
    expect($stats['remaining_games'])->toBe(9);
    expect($stats['completion_percentage'])->toBe(25.0);
    expect($stats['is_completed'])->toBeFalse();
});

test('can get results by week', function () {
    $service = new LeagueService();

    // Create teams and season
    $teams = [];
    for ($i = 1; $i <= 4; $i++) {
        $teams[] = Team::create([
            'name' => "Team $i",
            'city' => "City $i",
            'strength' => 70 + $i * 5,
        ]);
    }

    $teamIds = collect($teams)->pluck('id')->toArray();
    $season = $service->createSeason('Test Season', $teamIds);

    // Complete games in week 1
    $week1Games = $season->games()->where('week', 1)->get();
    foreach ($week1Games as $game) {
        $game->completeGame(2, 1);
    }

    $results = $service->getResultsByWeek($season);

    expect($results)->toHaveKey(1);
    expect($results[1])->not->toBeEmpty();
    
    foreach ($results[1] as $result) {
        expect($result->isCompleted())->toBeTrue();
        expect($result->week)->toBe(1);
    }
});

test('can get upcoming fixtures', function () {
    $service = new LeagueService();

    // Create teams and season
    $teams = [];
    for ($i = 1; $i <= 4; $i++) {
        $teams[] = Team::create([
            'name' => "Team $i",
            'city' => "City $i",
            'strength' => 70 + $i * 5,
        ]);
    }

    $teamIds = collect($teams)->pluck('id')->toArray();
    $season = $service->createSeason('Test Season', $teamIds);

    $fixtures = $service->getUpcomingFixtures($season);

    expect($fixtures)->not->toBeEmpty();
    
    foreach ($fixtures as $weekFixtures) {
        foreach ($weekFixtures as $fixture) {
            expect($fixture->status)->toBe('scheduled');
        }
    }
});

test('can get next week', function () {
    $service = new LeagueService();

    // Create teams and season
    $teams = [];
    for ($i = 1; $i <= 4; $i++) {
        $teams[] = Team::create([
            'name' => "Team $i",
            'city' => "City $i",
            'strength' => 70 + $i * 5,
        ]);
    }

    $teamIds = collect($teams)->pluck('id')->toArray();
    $season = $service->createSeason('Test Season', $teamIds);

    // Complete week 1 games
    $week1Games = $season->games()->where('week', 1)->get();
    foreach ($week1Games as $game) {
        $game->completeGame(2, 1);
    }

    $nextWeek = $service->getNextWeek($season);

    expect($nextWeek)->toBe(2);
});

test('can check if week exists', function () {
    $service = new LeagueService();

    // Create teams and season
    $teams = [];
    for ($i = 1; $i <= 4; $i++) {
        $teams[] = Team::create([
            'name' => "Team $i",
            'city' => "City $i",
            'strength' => 70 + $i * 5,
        ]);
    }

    $teamIds = collect($teams)->pluck('id')->toArray();
    $season = $service->createSeason('Test Season', $teamIds);

    expect($service->weekExists($season, 1))->toBeTrue();
    expect($service->weekExists($season, 99))->toBeFalse();
});

test('can get season teams', function () {
    $service = new LeagueService();

    // Create teams and season
    $teams = [];
    for ($i = 1; $i <= 4; $i++) {
        $teams[] = Team::create([
            'name' => "Team $i",
            'city' => "City $i",
            'strength' => 70 + $i * 5,
        ]);
    }

    $teamIds = collect($teams)->pluck('id')->toArray();
    $season = $service->createSeason('Test Season', $teamIds);

    $seasonTeams = $service->getSeasonTeams($season);

    expect($seasonTeams)->toHaveCount(4);
    
    $seasonTeamIds = $seasonTeams->pluck('id')->toArray();
    expect($seasonTeamIds)->toEqual($teamIds);
}); 