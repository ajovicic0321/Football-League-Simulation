<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Season;
use App\Services\LeagueService;
use App\Services\MatchSimulationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class GameController extends Controller
{
    protected LeagueService $leagueService;
    protected MatchSimulationService $matchSimulationService;

    public function __construct(LeagueService $leagueService, MatchSimulationService $matchSimulationService)
    {
        $this->leagueService = $leagueService;
        $this->matchSimulationService = $matchSimulationService;
    }

    /**
     * Get all games for a season.
     */
    public function index(Season $season): JsonResponse
    {
        $games = $season->games()
            ->with('homeTeam', 'awayTeam')
            ->orderBy('week')
            ->orderBy('id')
            ->get()
            ->map(function ($game) {
                return [
                    'id' => $game->id,
                    'home_team' => [
                        'id' => $game->homeTeam->id,
                        'name' => $game->homeTeam->name,
                        'city' => $game->homeTeam->city,
                    ],
                    'away_team' => [
                        'id' => $game->awayTeam->id,
                        'name' => $game->awayTeam->name,
                        'city' => $game->awayTeam->city,
                    ],
                    'home_goals' => $game->home_goals,
                    'away_goals' => $game->away_goals,
                    'week' => $game->week,
                    'status' => $game->status,
                    'result' => $game->isCompleted() ? $game->getResultString() : null,
                    'winner' => $game->getWinner(),
                    'played_at' => $game->played_at,
                    'created_at' => $game->created_at,
                    'updated_at' => $game->updated_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'season' => [
                    'id' => $season->id,
                    'name' => $season->name,
                    'status' => $season->status,
                ],
                'games' => $games,
            ],
            'message' => 'Games retrieved successfully'
        ]);
    }

    /**
     * Get games for a specific week.
     */
    public function week(Season $season, int $week): JsonResponse
    {
        if (!$this->leagueService->weekExists($season, $week)) {
            return response()->json([
                'success' => false,
                'message' => 'Week not found'
            ], 404);
        }

        $games = $this->leagueService->getWeekGames($season, $week)
            ->map(function ($game) {
                return [
                    'id' => $game->id,
                    'home_team' => [
                        'id' => $game->homeTeam->id,
                        'name' => $game->homeTeam->name,
                        'city' => $game->homeTeam->city,
                    ],
                    'away_team' => [
                        'id' => $game->awayTeam->id,
                        'name' => $game->awayTeam->name,
                        'city' => $game->awayTeam->city,
                    ],
                    'home_goals' => $game->home_goals,
                    'away_goals' => $game->away_goals,
                    'week' => $game->week,
                    'status' => $game->status,
                    'result' => $game->isCompleted() ? $game->getResultString() : null,
                    'winner' => $game->getWinner(),
                    'played_at' => $game->played_at,
                    'created_at' => $game->created_at,
                    'updated_at' => $game->updated_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'season' => [
                    'id' => $season->id,
                    'name' => $season->name,
                    'status' => $season->status,
                ],
                'week' => $week,
                'games' => $games,
            ],
            'message' => 'Week games retrieved successfully'
        ]);
    }

    /**
     * Display the specified game.
     */
    public function show(Game $game): JsonResponse
    {
        $game->load('homeTeam', 'awayTeam', 'season');

        $gameData = [
            'id' => $game->id,
            'season' => [
                'id' => $game->season->id,
                'name' => $game->season->name,
                'status' => $game->season->status,
            ],
            'home_team' => [
                'id' => $game->homeTeam->id,
                'name' => $game->homeTeam->name,
                'city' => $game->homeTeam->city,
                'strength' => $game->homeTeam->strength,
            ],
            'away_team' => [
                'id' => $game->awayTeam->id,
                'name' => $game->awayTeam->name,
                'city' => $game->awayTeam->city,
                'strength' => $game->awayTeam->strength,
            ],
            'home_goals' => $game->home_goals,
            'away_goals' => $game->away_goals,
            'week' => $game->week,
            'status' => $game->status,
            'result' => $game->isCompleted() ? $game->getResultString() : null,
            'winner' => $game->getWinner(),
            'played_at' => $game->played_at,
            'created_at' => $game->created_at,
            'updated_at' => $game->updated_at,
        ];

        return response()->json([
            'success' => true,
            'data' => $gameData,
            'message' => 'Game retrieved successfully'
        ]);
    }

    /**
     * Update game result.
     */
    public function updateResult(Request $request, Game $game): JsonResponse
    {
        $validated = $request->validate([
            'home_goals' => 'required|integer|min:0|max:20',
            'away_goals' => 'required|integer|min:0|max:20',
        ]);

        try {
            $updatedGame = $this->leagueService->updateGameResult(
                $game,
                $validated['home_goals'],
                $validated['away_goals']
            );

            $updatedGame->load('homeTeam', 'awayTeam');

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $updatedGame->id,
                    'home_team' => $updatedGame->homeTeam->name,
                    'away_team' => $updatedGame->awayTeam->name,
                    'home_goals' => $updatedGame->home_goals,
                    'away_goals' => $updatedGame->away_goals,
                    'result' => $updatedGame->getResultString(),
                    'winner' => $updatedGame->getWinner(),
                    'status' => $updatedGame->status,
                    'played_at' => $updatedGame->played_at,
                ],
                'message' => 'Game result updated successfully'
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Simulate a single game.
     */
    public function simulate(Game $game): JsonResponse
    {
        if ($game->isCompleted()) {
            return response()->json([
                'success' => false,
                'message' => 'Game is already completed'
            ], 400);
        }

        try {
            $simulatedGame = $this->matchSimulationService->playGame($game);
            $simulatedGame->load('homeTeam', 'awayTeam');

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $simulatedGame->id,
                    'home_team' => $simulatedGame->homeTeam->name,
                    'away_team' => $simulatedGame->awayTeam->name,
                    'home_goals' => $simulatedGame->home_goals,
                    'away_goals' => $simulatedGame->away_goals,
                    'result' => $simulatedGame->getResultString(),
                    'winner' => $simulatedGame->getWinner(),
                    'status' => $simulatedGame->status,
                    'played_at' => $simulatedGame->played_at,
                ],
                'message' => 'Game simulated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to simulate game: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset a game (remove result).
     */
    public function reset(Game $game): JsonResponse
    {
        try {
            $resetGame = $this->leagueService->resetGame($game);
            $resetGame->load('homeTeam', 'awayTeam');

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $resetGame->id,
                    'home_team' => $resetGame->homeTeam->name,
                    'away_team' => $resetGame->awayTeam->name,
                    'home_goals' => $resetGame->home_goals,
                    'away_goals' => $resetGame->away_goals,
                    'status' => $resetGame->status,
                    'played_at' => $resetGame->played_at,
                ],
                'message' => 'Game reset successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset game: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Simulate all games in a specific week.
     */
    public function simulateWeek(Season $season, int $week): JsonResponse
    {
        if (!$this->leagueService->weekExists($season, $week)) {
            return response()->json([
                'success' => false,
                'message' => 'Week not found'
            ], 404);
        }

        try {
            $results = $this->matchSimulationService->playWeek($season, $week);

            $simulatedGames = collect($results)->map(function ($game) {
                return [
                    'id' => $game->id,
                    'home_team' => $game->homeTeam->name,
                    'away_team' => $game->awayTeam->name,
                    'home_goals' => $game->home_goals,
                    'away_goals' => $game->away_goals,
                    'result' => $game->getResultString(),
                    'winner' => $game->getWinner(),
                    'status' => $game->status,
                    'played_at' => $game->played_at,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'season' => [
                        'id' => $season->id,
                        'name' => $season->name,
                    ],
                    'week' => $week,
                    'games' => $simulatedGames,
                ],
                'message' => "Week $week simulated successfully"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to simulate week: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Simulate entire season.
     */
    public function simulateSeason(Season $season): JsonResponse
    {
        try {
            $results = $this->matchSimulationService->playAllRemainingGames($season);

            $simulatedGames = collect($results)->map(function ($game) {
                return [
                    'id' => $game->id,
                    'home_team' => $game->homeTeam->name,
                    'away_team' => $game->awayTeam->name,
                    'home_goals' => $game->home_goals,
                    'away_goals' => $game->away_goals,
                    'result' => $game->getResultString(),
                    'winner' => $game->getWinner(),
                    'week' => $game->week,
                    'status' => $game->status,
                    'played_at' => $game->played_at,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'season' => [
                        'id' => $season->id,
                        'name' => $season->name,
                    ],
                    'games' => $simulatedGames,
                    'total_games' => count($results),
                ],
                'message' => 'Season simulated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to simulate season: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get final table predictions.
     */
    public function predictions(Season $season): JsonResponse
    {
        try {
            $predictions = $this->matchSimulationService->predictFinalTable($season);

            return response()->json([
                'success' => true,
                'data' => [
                    'season' => [
                        'id' => $season->id,
                        'name' => $season->name,
                    ],
                    'predictions' => $predictions,
                    'generated_at' => now(),
                ],
                'message' => 'Final table predictions generated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate predictions: ' . $e->getMessage()
            ], 500);
        }
    }
}