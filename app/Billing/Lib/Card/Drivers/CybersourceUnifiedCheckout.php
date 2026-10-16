<?php

namespace App\Billing\Lib\Card\Drivers;

use App\Billing\Lib\Card\Data\CardOrder;
use App\Billing\Lib\Card\Data\CardSession;
use App\Billing\Lib\Card\Data\CardSessionContext;
use App\Billing\Lib\Card\Data\GatewayResult;
use App\Billing\Lib\Card\Enums\GatewayStatus;
use App\Billing\Lib\Cybersource\PaymentTypes;
use RuntimeException;
use Throwable;

/**
 * Cybersource Unified Checkout: drop-in UI with cards, Click to Pay
 * (Visa/Mastercard/Amex), Google Pay, Apple Pay, Paze.
 *
 * Two modes (config: unified_checkout.mode):
 *
 *  "token"    Browser calls up.show() and posts the transient token; the server
 *             authorises with /pts/v2/payments (same as Microform). Full server control.
 *
 *  "complete" Browser calls up.complete(tt) and Cybersource runs the payment,
 *             3-D Secure (Payer Authentication), Decision Manager and TMS for you.
 *             The browser posts back the payment id; the server re-reads the
 *             transaction from Cybersource and checks amount, currency and order
 *             number before trusting it. Easiest path to PSD2 SCA compliance.
 */
class CybersourceUnifiedCheckout extends CybersourceGateway
{
    public function createSession(CardOrder $order): array
    {
        $mode = $this->mode();

        $payload = array_filter([
            'clientVersion' => $this->options['client_version'] ?? '0.34',
            'targetOrigins' => $this->targetOrigins(),
            'allowedCardNetworks' => $this->allowedCardNetworks(),
            'allowedPaymentTypes' => PaymentTypes::forUnifiedCheckout(
                $this->options['payment_types'] ?? [PaymentTypes::PANENTRY, PaymentTypes::CLICKTOPAY, PaymentTypes::GOOGLEPAY, PaymentTypes::APPLEPAY],
                $this->options['payment_type_options'] ?? [],
            ),
            'country' => $this->options['country'] ?? $order->billTo['country'] ?? null,
            'locale' => $this->options['locale'] ?? 'en_GB',
            'buttonType' => $this->options['button_type'] ?? null,
            'captureMandate' => [
                'billingType' => $this->options['billing_type'] ?? 'FULL',
                'requestEmail' => ! isset($order->billTo['email']),
                'requestPhone' => false,
                'requestShipping' => data_get($this->options['capture_mandate'], 'request_shipping', false),
                'showAcceptedNetworkIcons' => true,
                'shipToCountries' => array_values(
                    array_filter(
                        array_unique([$this->options['country'] ?? $order->billTo['country'] ?? null, ...$this->getShipToCountries($this->options['capture_mandate'])])
                    )
                ),
            ],
            'completeMandate' => $mode === 'complete' ? array_filter([
                'type' => $order->capture ? 'CAPTURE' : 'AUTH', // 'CAPTURE', 'AUTH', 'PREFER_AUTH'
                'decisionManager' => (bool) ($this->options['decision_manager'] ?? false),
                'consumerAuthentication' => (bool) ($this->options['consumer_authentication'] ?? true),
            ], fn ($v) => $v !== null) : null,
            // Amount and order number are fixed here, inside the signed capture
            // context, so the browser cannot change them.
            'data' => [
                'orderInformation' => array_filter([
                    'amountDetails' => [
                        'totalAmount' => $order->amount,
                        'currency' => $order->currency,
                    ],
                    'billTo' => $order->billTo ?: null,
                ]),
                'clientReferenceInformation' => [
                    'code' => $order->orderNumber,
                ],
            ],
        ], fn ($v) => $v !== null);

        $response = $this->client->post('/up/v1/capture-contexts', $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Cybersource Unified Checkout session failed: ' . $response->body());
        }

        $session = $this->sessionFromCaptureContext($jwt = trim($response->body()), ['mode' => $mode]);
        $session['client']['type'] = 'cybersource_unified_checkout';
        $session['client']['mode'] = $mode;
        $session['client']['sidebar'] = (bool) ($this->options['sidebar'] ?? false);
        $session['context'] = new CardSessionContext($jwt);

        return $session;
    }

    public function charge(CardOrder $order, CardSession $session, string $token): GatewayResult
    {
        return ($session->state['mode'] ?? 'token') === 'complete'
            ? $this->confirmCompletedPayment($order, $session, $token)
            : $this->payWithTransientToken($order, $session, $token);
    }

    /**
     * In "complete" mode $token is the payment id from completeResponse.id.
     * It is only an id; everything is verified against Cybersource.
     */
    protected function confirmCompletedPayment(CardOrder $order, CardSession $session, string $paymentId): GatewayResult
    {
        if (! preg_match('/^\d{10,30}$/', $paymentId)) {
            return GatewayResult::failed($this->name(), 'Invalid payment reference.', 'INVALID_REFERENCE');
        }

        try {
            $result = $this->retrieve($paymentId);
        } catch (Throwable $e) {
            return GatewayResult::failed($this->name(), 'Could not confirm payment.', 'LOOKUP_FAILED', ['error' => $e->getMessage()]);
        }

        if ($result->status === GatewayStatus::Pending || $result->raw === []) {
            return $result;
        }

        $tx = $result->raw;
        $amount = (string) data_get($tx, 'orderInformation.amountDetails.totalAmount');
        $currency = strtoupper((string) data_get($tx, 'orderInformation.amountDetails.currency'));
        $code = (string) data_get($tx, 'clientReferenceInformation.code');

        // Compare in minor units; both values are 2-3 dp decimal strings.
        $matches = (int) round((float) $amount * 1000) === (int) round((float) $session->amount * 1000)
            && $currency === strtoupper($session->currency)
            && $code === $session->orderNumber;

        if (! $matches) {
            return GatewayResult::failed($this->name(), 'Payment does not match this order.', 'ORDER_MISMATCH', [
                'expected' => [$session->amount, $session->currency, $session->orderNumber],
                'actual' => [$amount, $currency, $code],
            ]);
        }

        return $result;
    }

    protected function mode(): string
    {
        return ($this->options['mode'] ?? 'token') === 'complete' ? 'complete' : 'token';
    }
}
