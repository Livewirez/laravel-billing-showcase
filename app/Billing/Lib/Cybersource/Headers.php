<?php

namespace App\Billing\Lib\Cybersource;

use Livewirez\Billing\Enums\RequestMethod;

use function sprintf;

/**
 * Cybersource HTTP Signature authentication (HmacSHA256).
 *
 * Signs the exact body string that will be sent. The previous version
 * re-encoded the payload array, which only matched what Laravel sends by
 * coincidence of json_encode flags. Pass the same string to withBody().
 */
class Headers
{
    public function __construct(protected array $config = [])
    {
        $this->config = $config === [] ? config('billing.providers.cybersource', []) : $config;
    }

    //  $messageBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

    public function generate(RequestMethod|string $httpMethod, string $requestPath, array $payload = []): array
    {
        $merchantId = $this->config['merchant_id'];
        $host = $this->config['host'][$this->config['environment']] 
            ?? parse_url($this->config['base_url'][$this->config['environment']], PHP_URL_HOST);
        $keyId = $this->config['key'];
        $secretKey = $this->config['shared_secret'];

        $date = gmdate('D, d M Y H:i:s T'); // RFC1123 date in GMT
        $headersList = ['host', 'date', '(request-target)', 'v-c-merchant-id'];
        $digest = null;

        $httpMethod = $httpMethod instanceof RequestMethod ? $httpMethod : RequestMethod::fromString($httpMethod);

        // For POST/PUT, compute the digest
        if (\in_array(strtoupper($httpMethod->value), ['POST', 'PUT'])) {
            $digest = static::generateDigest($payload);
            array_splice($headersList, 3, 0, 'digest');
        }

        $signatureString = implode("\n", $signatureLines = array_map(
            fn (string $header): string => match ($header) {
                'host' =>  "host: {$host}",
                'date' => "date: {$date}",
                '(request-target)' => "(request-target): " . strtolower($httpMethod->value) . " " . $requestPath,
                'digest' => "digest: {$digest}",
                'v-c-merchant-id' => "v-c-merchant-id: {$merchantId}",
                default => ''
            }, 
            $headersList
        ));

        // Compute HMAC SHA256 signature (base64 encoded)
        $signature = base64_encode(
            hash_hmac('sha256', $signatureString, base64_decode($secretKey), true)
        );

        // Construct headers
        $headers = [
            'v-c-merchant-id' => $merchantId,
            'Date' => $date,
            'Host' => $host,
            'Signature' => sprintf(
                'keyid="%s", algorithm="HmacSHA256", headers="%s", signature="%s"',
                $keyId,
                implode(' ', $headersList),
                $signature
            ),
            'Content-Type' => 'application/json',
        ];

        if ($digest) {
            $headers['Digest'] = $digest;
        }

        return $headers;
    }


    public static function generateDigest(array $payload): string
    {
        $messageBody = self::encode($payload);

        // Create the SHA-256 hash (raw binary output)
        $digestBytes = hash('sha256', $messageBody, true);

        // Encode the hash in Base64
        $digestString = base64_encode($digestBytes);

        // Create the final Digest string
        return "SHA-256={$digestString}";
    }

    public static function encode(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR);
    }
}
