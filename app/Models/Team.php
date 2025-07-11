<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'city',
        'strength',
        'logo_url',
        'primary_color',
        'secondary_color',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'strength' => 'integer',
    ];

    /**
     * Get all games where this team is the home team
     */
    public function homeGames(): HasMany
    {
        return $this->hasMany(Game::class, 'home_team_id');
    }

    /**
     * Get all games where this team is the away team
     */
    public function awayGames(): HasMany
    {
        return $this->hasMany(Game::class, 'away_team_id');
    }

    /**
     * Get all games for this team (home and away)
     */
    public function games()
    {
        return Game::where('home_team_id', $this->id)
            ->orWhere('away_team_id', $this->id);
    }

    /**
     * Calculate team statistics for a season
     */
    public function getStatsForSeason($seasonId = null)
    {
        $query = $this->games()->where('status', 'completed');
        
        if ($seasonId) {
            $query->where('season_id', $seasonId);
        }
        
        $games = $query->get();
        
        $stats = [
            'played' => 0,
            'won' => 0,
            'drawn' => 0,
            'lost' => 0,
            'goals_for' => 0,
            'goals_against' => 0,
            'goal_difference' => 0,
            'points' => 0,
        ];
        
        foreach ($games as $game) {
            $stats['played']++;
            
            if ($game->home_team_id == $this->id) {
                // This team is home
                $stats['goals_for'] += $game->home_goals;
                $stats['goals_against'] += $game->away_goals;
                
                if ($game->home_goals > $game->away_goals) {
                    $stats['won']++;
                    $stats['points'] += 3;
                } elseif ($game->home_goals == $game->away_goals) {
                    $stats['drawn']++;
                    $stats['points'] += 1;
                } else {
                    $stats['lost']++;
                }
            } else {
                // This team is away
                $stats['goals_for'] += $game->away_goals;
                $stats['goals_against'] += $game->home_goals;
                
                if ($game->away_goals > $game->home_goals) {
                    $stats['won']++;
                    $stats['points'] += 3;
                } elseif ($game->away_goals == $game->home_goals) {
                    $stats['drawn']++;
                    $stats['points'] += 1;
                } else {
                    $stats['lost']++;
                }
            }
        }
        
        $stats['goal_difference'] = $stats['goals_for'] - $stats['goals_against'];
        
        return $stats;
    }
}
