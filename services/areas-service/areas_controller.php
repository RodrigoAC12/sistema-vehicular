<?php
function handle_areas_request(string $action): void
{
    switch ($action) {
        case 'listar':
            areas_list();
            break;
        case 'crear':
            areas_create();
            break;
        case 'actualizar':
            areas_update();
            break;
        default:
            json_response(false, 'Acción no encontrada en Áreas Service', null, 404);
    }
}

function areas_list(): void
{
    require_method(['GET']);
    require_auth(['administrador', 'coordinador', 'solicitante']);
    $db = Database::connection();
    $stmt = $db->query("SELECT id_area, nombre, responsable, estado, created_at FROM areas ORDER BY nombre");
    json_response(true, 'Áreas obtenidas correctamente', $stmt->fetchAll());
}

function areas_create(): void
{
    require_method(['POST']);
    $user = require_auth(['administrador']);
    $data = get_json_input();
    required_fields($data, ['nombre', 'responsable']);

    $db = Database::connection();
    $stmt = $db->prepare('INSERT INTO areas (nombre, responsable, estado) VALUES (?, ?, ?)');
    $stmt->execute([clean_string($data['nombre']), clean_string($data['responsable']), $data['estado'] ?? 'activo']);
    log_action($db, (int)$user['id_usuario'], 'areas', 'crear', 'Área registrada');

    json_response(true, 'Área registrada correctamente', ['id_area' => (int)$db->lastInsertId()], 201);
}

function areas_update(): void
{
    require_method(['PUT', 'POST']);
    $user = require_auth(['administrador']);
    $data = get_json_input();
    required_fields($data, ['id_area', 'estado']);

    $estado = in_array($data['estado'], ['activo', 'inactivo'], true) ? $data['estado'] : null;
    if (!$estado) {
        json_response(false, 'Estado de área inválido', null, 422);
    }

    $db = Database::connection();
    $stmt = $db->prepare('UPDATE areas SET estado = ? WHERE id_area = ?');
    $stmt->execute([$estado, (int)$data['id_area']]);
    log_action($db, (int)$user['id_usuario'], 'areas', 'estado', 'Cambio de estado de área');

    json_response(true, 'Área actualizada correctamente');
}
