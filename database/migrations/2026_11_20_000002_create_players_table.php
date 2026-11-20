<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('nickname', 24);
            $table->string('color', 16);
            $table->string('token_hash', 64);
            $table->boolean('is_host')->default(false);
            $table->boolean('is_connected')->default(true);
            $table->unsignedTinyInteger('seat');
            $table->timestamps();

            $table->unique(['game_id', 'color']);
            $table->unique(['game_id', 'token_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
