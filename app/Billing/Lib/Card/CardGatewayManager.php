<?php

namespace App\Billing\Lib\Card;

use App\Billing\Lib\Card\Contracts\CardGateway;
use App\Billing\Lib\Card\Data\CardOrder;
use App\Billing\Lib\Card\Data\CardSession;
use App\Billing\Lib\Card\Data\PartialCardSession;
use App\Billing\Lib\Card\Drivers\CybersourceMicroform;
use App\Billing\Lib\Card\Drivers\CybersourceUnifiedCheckout;
use App\Billing\Lib\Card\Drivers\CybersourceClickToPay;
use App\Billing\Lib\Card\Drivers\MastercardGateway;
use App\Billing\Lib\Cybersource\CybersourceClient;
use Closure;
use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Resolves card gateways from config('billing.card') and owns the
 * server-side session store.
 *
 * Register as a singleton in your service provider:
 *   $this->app->singleton(CardGatewayManager::class);
 *
 * Add a gateway at runtime:
 *   CardGatewayManager::extend('stripe', fn (array $options) => new StripeGateway($options));
 */
class CardGatewayManager
{
    /** @var array<string, Closure(array): CardGateway> */
    protected static array $creators = [];

    /** @var array<string, CardGateway> */
    protected array $resolved = [];

    public static function extend(string $driver, Closure $creator): void
    {
        static::$creators[$driver] = $creator;
    }

    public function gateway(?string $name = null): CardGateway
    {
        $name ??= config('billing.card.default', 'cybersource_microform');

        return $this->resolved[$name] ??= $this->resolve($name);
    }

    /** @return string[] gateway names the checkout may offer */
    public function enabled(): array
    {
        return array_keys(array_filter(
            config('billing.card.gateways', []),
            fn (array $g) => $g['enabled'] ?? true
        ));
    }

    protected function resolve(string $name): CardGateway
    {
        $options = config("billing.card.gateways.{$name}");

        if (! is_array($options) || ! ($options['enabled'] ?? true)) {
            throw new RuntimeException("Card gateway [{$name}] is not configured or is disabled.");
        }

        $options['name'] = $name;
        $driver = $options['driver'] ?? $name;

        if (isset(static::$creators[$driver])) {
            return (static::$creators[$driver])($options);
        }

        return match ($driver) {
            'cybersource_microform', 'cybersource' => new CybersourceMicroform(
                new CybersourceClient(config('billing.providers.cybersource', [])), $options
            ),
            'cybersource_unified_checkout' => new CybersourceUnifiedCheckout(
                new CybersourceClient(config('billing.providers.cybersource', [])), $options
            ),
            'cybersource_click_to_pay' => new CybersourceClickToPay(
                new CybersourceClient(config('billing.providers.cybersource', [])), $options
            ),
            'mastercard_gateway' => new MastercardGateway(
                config('billing.providers.mastercard', []), $options
            ),
            default => throw new RuntimeException("Unknown card gateway driver [{$driver}]."),
        };
    }

    /* ------------------------------------------------------------------ */
    /* Sessions                                                            */
    /* ------------------------------------------------------------------ */

    public function openSession(CardOrder $order, ?string $gatewayName = null): CardSession
    {
        $gateway = $this->gateway($gatewayName);
        $created = $gateway->createSession($order);

        $session = new CardSession(
            ref: Str::random(40),
            gateway: $gateway->name(),
            orderNumber: $order->orderNumber,
            amount: $order->amount,
            currency: $order->currency,
            billableKey: $order->billableKey,
            clientConfig: $created['client'],
            state: $created['state'],
            expiresAt: new DateTimeImmutable('+' . $created['ttl'] . ' seconds'),
            ctx: $created['context']
        );

        Cache::put($this->key($session->ref), $session->toArray(), $created['ttl']);

        return $session;
    }

    public function openPartialSession(?string $gatewayName = null): PartialCardSession
    {
        $gateway = $this->gateway($gatewayName);
        $created = $gateway->createPartialSession();

        $session = new PartialCardSession(
            ref: Str::random(40),
            gateway: $gateway->name(),
            clientConfig: $created['client'],
            state: $created['state'],
            expiresAt: new DateTimeImmutable('+' . $created['ttl'] . ' seconds'),
            ctx: $created['context']
        );

        Cache::put($this->key($session->ref), $session->toArray(), $created['ttl']);

        return $session;
    }

    public function transformSession(CardOrder $order, PartialCardSession $oldSession, ?string $gatewayName = null): CardSession
    {
        $gateway = $this->gateway($gatewayName);
        $ttl = max(60, (int) (time() + 900) - time());

        $newSession = new CardSession(
            ref: $oldSession->ref,
            gateway: $oldSession->gateway ?: $gateway->name(),
            orderNumber: $order->orderNumber,
            amount: $order->amount,
            currency: $order->currency,
            billableKey: $order->billableKey,
            clientConfig: $oldSession->clientConfig,
            state: $oldSession->state,
            expiresAt: new DateTimeImmutable('+' . $ttl . ' seconds'),
            ctx: $oldSession->ctx,
        );

        Cache::put($this->key($newSession->ref), $newSession->toArray(), $ttl);

        return $newSession;
    }

    public function transferSession(CardSession $oldSession, ?string $gatewayName = null): CardSession
    {
        $gateway = $this->gateway($gatewayName);
        $ttl = max(60, (int) (time() + 900) - time());

        $newSession = new CardSession(
            ref: $oldSession->ref,
            gateway: $oldSession->gateway ?: $gateway->name(),
            orderNumber: $oldSession->orderNumber,
            amount: $oldSession->amount,
            currency: $oldSession->currency,
            billableKey: $oldSession->billableKey,
            clientConfig: $oldSession->clientConfig,
            state: $oldSession->state,
            expiresAt: new DateTimeImmutable('+' . $ttl . ' seconds'),
            ctx: $oldSession->ctx,
        );

        Cache::put($this->key($newSession->ref), $newSession->toArray(), $ttl);

        return $newSession;
    }

    public function findSession(string $ref): CardSession|PartialCardSession|null
    {
        if (! preg_match('/^[A-Za-z0-9]{40}$/', $ref)) {
            return null;
        }

        $data = Cache::get($this->key($ref));

        if (is_array($data)) {
            // Determine the type of session based on the data structure
            if (isset($data['orderNumber'], $data['amount'], $data['currency'], $data['billableKey'])) {
                return CardSession::fromArray($data);
            }

            return PartialCardSession::fromArray($data);
        }

        return null;
    }

    public function closeSession(string $ref): void
    {
        Cache::forget($this->key($ref));
    }

    /**
     * Serialise charges per session so a double-clicked Pay button can't
     * submit the (reusable for 15 minutes) transient token twice.
     */
    public function lock(string $ref, Closure $callback): mixed
    {
        return Cache::lock($this->key($ref) . ':lock', 120)->block(10, $callback);
    }

    protected function key(string $ref): string
    {
        return 'billing:card-session:' . $ref;
    }
}
