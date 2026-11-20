<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('winner_player_id')->nullable();
            $table->unsignedTinyInteger('board_arms');
            $table->unsignedInteger('duration_seconds');
            $table->unsignedInteger('turns');
            $table->unsignedInteger('total_rolls');
            $table->unsignedInteger('total_captures');
            $table->json('ranking');
            $table->json('payload');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_results');
    }
};
