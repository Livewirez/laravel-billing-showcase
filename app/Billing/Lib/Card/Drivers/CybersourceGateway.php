<?php

namespace App\Billing\Lib\Card\Drivers;

use App\Billing\Lib\Card\Contracts\CardGateway;
use App\Billing\Lib\Card\Data\CardOrder;
use App\Billing\Lib\Card\Data\CardSession;
use App\Billing\Lib\Card\Data\GatewayResult;
use App\Billing\Lib\Card\Enums\GatewayStatus;
use App\Billing\Lib\Cybersource\CybersourceClient;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

/**
 * Shared Cybersource behaviour: transient-token payments and follow-ons.
 * Microform and Unified Checkout differ only in how the session is created
 * and what the browser hands back.
 */
abstract class CybersourceGateway implements CardGateway
{
    public function __construct(
        protected CybersourceClient $client,
        protected array $options = [],
    ) {}

    public function name(): string
    {
        return $this->options['name'];
    }

    /**
     * targetOrigins must be https and hard-coded server side (never taken from the request).
     */
    protected function targetOrigins(): array
    {
        $origins = $this->options['target_origins'] ?? [config('app.url')];

        return array_values(array_map(function (string $origin) {
            $parts = parse_url($origin);

            return 'https://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        }, $origins));
    }

    protected function allowedCardNetworks(): array
    {
        return $this->options['card_networks'] ?? ['VISA', 'MASTERCARD', 'AMEX'];
    }

    protected function getShipToCountries(array $data): array 
    {
        $data = $data === [] ? $this->options : $data;

        foreach([
            data_get($data, 'capture_mandate.ship_to_countries'),
            data_get($data, 'ship_to_countries'),
        ] as $val) {

            if (\is_string($val)) {
                return array_map('trim', explode(',', $val));
            }

            if (\is_array($val)) {
                return $val;
            }

        }

        throw new RuntimeException('No ship_to_countries found in capture_mandate or options.');
    }

    public function createPartialSession(): array
    {
        throw new \Exception('Unsupported Function');
    }

    /**
     * Verify the capture context returned by the sessions endpoint and turn
     * it into the cached session state.
     */
    protected function sessionFromCaptureContext(string $captureContext, array $extraState = []): array
    {
        $payload = $this->client->verifyCaptureContext($captureContext);
        $ctx = $payload['ctx'][0]['data'] ?? [];

        return [
            'client' => [
                'capture_context' => $captureContext,
                'client_library' => $ctx['clientLibrary'] ?? null,
                'client_library_integrity' => $ctx['clientLibraryIntegrity'] ?? null,
            ],
            'state' => [
                'capture_context_payload' => $payload,
                ...$extraState,
            ],
            'ttl' => max(60, (int) ($payload['exp'] ?? time() + 900) - time()),
        ];
    }

    /**
     * Verify a transient token against the session's capture context and pay with it.
     */
    protected function payWithTransientToken(CardOrder $order, CardSession $session, string $token): GatewayResult
    {
        try {
            $tokenPayload = $this->client->verifyTransientToken(
                $token,
                $session->state['capture_context_payload']
            );
        } catch (Throwable $e) {
            return GatewayResult::failed($this->name(), 'Card token could not be verified.', 'TOKEN_INVALID', [
                'error' => $e->getMessage(),
            ]);
        }

        $payload = [
            'clientReferenceInformation' => [
                'code' => $order->orderNumber,
            ],
            'processingInformation' => array_filter([
                'capture' => $order->capture,
                'commerceIndicator' => 'internet',
                // Only skip Decision Manager when explicitly configured to; otherwise
                // your MID's default profile applies.
                'actionList' => ($this->options['decision_manager'] ?? null) === false ? ['DECISION_SKIP'] : null,
            ], fn ($v) => $v !== null),
            'tokenInformation' => [
                'transientTokenJwt' => $token,
            ],
            'orderInformation' => array_filter([
                'amountDetails' => [
                    'totalAmount' => $order->amount,
                    'currency' => $order->currency,
                ],
                'billTo' => $order->billTo ?: null,
            ]),
        ];

        $response = $this->client->post('/pts/v2/payments', $payload);

        return $this->mapPaymentResponse($response, $order->capture, $this->cardFromToken($tokenPayload));
    }
    /**
     * Summary of capture
     * @param string $reference
     * @param CardOrder $order
     * @param mixed $operationId
     * @param array $processingInformation
     * @return GatewayResult
     */
    /*
    *----------------------------------|-----------------------|--------------------|--------------------------------------------
    * Scenario                         | captureSequenceNumber | totalCaptureCount  | Logic Rules
    * ---------------------------------|-----------------------|--------------------|---------------------------------------------
    * Known total shipments            | Current package index | Total shipments    | Example: For the 2nd of 5 captures, pass 2 and 5.
    *
    * Unknown total shipments          | Current package index | Estimated sequence | Set totalCaptureCount at least one higher than
    *                                  |                       | or 99              | captureSequenceNumber, or use 99, until the
    *                                  |                       |                    | final capture.
    *
    * Final capture                    | Final package index   | Same as sequence   | Set both values equal to signal that no further
    *                                  |                       | number             | captures are expected.
    */
    public function capture(string $reference, CardOrder $order, ?string $operationId = null, array $processingInformation = []): GatewayResult
    {
        $response = $this->client->post('/pts/v2/payments/' . rawurlencode($reference) . '/captures', [
            'clientReferenceInformation' => ['code' => $order->orderNumber],
            'processingInformation' => array_replace_recursive([
                'captureOptions' => [
                    'captureSequenceNumber' => 1,
                    'totalCaptureCount' => 1
                ],
            ], $processingInformation),
            'orderInformation' => [
                'amountDetails' => [
                    'totalAmount' => $order->amount,
                    'currency' => $order->currency,
                ]
            ],
        ]);

        return $this->mapPaymentResponse($response, true, pendingMeans: GatewayStatus::Captured);
    }

    public function refund(string $reference, string $amount, string $currency, string $orderNumber, ?string $operationId = null, array $processingInformation = []): GatewayResult
    {
        // A refund must target the capture/sale id, not the auth id when they differ.
        $response = $this->client->post('/pts/v2/payments/' . rawurlencode($reference) . '/refunds', [
            'clientReferenceInformation' => ['code' => $orderNumber],
            'orderInformation' => ['amountDetails' => [
                'totalAmount' => $amount,
                'currency' => $currency,
            ]],
        ]);

        return $this->mapPaymentResponse($response, true, pendingMeans: GatewayStatus::Refunded);
    }

    public function void(string $reference, string $orderNumber, ?string $operationId = null, array $processingInformation = []): GatewayResult
    {
        // Voids work for same-day sales/captures before settlement. For an
        // auth-only payment use /reversals instead (see reverseAuthorization()).
        $response = $this->client->post('/pts/v2/payments/' . rawurlencode($reference) . '/voids', [
            'clientReferenceInformation' => ['code' => $orderNumber],
        ]);

        return $this->mapPaymentResponse($response, true, pendingMeans: GatewayStatus::Voided);
    }

    public function reverseAuthorization(string $reference, string $amount, string $currency, string $orderNumber, array $processingInformation = []): GatewayResult
    {
        $response = $this->client->post('/pts/v2/payments/' . rawurlencode($reference) . '/reversals', [
            'clientReferenceInformation' => ['code' => $orderNumber],
            'reversalInformation' => ['amountDetails' => ['totalAmount' => $amount, 'currency' => $currency]],
        ]);

        return $this->mapPaymentResponse($response, false, pendingMeans: GatewayStatus::Voided);
    }

    public function retrieve(string $reference): GatewayResult
    {
        $response = $this->client->retrieveTransaction($reference);
        $json = $response->json() ?? [];

        if (! $response->successful()) {
            return new GatewayResult(
                $this->name(),
                $response->status() === 404 ? GatewayStatus::Pending : GatewayStatus::Failed,
                reference: $reference,
                message: data_get($json, 'message', 'Transaction lookup failed.'),
                raw: $json,
                httpStatus: $response->status(),
            );
        }

        return new GatewayResult(
            $this->name(),
            $this->statusFromTransaction($json),
            reference: (string) data_get($json, 'id', $reference),
            transactionId: data_get($json, 'processorInformation.networkTransactionId')
                ?? data_get($json, 'processorInformation.transactionId'),
            approvalCode: data_get($json, 'processorInformation.approvalCode'),
            message: data_get($json, 'applicationInformation.status'),
            reason: (string) data_get($json, 'applicationInformation.reasonCode'),
            card: array_filter([
                'last4' => data_get($json, 'paymentInformation.card.suffix'),
                'bin' => data_get($json, 'paymentInformation.card.prefix'),
                'type' => data_get($json, 'paymentInformation.card.type'),
                'exp_month' => data_get($json, 'paymentInformation.card.expirationMonth'),
                'exp_year' => data_get($json, 'paymentInformation.card.expirationYear'),
            ]),
            raw: $json,
            httpStatus: $response->status(),
        );
    }

    /**
     * Map a Transaction Search (/tss/v2/transactions) record to a status.
     * reasonCode 100 = success, 480 = held for review; each application
     * (ics_auth, ics_bill, ics_credit, ...) carries rCode "1" when it succeeded.
     */
    protected function statusFromTransaction(array $tx): GatewayStatus
    {
        $reasonCode = (int) data_get($tx, 'applicationInformation.reasonCode');

        $succeeded = collect(data_get($tx, 'applicationInformation.applications', []))
            ->filter(fn ($app) => (string) ($app['rCode'] ?? '') === '1')
            ->pluck('name')
            ->all();

        if ($reasonCode === 480) {
            return GatewayStatus::PendingReview;
        }

        if ($reasonCode !== 100) {
            return $reasonCode === 0 ? GatewayStatus::Pending : GatewayStatus::Declined;
        }

        return match (true) {
            in_array('ics_credit', $succeeded, true) => GatewayStatus::Refunded,
            in_array('ics_void', $succeeded, true),
            in_array('ics_auth_reversal', $succeeded, true) => GatewayStatus::Voided,
            in_array('ics_bill', $succeeded, true) => GatewayStatus::Captured,
            in_array('ics_auth', $succeeded, true) => GatewayStatus::Authorized,
            default => GatewayStatus::Pending,
        };
    }

    /**
     * Map /pts/v2/payments (and follow-on) responses. The API answers
     * 201 for approvals AND declines, 400 for INVALID_REQUEST, 502 for SERVER_ERROR.
     */
    protected function mapPaymentResponse(
        Response $response,
        bool $captured,
        array $card = [],
        GatewayStatus $pendingMeans = GatewayStatus::Pending,
    ): GatewayResult {
        $json = $response->json() ?? [];
        $status = data_get($json, 'status');

        $mapped = match ($status) {
            'AUTHORIZED' => $captured ? GatewayStatus::Captured : GatewayStatus::Authorized,
            'PENDING', 'TRANSMITTED' => $pendingMeans,
            'VOIDED', 'REVERSED' => GatewayStatus::Voided,
            'AUTHORIZED_PENDING_REVIEW', 'PENDING_REVIEW',
            // Partial approvals hold funds for less than the order total: send to review.
            'PARTIAL_AUTHORIZED' => GatewayStatus::PendingReview,
            'PENDING_AUTHENTICATION' => GatewayStatus::PendingAuthentication,
            'DECLINED', 'AUTHORIZED_RISK_DECLINED' => GatewayStatus::Declined,
            'INVALID_REQUEST' => GatewayStatus::Failed,
            // SERVER_ERROR, timeouts, unexpected bodies: the charge may or may not exist.
            default => $response->serverError() || $status === 'SERVER_ERROR'
                ? GatewayStatus::Pending
                : GatewayStatus::Failed,
        };

        return new GatewayResult(
            $this->name(),
            $mapped,
            reference: data_get($json, 'id'),
            transactionId: data_get($json, 'processorInformation.transactionId'),
            approvalCode: data_get($json, 'processorInformation.approvalCode'),
            message: data_get($json, 'errorInformation.message') ?? data_get($json, 'message') ?? $status,
            reason: data_get($json, 'errorInformation.reason') ?? data_get($json, 'reason'),
            card: $card,
            raw: $json,
            httpStatus: $response->status(),
        );
    }

    protected function cardFromToken(array $tokenPayload): array
    {
        $card = data_get($tokenPayload, 'content.paymentInformation.card', []);

        return array_filter([
            'masked' => data_get($card, 'number.maskedValue'),
            'bin' => data_get($card, 'number.bin') ?? data_get($card, 'number.prefix'),
            'types' => data_get($card, 'number.detectedCardTypes')
                ?? array_filter([data_get($card, 'type.value')]),
            'exp_month' => data_get($card, 'expirationMonth.value'),
            'exp_year' => data_get($card, 'expirationYear.value'),
        ]);
    }

    public function handleWebhook(Request $request): SymfonyResponse
    {
        return response()->json();
    }
}
