<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained('seasons')->onDelete('cascade');
            $table->foreignId('home_team_id')->constrained('teams')->onDelete('cascade');
            $table->foreignId('away_team_id')->constrained('teams')->onDelete('cascade');
            $table->integer('home_goals')->nullable();
            $table->integer('away_goals')->nullable();
            $table->integer('week'); // Game week (1-6 for round-robin tournament)
            $table->enum('status', ['scheduled', 'completed'])->default('scheduled');
            $table->datetime('played_at')->nullable();
            $table->timestamps();
            
            // Ensure unique game per week per team combination
            $table->unique(['season_id', 'home_team_id', 'away_team_id', 'week']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
