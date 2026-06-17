<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/response.php';

function bearer_token(): ?string
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authorization = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    if (preg_match('/Bearer\s+(.+)/i', $authorization, $matches)) {
        return trim($matches[1]);
    }

    return $headers['X-Auth-Token'] ?? $_SERVER['HTTP_X_AUTH_TOKEN'] ?? null;
}

function current_user(): ?array
{
    $token = bearer_token();
    if (!$token) {
        return null;
    }

    $db = Database::connection();
    $stmt = $db->prepare(
        "SELECT u.id_usuario, u.id_rol, u.id_area, u.nombres, u.apellidos, u.email, u.estado,
                r.nombre AS rol, a.nombre AS area
         FROM usuarios u
         INNER JOIN roles r ON r.id_rol = u.id_rol
         LEFT JOIN areas a ON a.id_area = u.id_area
         WHERE u.token = ? AND u.estado = 'activo'
         LIMIT 1"
    );
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function require_auth(array $roles = []): array
{
    $user = current_user();
    if (!$user) {
        json_response(false, 'Token inválido o sesión expirada', null, 401);
    }

    if ($roles && !in_array($user['rol'], $roles, true)) {
        json_response(false, 'No tiene permisos para realizar esta acción', null, 403);
    }

    return $user;
}
