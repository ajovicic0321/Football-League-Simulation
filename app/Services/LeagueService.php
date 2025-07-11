<?php

namespace App\Services;

use App\Models\Team;
use App\Models\Game;
use App\Models\Season;
use Carbon\Carbon;

class LeagueService
{
    /**
     * Create a new season with all required fixtures
     * 
     * @param string $name
     * @param array $teamIds
     * @return Season
     */
    public function createSeason(string $name, array $teamIds = null): Season
    {
        // If no teams specified, use all active teams
        if ($teamIds === null) {
            $teamIds = Team::where('is_active', true)->pluck('id')->toArray();
        }

        if (count($teamIds) < 2) {
            throw new \InvalidArgumentException('At least 2 teams are required to create a season');
        }

        $season = Season::create([
            'name' => $name,
            'start_date' => Carbon::now(),
            'status' => 'upcoming',
            'is_current' => false,
        ]);

        $this->generateFixtures($season, $teamIds);

        return $season;
    }

    /**
     * Generate all fixtures for a season using round-robin format
     * 
     * @param Season $season
     * @param array $teamIds
     * @return void
     */
    public function generateFixtures(Season $season, array $teamIds): void
    {
        $teams = Team::whereIn('id', $teamIds)->get();
        $teamCount = count($teams);
        
        if ($teamCount < 2) {
            throw new \InvalidArgumentException('At least 2 teams are required');
        }

        $week = 1;
        
        // Double round-robin: each team plays every other team twice (home and away)
        for ($round = 1; $round <= 2; $round++) {
            for ($i = 0; $i < $teamCount; $i++) {
                for ($j = $i + 1; $j < $teamCount; $j++) {
                    if ($round === 1) {
                        // First round: team i at home
                        Game::create([
                            'season_id' => $season->id,
                            'home_team_id' => $teams[$i]->id,
                            'away_team_id' => $teams[$j]->id,
                            'week' => $week,
                            'status' => 'scheduled',
                        ]);
                    } else {
                        // Second round: team j at home (reverse fixture)
                        Game::create([
                            'season_id' => $season->id,
                            'home_team_id' => $teams[$j]->id,
                            'away_team_id' => $teams[$i]->id,
                            'week' => $week,
                            'status' => 'scheduled',
                        ]);
                    }
                    $week++;
                }
            }
        }
    }

    /**
     * Start a season (set as current and active)
     * 
     * @param Season $season
     * @return Season
     */
    public function startSeason(Season $season): Season
    {
        // End current season if exists
        $currentSeason = Season::getCurrent();
        if ($currentSeason && $currentSeason->id !== $season->id) {
            $currentSeason->update(['is_current' => false]);
        }

        $season->setCurrent();
        
        return $season->fresh();
    }

    /**
     * Get the current league table for a season
     * 
     * @param Season|null $season
     * @return array
     */
    public function getLeagueTable(Season $season = null): array
    {
        if ($season === null) {
            $season = Season::getCurrent();
        }

        if (!$season) {
            return [];
        }

        return $season->getStandings();
    }

    /**
     * Get all games for a specific week
     * 
     * @param Season $season
     * @param int $week
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getWeekGames(Season $season, int $week)
    {
        return $season->games()
            ->where('week', $week)
            ->with(['homeTeam', 'awayTeam'])
            ->orderBy('id')
            ->get();
    }

    /**
     * Get all results (completed games) grouped by week
     * 
     * @param Season $season
     * @return array
     */
    public function getResultsByWeek(Season $season): array
    {
        $completedGames = $season->games()
            ->where('status', 'completed')
            ->with(['homeTeam', 'awayTeam'])
            ->orderBy('week')
            ->orderBy('id')
            ->get();

        $results = [];
        foreach ($completedGames as $game) {
            $results[$game->week][] = $game;
        }

        return $results;
    }

    /**
     * Get upcoming fixtures (scheduled games) grouped by week
     * 
     * @param Season $season
     * @return array
     */
    public function getUpcomingFixtures(Season $season): array
    {
        $upcomingGames = $season->games()
            ->where('status', 'scheduled')
            ->with(['homeTeam', 'awayTeam'])
            ->orderBy('week')
            ->orderBy('id')
            ->get();

        $fixtures = [];
        foreach ($upcomingGames as $game) {
            $fixtures[$game->week][] = $game;
        }

        return $fixtures;
    }

    /**
     * Update the result of a specific game
     * 
     * @param Game $game
     * @param int $homeGoals
     * @param int $awayGoals
     * @return Game
     */
    public function updateGameResult(Game $game, int $homeGoals, int $awayGoals): Game
    {
        if ($homeGoals < 0 || $awayGoals < 0) {
            throw new \InvalidArgumentException('Goals cannot be negative');
        }

        if ($homeGoals > 20 || $awayGoals > 20) {
            throw new \InvalidArgumentException('Goals seem unrealistic (max 20)');
        }

        $game->completeGame($homeGoals, $awayGoals);
        
        return $game;
    }

    /**
     * Reset a game to scheduled status
     * 
     * @param Game $game
     * @return Game
     */
    public function resetGame(Game $game): Game
    {
        $game->update([
            'home_goals' => null,
            'away_goals' => null,
            'status' => 'scheduled',
            'played_at' => null,
        ]);

        return $game->fresh();
    }

    /**
     * Get season statistics and progress
     * 
     * @param Season $season
     * @return array
     */
    public function getSeasonStats(Season $season): array
    {
        $totalGames = $season->games()->count();
        $completedGames = $season->games()->where('status', 'completed')->count();
        $completionPercentage = $totalGames > 0 ? round(($completedGames / $totalGames) * 100, 1) : 0;

        $currentWeek = 1;
        if ($completedGames > 0) {
            $lastCompletedGame = $season->games()
                ->where('status', 'completed')
                ->orderBy('week', 'desc')
                ->first();
            $currentWeek = $lastCompletedGame ? $lastCompletedGame->week + 1 : 1;
        }

        $totalWeeks = $totalGames > 0 ? $season->games()->max('week') : 0;

        return [
            'total_games' => $totalGames,
            'completed_games' => $completedGames,
            'remaining_games' => $totalGames - $completedGames,
            'completion_percentage' => $completionPercentage,
            'current_week' => min($currentWeek, $totalWeeks),
            'total_weeks' => $totalWeeks,
            'status' => $season->status,
            'is_completed' => $completedGames === $totalGames,
        ];
    }

    /**
     * Get the next week number that has scheduled games
     * 
     * @param Season $season
     * @return int|null
     */
    public function getNextWeek(Season $season): ?int
    {
        $nextGame = $season->games()
            ->where('status', 'scheduled')
            ->orderBy('week')
            ->first();

        return $nextGame ? $nextGame->week : null;
    }

    /**
     * Check if a specific week has any games
     * 
     * @param Season $season
     * @param int $week
     * @return bool
     */
    public function weekExists(Season $season, int $week): bool
    {
        return $season->games()->where('week', $week)->exists();
    }

    /**
     * Get all teams in the current season
     * 
     * @param Season $season
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getSeasonTeams(Season $season)
    {
        $teamIds = $season->games()
            ->select('home_team_id as team_id')
            ->union(
                $season->games()->select('away_team_id as team_id')
            )
            ->distinct()
            ->pluck('team_id');

        return Team::whereIn('id', $teamIds)->get();
    }
} 