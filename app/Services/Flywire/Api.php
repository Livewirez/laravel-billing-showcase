<?php

namespace App\Services\Flywire;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\ConnectionException;

class Api
{
    protected const string BASE_URL = 'https://api-platform.flywire.com';

    protected const string SANDBOX_BASE_URL = 'https://api-platform-sandbox.flywire.com';
}   