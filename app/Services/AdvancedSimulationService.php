<?php

namespace App\Services;

use App\Models\Team;
use App\Models\Game;
use App\Models\Season;
use Illuminate\Support\Collection;

class AdvancedSimulationService
{
    private MatchSimulationService $basicSimulation;
    
    // Configuration for different simulation modes
    private const SIMULATION_MODES = [
        'basic' => ['randomness' => 0.3, 'form_weight' => 0.1],
        'realistic' => ['randomness' => 0.2, 'form_weight' => 0.25],
        'predictable' => ['randomness' => 0.1, 'form_weight' => 0.4],
    ];

    public function __construct(MatchSimulationService $basicSimulation)
    {
        $this->basicSimulation = $basicSimulation;
    }

    /**
     * Enhanced match simulation with form, momentum, and fatigue
     */
    public function simulateEnhancedMatch(Team $homeTeam, Team $awayTeam, string $mode = 'realistic'): array
    {
        $config = self::SIMULATION_MODES[$mode] ?? self::SIMULATION_MODES['realistic'];
        
        // Get team form and momentum
        $homeForm = $this->calculateTeamForm($homeTeam);
        $awayForm = $this->calculateTeamForm($awayTeam);
        
        // Calculate effective strength with form
        $homeEffectiveStrength = $this->calculateEffectiveStrength($homeTeam, $homeForm, true);
        $awayEffectiveStrength = $this->calculateEffectiveStrength($awayTeam, $awayForm, false);
        
        // Apply random events (injuries, cards, weather)
        $homeMultiplier = $this->applyRandomEvents($config['randomness']);
        $awayMultiplier = $this->applyRandomEvents($config['randomness']);
        
        // Calculate final goals with all factors
        $homeGoals = $this->calculateEnhancedGoals(
            $homeEffectiveStrength * $homeMultiplier,
            $awayEffectiveStrength
        );
        
        $awayGoals = $this->calculateEnhancedGoals(
            $awayEffectiveStrength * $awayMultiplier,
            $homeEffectiveStrength
        );

        return [
            'home_goals' => $homeGoals,
            'away_goals' => $awayGoals,
            'metadata' => [
                'home_form' => $homeForm,
                'away_form' => $awayForm,
                'home_effective_strength' => $homeEffectiveStrength,
                'away_effective_strength' => $awayEffectiveStrength,
                'simulation_mode' => $mode
            ]
        ];
    }

    /**
     * Calculate team form based on recent results
     */
    private function calculateTeamForm(Team $team): array
    {
        $recentGames = Game::where(function($query) use ($team) {
            $query->where('home_team_id', $team->id)
                  ->orWhere('away_team_id', $team->id);
        })
        ->where('status', 'completed')
        ->orderBy('week', 'desc')
        ->limit(5)
        ->get();

        if ($recentGames->isEmpty()) {
            return ['score' => 0, 'trend' => 'neutral', 'confidence' => 0.5];
        }

        $formPoints = 0;
        $goalsFor = 0;
        $goalsAgainst = 0;
        $results = [];

        foreach ($recentGames as $game) {
            $isHome = $game->home_team_id === $team->id;
            $teamGoals = $isHome ? $game->home_goals : $game->away_goals;
            $opponentGoals = $isHome ? $game->away_goals : $game->home_goals;
            
            $goalsFor += $teamGoals;
            $goalsAgainst += $opponentGoals;
            
            if ($teamGoals > $opponentGoals) {
                $formPoints += 3;
                $results[] = 'W';
            } elseif ($teamGoals === $opponentGoals) {
                $formPoints += 1;
                $results[] = 'D';
            } else {
                $results[] = 'L';
            }
        }

        $maxPossiblePoints = $recentGames->count() * 3;
        $formScore = $formPoints / $maxPossiblePoints;
        
        // Calculate trend (improving/declining)
        $trend = $this->calculateFormTrend($results);
        
        return [
            'score' => $formScore,
            'trend' => $trend,
            'goals_for_avg' => $goalsFor / $recentGames->count(),
            'goals_against_avg' => $goalsAgainst / $recentGames->count(),
            'confidence' => min(1.0, $recentGames->count() / 5)
        ];
    }

    /**
     * Calculate if team form is improving or declining
     */
    private function calculateFormTrend(array $results): string
    {
        if (count($results) < 3) return 'neutral';
        
        $recentPoints = 0;
        $earlierPoints = 0;
        
        // Recent 2 games vs earlier 3 games
        for ($i = 0; $i < min(2, count($results)); $i++) {
            $recentPoints += $results[$i] === 'W' ? 3 : ($results[$i] === 'D' ? 1 : 0);
        }
        
        for ($i = 2; $i < count($results); $i++) {
            $earlierPoints += $results[$i] === 'W' ? 3 : ($results[$i] === 'D' ? 1 : 0);
        }
        
        $recentAvg = $recentPoints / min(2, count($results));
        $earlierAvg = $earlierPoints / max(1, count($results) - 2);
        
        if ($recentAvg > $earlierAvg + 0.5) return 'improving';
        if ($recentAvg < $earlierAvg - 0.5) return 'declining';
        return 'stable';
    }

    /**
     * Calculate effective team strength with form and home advantage
     */
    private function calculateEffectiveStrength(Team $team, array $form, bool $isHome): float
    {
        $baseStrength = $team->strength;
        
        // Home advantage
        if ($isHome) {
            $baseStrength += 5;
        }
        
        // Form adjustment (-10 to +15 points based on form and trend)
        $formAdjustment = ($form['score'] - 0.5) * 20; // -10 to +10
        
        // Trend bonus/penalty
        $trendAdjustment = match($form['trend']) {
            'improving' => 5,
            'declining' => -5,
            default => 0
        };
        
        return max(30, $baseStrength + $formAdjustment + $trendAdjustment);
    }

    /**
     * Apply random events that can affect match outcome
     */
    private function applyRandomEvents(float $randomnessLevel): float
    {
        $events = [];
        
        // Injury (reduces performance)
        if (mt_rand(1, 100) <= $randomnessLevel * 100) {
            $events[] = ['type' => 'injury', 'impact' => -0.1];
        }
        
        // Weather conditions
        if (mt_rand(1, 100) <= $randomnessLevel * 50) {
            $weather = ['rain' => -0.05, 'wind' => -0.03, 'perfect' => 0.05];
            $condition = array_rand($weather);
            $events[] = ['type' => 'weather', 'condition' => $condition, 'impact' => $weather[$condition]];
        }
        
        // Referee decisions
        if (mt_rand(1, 100) <= $randomnessLevel * 30) {
            $decisions = ['favorable' => 0.08, 'unfavorable' => -0.08];
            $decision = array_rand($decisions);
            $events[] = ['type' => 'referee', 'decision' => $decision, 'impact' => $decisions[$decision]];
        }
        
        // Calculate total multiplier
        $totalImpact = 0;
        foreach ($events as $event) {
            $totalImpact += $event['impact'];
        }
        
        return max(0.7, min(1.3, 1 + $totalImpact));
    }

    /**
     * Enhanced goal calculation with realistic distributions
     */
    private function calculateEnhancedGoals(float $attackStrength, float $defenseStrength): int
    {
        // Calculate expected goals using Poisson-like distribution
        $strengthDiff = $attackStrength - $defenseStrength;
        $expectedGoals = 1.5 + ($strengthDiff / 40); // Base 1.5 goals per team
        
        // Apply randomness with weighted probability
        $goals = 0;
        $probability = $expectedGoals;
        
        while ($probability > 0 && $goals < 8) {
            if (mt_rand(1, 1000) <= ($probability * 1000)) {
                $goals++;
                $probability *= 0.6; // Each additional goal becomes less likely
            } else {
                break;
            }
        }
        
        return min(8, max(0, $goals));
    }

    /**
     * Auto-play system with configurable speed and stopping conditions
     */
    public function autoPlaySeason(
        Season $season, 
        array $options = []
    ): array {
        $defaultOptions = [
            'mode' => 'realistic',
            'stop_at_week' => null,
            'max_games_per_batch' => 5,
            'include_analytics' => true
        ];
        
        $options = array_merge($defaultOptions, $options);
        $results = [];
        $analytics = [];
        
        $currentWeek = $this->getCurrentWeek($season);
        $maxWeek = $options['stop_at_week'] ?? $this->getMaxWeek($season);
        
        for ($week = $currentWeek; $week <= $maxWeek; $week++) {
            $weekResults = $this->playWeekWithEnhancements($season, $week, $options['mode']);
            $results = array_merge($results, $weekResults);
            
            if ($options['include_analytics']) {
                $analytics[$week] = $this->generateWeekAnalytics($season, $week, $weekResults);
            }
            
            // Check if we should continue
            if (count($results) >= $options['max_games_per_batch']) {
                break;
            }
        }
        
        return [
            'games' => $results,
            'analytics' => $analytics,
            'season_status' => $this->getSeasonStatus($season),
            'next_week' => min($week + 1, $maxWeek)
        ];
    }

    /**
     * Play a week with enhanced simulation
     */
    public function playWeekWithEnhancements(Season $season, int $week, string $mode = 'realistic'): array
    {
        $games = $season->games()
            ->where('week', $week)
            ->where('status', 'scheduled')
            ->with(['homeTeam', 'awayTeam'])
            ->get();

        $results = [];
        
        foreach ($games as $game) {
            $result = $this->simulateEnhancedMatch($game->homeTeam, $game->awayTeam, $mode);
            $game->completeGame($result['home_goals'], $result['away_goals']);
            $results[] = $game->fresh();
        }

        return $results;
    }

    /**
     * Multiple prediction algorithms with confidence intervals
     */
    public function generateAdvancedPredictions(Season $season): array
    {
        return [
            'strength_based' => $this->strengthBasedPrediction($season),
            'form_based' => $this->formBasedPrediction($season),
            'statistical' => $this->statisticalPrediction($season),
            'monte_carlo' => $this->monteCarloSimulation($season),
            'consensus' => $this->consensusPrediction($season)
        ];
    }

    /**
     * Strength-based prediction (current implementation enhanced)
     */
    private function strengthBasedPrediction(Season $season): array
    {
        $predictions = $this->basicSimulation->predictFinalTable($season);
        
        foreach ($predictions as &$prediction) {
            $prediction['confidence'] = 0.7;
            $prediction['method'] = 'strength_based';
        }
        
        return $predictions;
    }

    /**
     * Form-based prediction using recent performance
     */
    private function formBasedPrediction(Season $season): array
    {
        $teams = Team::where('is_active', true)->get();
        $predictions = [];
        
        foreach ($teams as $team) {
            $form = $this->calculateTeamForm($team);
            $currentStats = $this->getCurrentTeamStats($season, $team);
            
            // Project based on current form
            $remainingGames = $this->getRemainingGamesCount($season, $team);
            $formMultiplier = $form['score'] * 2; // 0-2 range
            
            $projectedPoints = $currentStats['points'] + ($remainingGames * $formMultiplier);
            $projectedGoalDiff = $currentStats['goal_difference'] + 
                (($form['goals_for_avg'] - $form['goals_against_avg']) * $remainingGames);
            
            $predictions[] = [
                'team' => $team,
                'predicted_points' => round($projectedPoints),
                'predicted_goal_difference' => round($projectedGoalDiff),
                'form_score' => $form['score'],
                'confidence' => $form['confidence'] * 0.8,
                'method' => 'form_based'
            ];
        }
        
        // Sort by predicted points
        usort($predictions, function($a, $b) {
            if ($a['predicted_points'] !== $b['predicted_points']) {
                return $b['predicted_points'] <=> $a['predicted_points'];
            }
            return $b['predicted_goal_difference'] <=> $a['predicted_goal_difference'];
        });
        
        // Add positions
        foreach ($predictions as $index => &$prediction) {
            $prediction['position'] = $index + 1;
        }
        
        return $predictions;
    }

    /**
     * Statistical prediction using historical patterns
     */
    private function statisticalPrediction(Season $season): array
    {
        $teams = Team::where('is_active', true)->get();
        $predictions = [];
        
        foreach ($teams as $team) {
            $stats = $this->calculateAdvancedStats($season, $team);
            
            $predictions[] = [
                'team' => $team,
                'predicted_points' => $stats['projected_points'],
                'predicted_goal_difference' => $stats['projected_gd'],
                'xg_for' => $stats['expected_goals_for'],
                'xg_against' => $stats['expected_goals_against'],
                'confidence' => $stats['statistical_confidence'],
                'method' => 'statistical'
            ];
        }
        
        // Sort and add positions
        usort($predictions, function($a, $b) {
            if ($a['predicted_points'] !== $b['predicted_points']) {
                return $b['predicted_points'] <=> $a['predicted_points'];
            }
            return $b['predicted_goal_difference'] <=> $a['predicted_goal_difference'];
        });
        
        foreach ($predictions as $index => &$prediction) {
            $prediction['position'] = $index + 1;
        }
        
        return $predictions;
    }

    /**
     * Monte Carlo simulation (multiple runs for confidence)
     */
    private function monteCarloSimulation(Season $season, int $simulations = 1000): array
    {
        $allResults = [];
        
        for ($i = 0; $i < $simulations; $i++) {
            $result = $this->basicSimulation->predictFinalTable($season);
            foreach ($result as $position => $team) {
                $teamId = $team['team']->id;
                $allResults[$teamId][$position + 1] = ($allResults[$teamId][$position + 1] ?? 0) + 1;
            }
        }
        
        // Calculate probabilities and most likely positions
        $finalPredictions = [];
        foreach ($allResults as $teamId => $positions) {
            $team = Team::find($teamId);
            $mostLikelyPosition = array_keys($positions, max($positions))[0];
            $confidence = max($positions) / $simulations;
            
            $finalPredictions[] = [
                'team' => $team,
                'most_likely_position' => $mostLikelyPosition,
                'position_probabilities' => array_map(fn($count) => $count / $simulations, $positions),
                'confidence' => $confidence,
                'method' => 'monte_carlo'
            ];
        }
        
        // Sort by most likely position
        usort($finalPredictions, fn($a, $b) => $a['most_likely_position'] <=> $b['most_likely_position']);
        
        return $finalPredictions;
    }

    /**
     * Consensus prediction combining all methods
     */
    private function consensusPrediction(Season $season): array
    {
        $strengthPrediction = $this->strengthBasedPrediction($season);
        $formPrediction = $this->formBasedPrediction($season);
        $statPrediction = $this->statisticalPrediction($season);
        
        $teams = Team::where('is_active', true)->get();
        $consensus = [];
        
        foreach ($teams as $team) {
            $teamId = $team->id;
            
            // Find team in each prediction
            $strengthPos = $this->findTeamPosition($strengthPrediction, $teamId);
            $formPos = $this->findTeamPosition($formPrediction, $teamId);
            $statPos = $this->findTeamPosition($statPrediction, $teamId);
            
            // Weighted average (can adjust weights)
            $consensusPosition = round(($strengthPos * 0.4 + $formPos * 0.35 + $statPos * 0.25));
            
            $consensus[] = [
                'team' => $team,
                'consensus_position' => $consensusPosition,
                'strength_position' => $strengthPos,
                'form_position' => $formPos,
                'statistical_position' => $statPos,
                'confidence' => 0.85, // High confidence due to multiple methods
                'method' => 'consensus'
            ];
        }
        
        // Sort by consensus position
        usort($consensus, fn($a, $b) => $a['consensus_position'] <=> $b['consensus_position']);
        
        // Add final positions
        foreach ($consensus as $index => &$prediction) {
            $prediction['position'] = $index + 1;
        }
        
        return $consensus;
    }

    // Helper methods...
    private function getCurrentWeek(Season $season): int
    {
        return $season->games()->where('status', 'completed')->max('week') + 1 ?? 1;
    }

    private function getMaxWeek(Season $season): int
    {
        return $season->games()->max('week') ?? 12;
    }

    private function getSeasonStatus(Season $season): array
    {
        $totalGames = $season->games()->count();
        $completedGames = $season->games()->where('status', 'completed')->count();
        
        return [
            'progress' => $totalGames > 0 ? ($completedGames / $totalGames) : 0,
            'completed_games' => $completedGames,
            'total_games' => $totalGames,
            'is_complete' => $completedGames === $totalGames
        ];
    }

    private function generateWeekAnalytics(Season $season, int $week, array $results): array
    {
        $totalGoals = 0;
        $upsets = 0; // Lower strength team winning
        
        foreach ($results as $game) {
            $totalGoals += $game->home_goals + $game->away_goals;
            
            // Check for upset
            if ($game->homeTeam->strength < $game->awayTeam->strength && $game->home_goals > $game->away_goals) {
                $upsets++;
            } elseif ($game->awayTeam->strength < $game->homeTeam->strength && $game->away_goals > $game->home_goals) {
                $upsets++;
            }
        }
        
        return [
            'week' => $week,
            'games_played' => count($results),
            'total_goals' => $totalGoals,
            'average_goals' => count($results) > 0 ? $totalGoals / count($results) : 0,
            'upsets' => $upsets,
            'entertainment_score' => $this->calculateEntertainmentScore($results)
        ];
    }

    private function calculateEntertainmentScore(array $games): float
    {
        if (empty($games)) return 0;
        
        $score = 0;
        foreach ($games as $game) {
            $goalDiff = abs($game->home_goals - $game->away_goals);
            $totalGoals = $game->home_goals + $game->away_goals;
            
            // High scoring close games are most entertaining
            $score += $totalGoals * 0.3 + (5 - min($goalDiff, 4)) * 0.2;
        }
        
        return min(10, $score / count($games));
    }

    private function getCurrentTeamStats(Season $season, Team $team): array
    {
        $standings = $season->getStandings();
        foreach ($standings as $standing) {
            if ($standing['team']->id === $team->id) {
                return $standing;
            }
        }
        return ['points' => 0, 'goal_difference' => 0];
    }

    private function getRemainingGamesCount(Season $season, Team $team): int
    {
        return $season->games()
            ->where(function($query) use ($team) {
                $query->where('home_team_id', $team->id)
                      ->orWhere('away_team_id', $team->id);
            })
            ->where('status', 'scheduled')
            ->count();
    }

    private function calculateAdvancedStats(Season $season, Team $team): array
    {
        // Simplified implementation - can be expanded
        $stats = $this->getCurrentTeamStats($season, $team);
        $form = $this->calculateTeamForm($team);
        
        return [
            'projected_points' => $stats['points'] + ($this->getRemainingGamesCount($season, $team) * $form['score'] * 2),
            'projected_gd' => $stats['goal_difference'] + (($form['goals_for_avg'] - $form['goals_against_avg']) * $this->getRemainingGamesCount($season, $team)),
            'expected_goals_for' => $form['goals_for_avg'],
            'expected_goals_against' => $form['goals_against_avg'],
            'statistical_confidence' => $form['confidence']
        ];
    }

    private function findTeamPosition(array $predictions, int $teamId): int
    {
        foreach ($predictions as $index => $prediction) {
            if ($prediction['team']->id === $teamId) {
                return $index + 1;
            }
        }
        return count($predictions); // Last position if not found
    }
} 