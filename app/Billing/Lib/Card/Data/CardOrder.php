<?php

namespace App\Billing\Lib\Card\Data;

use Livewirez\Billing\Interfaces\Billable;
use Livewirez\Billing\Interfaces\CartInterface;
use Livewirez\Billing\Lib\Orders\CompleteOrderRequest;
use Livewirez\Billing\Lib\Orders\InitializeOrderRequest;
use Livewirez\Billing\Money;

/**
 * Everything a gateway needs to know about the order being paid for.
 * Built server-side from the cart: amounts never come from the browser.
 */
final class CardOrder
{
    /**
     * @param string $amount     decimal string in major units, e.g. "12.50"
     * @param string $attemptId  unique per payment attempt; used as idempotency key where supported
     * @param array  $billTo     Cybersource-style billTo keys; empty values are dropped
     */
    public function __construct(
        public readonly string $orderNumber,
        public readonly string $attemptId,
        public readonly string $amount,
        public readonly string $currency,
        public readonly string|int $billableKey,
        public readonly array $billTo = [],
        public readonly bool $capture = true,
    ) {}

    public static function fromCart(CartInterface $cart, InitializeOrderRequest $request, bool $capture = true): self
    {
        $currency = $cart->getCurrencyCode();
        $user = $request->getUser();
        $address = $request->getBillingAddress();

        return new self(
            orderNumber: (string) $request->getOrderNumber(),
            attemptId: (string) ($request->getBillingPaymentTransactionId() ?? $request->getOrderNumber()),
            amount: (string) Money::formatAmountUsingCurrency($cart->getGrandTotal(), $currency),
            currency: $currency,
            billableKey: $user->getKey(),
            // No invented fallbacks: missing fields are omitted and the
            // gateway reports MISSING_FIELD rather than charging with fake AVS data.
            billTo: array_filter([
                'firstName' => $user->getName(),
                'lastName' => $user->getName(Billable::LAST_NAME),
                'email' => $user->getEmail(),
                'address1' => $address?->getAddressLine1() ?? '1Market St',
                'address2' => $address?->getAddressLine2() ?? 'Address 2',
                'locality' => $address?->getCity() ?? 'San Francisco',
                'administrativeArea' => $address?->getState() ?? 'CA',
                'postalCode' => $address?->getPostalCode() ?? '94103',
                'country' => $address?->getCountry() ?? 'US',
                'phoneNumber' => $address?->getPhone() ?? '+1234567890',
            ], fn ($v) => $v !== null && $v !== ''),
            capture: $capture,
        );
    }

    public static function fromCompleteOrderRequest(CompleteOrderRequest $request, bool $capture = true): self
    {
        $currency = $request->getMetadata()['currency'];
        $user = $request->getUser();
        $address = $request->getBillingAddress();
        
        return new self(
            orderNumber: (string) $request->getOrderNumber(),
            attemptId: (string) ($request->getBillingPaymentTransactionId() ?? $request->getOrderNumber()),
            amount: (string) Money::formatAmountUsingCurrency($request->getMetadata()['amount'], $currency),
            currency: $currency,
            billableKey: $user->getKey(),
            // No invented fallbacks: missing fields are omitted and the
            // gateway reports MISSING_FIELD rather than charging with fake AVS data.
            billTo: array_filter([
                'firstName' => $user->getName(),
                'lastName' => $user->getName(Billable::LAST_NAME),
                'email' => $user->getEmail(),
                'address1' => $address?->getAddressLine1() ?? '1Market St',
                'address2' => $address?->getAddressLine2() ?? 'Address 2',
                'locality' => $address?->getCity() ?? 'San Francisco',
                'administrativeArea' => $address?->getState() ?? 'CA',
                'postalCode' => $address?->getPostalCode() ?? '94103',
                'country' => $address?->getCountry() ?? 'US',
                'phoneNumber' => $address?->getPhone() ?? '+1234567890',
            ], fn ($v) => $v !== null && $v !== ''),
            capture: $capture,
        );
    }
}
