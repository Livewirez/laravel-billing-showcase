<?php

namespace App\Billing\Lib\Card\Data;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * A checkout session with a card gateway.
 *
 *  - clientConfig is safe to send to the browser (capture context, library URL, session id).
 *  - state is server-only and is kept in the cache (verified capture context payload, etc.).
 *
 * The browser only ever sends back {card_session: $ref, token: ...}; the
 * gateway, amount and order are looked up from here, never trusted from the request.
 */
final class CardSession
{
    public function __construct(
        public readonly string $ref,
        public readonly string $gateway,
        public readonly string $orderNumber,
        public readonly string $amount,
        public readonly string $currency,
        public readonly string|int $billableKey,
        public readonly array $clientConfig,
        public readonly array $state,
        public readonly DateTimeInterface $expiresAt,
        public readonly CardSessionContext $ctx
    ) {}

    public function toClient(): array
    {
        return [
            'card_session' => $this->ref,
            'gateway' => $this->gateway,
            'expires_at' => $this->expiresAt->format(DATE_ATOM),
            ...$this->clientConfig,
        ];
    }

    public function toArray(): array
    {
        return [
            ...array_filter(get_object_vars($this), fn (string $key): bool => !in_array($key, ['expiresAt', 'ctx'], true), ARRAY_FILTER_USE_KEY),
            'expiresAt' => $this->expiresAt->format('Y-m-d H:i:s.u'),
            'ctx' => $this->ctx->toArray()
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            ref: $data['ref'],
            gateway: $data['gateway'],
            orderNumber: $data['orderNumber'],
            amount: $data['amount'],
            currency: $data['currency'],
            billableKey: $data['billableKey'],
            clientConfig: $data['clientConfig'],
            state: $data['state'],
            expiresAt: DateTimeImmutable::createFromFormat('Y-m-d H:i:s.u',  $data['expiresAt']),
            ctx: CardSessionContext::fromArray($data['ctx'])
        );
    }
}
