<?php

use App\Console\Commands\CleanupAbandonedRooms;
use App\Domain\Game\Enums\GameStatus;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\Player;
use App\Support\PlayerSession;
use Database\Seeders\DemoGameSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

it('crea una partida demo terminada consultable por el endpoint de resultado', function () {
    $this->seed(DemoGameSeeder::class);

    $game = Game::where('code', DemoGameSeeder::CODE)->firstOrFail();

    expect($game->status)->toBe(GameStatus::Finished)
        ->and($game->arms)->toBe(4)
        ->and($game->players()->count())->toBe(4)
        ->and($game->events()->count())->toBe(3)
        ->and($game->result)->not->toBeNull();

    $this->withHeader(PlayerSession::TOKEN_HEADER, DemoGameSeeder::TOKEN_PREFIX.'1')
        ->getJson('/api/games/DEMO/result')
        ->assertOk()
        ->assertJsonPath('status', 'finished')
        ->assertJsonPath('winner.nickname', 'Lucía')
        ->assertJsonPath('ranking.0.pieces_meta', 4);
});

it('borra solo las salas en espera abandonadas con sus jugadores y eventos', function () {
    $old = Game::create(['code' => 'OLD1', 'status' => GameStatus::Waiting, 'version' => 0]);
    $recent = Game::create(['code' => 'NEW1', 'status' => GameStatus::Waiting, 'version' => 0]);
    $finished = Game::create([
        'code' => 'OLD2',
        'status' => GameStatus::Finished,
        'version' => 1,
        'arms' => 4,
        'started_at' => now()->subDay(),
        'finished_at' => now(),
    ]);

    $old->players()->create([
        'nickname' => 'Ana', 'color' => 'red', 'token_hash' => 'hash',
        'is_host' => true, 'is_connected' => true, 'seat' => 0,
    ]);
    $old->events()->create(['sequence' => 1, 'type' => 'game_started', 'payload' => []]);

    DB::table('games')->where('id', $old->id)->update(['updated_at' => now()->subHours(48)]);
    DB::table('games')->where('id', $finished->id)->update(['updated_at' => now()->subHours(48)]);

    Artisan::call(CleanupAbandonedRooms::class);

    expect(Game::find($old->id))->toBeNull()
        ->and(Player::where('game_id', $old->id)->exists())->toBeFalse()
        ->and(GameEvent::where('game_id', $old->id)->exists())->toBeFalse()
        ->and(Game::find($recent->id))->not->toBeNull()
        ->and(Game::find($finished->id))->not->toBeNull();
});

it('inyecta la configuración pública de Pusher sin exponer el secreto', function () {
    config()->set('broadcasting.default', 'pusher');
    config()->set('broadcasting.connections.pusher.key', 'public-key');
    config()->set('broadcasting.connections.pusher.secret', 'super-secreto');
    config()->set('broadcasting.connections.pusher.options.cluster', 'mt1');
    config()->set('broadcasting.connections.pusher.options.host', 'api-mt1.pusher.com');
    config()->set('broadcasting.connections.pusher.options.port', 443);
    config()->set('broadcasting.connections.pusher.options.scheme', 'https');

    $this->get('/')
        ->assertOk()
        ->assertSee('__PARCHIS_BROADCAST__', false)
        ->assertSee('public-key', false)
        ->assertSee('api-mt1.pusher.com', false)
        ->assertDontSee('super-secreto', false);
});

it('limita la creación de salas por dirección IP', function () {
    Event::fake();

    for ($i = 0; $i < 10; $i++) {
        $this->post('/rooms', ['nickname' => "Jugador{$i}", 'color' => 'red'])->assertRedirect();
    }

    $this->post('/rooms', ['nickname' => 'Excedido', 'color' => 'blue'])->assertStatus(429);
});
