<?php

namespace App\Billing\Lib\Card\Support;

use RuntimeException;

/**
 * Minimal RS256 JWT verification with JWK -> PEM conversion.
 *
 * Cybersource signs capture contexts and transient tokens with RS256 and
 * publishes the keys as JWKs (n/e). This avoids pulling in a JWT library;
 * if you already use firebase/php-jwt you can swap verifyRs256() for
 * JWT::decode($jwt, JWK::parseKey($jwk, 'RS256')).
 */
final class Jwt
{
    /**
     * @return array{header: array, payload: array, signing_input: string, signature: string}
     */
    public static function decode(string $jwt): array
    {
        $parts = explode('.', trim($jwt));

        if (count($parts) !== 3) {
            throw new RuntimeException('Malformed JWT: expected 3 segments.');
        }

        [$h, $p, $s] = $parts;

        $header = json_decode(self::b64urlDecode($h), true);
        $payload = json_decode(self::b64urlDecode($p), true);

        if (! is_array($header) || ! is_array($payload)) {
            throw new RuntimeException('Malformed JWT: header or payload is not JSON.');
        }

        return [
            'header' => $header,
            'payload' => $payload,
            'signing_input' => "{$h}.{$p}",
            'signature' => self::b64urlDecode($s),
        ];
    }

    /**
     * Verify signature + exp/iat/nbf and return the payload.
     */
    public static function verifyRs256(string $jwt, array $jwk, int $leeway = 60): array
    {
        $decoded = self::decode($jwt);

        if (($decoded['header']['alg'] ?? null) !== 'RS256') {
            throw new RuntimeException('Unsupported JWT alg: ' . ($decoded['header']['alg'] ?? 'none'));
        }

        $ok = openssl_verify(
            $decoded['signing_input'],
            $decoded['signature'],
            self::jwkToPem($jwk),
            OPENSSL_ALGO_SHA256
        );

        if ($ok !== 1) {
            throw new RuntimeException('JWT signature verification failed.');
        }

        $now = time();
        $payload = $decoded['payload'];

        if (isset($payload['exp']) && $now - $leeway >= (int) $payload['exp']) {
            throw new RuntimeException('JWT has expired.');
        }

        if (isset($payload['nbf']) && $now + $leeway < (int) $payload['nbf']) {
            throw new RuntimeException('JWT is not yet valid.');
        }

        if (isset($payload['iat']) && $now + $leeway < (int) $payload['iat']) {
            throw new RuntimeException('JWT issued in the future.');
        }

        return $payload;
    }

    /**
     * Build a SubjectPublicKeyInfo PEM from an RSA JWK ({kty:"RSA", n, e}).
     */
    public static function jwkToPem(array $jwk): string
    {
        if (($jwk['kty'] ?? null) !== 'RSA' || empty($jwk['n']) || empty($jwk['e'])) {
            throw new RuntimeException('Only RSA JWKs with n and e are supported.');
        }

        // The PDF examples wrap long "n" values across lines; strip whitespace.
        $n = self::b64urlDecode(preg_replace('/\s+/', '', $jwk['n']));
        $e = self::b64urlDecode(preg_replace('/\s+/', '', $jwk['e']));

        $rsaPublicKey = self::derSequence(self::derInteger($n) . self::derInteger($e));

        // AlgorithmIdentifier { rsaEncryption (1.2.840.113549.1.1.1), NULL }
        $algorithm = hex2bin('300d06092a864886f70d0101010500');

        $bitString = "\x03" . self::derLength(strlen($rsaPublicKey) + 1) . "\x00" . $rsaPublicKey;

        $spki = self::derSequence($algorithm . $bitString);

        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($spki), 64, "\n")
            . "-----END PUBLIC KEY-----\n";
    }

    public static function b64urlDecode(string $data): string
    {
        $data = strtr($data, '-_', '+/');
        $pad = strlen($data) % 4;

        if ($pad) {
            $data .= str_repeat('=', 4 - $pad);
        }

        $out = base64_decode($data, true);

        if ($out === false) {
            throw new RuntimeException('Invalid base64url data.');
        }

        return $out;
    }

    public static function b64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function derInteger(string $bytes): string
    {
        $bytes = ltrim($bytes, "\x00");

        // Prepend 0x00 so the INTEGER is not read as negative.
        if ($bytes === '' || ord($bytes[0]) > 0x7f) {
            $bytes = "\x00" . $bytes;
        }

        return "\x02" . self::derLength(strlen($bytes)) . $bytes;
    }

    private static function derSequence(string $content): string
    {
        return "\x30" . self::derLength(strlen($content)) . $content;
    }

    private static function derLength(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }

        $bytes = ltrim(pack('N', $length), "\x00");

        return chr(0x80 | strlen($bytes)) . $bytes;
    }
}
