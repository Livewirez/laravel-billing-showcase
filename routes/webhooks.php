<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\CardWebhookMiddleware;
use App\Http\Middleware\CardHealthMiddleware;

Route::prefix('webhooks/payments')->name('webhooks.')->group(function () {

    Route::post('card', fn (
        \Illuminate\Http\Request $request, 
        \Livewirez\Billing\Actions\HandleWebhooks $action
    ) => $action->handle($request, \Livewirez\Billing\Enums\PaymentProvider::Card))
        ->middleware([CardWebhookMiddleware::class]) 
        ->name('card.handle');
    
    Route::post('card/health', fn (
        \Illuminate\Http\Request $request, 
        \Livewirez\Billing\Actions\HandleWebhooks $action
    ) => $action->handle($request, \Livewirez\Billing\Enums\PaymentProvider::Card))
        ->middleware([CardHealthMiddleware::class]) 
        ->name('card.health');
})->withoutMiddleware('api');
