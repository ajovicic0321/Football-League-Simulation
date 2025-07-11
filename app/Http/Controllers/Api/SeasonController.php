<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Season;
use App\Models\Team;
use App\Services\LeagueService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SeasonController extends Controller
{
    protected LeagueService $leagueService;

    public function __construct(LeagueService $leagueService)
    {
        $this->leagueService = $leagueService;
    }

    /**
     * Display a listing of seasons.
     */
    public function index(): JsonResponse
    {
        $seasons = Season::with('games')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($season) {
                return [
                    'id' => $season->id,
                    'name' => $season->name,
                    'start_date' => $season->start_date,
                    'end_date' => $season->end_date,
                    'status' => $season->status,
                    'is_current' => $season->is_current,
                    'games_count' => $season->games->count(),
                    'completed_games' => $season->games->where('status', 'completed')->count(),
                    'created_at' => $season->created_at,
                    'updated_at' => $season->updated_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $seasons,
            'message' => 'Seasons retrieved successfully'
        ]);
    }

    /**
     * Store a newly created season.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'team_ids' => 'nullable|array|min:2',
            'team_ids.*' => 'exists:teams,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
        ]);

        try {
            $season = $this->leagueService->createSeason(
                $validated['name'],
                $validated['team_ids'] ?? null,
                $validated['start_date'] ?? null,
                $validated['end_date'] ?? null
            );

            return response()->json([
                'success' => true,
                'data' => $season->load('games'),
                'message' => 'Season created successfully'
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Display the specified season.
     */
    public function show(Season $season): JsonResponse
    {
        $season->load('games.homeTeam', 'games.awayTeam');
        $stats = $this->leagueService->getSeasonStats($season);

        $seasonData = [
            'id' => $season->id,
            'name' => $season->name,
            'start_date' => $season->start_date,
            'end_date' => $season->end_date,
            'status' => $season->status,
            'is_current' => $season->is_current,
            'statistics' => $stats,
            'teams' => $this->leagueService->getSeasonTeams($season),
            'created_at' => $season->created_at,
            'updated_at' => $season->updated_at,
        ];

        return response()->json([
            'success' => true,
            'data' => $seasonData,
            'message' => 'Season retrieved successfully'
        ]);
    }

    /**
     * Get the current active season.
     */
    public function current(): JsonResponse
    {
        $season = Season::getCurrent();

        if (!$season) {
            return response()->json([
                'success' => false,
                'message' => 'No current season found'
            ], 404);
        }

        $season->load('games.homeTeam', 'games.awayTeam');
        $stats = $this->leagueService->getSeasonStats($season);

        $seasonData = [
            'id' => $season->id,
            'name' => $season->name,
            'start_date' => $season->start_date,
            'end_date' => $season->end_date,
            'status' => $season->status,
            'is_current' => $season->is_current,
            'statistics' => $stats,
            'teams' => $this->leagueService->getSeasonTeams($season),
            'created_at' => $season->created_at,
            'updated_at' => $season->updated_at,
        ];

        return response()->json([
            'success' => true,
            'data' => $seasonData,
            'message' => 'Current season retrieved successfully'
        ]);
    }

    /**
     * Start a season.
     */
    public function start(Season $season): JsonResponse
    {
        if ($season->status !== 'upcoming') {
            return response()->json([
                'success' => false,
                'message' => 'Only upcoming seasons can be started'
            ], 400);
        }

        try {
            $activeSeason = $this->leagueService->startSeason($season);

            return response()->json([
                'success' => true,
                'data' => $activeSeason,
                'message' => 'Season started successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to start season: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get league table for a season.
     */
    public function table(Season $season): JsonResponse
    {
        $table = $this->leagueService->getLeagueTable($season);

        return response()->json([
            'success' => true,
            'data' => [
                'season' => [
                    'id' => $season->id,
                    'name' => $season->name,
                    'status' => $season->status,
                ],
                'table' => $table,
                'last_updated' => now(),
            ],
            'message' => 'League table retrieved successfully'
        ]);
    }

    /**
     * Get season statistics.
     */
    public function stats(Season $season): JsonResponse
    {
        $stats = $this->leagueService->getSeasonStats($season);

        return response()->json([
            'success' => true,
            'data' => [
                'season' => [
                    'id' => $season->id,
                    'name' => $season->name,
                    'status' => $season->status,
                ],
                'statistics' => $stats,
            ],
            'message' => 'Season statistics retrieved successfully'
        ]);
    }

    /**
     * Get results grouped by week.
     */
    public function results(Season $season): JsonResponse
    {
        $results = $this->leagueService->getResultsByWeek($season);

        return response()->json([
            'success' => true,
            'data' => [
                'season' => [
                    'id' => $season->id,
                    'name' => $season->name,
                    'status' => $season->status,
                ],
                'results' => $results,
            ],
            'message' => 'Season results retrieved successfully'
        ]);
    }

    /**
     * Get upcoming fixtures.
     */
    public function fixtures(Season $season): JsonResponse
    {
        $fixtures = $this->leagueService->getUpcomingFixtures($season);

        return response()->json([
            'success' => true,
            'data' => [
                'season' => [
                    'id' => $season->id,
                    'name' => $season->name,
                    'status' => $season->status,
                ],
                'fixtures' => $fixtures,
            ],
            'message' => 'Upcoming fixtures retrieved successfully'
        ]);
    }
}