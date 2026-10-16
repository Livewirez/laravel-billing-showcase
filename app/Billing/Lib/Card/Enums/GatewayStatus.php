<?php

namespace App\Billing\Lib\Card\Enums;

/**
 * Gateway-neutral outcome of a card operation. Every driver maps its own
 * statuses into these, so CardProvider never sees "AUTHORIZED_PENDING_REVIEW"
 * or "gatewayCode=APPROVED" directly.
 */
enum GatewayStatus: string
{
    /** Funds authorised, not yet captured. */
    case Authorized = 'authorized';

    /** Sale/capture accepted (settlement follows asynchronously). */
    case Captured = 'captured';

    /** Authorised but held by fraud screening; do not ship yet. */
    case PendingReview = 'pending_review';

    /** Card requires 3-D Secure / SCA before it can be authorised. */
    case PendingAuthentication = 'pending_authentication';

    /** Outcome unknown (timeout, 5xx) or async; reconcile later. */
    case Pending = 'pending';

    case Declined = 'declined';
    case Failed = 'failed';
    case Voided = 'voided';
    case Refunded = 'refunded';

    public function isSuccessful(): bool
    {
        return in_array($this, [self::Authorized, self::Captured, self::Voided, self::Refunded], true);
    }

    public function isFinal(): bool
    {
        return ! in_array($this, [self::Pending, self::PendingReview, self::PendingAuthentication], true);
    }
}
