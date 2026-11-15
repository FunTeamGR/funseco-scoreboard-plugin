<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lifetime round points per player (Top Roundscore): Rounds and Cup races add their points here.
        Schema::create('scoreboard_round_points', function (Blueprint $table) {
            $table->unsignedBigInteger('player_id')->primary();
            $table->foreign('player_id')->references('id')->on('players')->cascadeOnDelete();
            $table->unsignedBigInteger('points')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scoreboard_round_points');
    }
};
