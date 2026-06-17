<?php
function clean_string($value): string
{
    return trim(filter_var((string)$value, FILTER_SANITIZE_SPECIAL_CHARS));
}

function required_fields(array $data, array $fields): void
{
    foreach ($fields as $field) {
        if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
            json_response(false, "El campo {$field} es obligatorio", null, 422);
        }
    }
}

function valid_service_date(string $date): bool
{
    $serviceDate = DateTime::createFromFormat('Y-m-d', $date);
    if (!$serviceDate || $serviceDate->format('Y-m-d') !== $date) {
        return false;
    }

    $tomorrow = new DateTime('tomorrow');
    $tomorrow->setTime(0, 0, 0);
    $serviceDate->setTime(0, 0, 0);

    return $serviceDate >= $tomorrow;
}

function valid_service_hour(string $hour): bool
{
    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $hour)) {
        return false;
    }

    $time = substr($hour, 0, 5);
    return $time >= '08:00' && $time <= '16:00';
}

function normalize_time(string $hour): string
{
    return strlen($hour) === 5 ? "{$hour}:00" : $hour;
}

function ensure_area_exists(PDO $db, int $idArea): void
{
    $stmt = $db->prepare("SELECT id_area FROM areas WHERE id_area = ? AND estado = 'activo'");
    $stmt->execute([$idArea]);
    if (!$stmt->fetch()) {
        json_response(false, 'El área solicitante no existe o está inactiva', null, 422);
    }
}

function next_queue_order(PDO $db): int
{
    $stmt = $db->query("SELECT COALESCE(MAX(`orden`), 0) + 1 AS siguiente FROM cola_vehicular WHERE estado = 'en_cola'");
    return (int)$stmt->fetch()['siguiente'];
}

function add_vehicle_to_queue(PDO $db, int $idVehiculo): void
{
    $stmt = $db->prepare("SELECT id_cola FROM cola_vehicular WHERE id_vehiculo = ? AND estado = 'en_cola' LIMIT 1");
    $stmt->execute([$idVehiculo]);
    if ($stmt->fetch()) {
        return;
    }

    $insert = $db->prepare("INSERT INTO cola_vehicular (id_vehiculo, `orden`, estado) VALUES (?, ?, 'en_cola')");
    $insert->execute([$idVehiculo, next_queue_order($db)]);
}

function log_action(PDO $db, ?int $idUsuario, string $modulo, string $accion, string $descripcion): void
{
    $stmt = $db->prepare("INSERT INTO logs_sistema (id_usuario, modulo, accion, descripcion) VALUES (?, ?, ?, ?)");
    $stmt->execute([$idUsuario, $modulo, $accion, $descripcion]);
}
