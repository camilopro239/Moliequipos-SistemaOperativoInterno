<?php

require_once __DIR__ . '/../config/env.php';

env_load(__DIR__ . '/../.env');

class JWT
{
    public static function isConfigured(): bool
    {
        return self::secret() !== null;
    }

    public static function generate(array $payload, int $expSeconds = 3600): string
    {
        $secret = self::secret();
        if ($secret === null) {
            throw new RuntimeException('JWT_SECRET no esta configurado');
        }

        $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload['exp'] = time() + $expSeconds;
        $payloadEncoded = base64_encode(json_encode($payload));

        $signature = hash_hmac(
            'sha256',
            "$header.$payloadEncoded",
            $secret,
            true
        );

        return "$header.$payloadEncoded." . base64_encode($signature);
    }

    public static function validate(string $token): ?array
    {
        $secret = self::secret();
        if ($secret === null) {
            return null;
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$header, $payload, $signature] = $parts;

        $expected = base64_encode(hash_hmac(
            'sha256',
            "$header.$payload",
            $secret,
            true
        ));

        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $data = json_decode(base64_decode($payload), true);
        if (($data['exp'] ?? 0) < time()) {
            return null;
        }

        return $data;
    }

    private static function secret(): ?string
    {
        $secret = trim((string) env('JWT_SECRET', ''));
        if ($secret === '') {
            return null;
        }

        return $secret;
    }
}
