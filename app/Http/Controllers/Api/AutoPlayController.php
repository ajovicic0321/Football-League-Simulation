<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AdvancedSimulationService;
use App\Models\Season;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AutoPlayController extends Controller
{
    private AdvancedSimulationService $simulationService;

    public function __construct(AdvancedSimulationService $simulationService)
    {
        $this->simulationService = $simulationService;
    }

    /**
     * Start auto-play session
     */
    public function start(Request $request, Season $season): JsonResponse
    {
        $request->validate([
            'speed' => 'in:slow,normal,fast',
            'mode' => 'in:basic,realistic,predictable',
            'stop_at_week' => 'nullable|integer|min:1|max:50',
            'max_games_per_batch' => 'integer|min:1|max:20'
        ]);

        $options = [
            'mode' => $request->get('mode', 'realistic'),
            'stop_at_week' => $request->get('stop_at_week'),
            'max_games_per_batch' => $this->getGamesPerBatch($request->get('speed', 'normal')),
            'include_analytics' => true
        ];

        try {
            $result = $this->simulationService->autoPlaySeason($season, $options);

            return response()->json([
                'success' => true,
                'data' => [
                    'session_id' => uniqid('autoplay_'),
                    'games_simulated' => count($result['games']),
                    'games' => $result['games'],
                    'analytics' => $result['analytics'],
                    'season_status' => $result['season_status'],
                    'next_week' => $result['next_week'],
                    'options' => $options
                ],
                'message' => 'Auto-play session started successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to start auto-play: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Continue auto-play session
     */
    public function continue(Request $request, Season $season): JsonResponse
    {
        $request->validate([
            'session_id' => 'required|string',
            'current_week' => 'required|integer|min:1',
            'speed' => 'in:slow,normal,fast',
            'mode' => 'in:basic,realistic,predictable'
        ]);

        $options = [
            'mode' => $request->get('mode', 'realistic'),
            'stop_at_week' => $request->get('stop_at_week'),
            'max_games_per_batch' => $this->getGamesPerBatch($request->get('speed', 'normal')),
            'include_analytics' => true
        ];

        try {
            $result = $this->simulationService->autoPlaySeason($season, $options);

            return response()->json([
                'success' => true,
                'data' => [
                    'session_id' => $request->get('session_id'),
                    'games_simulated' => count($result['games']),
                    'games' => $result['games'],
                    'analytics' => $result['analytics'],
                    'season_status' => $result['season_status'],
                    'next_week' => $result['next_week'],
                    'is_complete' => $result['season_status']['is_complete']
                ],
                'message' => 'Auto-play continued successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to continue auto-play: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Stop auto-play session
     */
    public function stop(Request $request, Season $season): JsonResponse
    {
        $request->validate([
            'session_id' => 'required|string'
        ]);

        // Get current season status
        $status = $this->simulationService->getSeasonStatus($season);

        return response()->json([
            'success' => true,
            'data' => [
                'session_id' => $request->get('session_id'),
                'final_status' => $status,
                'stopped_at' => now()->toISOString()
            ],
            'message' => 'Auto-play session stopped'
        ]);
    }

    /**
     * Get auto-play configuration options
     */
    public function getOptions(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'speeds' => [
                    'slow' => ['name' => 'Slow', 'games_per_batch' => 1, 'description' => 'One game at a time'],
                    'normal' => ['name' => 'Normal', 'games_per_batch' => 3, 'description' => 'Several games per batch'],
                    'fast' => ['name' => 'Fast', 'games_per_batch' => 7, 'description' => 'Complete weeks quickly']
                ],
                'modes' => [
                    'basic' => ['name' => 'Basic', 'description' => 'Simple strength-based simulation'],
                    'realistic' => ['name' => 'Realistic', 'description' => 'Form and momentum considered'],
                    'predictable' => ['name' => 'Predictable', 'description' => 'Less randomness, form-heavy']
                ],
                'analytics_available' => true
            ],
            'message' => 'Auto-play options retrieved'
        ]);
    }

    /**
     * Get advanced predictions using multiple algorithms
     */
    public function getAdvancedPredictions(Season $season): JsonResponse
    {
        try {
            $predictions = $this->simulationService->generateAdvancedPredictions($season);

            return response()->json([
                'success' => true,
                'data' => [
                    'predictions' => $predictions,
                    'generated_at' => now()->toISOString(),
                    'methods_used' => array_keys($predictions),
                    'recommended_method' => 'consensus'
                ],
                'message' => 'Advanced predictions generated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate predictions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Simulate with enhanced algorithms
     */
    public function simulateEnhanced(Request $request, Season $season): JsonResponse
    {
        $request->validate([
            'week' => 'required|integer|min:1|max:50',
            'mode' => 'in:basic,realistic,predictable'
        ]);

        $week = $request->get('week');
        $mode = $request->get('mode', 'realistic');

        try {
            $results = $this->simulationService->playWeekWithEnhancements($season, $week, $mode);

            return response()->json([
                'success' => true,
                'data' => [
                    'week' => $week,
                    'mode' => $mode,
                    'games' => $results,
                    'games_simulated' => count($results),
                    'analytics' => $this->simulationService->generateWeekAnalytics($season, $week, $results)
                ],
                'message' => "Week {$week} simulated with enhanced algorithms"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to simulate week: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get detailed analytics for a specific week
     */
    public function getWeekAnalytics(Season $season, int $week): JsonResponse
    {
        try {
            $games = $season->games()
                ->where('week', $week)
                ->where('status', 'completed')
                ->with(['homeTeam', 'awayTeam'])
                ->get()
                ->toArray();

            if (empty($games)) {
                return response()->json([
                    'success' => false,
                    'message' => "No completed games found for week {$week}"
                ], 404);
            }

            $analytics = $this->simulationService->generateWeekAnalytics($season, $week, $games);

            return response()->json([
                'success' => true,
                'data' => [
                    'week' => $week,
                    'analytics' => $analytics,
                    'games' => $games
                ],
                'message' => "Analytics for week {$week} retrieved"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get analytics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Map speed setting to games per batch
     */
    private function getGamesPerBatch(string $speed): int
    {
        return match($speed) {
            'slow' => 1,
            'fast' => 7,
            default => 3 // normal
        };
    }
} 