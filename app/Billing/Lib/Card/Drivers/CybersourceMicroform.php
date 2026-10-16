<?php

namespace App\Billing\Lib\Card\Drivers;

use RuntimeException;
use App\Billing\Lib\Card\Data\CardOrder;
use App\Billing\Lib\Card\Data\CardSession;
use App\Billing\Lib\Card\Data\GatewayResult;
use App\Billing\Lib\Card\Data\CardSessionContext;

/**
 * Cybersource Microform Integration v2: your own form, with the PAN and CVN
 * fields rendered as Cybersource-hosted iframes.
 *
 * Note: Microform only tokenises. It does not run 3-D Secure. For UK/EEA
 * cardholders (PSD2 SCA) either enable Payer Authentication on your MID and
 * add the setup/enrol calls, or use CybersourceUnifiedCheckout with
 * consumer_authentication enabled.
 */
class CybersourceMicroform extends CybersourceGateway
{
    public function createSession(CardOrder $order): array
    {
        $response = $this->client->post('/microform/v2/sessions', [
            'clientVersion' => 'v2.0',
            'targetOrigins' => $this->targetOrigins(),
            'allowedCardNetworks' => $this->allowedCardNetworks(),
            'allowedPaymentTypes' => ['CARD'],
            'transientTokenResponseOptions' => [
                // Cybersource's recommended default: don't return the card prefix unless you need it.
                'includeCardPrefix' => (bool) ($this->options['include_card_prefix'] ?? false),
            ],
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Cybersource Microform session failed: ' . $response->body());
        }

        // The response body is the raw JWT, not JSON.
        $session = $this->sessionFromCaptureContext($jwt = trim($response->body()));
        $session['client']['type'] = 'cybersource_microform';
        $session['context'] = new CardSessionContext($jwt);

        return $session;
    }

    #[\Override]
    public function createPartialSession(): array
    {
        $response = $this->client->post('/microform/v2/sessions', [
            'clientVersion' => 'v2.0',
            'targetOrigins' => $this->targetOrigins(),
            'allowedCardNetworks' => $this->allowedCardNetworks(),
            'allowedPaymentTypes' => ['CARD'],
            'transientTokenResponseOptions' => [
                // Cybersource's recommended default: don't return the card prefix unless you need it.
                'includeCardPrefix' => (bool) ($this->options['include_card_prefix'] ?? false),
            ],
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Cybersource Microform session failed: ' . $response->body());
        }

        // The response body is the raw JWT, not JSON.
        $session = $this->sessionFromCaptureContext($jwt = trim($response->body()));
        $session['client']['type'] = 'cybersource_microform';
        $session['context'] = new CardSessionContext($jwt);

        return $session;
    }

    public function testSession(): \Illuminate\Http\Client\Response
    {
        $response = $this->client->post('/microform/v2/sessions', [
            'clientVersion' => 'v2.0',
            'targetOrigins' => $this->targetOrigins(),
            'allowedCardNetworks' => $this->allowedCardNetworks(),
            'allowedPaymentTypes' => ['CARD'],
            'transientTokenResponseOptions' => [
                // Cybersource's recommended default: don't return the card prefix unless you need it.
                'includeCardPrefix' => (bool) ($this->options['include_card_prefix'] ?? false),
            ],
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Cybersource Microform session failed: ' . $response->body());
        }
        
        return $response;
    }

    public function charge(CardOrder $order, CardSession $session, string $token): GatewayResult
    {
        return $this->payWithTransientToken($order, $session, $token);
    }
}
