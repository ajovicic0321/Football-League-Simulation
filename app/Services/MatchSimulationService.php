<?php

namespace App\Services;

use App\Models\Team;
use App\Models\Game;
use App\Models\Season;

class MatchSimulationService
{
    /**
     * Simulate a single match between two teams based on their strengths
     * 
     * @param Team $homeTeam
     * @param Team $awayTeam
     * @return array ['home_goals' => int, 'away_goals' => int]
     */
    public function simulateMatch(Team $homeTeam, Team $awayTeam): array
    {
        // Home advantage: +5 strength boost for home team
        $homeStrength = $homeTeam->strength + 5;
        $awayStrength = $awayTeam->strength;
        
        // Calculate goal probabilities based on strength difference
        $strengthDifference = $homeStrength - $awayStrength;
        
        // Base goals (0-4 range) with some randomness
        $homeGoals = $this->calculateGoals($homeStrength, $strengthDifference);
        $awayGoals = $this->calculateGoals($awayStrength, -$strengthDifference);
        
        return [
            'home_goals' => $homeGoals,
            'away_goals' => $awayGoals
        ];
    }

    /**
     * Calculate goals for a team based on strength and match context
     * 
     * @param int $teamStrength
     * @param int $strengthDifference
     * @return int
     */
    private function calculateGoals(int $teamStrength, int $strengthDifference): int
    {
        // Base goal expectation based on team strength (0-4 goals)
        $baseGoals = ($teamStrength / 100) * 4;
        
        // Adjust for strength difference (-2 to +2 goals)
        $strengthAdjustment = ($strengthDifference / 50) * 2;
        
        // Add some randomness (-1 to +1 goals)
        $randomFactor = (mt_rand(-100, 100) / 100);
        
        // Calculate final goals with minimum 0
        $goals = $baseGoals + $strengthAdjustment + $randomFactor;
        $goals = max(0, round($goals));
        
        // Cap maximum goals at 6 for realism
        return min(6, $goals);
    }

    /**
     * Simulate and complete a scheduled game
     * 
     * @param Game $game
     * @return Game
     */
    public function playGame(Game $game): Game
    {
        if ($game->isCompleted()) {
            throw new \InvalidArgumentException('Game is already completed');
        }

        $result = $this->simulateMatch($game->homeTeam, $game->awayTeam);
        
        $game->completeGame($result['home_goals'], $result['away_goals']);
        
        return $game;
    }

    /**
     * Simulate all remaining games in a week
     * 
     * @param Season $season
     * @param int $week
     * @return array
     */
    public function playWeek(Season $season, int $week): array
    {
        $games = $season->games()
            ->where('week', $week)
            ->where('status', 'scheduled')
            ->with(['homeTeam', 'awayTeam'])
            ->get();

        $results = [];
        
        foreach ($games as $game) {
            $this->playGame($game);
            $results[] = $game->fresh();
        }

        return $results;
    }

    /**
     * Simulate all remaining games in the season
     * 
     * @param Season $season
     * @return array
     */
    public function playAllRemainingGames(Season $season): array
    {
        $remainingGames = $season->games()
            ->where('status', 'scheduled')
            ->with(['homeTeam', 'awayTeam'])
            ->orderBy('week')
            ->get();

        $results = [];
        
        foreach ($remainingGames as $game) {
            $this->playGame($game);
            $results[] = $game->fresh();
        }

        // Update season status if all games are completed
        if ($season->games()->where('status', 'scheduled')->count() === 0) {
            $season->update(['status' => 'completed']);
        }

        return $results;
    }

    /**
     * Generate a prediction for the final league table based on remaining games
     * 
     * @param Season $season
     * @return array
     */
    public function predictFinalTable(Season $season): array
    {
        // Get current standings
        $currentStandings = $season->getStandings();
        
        // Simulate remaining games without saving to database
        $remainingGames = $season->games()
            ->where('status', 'scheduled')
            ->with(['homeTeam', 'awayTeam'])
            ->get();

        // Create a temporary copy of stats for prediction
        $predictedStats = [];
        foreach ($currentStandings as $standing) {
            $predictedStats[$standing['team']->id] = $standing;
        }

        // Simulate each remaining game
        foreach ($remainingGames as $game) {
            $result = $this->simulateMatch($game->homeTeam, $game->awayTeam);
            
            // Update predicted stats for home team
            $homeId = $game->home_team_id;
            $predictedStats[$homeId]['played']++;
            $predictedStats[$homeId]['goals_for'] += $result['home_goals'];
            $predictedStats[$homeId]['goals_against'] += $result['away_goals'];
            
            if ($result['home_goals'] > $result['away_goals']) {
                $predictedStats[$homeId]['won']++;
                $predictedStats[$homeId]['points'] += 3;
            } elseif ($result['home_goals'] == $result['away_goals']) {
                $predictedStats[$homeId]['drawn']++;
                $predictedStats[$homeId]['points'] += 1;
            } else {
                $predictedStats[$homeId]['lost']++;
            }
            
            // Update predicted stats for away team
            $awayId = $game->away_team_id;
            $predictedStats[$awayId]['played']++;
            $predictedStats[$awayId]['goals_for'] += $result['away_goals'];
            $predictedStats[$awayId]['goals_against'] += $result['home_goals'];
            
            if ($result['away_goals'] > $result['home_goals']) {
                $predictedStats[$awayId]['won']++;
                $predictedStats[$awayId]['points'] += 3;
            } elseif ($result['away_goals'] == $result['home_goals']) {
                $predictedStats[$awayId]['drawn']++;
                $predictedStats[$awayId]['points'] += 1;
            } else {
                $predictedStats[$awayId]['lost']++;
            }
            
            // Update goal differences
            $predictedStats[$homeId]['goal_difference'] = 
                $predictedStats[$homeId]['goals_for'] - $predictedStats[$homeId]['goals_against'];
            $predictedStats[$awayId]['goal_difference'] = 
                $predictedStats[$awayId]['goals_for'] - $predictedStats[$awayId]['goals_against'];
        }

        // Sort predicted standings by Premier League rules
        $predictedArray = array_values($predictedStats);
        usort($predictedArray, function ($a, $b) {
            if ($a['points'] !== $b['points']) {
                return $b['points'] <=> $a['points'];
            }
            
            if ($a['goal_difference'] !== $b['goal_difference']) {
                return $b['goal_difference'] <=> $a['goal_difference'];
            }
            
            return $b['goals_for'] <=> $a['goals_for'];
        });

        // Add positions
        foreach ($predictedArray as $index => &$standing) {
            $standing['position'] = $index + 1;
            $standing['is_prediction'] = true;
        }

        return $predictedArray;
    }
} 