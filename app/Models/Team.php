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

    /**
     * Get team's form (last 5 games results)
     */
    public function getRecentForm($seasonId = null, $limit = 5): array
    {
        $query = $this->games()->where('status', 'completed');
        
        if ($seasonId) {
            $query->where('season_id', $seasonId);
        }
        
        $recentGames = $query->orderBy('played_at', 'desc')
            ->limit($limit)
            ->get();

        $form = [];
        foreach ($recentGames as $game) {
            if ($game->home_team_id == $this->id) {
                // This team was home
                if ($game->home_goals > $game->away_goals) {
                    $form[] = 'W';
                } elseif ($game->home_goals == $game->away_goals) {
                    $form[] = 'D';
                } else {
                    $form[] = 'L';
                }
            } else {
                // This team was away
                if ($game->away_goals > $game->home_goals) {
                    $form[] = 'W';
                } elseif ($game->away_goals == $game->home_goals) {
                    $form[] = 'D';
                } else {
                    $form[] = 'L';
                }
            }
        }

        return array_reverse($form); // Return in chronological order
    }

    /**
     * Get team's head-to-head record against another team
     */
    public function getHeadToHeadRecord(Team $opponent, $seasonId = null): array
    {
        $query = Game::where('status', 'completed')
            ->where(function ($q) use ($opponent) {
                $q->where(function ($subQ) use ($opponent) {
                    $subQ->where('home_team_id', $this->id)
                         ->where('away_team_id', $opponent->id);
                })->orWhere(function ($subQ) use ($opponent) {
                    $subQ->where('home_team_id', $opponent->id)
                         ->where('away_team_id', $this->id);
                });
            });

        if ($seasonId) {
            $query->where('season_id', $seasonId);
        }

        $games = $query->get();

        $record = [
            'played' => 0,
            'won' => 0,
            'drawn' => 0,
            'lost' => 0,
            'goals_for' => 0,
            'goals_against' => 0,
        ];

        foreach ($games as $game) {
            $record['played']++;
            
            if ($game->home_team_id == $this->id) {
                $record['goals_for'] += $game->home_goals;
                $record['goals_against'] += $game->away_goals;
                
                if ($game->home_goals > $game->away_goals) {
                    $record['won']++;
                } elseif ($game->home_goals == $game->away_goals) {
                    $record['drawn']++;
                } else {
                    $record['lost']++;
                }
            } else {
                $record['goals_for'] += $game->away_goals;
                $record['goals_against'] += $game->home_goals;
                
                if ($game->away_goals > $game->home_goals) {
                    $record['won']++;
                } elseif ($game->away_goals == $game->home_goals) {
                    $record['drawn']++;
                } else {
                    $record['lost']++;
                }
            }
        }

        return $record;
    }

    /**
     * Get team's strength rating as a descriptive string
     */
    public function getStrengthDescription(): string
    {
        return match (true) {
            $this->strength >= 90 => 'World Class',
            $this->strength >= 80 => 'Excellent',
            $this->strength >= 70 => 'Good',
            $this->strength >= 60 => 'Average',
            $this->strength >= 50 => 'Below Average',
            default => 'Poor'
        };
    }

    /**
     * Calculate win percentage
     */
    public function getWinPercentage($seasonId = null): float
    {
        $stats = $this->getStatsForSeason($seasonId);
        
        if ($stats['played'] === 0) {
            return 0.0;
        }

        return round(($stats['won'] / $stats['played']) * 100, 1);
    }

    /**
     * Get team's average goals per game
     */
    public function getAverageGoalsPerGame($seasonId = null): float
    {
        $stats = $this->getStatsForSeason($seasonId);
        
        if ($stats['played'] === 0) {
            return 0.0;
        }

        return round($stats['goals_for'] / $stats['played'], 2);
    }
}
