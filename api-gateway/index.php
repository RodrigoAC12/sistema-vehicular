<?php
require_once __DIR__ . '/../services/shared/bootstrap.php';

$routes = require __DIR__ . '/routes.php';

$service = $_GET['service'] ?? null;
$action = $_GET['action'] ?? null;

if ((!$service || !$action) && !empty($_SERVER['PATH_INFO'])) {
    $parts = array_values(array_filter(explode('/', trim($_SERVER['PATH_INFO'], '/'))));
    $service = $service ?: ($parts[0] ?? null);
    $action = $action ?: ($parts[1] ?? null);
}

if (!$service || !$action) {
    json_response(false, 'Debe indicar service y action', [
        'ejemplo' => 'api-gateway/index.php?service=solicitudes&action=listar'
    ], 400);
}

if (!isset($routes[$service])) {
    json_response(false, 'El servicio solicitado no existe', null, 404);
}

require_once $routes[$service];

$handler = 'handle_' . str_replace('-', '_', $service) . '_request';
if (!function_exists($handler)) {
    json_response(false, 'El servicio no tiene un manejador válido', null, 500);
}

$handler($action);
