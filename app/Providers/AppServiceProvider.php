<?php

namespace App\Providers;

use App\Domain\Game\Services\DiceRoller;
use App\Infrastructure\Dice\RandomDiceRoller;
use App\Support\PlayerSession;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DiceRoller::class, RandomDiceRoller::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureHttps();
        $this->configureRateLimiting();
        $this->ensureDatabaseSchema();
    }

    /**
     * En Wasmer Hobby no hay cron jobs ni SSH: inicializa el esquema en el
     * primer arranque. Solo se activa con PARCHIS_AUTO_MIGRATE=true.
     */
    protected function ensureDatabaseSchema(): void
    {
        if (! config('parchis.auto_migrate') || app()->runningInConsole()) {
            return;
        }

        try {
            if (! Schema::hasTable('games')) {
                Artisan::call('migrate', ['--force' => true]);
            }
        } catch (Throwable $exception) {
            Log::error('No se pudo inicializar el esquema de la base de datos.', [
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Fuerza HTTPS en los enlaces generados cuando el proxy termina TLS.
     */
    protected function configureHttps(): void
    {
        if (config('app.force_https') === true) {
            URL::forceScheme('https');
        }
    }

    /**
     * Limita las acciones sensibles sin login (crear sala, unirse y jugar).
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('room-create', fn (Request $request): Limit => Limit::perMinute(10)
            ->by($request->ip() ?? 'unknown'));

        RateLimiter::for('room-join', fn (Request $request): Limit => Limit::perMinute(20)
            ->by($request->ip() ?? 'unknown'));

        RateLimiter::for('game-action', fn (Request $request): Limit => Limit::perMinute(120)
            ->by(PlayerSession::tokenFor(null, $request).'|'.($request->ip() ?? 'unknown')));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
