<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Season extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'status',
        'is_current',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_current' => 'boolean',
    ];

    /**
     * Get all games for this season
     */
    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }

    /**
     * Get the current active season
     */
    public static function getCurrent()
    {
        return static::where('is_current', true)->first();
    }

    /**
     * Set this season as the current season
     */
    public function setCurrent()
    {
        // Remove current flag from all other seasons
        static::where('is_current', true)->update(['is_current' => false]);
        
        // Set this season as current
        $this->update(['is_current' => true, 'status' => 'active']);
    }

    /**
     * Get league standings for this season
     */
    public function getStandings()
    {
        $teams = Team::where('is_active', true)->get();
        $standings = [];

        foreach ($teams as $team) {
            $stats = $team->getStatsForSeason($this->id);
            $standings[] = array_merge(['team' => $team], $stats);
        }

        // Sort by Premier League rules: Points, Goal Difference, Goals For
        usort($standings, function ($a, $b) {
            if ($a['points'] !== $b['points']) {
                return $b['points'] <=> $a['points']; // Higher points first
            }
            
            if ($a['goal_difference'] !== $b['goal_difference']) {
                return $b['goal_difference'] <=> $a['goal_difference']; // Better GD first
            }
            
            return $b['goals_for'] <=> $a['goals_for']; // More goals first
        });

        // Add position numbers
        foreach ($standings as $index => &$standing) {
            $standing['position'] = $index + 1;
        }

        return $standings;
    }

    /**
     * Check if the season is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Get completion percentage of the season
     */
    public function getCompletionPercentage(): float
    {
        $totalGames = $this->games()->count();
        $completedGames = $this->games()->where('status', 'completed')->count();

        if ($totalGames === 0) {
            return 0;
        }

        return round(($completedGames / $totalGames) * 100, 1);
    }
}
