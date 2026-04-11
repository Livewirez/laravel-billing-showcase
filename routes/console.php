<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('setup:app', function () {
    if ($token = \App\Tests\DBConfig::createTestData()) {
        $this->info("Token: {$token}"); // check
    }
})->purpose('Create sample data');