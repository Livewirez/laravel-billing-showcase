<?php

namespace App\Billing\Lib\Card\Drivers;

use App\Billing\Lib\Card\Contracts\CardGateway;
use App\Billing\Lib\Card\Data\CardOrder;
use App\Billing\Lib\Card\Data\CardSession;
use App\Billing\Lib\Card\Data\GatewayResult;
use App\Billing\Lib\Card\Enums\GatewayStatus;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Mastercard Gateway (MPGS) Hosted Session.
 *
 * This is the "Mastercard direct" option: Mastercard runs the gateway, but
 * the merchant account still comes from an acquiring bank that offers MPGS
 * (it issues your merchant id, API password and regional host).
 *
 * Every operation is a PUT to /order/{orderId}/transaction/{transactionId},
 * so retrying with the same ids is idempotent, unlike Cybersource POSTs.
 *
 * 3-D Secure: for UK/EEA cards add INITIATE_AUTHENTICATION / AUTHENTICATE_PAYER
 * before PAY (or use your acquirer's hosted checkout). Not implemented here.
 */
class MastercardGateway implements CardGateway
{
    public function __construct(protected array $config, protected array $options = []) {}

    public function name(): string
    {
        return $this->options['name'];
    }

    public function createPartialSession(): array
    {
        throw new \Exception('Unsupported Function');
    }

    public function createSession(CardOrder $order): array
    {
        $session = $this->http()->post('session', ['session' => ['authenticationLimit' => 5]]);
        $sessionId = $session->json('session.id');

        if (! $session->successful() || ! $sessionId) {
            throw new RuntimeException('Mastercard Gateway session failed: ' . $session->body());
        }

        // Pin the amount on the session server-side.
        $update = $this->http()->put("session/{$sessionId}", [
            'order' => ['amount' => $order->amount, 'currency' => $order->currency],
        ]);

        if (! $update->successful()) {
            throw new RuntimeException('Mastercard Gateway session update failed: ' . $update->body());
        }

        return [
            'client' => [
                'type' => 'mastercard_hosted_session',
                'session_id' => $sessionId,
                'session_js' => sprintf(
                    '%s/form/version/%s/merchant/%s/session.js',
                    $this->baseUrl(), $this->version(), $this->config['merchant_id']
                ),
            ],
            'state' => ['session_id' => $sessionId],
            'ttl' => 15 * 60,
        ];
    }

    public function charge(CardOrder $order, CardSession $session, string $token): GatewayResult
    {
        // The browser just confirms the session was filled; the server uses its own copy.
        if (! hash_equals($session->state['session_id'], $token)) {
            return GatewayResult::failed($this->name(), 'Session mismatch.', 'SESSION_MISMATCH');
        }

        $response = $this->transaction($order->orderNumber, $order->attemptId, [
            'apiOperation' => $order->capture ? 'PAY' : 'AUTHORIZE',
            'order' => [
                'amount' => $order->amount,
                'currency' => $order->currency,
                'reference' => $order->orderNumber,
            ],
            'session' => ['id' => $session->state['session_id']],
            'sourceOfFunds' => ['type' => 'CARD'],
            'billing' => array_filter([
                'address' => array_filter([
                    'street' => $order->billTo['address1'] ?? null,
                    'city' => $order->billTo['locality'] ?? null,
                    'postcodeZip' => $order->billTo['postalCode'] ?? null,
                    // MPGS expects ISO 3166 alpha-3; only send if you store alpha-3.
                ]),
            ]),
            'customer' => array_filter([
                'email' => $order->billTo['email'] ?? null,
                'firstName' => $order->billTo['firstName'] ?? null,
                'lastName' => $order->billTo['lastName'] ?? null,
            ]),
        ]);

        return $this->map($response, $order->orderNumber, $order->capture);
    }

    public function capture(string $reference, CardOrder $order, ?string $operationId = null, array $metadata = []): GatewayResult
    {
        return $this->map($this->transaction($reference, $operationId ?? 'capture-' . $order->attemptId, [
            'apiOperation' => 'CAPTURE',
            'transaction' => ['amount' => $order->amount, 'currency' => $order->currency],
        ]), $reference, true);
    }

    public function refund(string $reference, string $amount, string $currency, string $orderNumber, ?string $operationId = null, array $metadata = []): GatewayResult
    {
        return $this->map($this->transaction($reference, $operationId ?? 'refund-' . bin2hex(random_bytes(6)), [
            'apiOperation' => 'REFUND',
            'transaction' => ['amount' => $amount, 'currency' => $currency],
        ]), $reference, true);
    }

    public function void(string $reference, string $orderNumber, ?string $operationId = null, array $metadata = []): GatewayResult
    {
        $order = $this->http()->get("order/{$reference}")->json() ?? [];
        $target = collect($order['transaction'] ?? [])
            ->first(fn ($t) => in_array(data_get($t, 'transaction.type'), ['PAYMENT', 'AUTHORIZATION', 'CAPTURE'], true));

        if (! $target) {
            return GatewayResult::failed($this->name(), 'Nothing to void on this order.', 'NO_TARGET', $order);
        }

        return $this->map($this->transaction($reference, $operationId ?? 'void-' . data_get($target, 'transaction.id'), [
            'apiOperation' => 'VOID',
            'transaction' => ['targetTransactionId' => data_get($target, 'transaction.id')],
        ]), $reference, true);
    }

    public function retrieve(string $reference): GatewayResult
    {
        $response = $this->http()->get("order/{$reference}");
        $json = $response->json() ?? [];

        $status = match (data_get($json, 'status')) {
            'CAPTURED' => GatewayStatus::Captured,
            'AUTHORIZED' => GatewayStatus::Authorized,
            'REFUNDED', 'PARTIALLY_REFUNDED' => GatewayStatus::Refunded,
            'CANCELLED', 'AUTHORIZED_CANCELLED' => GatewayStatus::Voided,
            'FAILED', 'DECLINED' => GatewayStatus::Declined,
            default => $response->successful() ? GatewayStatus::Pending : GatewayStatus::Failed,
        };

        return new GatewayResult(
            $this->name(),
            $status,
            reference: $reference,
            message: data_get($json, 'status') ?? data_get($json, 'error.explanation'),
            card: $this->card($json),
            raw: $json,
            httpStatus: $response->status(),
        );
    }

    protected function transaction(string $orderId, string $transactionId, array $body): Response
    {
        return $this->http()->put(
            'order/' . rawurlencode($orderId) . '/transaction/' . rawurlencode($transactionId),
            $body
        );
    }

    protected function map(Response $response, string $orderId, bool $captured): GatewayResult
    {
        $json = $response->json() ?? [];
        $gatewayCode = data_get($json, 'response.gatewayCode');

        $status = match (data_get($json, 'result')) {
            'SUCCESS' => match (data_get($json, 'transaction.type')) {
                'REFUND' => GatewayStatus::Refunded,
                'VOID_AUTHORIZATION', 'VOID_CAPTURE', 'VOID_PAYMENT', 'VOID_REFUND' => GatewayStatus::Voided,
                'AUTHORIZATION' => GatewayStatus::Authorized,
                default => $captured ? GatewayStatus::Captured : GatewayStatus::Authorized,
            },
            'PENDING' => GatewayStatus::Pending,
            'FAILURE' => GatewayStatus::Declined,
            default => $response->serverError() ? GatewayStatus::Pending : GatewayStatus::Failed,
        };

        if ($gatewayCode === 'PENDING' || $gatewayCode === 'SUBMITTED') {
            $status = GatewayStatus::Pending;
        }

        return new GatewayResult(
            $this->name(),
            $status,
            reference: $orderId,
            transactionId: data_get($json, 'transaction.receipt') ?? data_get($json, 'transaction.id'),
            approvalCode: data_get($json, 'transaction.authorizationCode'),
            message: data_get($json, 'error.explanation') ?? $gatewayCode,
            reason: data_get($json, 'error.cause') ?? $gatewayCode,
            card: $this->card($json),
            raw: $json,
            httpStatus: $response->status(),
        );
    }

    protected function card(array $json): array
    {
        return array_filter([
            'masked' => data_get($json, 'sourceOfFunds.provided.card.number'),
            'brand' => data_get($json, 'sourceOfFunds.provided.card.brand'),
            'exp_month' => data_get($json, 'sourceOfFunds.provided.card.expiry.month'),
            'exp_year' => data_get($json, 'sourceOfFunds.provided.card.expiry.year'),
        ]);
    }

    protected function http(): PendingRequest
    {
        return Http::withBasicAuth('merchant.' . $this->config['merchant_id'], $this->config['api_password'])
            ->baseUrl(sprintf(
                '%s/api/rest/version/%s/merchant/%s/',
                $this->baseUrl(), $this->version(), $this->config['merchant_id']
            ))
            ->acceptJson()
            ->asJson()
            ->timeout(60)
            // Safe: every write is a PUT keyed by order/transaction id.
            ->retry([500, 1500], 0, fn (Exception $e) => $e instanceof ConnectionException, throw: false);
    }

    protected function baseUrl(): string
    {
        return rtrim($this->config['base_url'], '/');
    }

    protected function version(): string
    {
        return (string) ($this->config['api_version'] ?? '100');
    }

    public function handleWebhook(Request $request): SymfonyResponse
    {
        return response()->json();
    }
}
