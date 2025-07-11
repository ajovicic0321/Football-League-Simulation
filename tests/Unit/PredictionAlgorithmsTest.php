<?php

use App\Models\Team;
use App\Models\Season;
use App\Models\Game;
use App\Services\AdvancedSimulationService;
use App\Services\MatchSimulationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->basicSimulation = new MatchSimulationService();
    $this->service = new AdvancedSimulationService($this->basicSimulation);
    
    $this->teams = [
        Team::create(['name' => 'Strong Team', 'city' => 'Strong City', 'strength' => 90]),
        Team::create(['name' => 'Average Team', 'city' => 'Average City', 'strength' => 70]),
        Team::create(['name' => 'Weak Team', 'city' => 'Weak City', 'strength' => 50]),
    ];
    
    $this->season = Season::create([
        'name' => 'Test Season',
        'start_date' => now(),
        'status' => 'active',
        'is_current' => true,
    ]);
});

describe('Form Calculation Algorithms', function () {
    test('calculates form score correctly with different results', function () {
        // Create games with known results
        $games = [
            ['result' => 'W', 'home_goals' => 3, 'away_goals' => 1], // Win: 3 points
            ['result' => 'W', 'home_goals' => 2, 'away_goals' => 0], // Win: 3 points  
            ['result' => 'D', 'home_goals' => 1, 'away_goals' => 1], // Draw: 1 point
            ['result' => 'L', 'home_goals' => 0, 'away_goals' => 2], // Loss: 0 points
            ['result' => 'D', 'home_goals' => 2, 'away_goals' => 2], // Draw: 1 point
        ];
        
        foreach ($games as $index => $gameData) {
            Game::create([
                'season_id' => $this->season->id,
                'home_team_id' => $this->teams[0]->id,
                'away_team_id' => $this->teams[1]->id,
                'week' => $index + 1,
                'status' => 'completed',
                'home_goals' => $gameData['home_goals'],
                'away_goals' => $gameData['away_goals'],
                'played_at' => now()->subDays(5 - $index),
            ]);
        }
        
        // Use reflection to test private method
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateTeamForm');
        $method->setAccessible(true);
        
        $form = $method->invoke($this->service, $this->teams[0]);
        
        // Expected: 2 wins (6 points) + 2 draws (2 points) + 1 loss (0 points) = 8/15 ≈ 0.533
        expect($form['score'])->toBeFloat()->toBeBetween(0.4, 0.6);
        expect($form['trend'])->toBeIn(['improving', 'declining', 'stable']);
        expect($form['confidence'])->toBe(1.0); // Full confidence with 5 games
    });

    test('handles insufficient games for form calculation', function () {
        // Create only 2 games
        for ($i = 0; $i < 2; $i++) {
            Game::create([
                'season_id' => $this->season->id,
                'home_team_id' => $this->teams[0]->id,
                'away_team_id' => $this->teams[1]->id,
                'week' => $i + 1,
                'status' => 'completed',
                'home_goals' => 1,
                'away_goals' => 0,
                'played_at' => now()->subDays(2 - $i),
            ]);
        }
        
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateTeamForm');
        $method->setAccessible(true);
        
        $form = $method->invoke($this->service, $this->teams[0]);
        
        expect($form['confidence'])->toBe(0.4); // 2/5 = 0.4 confidence
        expect($form['score'])->toBe(1); // 2 wins = perfect score
    });

    test('calculates form trend correctly', function () {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateFormTrend');
        $method->setAccessible(true);
        
        // Improving trend: recent games better than earlier
        $improvingResults = ['W', 'W', 'L', 'L', 'L']; // Recent wins, earlier losses
        $trend = $method->invoke($this->service, $improvingResults);
        expect($trend)->toBe('improving');
        
        // Declining trend: recent games worse than earlier  
        $decliningResults = ['L', 'L', 'W', 'W', 'W']; // Recent losses, earlier wins
        $trend = $method->invoke($this->service, $decliningResults);
        expect($trend)->toBe('declining');
        
        // Stable trend: similar performance
        $stableResults = ['W', 'D', 'W', 'D', 'W']; // Consistent performance
        $trend = $method->invoke($this->service, $stableResults);
        expect($trend)->toBe('stable');
    });
});

describe('Effective Strength Calculations', function () {
    test('applies home advantage correctly', function () {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateEffectiveStrength');
        $method->setAccessible(true);
        
        $form = ['score' => 0.5, 'trend' => 'stable']; // Neutral form
        
        $homeStrength = $method->invoke($this->service, $this->teams[0], $form, true);
        $awayStrength = $method->invoke($this->service, $this->teams[0], $form, false);
        
        expect($homeStrength)->toBeGreaterThan($awayStrength);
        expect($homeStrength - $awayStrength)->toBe(5.0); // Home advantage
    });

    test('applies form adjustments correctly', function () {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateEffectiveStrength');
        $method->setAccessible(true);
        
        $goodForm = ['score' => 1.0, 'trend' => 'improving']; // Perfect form, improving
        $badForm = ['score' => 0.0, 'trend' => 'declining']; // Poor form, declining
        $neutralForm = ['score' => 0.5, 'trend' => 'stable']; // Average form, stable
        
        $baseStrength = $this->teams[0]->strength; // 90
        
        $goodEffective = $method->invoke($this->service, $this->teams[0], $goodForm, false);
        $badEffective = $method->invoke($this->service, $this->teams[0], $badForm, false);
        $neutralEffective = $method->invoke($this->service, $this->teams[0], $neutralForm, false);
        
        expect($goodEffective)->toBeGreaterThan($neutralEffective);
        expect($neutralEffective)->toBeGreaterThan($badEffective);
        expect($goodEffective)->toBeFloat()->toBeBetween($baseStrength + 10, $baseStrength + 20); // Form + trend bonus
        expect($badEffective)->toBeFloat()->toBeBetween($baseStrength - 20, $baseStrength - 10); // Form + trend penalty
    });

    test('ensures minimum strength threshold', function () {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateEffectiveStrength');
        $method->setAccessible(true);
        
        $terribleForm = ['score' => 0.0, 'trend' => 'declining'];
        
        // Even weak team with terrible form should have minimum strength
        $effective = $method->invoke($this->service, $this->teams[2], $terribleForm, false);
        
        expect($effective)->toBeGreaterThanOrEqual(30); // Minimum threshold
    });
});

describe('Random Event Simulations', function () {
    test('random events stay within expected bounds', function () {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('applyRandomEvents');
        $method->setAccessible(true);
        
        // Test multiple times to ensure consistency
        for ($i = 0; $i < 100; $i++) {
            $multiplier = $method->invoke($this->service, 0.5); // Medium randomness
            
            expect($multiplier)->toBeFloat()
                ->toBeGreaterThanOrEqual(0.7)
                ->toBeLessThanOrEqual(1.3);
        }
    });

    test('higher randomness level increases variation', function () {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('applyRandomEvents');
        $method->setAccessible(true);
        
        $lowRandomResults = [];
        $highRandomResults = [];
        
        for ($i = 0; $i < 50; $i++) {
            $lowRandomResults[] = $method->invoke($this->service, 0.1);
            $highRandomResults[] = $method->invoke($this->service, 0.9);
        }
        
        $lowVariance = calculateVariance($lowRandomResults);
        $highVariance = calculateVariance($highRandomResults);
        
        expect($highVariance)->toBeGreaterThan($lowVariance);
    });
});

describe('Goal Calculation Algorithms', function () {
    test('enhanced goal calculation produces realistic distributions', function () {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateEnhancedGoals');
        $method->setAccessible(true);
        
        $results = [];
        
        // Test with strong vs weak attack/defense
        for ($i = 0; $i < 1000; $i++) {
            $goals = $method->invoke($this->service, 90, 50); // Strong attack vs weak defense
            $results[] = $goals;
        }
        
        $average = array_sum($results) / count($results);
        $maxGoals = max($results);
        $minGoals = min($results);
        
        expect($average)->toBeGreaterThan(1.0)->toBeLessThan(4.0); // Realistic average
        expect($maxGoals)->toBeLessThanOrEqual(8); // Maximum cap
        expect($minGoals)->toBeGreaterThanOrEqual(0); // Minimum floor
        
        // Most results should be 0-4 goals (realistic distribution)
        $realisticCount = count(array_filter($results, fn($g) => $g <= 4));
        expect($realisticCount / count($results))->toBeGreaterThan(0.8);
    });

    test('stronger attack produces more goals on average', function () {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateEnhancedGoals');
        $method->setAccessible(true);
        
        $strongAttackGoals = [];
        $weakAttackGoals = [];
        
        for ($i = 0; $i < 100; $i++) {
            $strongAttackGoals[] = $method->invoke($this->service, 95, 70);
            $weakAttackGoals[] = $method->invoke($this->service, 55, 70);
        }
        
        $strongAverage = array_sum($strongAttackGoals) / count($strongAttackGoals);
        $weakAverage = array_sum($weakAttackGoals) / count($weakAttackGoals);
        
        expect($strongAverage)->toBeGreaterThan($weakAverage);
    });
});

describe('Prediction Algorithm Accuracy', function () {
    test('strength-based prediction favors stronger teams', function () {
        // Create uneven matchups
        Game::create([
            'season_id' => $this->season->id,
            'home_team_id' => $this->teams[0]->id, // Strong (90)
            'away_team_id' => $this->teams[2]->id, // Weak (50)
            'week' => 1,
            'status' => 'scheduled',
        ]);
        
        $predictions = $this->service->generateAdvancedPredictions($this->season);
        $strengthPredictions = $predictions['strength_based'];
        
        // Find strong team and weak team in predictions
        $strongTeamPrediction = null;
        $weakTeamPrediction = null;
        
        foreach ($strengthPredictions as $prediction) {
            if ($prediction['team']->id === $this->teams[0]->id) {
                $strongTeamPrediction = $prediction;
            } elseif ($prediction['team']->id === $this->teams[2]->id) {
                $weakTeamPrediction = $prediction;
            }
        }
        
        expect($strongTeamPrediction['position'])->toBeLessThan($weakTeamPrediction['position']);
    });

    test('consensus prediction combines methods reasonably', function () {
        $predictions = $this->service->generateAdvancedPredictions($this->season);
        $consensus = $predictions['consensus'];
        
        foreach ($consensus as $prediction) {
            expect($prediction['consensus_position'])->toBeInt()
                ->toBeGreaterThanOrEqual(1)
                ->toBeLessThanOrEqual(count($this->teams));
            
            // Consensus position should be influenced by all methods
            expect($prediction)->toHaveKeys([
                'strength_position', 'form_position', 'statistical_position'
            ]);
        }
        
        // Verify all positions are unique
        $positions = array_column($consensus, 'position');
        expect(count(array_unique($positions)))->toBe(count($positions));
    });
});

describe('Analytics and Statistics', function () {
    test('entertainment score calculation is reasonable', function () {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateEntertainmentScore');
        $method->setAccessible(true);
        
        // High-scoring close game
        $excitingGame = (object) ['home_goals' => 4, 'away_goals' => 3];
        $excitingScore = $method->invoke($this->service, [$excitingGame]);
        
        // Low-scoring boring game  
        $boringGame = (object) ['home_goals' => 0, 'away_goals' => 0];
        $boringScore = $method->invoke($this->service, [$boringGame]);
        
        // Blowout game
        $blowoutGame = (object) ['home_goals' => 5, 'away_goals' => 0];
        $blowoutScore = $method->invoke($this->service, [$blowoutGame]);
        
        expect($excitingScore)->toBeGreaterThan($boringScore);
        expect($excitingScore)->toBeGreaterThan($blowoutScore); // Close games more entertaining
        expect($excitingScore)->toBeLessThanOrEqual(10); // Maximum score
        expect($boringScore)->toBeGreaterThanOrEqual(0); // Minimum score
    });

    test('season status calculation is accurate', function () {
        // Create games
        for ($i = 0; $i < 5; $i++) {
            Game::create([
                'season_id' => $this->season->id,
                'home_team_id' => $this->teams[$i % 3]->id,
                'away_team_id' => $this->teams[($i + 1) % 3]->id,
                'week' => $i + 1,
                'status' => $i < 3 ? 'completed' : 'scheduled',
                'home_goals' => $i < 3 ? 1 : null,
                'away_goals' => $i < 3 ? 0 : null,
            ]);
        }
        
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('getSeasonStatus');
        $method->setAccessible(true);
        
        $status = $method->invoke($this->service, $this->season);
        
        expect($status['total_games'])->toBe(5);
        expect($status['completed_games'])->toBe(3);
        expect($status['progress'])->toBe(0.6); // 3/5
        expect($status['is_complete'])->toBeFalse();
    });
});

// Helper method for variance calculation
function calculateVariance(array $values): float
{
    $mean = array_sum($values) / count($values);
    $squaredDiffs = array_map(fn($x) => pow($x - $mean, 2), $values);
    return array_sum($squaredDiffs) / count($squaredDiffs);
} 