<?php

namespace App\Billing\Lib\Cybersource;

/**
 * Builds the allowedPaymentTypes array for Unified Checkout capture contexts.
 *
 * Each type appears once: either as a plain string or as
 * {type, options}. The old constant listed GOOGLEPAY and CLICKTOPAY twice.
 *
 * Microform only understands CARD and CHECK; the values here are
 * Unified Checkout values and must not be sent to /microform/v2/sessions.
 */
class PaymentTypes
{
    public const PANENTRY = 'PANENTRY';
    public const CLICKTOPAY = 'CLICKTOPAY';
    public const GOOGLEPAY = 'GOOGLEPAY';
    public const APPLEPAY = 'APPLEPAY';
    public const PAZE = 'PAZE';
    public const CHECK = 'CHECK';

    public const AFTERPAY = 'AFTERPAY';
    public const BANCONTACT = 'BANCONTACT';
    public const DRAGONPAY = 'DRAGONPAY';
    public const IDEAL = 'IDEAL';
    public const KONBINI = 'KONBINI';
    public const MULTIBANCO = 'MULTIBANCO';
    public const MYBANK = 'MYBANK';
    public const P24 = 'P24';
    public const TINKPAYBYBANK = 'TINKPAYBYBANK';

    /** Options that apply per type when enabled. */
    private const OPTIONS = [
        self::GOOGLEPAY => ['allowedAuthMethods' => 'PAN_ONLY'],
        self::CLICKTOPAY => ['autoCheckEnrollment' => true],
    ];

    /**
     * @param string[] $types   e.g. ['PANENTRY', 'CLICKTOPAY', 'GOOGLEPAY']
     * @param array<string, array> $options per-type overrides; pass [] for a type to send it as a plain string
     */
    public static function forUnifiedCheckout(array $types, array $options = [], bool $useOptions = false): array
    {
        $types = array_values(array_unique(array_map('strtoupper', $types)));

        if ($useOptions) {
            return array_map(function (string $type) use ($options) {
                $typeOptions = array_key_exists($type, $options)
                    ? $options[$type]
                    : (self::OPTIONS[$type] ?? []);
    
                return $typeOptions === [] ? $type : ['type' => $type, 'options' => $typeOptions];
            }, $types);
        }

        return $types;
    }
}
