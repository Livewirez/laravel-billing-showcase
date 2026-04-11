<?php

namespace App\Providers;


use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewirez\Billing\Enums\PaymentProvider;
use Livewirez\Billing\Facades\Billing;
use Livewirez\Billing\Interfaces\Billable;
use Livewirez\Billing\Providers\CardProvider;
use Livewirez\Billing\Providers\PaddleProvider;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }

        Billing::extend(PaymentProvider::Paddle->value, function (Application $app, array $config) {
            return new PaddleProvider($config);
        });

        Billing::extend(
            PaymentProvider::Card->value, 
            fn (Application $app, array $config) => new CardProvider($config)
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
        $this->configureHttpClientGlobalMiddleware();
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

      /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }

    protected function configureHttpClientGlobalMiddleware(): void
    {
        Http::globalRequestMiddleware(fn (RequestInterface $request) => $request->withHeader(
            'X-Finished-At', now()->toDateTimeString()
        ));

        Http::globalResponseMiddleware(fn (ResponseInterface $response) => $response->withHeader(
            'X-Finished-At', now()->toDateTimeString()
        ));
    }
}
