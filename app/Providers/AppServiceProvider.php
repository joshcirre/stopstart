<?php

namespace App\Providers;

use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
    }

    /**
     * Configure the application's rate limiters.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('remote-commands', function (Request $request): Limit {
            $project = $request->route('project');

            return Limit::perMinute(120)->by(
                $project instanceof Project ? 'remote:'.$project->id : $request->ip()
            );
        });

        RateLimiter::for('dub-layers', function (Request $request): Limit {
            $project = $request->route('project');

            return Limit::perMinute(30)->by(
                $project instanceof Project ? 'dub-layers:'.$project->id : $request->ip()
            );
        });

        RateLimiter::for('dub-exports', function (Request $request): Limit {
            $project = $request->route('project');

            return Limit::perMinute(6)->by(
                $project instanceof Project ? 'dub-exports:'.$project->id : $request->ip()
            );
        });
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
