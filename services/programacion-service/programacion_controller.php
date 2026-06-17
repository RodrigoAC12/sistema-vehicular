<?php
function handle_programacion_request(string $action): void
{
    switch ($action) {
        case 'listar':
            programacion_list();
            break;
        case 'crear':
            programacion_create();
            break;
        case 'sugerir-vehiculo':
            programacion_suggest_vehicle();
            break;
        case 'iniciar-ruta':
            programacion_start_route();
            break;
        case 'cancelar':
            programacion_cancel();
            break;
        case 'detalle':
            programacion_detail();
            break;
        case 'publica':
            programacion_public();
            break;
        default:
            json_response(false, 'Acción no encontrada en Programación Service', null, 404);
    }
}

function programacion_base_sql(): string
{
    return "SELECT p.*, s.id_area, s.id_usuario, s.cantidad_personas, s.motivo,
                   a.nombre AS area, v.placa, v.marca, v.modelo, v.capacidad,
                   c.id_usuario AS id_usuario_conductor,
                   CONCAT(uc.nombres, ' ', uc.apellidos) AS conductor
            FROM programaciones p
            INNER JOIN solicitudes s ON s.id_solicitud = p.id_solicitud
            INNER JOIN areas a ON a.id_area = s.id_area
            INNER JOIN vehiculos v ON v.id_vehiculo = p.id_vehiculo
            INNER JOIN conductores c ON c.id_conductor = p.id_conductor
            INNER JOIN usuarios uc ON uc.id_usuario = c.id_usuario";
}

function programacion_list(): void
{
    require_method(['GET']);
    $user = require_auth(['administrador', 'coordinador', 'conductor']);
    $db = Database::connection();
    $where = [];
    $params = [];

    if (!empty($_GET['fecha'])) {
        $where[] = 'p.fecha_programada = ?';
        $params[] = $_GET['fecha'];
    }
    if (!empty($_GET['estado'])) {
        $where[] = 'p.estado = ?';
        $params[] = $_GET['estado'];
    }
    if ($user['rol'] === 'conductor') {
        $where[] = 'c.id_usuario = ?';
        $params[] = $user['id_usuario'];
    }

    $sql = programacion_base_sql();
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY p.fecha_programada DESC, p.hora_programada DESC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    json_response(true, 'Programaciones obtenidas correctamente', $stmt->fetchAll());
}

function programacion_suggest_vehicle(): void
{
    require_method(['GET', 'POST']);
    require_auth(['administrador', 'coordinador']);
    $data = $_SERVER['REQUEST_METHOD'] === 'GET' ? $_GET : get_json_input();
    $db = Database::connection();

    $capacidad = (int)($data['capacidad'] ?? 0);
    if (!empty($data['id_solicitud'])) {
        $stmt = $db->prepare("SELECT cantidad_personas FROM solicitudes WHERE id_solicitud = ? AND estado = 'pendiente'");
        $stmt->execute([(int)$data['id_solicitud']]);
        $solicitud = $stmt->fetch();
        if (!$solicitud) {
            json_response(false, 'Solicitud pendiente no encontrada', null, 404);
        }
        $capacidad = (int)$solicitud['cantidad_personas'];
    }
    if ($capacidad <= 0) {
        json_response(false, 'Debe indicar una capacidad requerida valida', null, 422);
    }

    $stmt = $db->prepare(
        "SELECT v.*, c.id_cola, c.`orden`
         FROM cola_vehicular c
         INNER JOIN vehiculos v ON v.id_vehiculo = c.id_vehiculo
         WHERE c.estado = 'en_cola'
           AND v.estado = 'disponible'
           AND v.capacidad >= ?
         ORDER BY c.`orden`, v.capacidad, v.kilometraje_actual
         LIMIT 1"
    );
    $stmt->execute([$capacidad]);
    $vehiculo = $stmt->fetch();
    if (!$vehiculo) {
        json_response(false, 'No hay vehículos disponibles para la capacidad solicitada', null, 404);
    }

    $vehiculo['justificacion'] = "Se sugiere el vehículo {$vehiculo['marca']} {$vehiculo['modelo']} porque tiene capacidad para {$vehiculo['capacidad']} personas, está disponible y es el primero en la cola compatible.";
    json_response(true, 'Vehículo sugerido correctamente', $vehiculo);
}

function programacion_create(): void
{
    require_method(['POST']);
    $user = require_auth(['administrador', 'coordinador']);
    $data = get_json_input();
    required_fields($data, ['id_solicitud', 'id_vehiculo', 'id_conductor']);

    $db = Database::connection();
    try {
        $db->beginTransaction();

        $stmt = $db->prepare('SELECT * FROM solicitudes WHERE id_solicitud = ? FOR UPDATE');
        $stmt->execute([(int)$data['id_solicitud']]);
        $solicitud = $stmt->fetch();
        if (!$solicitud || $solicitud['estado'] !== 'pendiente') {
            $db->rollBack();
            json_response(false, 'Solo se pueden programar solicitudes pendientes', null, 422);
        }

        $vehiculoStmt = $db->prepare("SELECT * FROM vehiculos WHERE id_vehiculo = ? FOR UPDATE");
        $vehiculoStmt->execute([(int)$data['id_vehiculo']]);
        $vehiculo = $vehiculoStmt->fetch();
        if (!$vehiculo || $vehiculo['estado'] !== 'disponible') {
            $db->rollBack();
            json_response(false, 'El vehículo no está disponible para programación', null, 422);
        }
        if ((int)$vehiculo['capacidad'] < (int)$solicitud['cantidad_personas']) {
            $db->rollBack();
            json_response(false, 'El vehículo no tiene capacidad suficiente', null, 422);
        }

        $conductorStmt = $db->prepare("SELECT * FROM conductores WHERE id_conductor = ? AND estado = 'activo'");
        $conductorStmt->execute([(int)$data['id_conductor']]);
        $conductor = $conductorStmt->fetch();
        if (!$conductor) {
            $db->rollBack();
            json_response(false, 'El conductor no existe o está inactivo', null, 422);
        }

        $fecha = $data['fecha_programada'] ?? $solicitud['fecha_servicio'];
        $hora = normalize_time($data['hora_programada'] ?? $solicitud['hora_servicio']);
        $conflict = $db->prepare(
            "SELECT id_programacion
             FROM programaciones
             WHERE fecha_programada = ?
               AND hora_programada = ?
               AND estado IN ('programada','en_ruta')
               AND (id_vehiculo = ? OR id_conductor = ?)"
        );
        $conflict->execute([$fecha, $hora, (int)$data['id_vehiculo'], (int)$data['id_conductor']]);
        if ($conflict->fetch()) {
            $db->rollBack();
            json_response(false, 'Ya existe una asignación para el mismo horario', null, 422);
        }

        $insert = $db->prepare(
            "INSERT INTO programaciones
             (id_solicitud, id_vehiculo, id_conductor, fecha_programada, hora_programada, destino, estado, observaciones)
             VALUES (?, ?, ?, ?, ?, ?, 'programada', ?)"
        );
        $insert->execute([
            (int)$data['id_solicitud'],
            (int)$data['id_vehiculo'],
            (int)$data['id_conductor'],
            $fecha,
            $hora,
            clean_string($data['destino'] ?? $solicitud['direccion']),
            clean_string($data['observaciones'] ?? '')
        ]);
        $idProgramacion = (int)$db->lastInsertId();

        $db->prepare("UPDATE solicitudes SET estado = 'programada' WHERE id_solicitud = ?")->execute([(int)$data['id_solicitud']]);
        $db->prepare("UPDATE vehiculos SET estado = 'asignado' WHERE id_vehiculo = ?")->execute([(int)$data['id_vehiculo']]);
        $db->prepare("UPDATE cola_vehicular SET estado = 'asignado' WHERE id_vehiculo = ? AND estado = 'en_cola'")->execute([(int)$data['id_vehiculo']]);
        log_action($db, (int)$user['id_usuario'], 'programacion', 'crear', 'Atención vehicular programada');

        $db->commit();
        json_response(true, 'Programación registrada correctamente', ['id_programacion' => $idProgramacion], 201);
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        json_response(false, 'No se pudo registrar la programación', null, 500);
    }
}

function programacion_start_route(): void
{
    require_method(['POST', 'PUT']);
    $user = require_auth(['administrador', 'coordinador', 'conductor']);
    $data = get_json_input();
    required_fields($data, ['id_programacion']);

    $db = Database::connection();
    $stmt = $db->prepare(programacion_base_sql() . " WHERE p.id_programacion = ? LIMIT 1");
    $stmt->execute([(int)$data['id_programacion']]);
    $programacion = $stmt->fetch();
    if (!$programacion || $programacion['estado'] !== 'programada') {
        json_response(false, 'Solo se puede iniciar una programación en estado programada', null, 422);
    }
    if ($user['rol'] === 'conductor' && (int)$programacion['id_usuario_conductor'] !== (int)$user['id_usuario']) {
        json_response(false, 'No puede iniciar una ruta asignada a otro conductor', null, 403);
    }

    $km = $db->prepare("SELECT id_kilometraje FROM kilometrajes WHERE id_programacion = ? AND estado = 'iniciado'");
    $km->execute([(int)$data['id_programacion']]);
    if (!$km->fetch()) {
        json_response(false, 'Debe registrar kilometraje inicial antes de iniciar ruta', null, 422);
    }

    $db->prepare("UPDATE programaciones SET estado = 'en_ruta' WHERE id_programacion = ?")->execute([(int)$data['id_programacion']]);
    $db->prepare("UPDATE vehiculos SET estado = 'en_ruta' WHERE id_vehiculo = ?")->execute([(int)$programacion['id_vehiculo']]);
    log_action($db, (int)$user['id_usuario'], 'programacion', 'iniciar_ruta', 'Ruta iniciada');

    json_response(true, 'Ruta iniciada correctamente');
}

function programacion_cancel(): void
{
    require_method(['POST', 'PUT']);
    $user = require_auth(['administrador', 'coordinador']);
    $data = get_json_input();
    required_fields($data, ['id_programacion']);

    $db = Database::connection();
    $stmt = $db->prepare('SELECT * FROM programaciones WHERE id_programacion = ?');
    $stmt->execute([(int)$data['id_programacion']]);
    $programacion = $stmt->fetch();
    if (!$programacion || !in_array($programacion['estado'], ['programada', 'en_ruta'], true)) {
        json_response(false, 'Programación no encontrada o no cancelable', null, 422);
    }

    $db->prepare("UPDATE programaciones SET estado = 'cancelada' WHERE id_programacion = ?")->execute([(int)$data['id_programacion']]);
    $db->prepare("UPDATE solicitudes SET estado = 'cancelada' WHERE id_solicitud = ?")->execute([(int)$programacion['id_solicitud']]);
    $db->prepare("UPDATE vehiculos SET estado = 'disponible' WHERE id_vehiculo = ?")->execute([(int)$programacion['id_vehiculo']]);
    add_vehicle_to_queue($db, (int)$programacion['id_vehiculo']);
    log_action($db, (int)$user['id_usuario'], 'programacion', 'cancelar', 'Programación cancelada');

    json_response(true, 'Programación cancelada correctamente');
}

function programacion_detail(): void
{
    require_method(['GET']);
    $user = require_auth(['administrador', 'coordinador', 'conductor']);
    $id = (int)($_GET['id'] ?? 0);
    $db = Database::connection();
    $stmt = $db->prepare(programacion_base_sql() . ' WHERE p.id_programacion = ? LIMIT 1');
    $stmt->execute([$id]);
    $programacion = $stmt->fetch();
    if (!$programacion) {
        json_response(false, 'Programación no encontrada', null, 404);
    }
    if ($user['rol'] === 'conductor' && (int)$programacion['id_usuario_conductor'] !== (int)$user['id_usuario']) {
        json_response(false, 'No puede consultar esta programación', null, 403);
    }

    $km = $db->prepare('SELECT * FROM kilometrajes WHERE id_programacion = ? LIMIT 1');
    $km->execute([$id]);
    $kilometraje = $km->fetch();
    $ret = $db->prepare('SELECT * FROM retornos WHERE id_programacion = ? LIMIT 1');
    $ret->execute([$id]);
    $retorno = $ret->fetch();

    $timeline = [
        ['label' => 'Solicitud registrada', 'complete' => true],
        ['label' => 'Solicitud programada', 'complete' => true],
        ['label' => 'Vehículo asignado', 'complete' => true],
        ['label' => 'Kilometraje inicial registrado', 'complete' => (bool)$kilometraje],
        ['label' => 'Ruta iniciada', 'complete' => in_array($programacion['estado'], ['en_ruta', 'finalizada'], true)],
        ['label' => 'Kilometraje final registrado', 'complete' => $kilometraje && $kilometraje['estado'] === 'finalizado'],
        ['label' => 'Retorno registrado', 'complete' => (bool)$retorno],
        ['label' => 'Atención finalizada', 'complete' => $programacion['estado'] === 'finalizada'],
    ];

    json_response(true, 'Detalle de programación obtenido correctamente', [
        'programacion' => $programacion,
        'kilometraje' => $kilometraje,
        'retorno' => $retorno,
        'timeline' => $timeline
    ]);
}

function programacion_public(): void
{
    require_method(['GET']);
    $force = isset($_GET['forzar']) && $_GET['forzar'] === '1';
    if (!$force && date('H:i') < '17:00') {
        json_response(true, 'La programación aún está en proceso. Estará disponible desde las 5:00 PM.', [
            'visible' => false,
            'programaciones' => []
        ]);
    }

    $fecha = $_GET['fecha'] ?? date('Y-m-d', strtotime('+1 day'));
    $db = Database::connection();
    $stmt = $db->prepare(
        "SELECT p.fecha_programada, p.hora_programada, p.destino, p.estado,
                v.placa, v.marca, v.modelo,
                CONCAT(uc.nombres, ' ', uc.apellidos) AS conductor,
                a.nombre AS area
         FROM programaciones p
         INNER JOIN solicitudes s ON s.id_solicitud = p.id_solicitud
         INNER JOIN areas a ON a.id_area = s.id_area
         INNER JOIN vehiculos v ON v.id_vehiculo = p.id_vehiculo
         INNER JOIN conductores c ON c.id_conductor = p.id_conductor
         INNER JOIN usuarios uc ON uc.id_usuario = c.id_usuario
         WHERE p.fecha_programada = ? AND p.estado IN ('programada','en_ruta','finalizada')
         ORDER BY p.hora_programada"
    );
    $stmt->execute([$fecha]);
    json_response(true, 'Programación pública obtenida correctamente', [
        'visible' => true,
        'fecha' => $fecha,
        'programaciones' => $stmt->fetchAll()
    ]);
}
