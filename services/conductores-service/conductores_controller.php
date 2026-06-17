<?php
function handle_conductores_request(string $action): void
{
    switch ($action) {
        case 'listar':
            conductores_list();
            break;
        case 'activos':
            conductores_active();
            break;
        case 'crear':
            conductores_create();
            break;
        case 'actualizar-estado':
            conductores_update_status();
            break;
        default:
            json_response(false, 'Acción no encontrada en Conductores Service', null, 404);
    }
}

function conductores_list(): void
{
    require_method(['GET']);
    require_auth(['administrador', 'coordinador']);
    $db = Database::connection();
    $stmt = $db->query(
        "SELECT c.*, u.nombres, u.apellidos, u.email
         FROM conductores c
         INNER JOIN usuarios u ON u.id_usuario = c.id_usuario
         ORDER BY u.nombres, u.apellidos"
    );
    json_response(true, 'Conductores obtenidos correctamente', $stmt->fetchAll());
}

function conductores_active(): void
{
    require_method(['GET']);
    require_auth(['administrador', 'coordinador']);
    $db = Database::connection();
    $stmt = $db->query(
        "SELECT c.*, CONCAT(u.nombres, ' ', u.apellidos) AS conductor, u.email
         FROM conductores c
         INNER JOIN usuarios u ON u.id_usuario = c.id_usuario
         WHERE c.estado = 'activo' AND u.estado = 'activo'
         ORDER BY conductor"
    );
    json_response(true, 'Conductores activos obtenidos correctamente', $stmt->fetchAll());
}

function conductores_create(): void
{
    require_method(['POST']);
    $user = require_auth(['administrador', 'coordinador']);
    $data = get_json_input();
    required_fields($data, ['id_usuario', 'licencia', 'telefono']);

    $db = Database::connection();
    $stmt = $db->prepare('INSERT INTO conductores (id_usuario, licencia, telefono, estado) VALUES (?, ?, ?, ?)');
    $stmt->execute([
        (int)$data['id_usuario'],
        clean_string($data['licencia']),
        clean_string($data['telefono']),
        $data['estado'] ?? 'activo'
    ]);
    log_action($db, (int)$user['id_usuario'], 'conductores', 'crear', 'Conductor registrado');

    json_response(true, 'Conductor registrado correctamente', ['id_conductor' => (int)$db->lastInsertId()], 201);
}

function conductores_update_status(): void
{
    require_method(['PUT', 'POST']);
    $user = require_auth(['administrador', 'coordinador']);
    $data = get_json_input();
    required_fields($data, ['id_conductor', 'estado']);

    if (!in_array($data['estado'], ['activo', 'inactivo'], true)) {
        json_response(false, 'Estado de conductor inválido', null, 422);
    }

    $db = Database::connection();
    $stmt = $db->prepare('UPDATE conductores SET estado = ? WHERE id_conductor = ?');
    $stmt->execute([$data['estado'], (int)$data['id_conductor']]);
    log_action($db, (int)$user['id_usuario'], 'conductores', 'estado', 'Cambio de estado de conductor');

    json_response(true, 'Conductor actualizado correctamente');
}
