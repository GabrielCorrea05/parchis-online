<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('type', 40);
            $table->json('payload');
            $table->timestamp('created_at')->nullable();

            $table->unique(['game_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_events');
    }
};
