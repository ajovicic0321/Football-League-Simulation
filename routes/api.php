<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\SeasonController;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\AutoPlayController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Teams API Routes
Route::prefix('teams')->group(function () {
    Route::get('/', [TeamController::class, 'index']);
    Route::post('/', [TeamController::class, 'store']);
    Route::get('/{team}', [TeamController::class, 'show']);
    Route::put('/{team}', [TeamController::class, 'update']);
    Route::delete('/{team}', [TeamController::class, 'destroy']);
    Route::get('/{team}/vs/{opponent}', [TeamController::class, 'headToHead']);
});

// Seasons API Routes
Route::prefix('seasons')->group(function () {
    Route::get('/', [SeasonController::class, 'index']);
    Route::post('/', [SeasonController::class, 'store']);
    Route::get('/current', [SeasonController::class, 'current']);
    Route::get('/{season}', [SeasonController::class, 'show']);
    Route::put('/{season}/start', [SeasonController::class, 'start']);
    Route::put('/{season}/reset', [SeasonController::class, 'reset']);
    Route::get('/{season}/table', [SeasonController::class, 'table']);
    Route::get('/{season}/stats', [SeasonController::class, 'stats']);
    Route::get('/{season}/results', [SeasonController::class, 'results']);
    Route::get('/{season}/fixtures', [SeasonController::class, 'fixtures']);
    
    // Games within seasons
    Route::get('/{season}/games', [GameController::class, 'index']);
    Route::get('/{season}/games/week/{week}', [GameController::class, 'week']);
    Route::post('/{season}/games/week/{week}/simulate', [GameController::class, 'simulateWeek']);
    Route::post('/{season}/simulate', [GameController::class, 'simulateSeason']);
    Route::get('/{season}/predictions', [GameController::class, 'predictions']);
});

// Individual Games API Routes
Route::prefix('games')->group(function () {
    Route::get('/{game}', [GameController::class, 'show']);
    Route::put('/{game}/result', [GameController::class, 'updateResult']);
    Route::post('/{game}/simulate', [GameController::class, 'simulate']);
    Route::put('/{game}/reset', [GameController::class, 'reset']);
});

// Health check route
// Auto-play and Enhanced Simulation
Route::prefix('autoplay')->group(function () {
    Route::get('/options', [AutoPlayController::class, 'getOptions']);
    Route::post('/seasons/{season}/start', [AutoPlayController::class, 'start']);
    Route::post('/seasons/{season}/continue', [AutoPlayController::class, 'continue']);
    Route::post('/seasons/{season}/stop', [AutoPlayController::class, 'stop']);
});

Route::prefix('advanced')->group(function () {
    Route::get('/seasons/{season}/predictions', [AutoPlayController::class, 'getAdvancedPredictions']);
    Route::post('/seasons/{season}/simulate', [AutoPlayController::class, 'simulateEnhanced']);
    Route::get('/seasons/{season}/weeks/{week}/analytics', [AutoPlayController::class, 'getWeekAnalytics']);
});

// Health check
Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'Football League Simulation API is running',
        'timestamp' => now(),
        'version' => '1.0.0'
    ]);
});
