<?php
function handle_retorno_request(string $action): void
{
    switch ($action) {
        case 'en-ruta':
            retorno_in_route();
            break;
        case 'registrar':
            retorno_register();
            break;
        case 'listar':
            retorno_list();
            break;
        default:
            json_response(false, 'Acción no encontrada en Retorno Service', null, 404);
    }
}

function retorno_in_route(): void
{
    require_method(['GET']);
    $user = require_auth(['administrador', 'coordinador', 'conductor']);
    $db = Database::connection();
    $sql = programacion_base_sql_for_return() . " WHERE p.estado = 'en_ruta'";
    $params = [];
    if ($user['rol'] === 'conductor') {
        $sql .= ' AND c.id_usuario = ?';
        $params[] = $user['id_usuario'];
    }
    $sql .= ' ORDER BY p.fecha_programada, p.hora_programada';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    json_response(true, 'Vehículos en ruta obtenidos correctamente', $stmt->fetchAll());
}

function programacion_base_sql_for_return(): string
{
    return "SELECT p.*, s.id_usuario, s.id_area, a.nombre AS area,
                   v.placa, v.marca, v.modelo,
                   c.id_usuario AS id_usuario_conductor,
                   CONCAT(u.nombres, ' ', u.apellidos) AS conductor
            FROM programaciones p
            INNER JOIN solicitudes s ON s.id_solicitud = p.id_solicitud
            INNER JOIN areas a ON a.id_area = s.id_area
            INNER JOIN vehiculos v ON v.id_vehiculo = p.id_vehiculo
            INNER JOIN conductores c ON c.id_conductor = p.id_conductor
            INNER JOIN usuarios u ON u.id_usuario = c.id_usuario";
}

function retorno_register(): void
{
    require_method(['POST']);
    $user = require_auth(['administrador', 'coordinador', 'conductor']);
    $data = get_json_input();
    required_fields($data, ['id_programacion']);

    $db = Database::connection();
    try {
        $db->beginTransaction();
        $stmt = $db->prepare(programacion_base_sql_for_return() . " WHERE p.id_programacion = ? FOR UPDATE");
        $stmt->execute([(int)$data['id_programacion']]);
        $programacion = $stmt->fetch();
        if (!$programacion || $programacion['estado'] !== 'en_ruta') {
            $db->rollBack();
            json_response(false, 'Solo se registra retorno de vehículos en ruta', null, 422);
        }
        if ($user['rol'] === 'conductor' && (int)$programacion['id_usuario_conductor'] !== (int)$user['id_usuario']) {
            $db->rollBack();
            json_response(false, 'No puede registrar retorno de otra asignación', null, 403);
        }

        $km = $db->prepare("SELECT id_kilometraje FROM kilometrajes WHERE id_programacion = ? AND estado = 'finalizado'");
        $km->execute([(int)$data['id_programacion']]);
        if (!$km->fetch()) {
            $db->rollBack();
            json_response(false, 'Debe registrar kilometraje final antes del retorno', null, 422);
        }

        $insert = $db->prepare(
            "INSERT INTO retornos (id_programacion, id_vehiculo, id_conductor, hora_salida, hora_retorno, observaciones)
             VALUES (?, ?, ?, ?, CURTIME(), ?)"
        );
        $insert->execute([
            (int)$data['id_programacion'],
            (int)$programacion['id_vehiculo'],
            (int)$programacion['id_conductor'],
            $programacion['hora_programada'],
            clean_string($data['observaciones'] ?? '')
        ]);
        $idRetorno = (int)$db->lastInsertId();

        $db->prepare("UPDATE programaciones SET estado = 'finalizada' WHERE id_programacion = ?")->execute([(int)$data['id_programacion']]);
        $db->prepare("UPDATE solicitudes SET estado = 'atendida' WHERE id_solicitud = ?")->execute([(int)$programacion['id_solicitud']]);
        $db->prepare("UPDATE vehiculos SET estado = 'disponible' WHERE id_vehiculo = ?")->execute([(int)$programacion['id_vehiculo']]);
        add_vehicle_to_queue($db, (int)$programacion['id_vehiculo']);
        log_action($db, (int)$user['id_usuario'], 'retorno', 'registrar', 'Retorno vehicular registrado');

        $db->commit();
        json_response(true, 'Retorno registrado correctamente', ['id_retorno' => $idRetorno], 201);
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        json_response(false, 'No se pudo registrar el retorno', null, 500);
    }
}

function retorno_list(): void
{
    require_method(['GET']);
    require_auth(['administrador', 'coordinador', 'conductor']);
    $db = Database::connection();
    $stmt = $db->query(
        "SELECT r.*, p.destino, p.fecha_programada, v.placa, v.marca, v.modelo,
                CONCAT(u.nombres, ' ', u.apellidos) AS conductor
         FROM retornos r
         INNER JOIN programaciones p ON p.id_programacion = r.id_programacion
         INNER JOIN vehiculos v ON v.id_vehiculo = r.id_vehiculo
         INNER JOIN conductores c ON c.id_conductor = r.id_conductor
         INNER JOIN usuarios u ON u.id_usuario = c.id_usuario
         ORDER BY r.created_at DESC"
    );
    json_response(true, 'Retornos obtenidos correctamente', $stmt->fetchAll());
}
