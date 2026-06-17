<?php
function json_response(bool $success, string $message, $data = null, int $status = 200): void
{
    http_response_code($status);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function get_json_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return $_POST ?: [];
    }

    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        json_response(false, 'JSON inválido en la solicitud', null, 400);
    }

    return is_array($data) ? $data : [];
}

function require_method(array $allowed): void
{
    if (!in_array($_SERVER['REQUEST_METHOD'], $allowed, true)) {
        json_response(false, 'Método HTTP no permitido', null, 405);
    }
}
