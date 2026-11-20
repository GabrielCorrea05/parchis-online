<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->string('code', 4)->unique();
            $table->string('status', 20)->default('waiting');
            $table->unsignedBigInteger('host_player_id')->nullable();
            $table->unsignedTinyInteger('arms')->nullable();
            $table->json('board_config')->nullable();
            $table->json('state')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->unsignedBigInteger('winner_player_id')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
