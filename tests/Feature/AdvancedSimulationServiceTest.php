<?php

use App\Models\Team;
use App\Models\Season;
use App\Models\Game;
use App\Services\AdvancedSimulationService;
use App\Services\MatchSimulationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->basicSimulation = new MatchSimulationService();
    $this->service = new AdvancedSimulationService($this->basicSimulation);
    
    // Create test teams with different strengths
    $this->teams = [
        Team::create(['name' => 'Manchester City', 'city' => 'Manchester', 'strength' => 85, 'primary_color' => '#6CABDD']),
        Team::create(['name' => 'Liverpool FC', 'city' => 'Liverpool', 'strength' => 82, 'primary_color' => '#C8102E']),
        Team::create(['name' => 'Arsenal FC', 'city' => 'London', 'strength' => 78, 'primary_color' => '#EF0107']),
        Team::create(['name' => 'Chelsea FC', 'city' => 'London', 'strength' => 75, 'primary_color' => '#034694']),
    ];
    
    $this->season = Season::create([
        'name' => '2024/25 Test Season',
        'start_date' => now(),
        'status' => 'active',
        'is_current' => true,
    ]);
});

describe('Enhanced Match Simulation', function () {
    test('enhanced simulation produces valid results with metadata', function () {
        $result = $this->service->simulateEnhancedMatch($this->teams[0], $this->teams[1]);

        expect($result)->toHaveKeys(['home_goals', 'away_goals', 'metadata']);
        expect($result['home_goals'])->toBeInt()->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(8);
        expect($result['away_goals'])->toBeInt()->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(8);
        
        expect($result['metadata'])->toHaveKeys([
            'home_form', 'away_form', 'home_effective_strength', 
            'away_effective_strength', 'simulation_mode'
        ]);
        
        expect($result['metadata']['simulation_mode'])->toBe('realistic');
    });

    test('different simulation modes produce different outcomes', function () {
        $modes = ['basic', 'realistic', 'predictable'];
        $results = [];
        
        foreach ($modes as $mode) {
            $results[$mode] = [];
            for ($i = 0; $i < 10; $i++) {
                $result = $this->service->simulateEnhancedMatch($this->teams[0], $this->teams[1], $mode);
                $results[$mode][] = $result['home_goals'] + $result['away_goals'];
            }
        }
        
        // Different modes should produce different average goal counts
        $avgBasic = array_sum($results['basic']) / count($results['basic']);
        $avgRealistic = array_sum($results['realistic']) / count($results['realistic']);
        $avgPredictable = array_sum($results['predictable']) / count($results['predictable']);
        
        expect($avgBasic)->not->toBe($avgRealistic);
        expect($avgRealistic)->not->toBe($avgPredictable);
    });

    test('form calculation works with existing games', function () {
        // Create some completed games for form calculation
        $games = [
            ['home' => $this->teams[0], 'away' => $this->teams[1], 'home_goals' => 3, 'away_goals' => 1], // Win
            ['home' => $this->teams[1], 'away' => $this->teams[0], 'home_goals' => 1, 'away_goals' => 1], // Draw
            ['home' => $this->teams[0], 'away' => $this->teams[2], 'home_goals' => 2, 'away_goals' => 0], // Win
        ];
        
        foreach ($games as $index => $gameData) {
            Game::create([
                'season_id' => $this->season->id,
                'home_team_id' => $gameData['home']->id,
                'away_team_id' => $gameData['away']->id,
                'week' => $index + 1,
                'status' => 'completed',
                'home_goals' => $gameData['home_goals'],
                'away_goals' => $gameData['away_goals'],
                'played_at' => now()->subDays(3 - $index),
            ]);
        }
        
        $result = $this->service->simulateEnhancedMatch($this->teams[0], $this->teams[3]);
        
        // Team 0 should have good form (2 wins, 1 draw)
        expect($result['metadata']['home_form']['score'])->toBeGreaterThan(0.5);
        expect($result['metadata']['home_form']['trend'])->toBeIn(['improving', 'stable']);
    });
});

describe('Auto-Play System', function () {
    beforeEach(function () {
        // Create a full fixture list
        $week = 1;
        $gameId = 1;
        
        // Create round-robin fixtures
        for ($i = 0; $i < count($this->teams); $i++) {
            for ($j = $i + 1; $j < count($this->teams); $j++) {
                Game::create([
                    'season_id' => $this->season->id,
                    'home_team_id' => $this->teams[$i]->id,
                    'away_team_id' => $this->teams[$j]->id,
                    'week' => $week,
                    'status' => 'scheduled',
                ]);
                
                $week = ($gameId % 2 == 0) ? $week + 1 : $week;
                $gameId++;
            }
        }
    });

    test('auto-play season simulates games correctly', function () {
        $options = [
            'mode' => 'realistic',
            'stop_at_week' => 3,
            'max_games_per_batch' => 5,
            'include_analytics' => true
        ];
        
        $result = $this->service->autoPlaySeason($this->season, $options);
        
        expect($result)->toHaveKeys(['games', 'analytics', 'season_status', 'next_week']);
        expect($result['games'])->toBeArray();
        expect($result['analytics'])->toBeArray();
        expect($result['season_status'])->toHaveKeys(['progress', 'completed_games', 'total_games', 'is_complete']);
        expect(count($result['games']))->toBeLessThanOrEqual(5); // max_games_per_batch
    });

    test('auto-play respects stop conditions', function () {
        $options = [
            'stop_at_week' => 2,
            'max_games_per_batch' => 10
        ];
        
        $result = $this->service->autoPlaySeason($this->season, $options);
        
        foreach ($result['games'] as $game) {
            expect($game->week)->toBeLessThanOrEqual(2);
        }
    });

    test('auto-play generates week analytics', function () {
        $options = [
            'stop_at_week' => 2,
            'include_analytics' => true
        ];
        
        $result = $this->service->autoPlaySeason($this->season, $options);
        
        expect($result['analytics'])->not->toBeEmpty();
        
        foreach ($result['analytics'] as $weekAnalytics) {
            expect($weekAnalytics)->toHaveKeys([
                'week', 'games_played', 'total_goals', 
                'average_goals', 'upsets', 'entertainment_score'
            ]);
            expect($weekAnalytics['entertainment_score'])->toBeFloat()
                ->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(10);
        }
    });
});

describe('Advanced Predictions', function () {
    beforeEach(function () {
        // Create some completed games for better predictions
        $games = [
            ['home' => $this->teams[0], 'away' => $this->teams[1], 'home_goals' => 2, 'away_goals' => 1, 'week' => 1],
            ['home' => $this->teams[2], 'away' => $this->teams[3], 'home_goals' => 1, 'away_goals' => 3, 'week' => 1],
            ['home' => $this->teams[0], 'away' => $this->teams[2], 'home_goals' => 3, 'away_goals' => 0, 'week' => 2],
            ['home' => $this->teams[1], 'away' => $this->teams[3], 'home_goals' => 2, 'away_goals' => 2, 'week' => 2],
        ];
        
        foreach ($games as $gameData) {
            Game::create([
                'season_id' => $this->season->id,
                'home_team_id' => $gameData['home']->id,
                'away_team_id' => $gameData['away']->id,
                'week' => $gameData['week'],
                'status' => 'completed',
                'home_goals' => $gameData['home_goals'],
                'away_goals' => $gameData['away_goals'],
                'played_at' => now()->subDays(7 - $gameData['week']),
            ]);
        }
        
        // Add remaining scheduled games
        Game::create([
            'season_id' => $this->season->id,
            'home_team_id' => $this->teams[0]->id,
            'away_team_id' => $this->teams[3]->id,
            'week' => 3,
            'status' => 'scheduled',
        ]);
    });

    test('generates multiple prediction algorithms', function () {
        $predictions = $this->service->generateAdvancedPredictions($this->season);
        
        $expectedMethods = ['strength_based', 'form_based', 'statistical', 'monte_carlo', 'consensus'];
        
        expect($predictions)->toHaveKeys($expectedMethods);
        
        foreach ($expectedMethods as $method) {
            expect($predictions[$method])->toBeArray();
            expect(count($predictions[$method]))->toBe(count($this->teams));
            
            // Check each prediction has required fields
            foreach ($predictions[$method] as $prediction) {
                expect($prediction)->toHaveKey('team');
                expect($prediction)->toHaveKey('confidence');
                expect($prediction)->toHaveKey('method');
                expect($prediction['method'])->toBe($method);
            }
        }
    });

    test('strength based prediction includes confidence scores', function () {
        $predictions = $this->service->generateAdvancedPredictions($this->season);
        $strengthPredictions = $predictions['strength_based'];
        
        foreach ($strengthPredictions as $prediction) {
            expect($prediction['confidence'])->toBeFloat()
                ->toBeGreaterThan(0)->toBeLessThanOrEqual(1);
        }
    });

    test('form based prediction considers recent performance', function () {
        $predictions = $this->service->generateAdvancedPredictions($this->season);
        $formPredictions = $predictions['form_based'];
        
        foreach ($formPredictions as $prediction) {
            expect($prediction)->toHaveKeys(['predicted_points', 'form_score', 'confidence']);
            expect($prediction['form_score'])->toBeFloat()
                ->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(1);
        }
    });

    test('monte carlo simulation provides position probabilities', function () {
        $predictions = $this->service->generateAdvancedPredictions($this->season);
        $monteCarlo = $predictions['monte_carlo'];
        
        foreach ($monteCarlo as $prediction) {
            expect($prediction)->toHaveKeys(['most_likely_position', 'position_probabilities', 'confidence']);
            expect($prediction['most_likely_position'])->toBeInt()
                ->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(count($this->teams));
            expect($prediction['position_probabilities'])->toBeArray();
        }
    });

    test('consensus prediction combines multiple methods', function () {
        $predictions = $this->service->generateAdvancedPredictions($this->season);
        $consensus = $predictions['consensus'];
        
        foreach ($consensus as $prediction) {
            expect($prediction)->toHaveKeys([
                'consensus_position', 'strength_position', 
                'form_position', 'statistical_position', 'confidence'
            ]);
            expect($prediction['confidence'])->toBe(0.85); // High confidence
        }
    });
});

describe('Performance Analytics', function () {
    test('calculates entertainment score correctly', function () {
        // Create games with different entertainment values
        $games = [
            Game::create([
                'season_id' => $this->season->id,
                'home_team_id' => $this->teams[0]->id,
                'away_team_id' => $this->teams[1]->id,
                'week' => 1,
                'status' => 'completed',
                'home_goals' => 3, // High-scoring close game
                'away_goals' => 2,
                'played_at' => now(),
            ]),
            Game::create([
                'season_id' => $this->season->id,
                'home_team_id' => $this->teams[2]->id,
                'away_team_id' => $this->teams[3]->id,
                'week' => 1,
                'status' => 'completed',
                'home_goals' => 0, // Boring low-scoring game
                'away_goals' => 0,
                'played_at' => now(),
            ]),
        ];
        
        $analytics = $this->service->generateWeekAnalytics($this->season, 1, $games);
        
        expect($analytics)->toHaveKeys([
            'week', 'games_played', 'total_goals', 
            'average_goals', 'upsets', 'entertainment_score'
        ]);
        
        expect($analytics['total_goals'])->toBe(5);
        expect($analytics['average_goals'])->toBe(2.5);
        expect($analytics['entertainment_score'])->toBeFloat()
            ->toBeGreaterThan(0)->toBeLessThanOrEqual(10);
    });

    test('detects upsets correctly', function () {
        // Create an upset (weaker team beats stronger team)
        $game = Game::create([
            'season_id' => $this->season->id,
            'home_team_id' => $this->teams[3]->id, // Weakest team (Chelsea, 75)
            'away_team_id' => $this->teams[0]->id, // Strongest team (Man City, 85)
            'week' => 1,
            'status' => 'completed',
            'home_goals' => 2,
            'away_goals' => 0, // Upset!
            'played_at' => now(),
        ]);
        
        $analytics = $this->service->generateWeekAnalytics($this->season, 1, [$game]);
        
        expect($analytics['upsets'])->toBe(1);
    });
});

describe('Integration Tests', function () {
    test('complete workflow: auto-play then predictions', function () {
        // Create fixture list
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
            'week' => 2,
            'status' => 'scheduled',
        ]);
        
        // Step 1: Auto-play some games
        $autoPlayResult = $this->service->autoPlaySeason($this->season, [
            'stop_at_week' => 1,
            'include_analytics' => true
        ]);
        
        expect($autoPlayResult['games'])->not->toBeEmpty();
        expect($autoPlayResult['analytics'])->not->toBeEmpty();
        
        // Step 2: Generate predictions for remaining games
        $predictions = $this->service->generateAdvancedPredictions($this->season);
        
        expect($predictions)->toHaveKeys(['strength_based', 'form_based', 'consensus']);
        
        // Step 3: Verify predictions are different due to completed games
        $consensus = $predictions['consensus'];
        expect(count($consensus))->toBe(count($this->teams));
        
        // Each team should have a position
        $positions = array_column($consensus, 'position');
        expect($positions)->toBe([1, 2, 3, 4]);
    });

    test('enhanced simulation maintains data consistency', function () {
        // Create and simulate multiple weeks
        for ($week = 1; $week <= 3; $week++) {
            Game::create([
                'season_id' => $this->season->id,
                'home_team_id' => $this->teams[($week - 1) % 4]->id,
                'away_team_id' => $this->teams[($week + 1) % 4]->id,
                'week' => $week,
                'status' => 'scheduled',
            ]);
        }
        
        // Simulate all weeks
        for ($week = 1; $week <= 3; $week++) {
            $results = $this->service->playWeekWithEnhancements($this->season, $week, 'realistic');
            expect($results)->not->toBeEmpty();
            
            foreach ($results as $game) {
                expect($game->status)->toBe('completed');
                expect($game->home_goals)->not->toBeNull();
                expect($game->away_goals)->not->toBeNull();
                expect($game->played_at)->not->toBeNull();
            }
        }
        
        // Verify league standings are consistent
        $standings = $this->season->getStandings();
        expect(count($standings))->toBe(count($this->teams));
        
        // Verify total points equal total possible points from games played
        $totalPoints = array_sum(array_column($standings, 'points'));
        $totalGames = $this->season->games()->where('status', 'completed')->count();
        $expectedPoints = $totalGames * 3; // 3 points per game total
        
        expect($totalPoints)->toBe($expectedPoints);
    });
});

describe('Error Handling and Edge Cases', function () {
    test('handles empty season gracefully', function () {
        $emptySeason = Season::create([
            'name' => 'Empty Season',
            'start_date' => now(),
            'status' => 'active',
            'is_current' => false,
        ]);
        
        $predictions = $this->service->generateAdvancedPredictions($emptySeason);
        
        expect($predictions)->toBeArray();
        expect($predictions['strength_based'])->toBeArray();
    });

    test('auto-play handles no scheduled games', function () {
        // Season with no games
        $result = $this->service->autoPlaySeason($this->season, []);
        
        expect($result['games'])->toBeArray();
        expect($result['games'])->toBeEmpty();
        expect($result['season_status']['progress'])->toBe(0);
    });

    test('form calculation handles teams with no games', function () {
        $result = $this->service->simulateEnhancedMatch($this->teams[0], $this->teams[1]);
        
        // Should still work with default form values
        expect($result['metadata']['home_form']['score'])->toBe(0);
        expect($result['metadata']['away_form']['score'])->toBe(0);
        expect($result['metadata']['home_form']['confidence'])->toBe(0.5);
    });
}); 