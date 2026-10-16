<?php

namespace App\Billing\Lib\Card\Data;

use App\Billing\Lib\Card\Enums\GatewayStatus;

final class GatewayResult
{
    /**
     * @param string|null $reference     the id you use for follow-on calls (Cybersource payment id / MPGS order id)
     * @param string|null $transactionId processor/network transaction id
     * @param array       $card          non-sensitive card details (masked number, brand, expiry) safe to store
     */
    public function __construct(
        public readonly string $gateway,
        public readonly GatewayStatus $status,
        public readonly ?string $reference = null,
        public readonly ?string $transactionId = null,
        public readonly ?string $approvalCode = null,
        public readonly ?string $message = null,
        public readonly ?string $reason = null,
        public readonly array $card = [],
        public readonly array $raw = [],
        public readonly ?int $httpStatus = null,
    ) {}

    public function successful(): bool
    {
        return $this->status->isSuccessful();
    }

    public static function failed(string $gateway, string $message, ?string $reason = null, array $raw = []): self
    {
        return new self($gateway, GatewayStatus::Failed, message: $message, reason: $reason, raw: $raw);
    }
}
