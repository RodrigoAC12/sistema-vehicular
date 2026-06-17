<?php
function handle_cola_request(string $action): void
{
    switch ($action) {
        case 'listar':
            cola_list();
            break;
        case 'agregar':
            cola_add();
            break;
        case 'retirar':
            cola_remove();
            break;
        case 'reordenar':
            cola_reorder();
            break;
        case 'siguiente':
            cola_next();
            break;
        default:
            json_response(false, 'Acción no encontrada en Cola Service', null, 404);
    }
}

function cola_list(): void
{
    require_method(['GET']);
    require_auth(['administrador', 'coordinador', 'conductor']);
    $db = Database::connection();
    $stmt = $db->query(
        "SELECT c.*, v.placa, v.marca, v.modelo, v.capacidad, v.kilometraje_actual, v.estado AS estado_vehiculo
         FROM cola_vehicular c
         INNER JOIN vehiculos v ON v.id_vehiculo = c.id_vehiculo
         WHERE c.estado = 'en_cola'
         ORDER BY c.`orden`, c.fecha_ingreso"
    );
    json_response(true, 'Cola vehicular obtenida correctamente', $stmt->fetchAll());
}

function cola_add(): void
{
    require_method(['POST']);
    $user = require_auth(['administrador', 'coordinador']);
    $data = get_json_input();
    required_fields($data, ['id_vehiculo']);

    $db = Database::connection();
    $stmt = $db->prepare("SELECT estado FROM vehiculos WHERE id_vehiculo = ?");
    $stmt->execute([(int)$data['id_vehiculo']]);
    $vehiculo = $stmt->fetch();
    if (!$vehiculo) {
        json_response(false, 'Vehículo no encontrado', null, 404);
    }
    if ($vehiculo['estado'] !== 'disponible') {
        json_response(false, 'Solo se agregan a cola vehículos disponibles', null, 422);
    }

    add_vehicle_to_queue($db, (int)$data['id_vehiculo']);
    log_action($db, (int)$user['id_usuario'], 'cola', 'agregar', 'Vehículo agregado a cola');
    json_response(true, 'Vehículo agregado a la cola correctamente');
}

function cola_remove(): void
{
    require_method(['PUT', 'POST']);
    $user = require_auth(['administrador', 'coordinador']);
    $data = get_json_input();
    required_fields($data, ['id_cola']);

    $db = Database::connection();
    $stmt = $db->prepare("UPDATE cola_vehicular SET estado = 'retirado' WHERE id_cola = ? AND estado = 'en_cola'");
    $stmt->execute([(int)$data['id_cola']]);
    log_action($db, (int)$user['id_usuario'], 'cola', 'retirar', 'Vehículo retirado de cola');

    json_response(true, 'Vehículo retirado de la cola correctamente');
}

function cola_reorder(): void
{
    require_method(['PUT', 'POST']);
    $user = require_auth(['administrador', 'coordinador']);
    $data = get_json_input();
    if (empty($data['ordenes']) || !is_array($data['ordenes'])) {
        json_response(false, 'Debe enviar la lista de ordenes', null, 422);
    }

    $db = Database::connection();
    $stmt = $db->prepare("UPDATE cola_vehicular SET `orden` = ? WHERE id_cola = ? AND estado = 'en_cola'");
    foreach ($data['ordenes'] as $item) {
        if (isset($item['id_cola'], $item['orden'])) {
            $stmt->execute([(int)$item['orden'], (int)$item['id_cola']]);
        }
    }
    log_action($db, (int)$user['id_usuario'], 'cola', 'reordenar', 'Cola vehicular reordenada');

    json_response(true, 'Cola reordenada correctamente');
}

function cola_next(): void
{
    require_method(['GET']);
    require_auth(['administrador', 'coordinador']);
    $capacidad = (int)($_GET['capacidad'] ?? 0);
    $db = Database::connection();
    $sql = "SELECT c.*, v.placa, v.marca, v.modelo, v.capacidad, v.kilometraje_actual
            FROM cola_vehicular c
            INNER JOIN vehiculos v ON v.id_vehiculo = c.id_vehiculo
            WHERE c.estado = 'en_cola' AND v.estado = 'disponible'";
    $params = [];
    if ($capacidad > 0) {
        $sql .= ' AND v.capacidad >= ?';
        $params[] = $capacidad;
    }
    $sql .= ' ORDER BY c.`orden`, c.fecha_ingreso LIMIT 1';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $vehiculo = $stmt->fetch();
    if (!$vehiculo) {
        json_response(false, 'No hay vehículo disponible en cola para la capacidad requerida', null, 404);
    }
    json_response(true, 'Vehículo sugerido desde cola', $vehiculo);
}
