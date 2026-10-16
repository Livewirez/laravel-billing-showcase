<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetTeamUrlDefaults;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

$apiRoutes = [
    __DIR__.'/../routes/api.php',
    __DIR__.'/../routes/orders.php',
    __DIR__.'/../routes/subscriptions.php',
];

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: $apiRoutes,
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: fn (Application $app) => Route::middleware([])->group( __DIR__.'/../routes/webhooks.php')
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->statefulApi();

        $middleware->throttleApi();

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SetTeamUrlDefaults::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule) {
        // $schedule->command('backup:monitor')->daily()->at('2:10');

        $schedule->command('telescope:prune --hours=96')->daily();
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $th) => $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->dontTruncateRequestExceptions();
    
    })->create();
