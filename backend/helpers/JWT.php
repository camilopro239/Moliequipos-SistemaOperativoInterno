<?php

class JWT {

    private static string $secret = 'CLAVE_SUPER_SECRETA_CAMBIAR';

    public static function generate(array $payload, int $expSeconds = 3600): string {
        $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload['exp'] = time() + $expSeconds;
        $payloadEncoded = base64_encode(json_encode($payload));

        $signature = hash_hmac(
            'sha256',
            "$header.$payloadEncoded",
            self::$secret,
            true
        );

        return "$header.$payloadEncoded." . base64_encode($signature);
    }

    public static function validate(string $token): ?array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;

        [$header, $payload, $signature] = $parts;

        $expected = base64_encode(hash_hmac(
            'sha256',
            "$header.$payload",
            self::$secret,
            true
        ));

        if (!hash_equals($expected, $signature)) return null;

        $data = json_decode(base64_decode($payload), true);
        if (($data['exp'] ?? 0) < time()) return null;

        return $data;
    }
}
