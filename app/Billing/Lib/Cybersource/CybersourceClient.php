<?php

namespace App\Billing\Lib\Cybersource;

use Exception;
use RuntimeException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\PendingRequest;
use Livewirez\Billing\Enums\RequestMethod;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use App\Billing\Lib\Card\Support\Jwt;

/**
 * Signed REST client for Cybersource / Visa Acceptance Platform.
 *
 * Retry policy:
 *  - GET requests retry on connection failures only.
 *  - POST requests are NEVER retried automatically. A timed-out
 *    POST /pts/v2/payments may already have charged the card; retrying it
 *    is how double charges happen. Reconcile with retrieveTransaction()
 *    instead.
 *  - HTTP 4xx/5xx are returned, not thrown: the payments API returns
 *    useful bodies (status/reason/message) on 400 and 502.
 */
class CybersourceClient
{
    public function __construct(protected array $config = [])
    {
        $this->config = $config === [] ? config('billing.providers.cybersource', []) : $config;
    }

    public function config(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->config : data_get($this->config, $key, $default);
    }

    public function baseUrl(): string
    {
        return rtrim($this->config['base_url'][$this->config['environment']], '/');
    }

    public function makeRequest(RequestMethod $method, string $uri, array $payload = [], bool $retryNotFound = false): Response | PromiseInterface
    {
        $request = Http::baseUrl(
            $this->config['base_url'][$this->config['environment']]
        )->withHeaders(
            $headers = new Headers($this->config)
            ->generate($method, $uri, $payload)
        )->retry(
            $retryNotFound ? [1000, 2000, 4000, 8000] : [300, 600],
            1000,
            function (Exception $e, PendingRequest $request) use ($uri) {
                Log::warning('Retry CybersourceClient::makeRequest: '. __METHOD__ . $uri, [
                    'error' => $e->getMessage(),
                    ...$e instanceof RequestException ? [
                        'status' => $e->response->status(),
                        'body' => $e->response->body()
                    ] : []
                ]);
                return $e instanceof ConnectionException || (
                    $e instanceof RequestException &&
                    in_array($e->response->status(), [404])
                ); // because it throws a 404 error sometimes 
            }
        )->throw(function (Response $r, RequestException $e) use ($uri) {
            Log::error('Throw CybersourceClient::makeRequest: '. __METHOD__ . $uri, [
                'response' => $r,
                'json_response' => $r->json(),
                'error' => $e->getMessage(),
                'status' => $r->status(),
                'body' => $r->body()  // Add this to see the full response body
            ]);
        })->truncateExceptionsAt(5000);

        return match ($method) {
            RequestMethod::Get => $request->get($uri, $payload),
            RequestMethod::Put => $request->put($uri, $payload),
            RequestMethod::Patch => $request->patch($uri, $payload),
            RequestMethod::Post => $request->post($uri, $payload),
            default => $request->post($uri, $payload),
        };
    }

    /**
     * @param bool $retryNotFound retry 404s too. Only for reads of resources that are
     *                            eventually consistent (Transaction Search lags a few
     *                            seconds behind a new payment, which is the "random 404").
     */
    public function get(string $path, array $query = [], bool $retryNotFound = false): Response
    {
        return $this->makeRequest(RequestMethod::Get, $path, $query, $retryNotFound);
    }

    public function post(string $path, array $payload): Response
    {
        return $this->makeRequest(RequestMethod::Post, $path, $payload);
    }

    /**
     * Fetch a Flex public key by kid and cache it. Used to verify capture contexts.
     */
    public function flexPublicKey(string $kid): array
    {
        $cacheKey = 'cybersource:flex-key:' . $this->config['environment'] . ':' . $kid;

        return Cache::remember($cacheKey, now()->addHours(12), function () use ($kid) {
            $response = $this->get('/flex/v2/public-keys/' . rawurlencode($kid));

            if (! $response->successful() || ! is_array($response->json())) {
                throw new RuntimeException("Unable to fetch Cybersource public key [{$kid}].");
            }

            return $response->json();
        });
    }

    /**
     * Verify a capture context JWT returned by /microform/v2/sessions or
     * /up/v1/capture-contexts, and return its decoded payload.
     */
    public function verifyCaptureContext(string $captureContext): array
    {
        $decoded = Jwt::decode($captureContext);
        $kid = $decoded['header']['kid'] ?? throw new RuntimeException('Capture context has no kid.');

        $payload = Jwt::verifyRs256($captureContext, $this->flexPublicKey($kid));

        if (! isset($payload['flx']['jwk'])) {
            throw new RuntimeException('Capture context does not contain flx.jwk.');
        }

        return $payload;
    }

    /**
     * Verify a transient token against the one-time key embedded in the
     * (already verified) capture context.
     */
    public function verifyTransientToken(string $transientToken, array $captureContextPayload): array
    {
        return Jwt::verifyRs256($transientToken, $captureContextPayload['flx']['jwk']);
    }

    public function retrieveTransaction(string $id): Response
    {
        return $this->get('/tss/v2/transactions/' . rawurlencode($id), retryNotFound: true);
    }
}
