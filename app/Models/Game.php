<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Game extends Model
{
    use HasFactory;

    protected $fillable = [
        'season_id',
        'home_team_id',
        'away_team_id',
        'home_goals',
        'away_goals',
        'week',
        'status',
        'played_at',
    ];

    protected $casts = [
        'home_goals' => 'integer',
        'away_goals' => 'integer',
        'week' => 'integer',
        'played_at' => 'datetime',
    ];

    /**
     * Get the season this game belongs to
     */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /**
     * Get the home team
     */
    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    /**
     * Get the away team
     */
    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    /**
     * Check if the game is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Get the winner of the game (returns Team model or null for draw)
     */
    public function getWinner()
    {
        if (!$this->isCompleted()) {
            return null;
        }

        if ($this->home_goals > $this->away_goals) {
            return $this->homeTeam;
        } elseif ($this->away_goals > $this->home_goals) {
            return $this->awayTeam;
        }

        return null; // Draw
    }

    /**
     * Check if the game is a draw
     */
    public function isDraw(): bool
    {
        return $this->isCompleted() && $this->home_goals === $this->away_goals;
    }

    /**
     * Get the result string (e.g., "2-1", "1-1")
     */
    public function getResultString(): string
    {
        if (!$this->isCompleted()) {
            return 'vs';
        }

        return $this->home_goals . '-' . $this->away_goals;
    }

    /**
     * Complete the game with scores
     */
    public function completeGame(int $homeGoals, int $awayGoals): void
    {
        $this->update([
            'home_goals' => $homeGoals,
            'away_goals' => $awayGoals,
            'status' => 'completed',
            'played_at' => Carbon::now(),
        ]);
    }
}
