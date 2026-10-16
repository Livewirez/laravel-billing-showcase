<?php

namespace App\Billing\Lib\Card\Contracts;

use Illuminate\Http\Request;
use App\Billing\Lib\Card\Data\CardOrder;
use App\Billing\Lib\Card\Data\CardSession;
use App\Billing\Lib\Card\Data\GatewayResult;
use Symfony\Component\HttpFoundation\Response;

/**
 * A card gateway that tokenises card data in the browser (hosted fields /
 * drop-in UI) so raw PANs never touch your servers (PCI SAQ A).
 *
 * Flow:
 *   1. createSession()  -> server asks the gateway for a client session, browser renders hosted fields
 *   2. browser tokenises -> posts {card_session, token} to your checkout endpoint
 *   3. charge()         -> server verifies the token and authorises / sells
 *   4. capture/refund/void/retrieve as follow-ons
 *
 * Add a new gateway (Stripe, Adyen, Checkout.com, Authorize.net ...) by
 * implementing this and registering it in config('billing.card.gateways').
 */
interface CardGateway
{
    /** Config key of this gateway instance, e.g. "cybersource_microform". */
    public function name(): string;

    /**
     * Ask the gateway for a client session. Returned clientConfig goes to the
     * browser; state stays on the server.
     *
     * @return array{client: array, state: array, ttl: int} ttl in seconds
     */
    public function createPartialSession();

    /**
     * Ask the gateway for a client session. Returned clientConfig goes to the
     * browser; state stays on the server.
     *
     * @return array{client: array, state: array, ttl: int} ttl in seconds
     */
    public function createSession(CardOrder $order): array;

    /**
     * Verify the browser token against the stored session and take payment.
     * Implementations must not trust anything in $token except what they verify.
     */
    public function charge(CardOrder $order, CardSession $session, string $token): GatewayResult;

    public function capture(string $reference, CardOrder $order, ?string $operationId = null, array $metadata = []): GatewayResult;

    public function refund(string $reference, string $amount, string $currency, string $orderNumber, ?string $operationId = null, array $metadata = []): GatewayResult;

    public function void(string $reference, string $orderNumber, ?string $operationId = null, array $metadata = []): GatewayResult;

    /** Re-read the authoritative state from the gateway (reconciliation, webhooks, completePayment). */
    public function retrieve(string $reference): GatewayResult;

    public function handleWebhook(Request $request): Response;
}
