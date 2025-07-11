<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Team;
use App\Models\Season;
use App\Models\Game;
use Carbon\Carbon;

class FootballLeagueSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing data (in order due to foreign keys)
        Game::query()->delete();
        Season::query()->delete();
        Team::query()->delete();

        // Create 4 teams with different strengths
        $teams = [
            [
                'name' => 'Manchester City',
                'city' => 'Manchester',
                'strength' => 85,
                'primary_color' => '#6CABDD',
                'secondary_color' => '#FFFFFF',
            ],
            [
                'name' => 'Liverpool FC',
                'city' => 'Liverpool',
                'strength' => 82,
                'primary_color' => '#C8102E',
                'secondary_color' => '#FFFFFF',
            ],
            [
                'name' => 'Arsenal FC',
                'city' => 'London',
                'strength' => 78,
                'primary_color' => '#EF0107',
                'secondary_color' => '#FFFFFF',
            ],
            [
                'name' => 'Chelsea FC',
                'city' => 'London',
                'strength' => 75,
                'primary_color' => '#034694',
                'secondary_color' => '#FFFFFF',
            ],
        ];

        $createdTeams = [];
        foreach ($teams as $teamData) {
            $createdTeams[] = Team::create($teamData);
        }

        // Create current season
        $season = Season::create([
            'name' => '2024/25 Insider Champions League',
            'start_date' => Carbon::now()->subDays(30),
            'status' => 'active',
            'is_current' => true,
        ]);

        // Create round-robin schedule (each team plays every other team twice - home and away)
        $week = 1;
        $games = [];

        // First round (everyone plays everyone once)
        for ($i = 0; $i < count($createdTeams); $i++) {
            for ($j = $i + 1; $j < count($createdTeams); $j++) {
                // Home game
                $games[] = [
                    'season_id' => $season->id,
                    'home_team_id' => $createdTeams[$i]->id,
                    'away_team_id' => $createdTeams[$j]->id,
                    'week' => $week,
                    'status' => 'scheduled',
                ];
                $week++;

                // Away game (reverse)
                $games[] = [
                    'season_id' => $season->id,
                    'home_team_id' => $createdTeams[$j]->id,
                    'away_team_id' => $createdTeams[$i]->id,
                    'week' => $week,
                    'status' => 'scheduled',
                ];
                $week++;
            }
        }

        // Create all games
        foreach ($games as $gameData) {
            Game::create($gameData);
        }

        $this->command->info('Created ' . count($createdTeams) . ' teams');
        $this->command->info('Created 1 season: ' . $season->name);
        $this->command->info('Created ' . count($games) . ' games');
        $this->command->info('Database seeded successfully!');
    }
}
