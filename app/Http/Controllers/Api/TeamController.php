<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    /**
     * Display a listing of teams.
     */
    public function index(): JsonResponse
    {
        $teams = Team::with('homeGames', 'awayGames')
            ->where('is_active', true)
            ->get()
            ->map(function ($team) {
                $stats = $team->getStatsForSeason();
                return [
                    'id' => $team->id,
                    'name' => $team->name,
                    'city' => $team->city,
                    'strength' => $team->strength,
                    'strength_description' => $team->getStrengthDescription(),
                    'colors' => $team->primary_color . ',' . $team->secondary_color,
                    'is_active' => $team->is_active,
                    'statistics' => [
                        'games_played' => $stats['played'],
                        'wins' => $stats['won'],
                        'draws' => $stats['drawn'],
                        'losses' => $stats['lost'],
                        'goals_for' => $stats['goals_for'],
                        'goals_against' => $stats['goals_against'],
                        'goal_difference' => $stats['goal_difference'],
                        'points' => $stats['points'],
                        'win_percentage' => $team->getWinPercentage(),
                        'avg_goals_per_game' => $team->getAverageGoalsPerGame(),
                        'recent_form' => $team->getRecentForm(),
                    ],
                    'created_at' => $team->created_at,
                    'updated_at' => $team->updated_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $teams,
            'message' => 'Teams retrieved successfully'
        ]);
    }

    /**
     * Store a newly created team.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:teams',
            'city' => 'required|string|max:255',
            'strength' => 'required|integer|min:1|max:100',
            'primary_color' => 'nullable|string|max:7',
            'secondary_color' => 'nullable|string|max:7',
            'is_active' => 'boolean',
        ]);

        $team = Team::create($validated);

        return response()->json([
            'success' => true,
            'data' => $team,
            'message' => 'Team created successfully'
        ], 201);
    }

    /**
     * Display the specified team with detailed statistics.
     */
    public function show(Team $team): JsonResponse
    {
        $team->load('homeGames.awayTeam', 'awayGames.homeTeam');
        $stats = $team->getStatsForSeason();

        $teamData = [
            'id' => $team->id,
            'name' => $team->name,
            'city' => $team->city,
            'strength' => $team->strength,
            'strength_description' => $team->getStrengthDescription(),
            'colors' => $team->primary_color . ',' . $team->secondary_color,
            'is_active' => $team->is_active,
            'statistics' => [
                'games_played' => $stats['played'],
                'wins' => $stats['won'],
                'draws' => $stats['drawn'],
                'losses' => $stats['lost'],
                'goals_for' => $stats['goals_for'],
                'goals_against' => $stats['goals_against'],
                'goal_difference' => $stats['goal_difference'],
                'points' => $stats['points'],
                'win_percentage' => $team->getWinPercentage(),
                'avg_goals_per_game' => $team->getAverageGoalsPerGame(),
                'recent_form' => $team->getRecentForm(),
            ],
            'recent_games' => $team->games()
                ->where('status', 'completed')
                ->orderBy('played_at', 'desc')
                ->take(5)
                ->get()
                ->map(function ($game) use ($team) {
                    $isHome = $game->home_team_id === $team->id;
                    $opponent = $isHome ? $game->awayTeam : $game->homeTeam;
                    
                    return [
                        'id' => $game->id,
                        'opponent' => $opponent->name,
                        'home_away' => $isHome ? 'H' : 'A',
                        'result' => $game->getResultString(),
                        'outcome' => $game->getWinner()?->id === $team->id ? 'W' : 
                                   ($game->getWinner() === null ? 'D' : 'L'),
                        'played_at' => $game->played_at,
                        'week' => $game->week,
                    ];
                })
                ->values(),
            'created_at' => $team->created_at,
            'updated_at' => $team->updated_at,
        ];

        return response()->json([
            'success' => true,
            'data' => $teamData,
            'message' => 'Team retrieved successfully'
        ]);
    }

    /**
     * Update the specified team.
     */
    public function update(Request $request, Team $team): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('teams')->ignore($team->id)],
            'city' => 'sometimes|string|max:255',
            'strength' => 'sometimes|integer|min:1|max:100',
            'primary_color' => 'nullable|string|max:7',
            'secondary_color' => 'nullable|string|max:7',
            'is_active' => 'sometimes|boolean',
        ]);

        $team->update($validated);

        return response()->json([
            'success' => true,
            'data' => $team,
            'message' => 'Team updated successfully'
        ]);
    }

    /**
     * Remove the specified team (soft delete by setting inactive).
     */
    public function destroy(Team $team): JsonResponse
    {
        $team->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Team deactivated successfully'
        ]);
    }

    /**
     * Get head-to-head record between two teams.
     */
    public function headToHead(Team $team, Team $opponent): JsonResponse
    {
        $record = $team->getHeadToHeadRecord($opponent);

        return response()->json([
            'success' => true,
            'data' => [
                'team1' => [
                    'id' => $team->id,
                    'name' => $team->name,
                ],
                'team2' => [
                    'id' => $opponent->id,
                    'name' => $opponent->name,
                ],
                'record' => $record,
            ],
            'message' => 'Head-to-head record retrieved successfully'
        ]);
    }
}