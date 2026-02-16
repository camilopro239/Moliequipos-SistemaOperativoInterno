<?php

class Usuario {

    public static function findByEmail(PDO $pdo, string $email): ?array {
        $stmt = $pdo->prepare(
            "SELECT id, nombre, email, clave, rol
             FROM usuarios
             WHERE email = ? LIMIT 1"
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        return $user ?: null;
    }
}
