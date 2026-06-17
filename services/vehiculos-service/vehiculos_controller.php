<?php
function handle_vehiculos_request(string $action): void
{
    switch ($action) {
        case 'listar':
            vehiculos_list();
            break;
        case 'disponibles':
            vehiculos_available();
            break;
        case 'crear':
            vehiculos_create();
            break;
        case 'actualizar-estado':
            vehiculos_update_status();
            break;
        case 'detalle':
            vehiculos_detail();
            break;
        default:
            json_response(false, 'Acción no encontrada en Vehículos Service', null, 404);
    }
}

function vehiculos_list(): void
{
    require_method(['GET']);
    require_auth(['administrador', 'coordinador', 'conductor']);
    $db = Database::connection();
    $where = [];
    $params = [];
    if (!empty($_GET['estado'])) {
        $where[] = 'estado = ?';
        $params[] = $_GET['estado'];
    }
    $sql = 'SELECT * FROM vehiculos';
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY placa';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    json_response(true, 'Vehículos obtenidos correctamente', $stmt->fetchAll());
}

function vehiculos_available(): void
{
    require_method(['GET']);
    require_auth(['administrador', 'coordinador']);
    $capacidad = (int)($_GET['capacidad'] ?? 0);
    $db = Database::connection();
    $sql = "SELECT v.*, c.`orden`
            FROM vehiculos v
            LEFT JOIN cola_vehicular c ON c.id_vehiculo = v.id_vehiculo AND c.estado = 'en_cola'
            WHERE v.estado = 'disponible'";
    $params = [];
    if ($capacidad > 0) {
        $sql .= ' AND v.capacidad >= ?';
        $params[] = $capacidad;
    }
    $sql .= ' ORDER BY COALESCE(c.`orden`, 9999), v.capacidad, v.kilometraje_actual';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    json_response(true, 'Vehículos disponibles obtenidos correctamente', $stmt->fetchAll());
}

function vehiculos_create(): void
{
    require_method(['POST']);
    $user = require_auth(['administrador', 'coordinador']);
    $data = get_json_input();
    required_fields($data, ['placa', 'marca', 'modelo', 'anio', 'capacidad', 'kilometraje_actual']);

    $capacidad = (int)$data['capacidad'];
    if ($capacidad <= 0) {
        json_response(false, 'La capacidad debe ser mayor que 0', null, 422);
    }

    $db = Database::connection();
    $stmt = $db->prepare(
        "INSERT INTO vehiculos (placa, marca, modelo, anio, capacidad, kilometraje_actual, estado, observacion)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $estado = $data['estado'] ?? 'disponible';
    $stmt->execute([
        strtoupper(clean_string($data['placa'])),
        clean_string($data['marca']),
        clean_string($data['modelo']),
        (int)$data['anio'],
        $capacidad,
        (int)$data['kilometraje_actual'],
        $estado,
        clean_string($data['observacion'] ?? '')
    ]);
    $id = (int)$db->lastInsertId();
    if ($estado === 'disponible') {
        add_vehicle_to_queue($db, $id);
    }
    log_action($db, (int)$user['id_usuario'], 'vehiculos', 'crear', 'Vehículo registrado');

    json_response(true, 'Vehículo registrado correctamente', ['id_vehiculo' => $id], 201);
}

function vehiculos_update_status(): void
{
    require_method(['PUT', 'POST']);
    $user = require_auth(['administrador', 'coordinador']);
    $data = get_json_input();
    required_fields($data, ['id_vehiculo', 'estado']);

    $valid = ['disponible', 'asignado', 'en_ruta', 'retornando', 'mantenimiento', 'fuera_servicio'];
    if (!in_array($data['estado'], $valid, true)) {
        json_response(false, 'Estado vehicular inválido', null, 422);
    }

    $db = Database::connection();
    $stmt = $db->prepare('UPDATE vehiculos SET estado = ? WHERE id_vehiculo = ?');
    $stmt->execute([$data['estado'], (int)$data['id_vehiculo']]);

    if ($data['estado'] === 'disponible') {
        add_vehicle_to_queue($db, (int)$data['id_vehiculo']);
    } else {
        $q = $db->prepare("UPDATE cola_vehicular SET estado = 'retirado' WHERE id_vehiculo = ? AND estado = 'en_cola'");
        $q->execute([(int)$data['id_vehiculo']]);
    }
    log_action($db, (int)$user['id_usuario'], 'vehiculos', 'estado', 'Cambio de estado vehicular');

    json_response(true, 'Estado vehicular actualizado correctamente');
}

function vehiculos_detail(): void
{
    require_method(['GET']);
    require_auth(['administrador', 'coordinador', 'conductor']);
    $id = (int)($_GET['id'] ?? 0);
    $db = Database::connection();
    $stmt = $db->prepare('SELECT * FROM vehiculos WHERE id_vehiculo = ?');
    $stmt->execute([$id]);
    $vehiculo = $stmt->fetch();
    if (!$vehiculo) {
        json_response(false, 'Vehículo no encontrado', null, 404);
    }
    json_response(true, 'Vehículo obtenido correctamente', $vehiculo);
}
