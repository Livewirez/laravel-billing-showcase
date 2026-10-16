<?php

namespace App\Billing\Providers;

use App\Billing\Lib\Card\CardGatewayManager;
use App\Billing\Lib\Card\Data\CardOrder;
use App\Billing\Lib\Card\Data\CardSession;
use App\Billing\Lib\Card\Data\GatewayResult;
use App\Billing\Lib\Card\Data\PartialCardSession;
use App\Billing\Lib\Card\Enums\GatewayStatus;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Livewirez\Billing\Enums\PaymentStatus;
use Livewirez\Billing\Enums\SubscriptionStatus;
use Livewirez\Billing\ErrorInfo;
use Livewirez\Billing\Interfaces\Billable;
use Livewirez\Billing\Interfaces\CartInterface;
use Livewirez\Billing\Interfaces\PaymentProviderInterface;
use Livewirez\Billing\Interfaces\ProductInterface;
use Livewirez\Billing\Interfaces\TokenizedPaymentProviderInterface;
use Livewirez\Billing\Lib\Cart;
use Livewirez\Billing\Lib\Orders\CompleteOrderRequest;
use Livewirez\Billing\Lib\Orders\InitializeOrderRequest;
use Livewirez\Billing\Models\BillingPlan;
use Livewirez\Billing\Models\BillingPlanPrice;
use Livewirez\Billing\PaymentResult;
use Livewirez\Billing\SubscriptionResult;
use Symfony\Component\HttpFoundation\Response;
use Tekord\Result\Result;

/**
 * Card payments through any configured CardGateway (Cybersource Microform,
 * Cybersource Unified Checkout, Mastercard Gateway, or your own).
 *
 * Checkout flow:
 *   1. GET/POST /checkout/card-session -> $provider->createCardSession($cart, $request)
 *      returns ->toClient() for the browser (capture context / session id).
 *   2. Browser tokenises and calls your checkout endpoint with metadata:
 *      ['card_session' => ..., 'token' => ...]
 *   3. initializePayment() verifies and charges.
 */
class CardProvider implements PaymentProviderInterface
{
    public function __construct(protected ?CardGatewayManager $gateways = null)
    {
        $this->gateways ??= app(CardGatewayManager::class);
    }

    /**
     * Step 1: open a gateway session for the browser.
     */
    public function createCardSession(
        CartInterface|ProductInterface $cart,
        InitializeOrderRequest $request,
        ?string $gateway = null,
    ): CardSession {
        if ($cart instanceof ProductInterface) {
            $cart = Cart::fromProduct($cart);
        }

        if ($gateway !== null && ! in_array($gateway, $this->gateways->enabled(), true)) {
            throw new Exception("Card gateway [{$gateway}] is not enabled.");
        }

        return $this->gateways->openSession(CardOrder::fromCart($cart, $request), $gateway);
    }

     /**
     * Step 1: open a gateway session for the browser.
     */
    public function createPartialCardSession(
        ?string $gateway = null,
    ): PartialCardSession {
        
        if ($gateway !== null && ! in_array($gateway, $this->gateways->enabled(), true)) {
            throw new Exception("Card gateway [{$gateway}] is not enabled.");
        }

        return $this->gateways->openPartialSession($gateway);
    }

    public function getTokenPaymentProvider(): TokenizedPaymentProviderInterface
    {
        throw new Exception('Unsupported');
    }

    public function initializePayment(CartInterface|ProductInterface $cart, InitializeOrderRequest $request): PaymentResult
    {
        if ($cart instanceof ProductInterface) {
            $cart = Cart::fromProduct($cart);
        }

        $billingOrderId = $request->getBillingOrderId();
        $metadata = $request->getMetadata();

        $paymentData = [
            'billing_order_id' => $billingOrderId,
            'billing_payment_transaction_id' => $request->getBillingPaymentTransactionId(),
            'order_number' => $request->getOrderNumber(),
            'product_type' => $request->getProductType()->value,
            'billable_id' => $request->getUser()->getKey(),
            'billable_type' => $request->getUser()->getMorphClass(),
        ];

        $ref = $metadata['card_session'] ?? null;
        $token = $metadata['token'] ?? null;

        Log::info(__METHOD__, [
            'ref' => $ref, 'token' => $token
        ]);

        if (! is_string($ref) || ! is_string($token) || $token === '') {
            return $this->rejected($billingOrderId, 'Card details missing. Please re-enter your card.', 'MISSING_TOKEN', $paymentData);
        }

        $session = $this->gateways->findSession($ref);

        if ($session === null) {
            return $this->rejected($billingOrderId, 'Your card session expired. Please re-enter your card.', 'SESSION_EXPIRED', $paymentData);
        }

        if ($session instanceof PartialCardSession) {
            $session = $this->gateways->transformSession(CardOrder::fromCart($cart, $request), $session);
        }

        // The session must belong to this user and this order.
        if (
            (string) $session->billableKey !== (string) $request->getUser()->getKey()
            || $session->orderNumber !== (string) $request->getOrderNumber()
        ) {
            return $this->rejected($billingOrderId, 'Card session does not match this order.', 'SESSION_MISMATCH', $paymentData);
        }

        $order = CardOrder::fromCart($cart, $request);
        $gateway = $this->gateways->gateway($session->gateway);

        return $this->gateways->lock($ref, function () use ($gateway, $order, $session, $token, $ref, $billingOrderId, $paymentData) {
            $result = $gateway->charge($order, $session, $token);

            Log::info('Card charge', [
                'gateway' => $result->gateway,
                'order' => $order->orderNumber,
                'status' => $result->status->value,
                'reference' => $result->reference,
                'reason' => $result->reason,
            ]);

            // A final answer (approved or declined) consumes the session so the
            // transient token can't be replayed. Pending outcomes keep it for reconciliation.
            if ($result->status->isFinal()) {
                $this->gateways->closeSession($ref);
            }

            return $this->toPaymentResult($result, $billingOrderId, 'Payment Initialization', $paymentData);
        });
    }

    public function completePayment(CompleteOrderRequest $request): ?PaymentResult
    {
        $metadata = $request->getMetadata();
        $gateway = $this->gateways->gateway($metadata['card_gateway'] ?? null);

        return $this->gateways->lock($request->getOrderNumber(), function () use ($gateway, $request) {
            // $result = $gateway->retrieve($request->getProviderTransactionId());

            $user = $request->getUser();
            $address = $request->getBillingAddress();

            $result = $gateway->capture(
                $request->getProviderTransactionId(), 
                CardOrder::fromCompleteOrderRequest($request)
            );

            Log::info('Card Completion', [
                'gateway' => $result->gateway,
                'order' => $request->getOrderNumber(),
                'status' => $result->status->value,
                'reference' => $result->reference,
                'reason' => $result->reason,
            ]);

            return $this->toPaymentResult($result, $request->getBillingOrderId(), 'Payment Completion', [], completion: true);
        });
    }

    public function refundPayment(string $billingOrderId, string $providerOrderId): bool
    {
        // The interface gives no amount or gateway, so read both from the gateway.
        // Uses the default gateway; pass the stored card_gateway if you run several.
        $gateway = $this->gateways->gateway();
        $current = $gateway->retrieve($providerOrderId);

        $amount = data_get($current->raw, 'orderInformation.amountDetails.totalAmount')
            ?? data_get($current->raw, 'totalCapturedAmount');
        $currency = data_get($current->raw, 'orderInformation.amountDetails.currency')
            ?? data_get($current->raw, 'currency');
        $orderNumber = data_get($current->raw, 'clientReferenceInformation.code') ?? $billingOrderId;

        if ($current->status !== GatewayStatus::Captured || ! $amount || ! $currency) {
            Log::warning('Card refund skipped: payment not in a refundable state', [
                'reference' => $providerOrderId,
                'status' => $current->status->value,
            ]);

            return false;
        }

        $refund = $gateway->refund($providerOrderId, (string) $amount, (string) $currency, (string) $orderNumber, 'refund-' . $billingOrderId);

        return $refund->status === GatewayStatus::Refunded;
    }

    public function getPaymentStatus(string $providerOrderId): PaymentStatus
    {
        return $this->paymentStatus($this->gateways->gateway()->retrieve($providerOrderId)->status, completion: true);
    }

    /* ------------------------------------------------------------------ */

    protected function paymentStatus(GatewayStatus $status, bool $completion = false): PaymentStatus
    {
        // Adjust here if your PaymentStatus enum has PENDING / REFUNDED / VOIDED cases.
        return match ($status) {
            GatewayStatus::Captured => $completion ? PaymentStatus::COMPLETED : PaymentStatus::PAID,
            GatewayStatus::Authorized,
            GatewayStatus::PendingReview => PaymentStatus::APPROVED,
            GatewayStatus::Failed => PaymentStatus::FAILED,
            GatewayStatus::Pending,
            GatewayStatus::PendingAuthentication,
            GatewayStatus::Declined,
            GatewayStatus::Voided,
            GatewayStatus::Refunded => PaymentStatus::UNPAID,
        };
    }

    protected function toPaymentResult(
        GatewayResult $r,
        string|int|null $billingOrderId,
        string $stage,
        array $paymentData,
        bool $completion = false,
    ): PaymentResult {
        $success = in_array($r->status, [GatewayStatus::Captured, GatewayStatus::Authorized, GatewayStatus::PendingReview], true);

        // Pending outcomes are not failures: don't throw, let reconciliation decide.
        $shouldThrow = in_array($r->status, [GatewayStatus::Declined, GatewayStatus::Failed], true);

        $metadata = [
            'provider_class' => static::class,
            'card_gateway' => $r->gateway,
            'gateway_status' => $r->status->value,
            'gateway_reason' => $r->reason,
            'card' => $r->card,
            ...$paymentData,
            'gateway_response' => $r->raw,
        ];

        $result = $success
            ? Result::success($r->raw)
            : Result::fail(new ErrorInfo(
                "Payment Failure: " . ($r->message ?? $r->status->value),
                $r->httpStatus ?? 0,
                $r->reason ?? $r->message,
                [
                    'billingOrderId' => $billingOrderId,
                    'billing_order_id' => $billingOrderId,
                    'status' => $r->raw,
                    'type' => $r->status->value,
                ],
            ));

        return new PaymentResult(
            $success,
            $billingOrderId,
            $this->paymentStatus($r->status, $completion),
            $result,
            null,
            $r->reference,
            $r->transactionId,
            $r->approvalCode ?? $r->transactionId,
            $r->reference,
            $stage . ($success ? ' Success' : ($shouldThrow ? ' Failure' : ' Pending')),
            $metadata,
            $shouldThrow
        );
    }

    protected function rejected(string|int|null $billingOrderId, string $message, string $reason, array $paymentData): PaymentResult
    {
        return $this->toPaymentResult(
            GatewayResult::failed('card', $message, $reason),
            $billingOrderId,
            'Payment Initialization',
            $paymentData,
        );
    }

    /* ------------------------------------------------------------------ */
    /* Subscriptions: one-off card tokens cannot renew. Use TMS tokens     */
    /* (Cybersource) or stored credentials (MPGS) to support these.        */
    /* ------------------------------------------------------------------ */

    public function initiateSubscription(BillingPlan $plan, BillingPlanPrice $planPrice, InitializeOrderRequest $request): SubscriptionResult
    {
        throw new Exception('Unsupported');
    }

    public function startSubscription(CompleteOrderRequest $request): SubscriptionResult
    {
        throw new Exception('Unsupported');
    }

    public function updateSubscription(string $billingSubscriptionId, string $providerSubscriptionId, BillingPlanPrice $newPlanPrice, array $data = []): SubscriptionResult
    {
        throw new Exception('Unsupported');
    }

    public function getSubscription(string $providerSubscriptionId): array
    {
        throw new Exception('Unsupported');
    }

    public function listSubscriptions(): array
    {
        throw new Exception('Unsupported');
    }

    public function cancelSubscription(string $providerSubscriptionId): bool
    {
        throw new Exception('Unsupported');
    }

    public function pauseSubscription(string $providerSubscriptionId): bool
    {
        throw new Exception('Unsupported');
    }

    public function resumeSubscription(string $providerSubscriptionId): bool
    {
        throw new Exception('Unsupported');
    }

    public function getSubscriptionStatus(string $providerSubscriptionId): SubscriptionStatus
    {
        throw new Exception('Unsupported');
    }

    public function handleWebhook(Request $request): Response
    {
        $gateway = $this->gateways->gateway($request->query('gateway'));
        
        return $gateway->handleWebhook($request);
    }
}
