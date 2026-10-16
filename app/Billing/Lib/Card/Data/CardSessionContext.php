<?php

namespace App\Billing\Lib\Card\Data;

readonly class CardSessionContext
{
    public function __construct(
        public string $token
    ) {} 

    public function toArray(): array
    {
        return get_object_vars($this);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            token: $data['token'],
        );
    }
}