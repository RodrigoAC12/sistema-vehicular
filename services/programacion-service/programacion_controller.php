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
        case 'optimizar-ruta':
            programacion_optimize_route();
            break;
        case 'crear-ruta':
            programacion_create_route();
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
                   s.tipo_solicitud, s.resultado_especial,
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

function parse_request_ids($value): array
{
    $items = is_array($value) ? $value : explode(',', (string)$value);
    $ids = [];
    foreach ($items as $item) {
        $id = (int)$item;
        if ($id > 0 && !in_array($id, $ids, true)) {
            $ids[] = $id;
        }
    }

    if (!$ids) {
        json_response(false, 'Debe seleccionar al menos una solicitud pendiente', null, 422);
    }

    return $ids;
}

function route_requests(PDO $db, array $ids, bool $forUpdate = false): array
{
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT s.*, a.nombre AS area
            FROM solicitudes s
            INNER JOIN areas a ON a.id_area = s.id_area
            WHERE s.id_solicitud IN ({$placeholders})
              AND s.estado = 'pendiente'";
    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($ids);
    $rows = $stmt->fetchAll();
    if (count($rows) !== count($ids)) {
        json_response(false, 'Todas las solicitudes de la ruta deben estar pendientes', null, 422);
    }

    $byId = [];
    foreach ($rows as $row) {
        $byId[(int)$row['id_solicitud']] = $row;
    }

    $ordered = [];
    foreach ($ids as $id) {
        $ordered[] = $byId[$id];
    }

    return $ordered;
}

function route_leg_estimate(string $from, string $to): array
{
    $fromText = strtolower(trim($from ?: 'Empresa JX'));
    $toText = strtolower(trim($to));
    $hash = abs((int)sprintf('%u', crc32($fromText)) - (int)sprintf('%u', crc32($toText)));
    $distance = round(1.2 + (($hash % 130) / 10), 2);
    $minutes = (int)ceil(8 + ($distance * 3.2));

    return [
        'distancia_km' => $distance,
        'duracion_min' => $minutes
    ];
}

function optimize_route_plan(array $requests, string $origin): array
{
    $remaining = array_values($requests);
    $current = $origin ?: 'Empresa JX';
    $plan = [];
    $totalDistance = 0.0;
    $totalMinutes = 0;
    $order = 1;

    while ($remaining) {
        $bestIndex = 0;
        $bestLeg = null;
        foreach ($remaining as $index => $request) {
            $leg = route_leg_estimate($current, (string)$request['direccion']);
            if (
                $bestLeg === null ||
                $leg['duracion_min'] < $bestLeg['duracion_min'] ||
                ($leg['duracion_min'] === $bestLeg['duracion_min'] && $leg['distancia_km'] < $bestLeg['distancia_km'])
            ) {
                $bestIndex = $index;
                $bestLeg = $leg;
            }
        }

        $selected = $remaining[$bestIndex];
        array_splice($remaining, $bestIndex, 1);
        $totalDistance += (float)$bestLeg['distancia_km'];
        $totalMinutes += (int)$bestLeg['duracion_min'];
        $selected['orden_ruta'] = $order++;
        $selected['distancia_tramo_km'] = (float)$bestLeg['distancia_km'];
        $selected['duracion_tramo_min'] = (int)$bestLeg['duracion_min'];
        $selected['distancia_acumulada_km'] = round($totalDistance, 2);
        $selected['duracion_acumulada_min'] = $totalMinutes;
        $plan[] = $selected;
        $current = (string)$selected['direccion'];
    }

    return [
        'origen' => $origin ?: 'Empresa JX',
        'distancia_total_km' => round($totalDistance, 2),
        'duracion_total_min' => $totalMinutes,
        'pedidos' => $plan
    ];
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

function programacion_optimize_route(): void
{
    require_method(['GET', 'POST']);
    require_auth(['administrador', 'coordinador']);
    $data = $_SERVER['REQUEST_METHOD'] === 'GET' ? $_GET : get_json_input();
    $ids = parse_request_ids($data['ids_solicitud'] ?? '');
    $origin = clean_string($data['origen_ruta'] ?? 'Empresa JX');

    $db = Database::connection();
    $requests = route_requests($db, $ids);
    $totalPeople = 0;
    foreach ($requests as $request) {
        $totalPeople += (int)$request['cantidad_personas'];
    }

    $plan = optimize_route_plan($requests, $origin);
    $plan['total_personas'] = $totalPeople;
    json_response(true, 'Ruta corta calculada correctamente', $plan);
}

function programacion_create_route(): void
{
    require_method(['POST']);
    $user = require_auth(['administrador', 'coordinador']);
    $data = get_json_input();
    required_fields($data, ['ids_solicitud', 'id_vehiculo', 'id_conductor']);
    $ids = parse_request_ids($data['ids_solicitud']);

    $db = Database::connection();
    try {
        $db->beginTransaction();

        $requests = route_requests($db, $ids, true);
        $fecha = $data['fecha_programada'] ?? $requests[0]['fecha_servicio'];
        $hora = normalize_time($data['hora_programada'] ?? $requests[0]['hora_servicio']);
        if (!valid_service_date_from_today((string)$fecha)) {
            $db->rollBack();
            json_response(false, 'La fecha de ruta no puede ser anterior a hoy', null, 422);
        }
        if (!valid_service_hour($hora)) {
            $db->rollBack();
            json_response(false, 'La hora de ruta debe estar entre 08:00 y 16:00', null, 422);
        }

        $totalPeople = 0;
        foreach ($requests as $request) {
            if ($request['fecha_servicio'] !== $fecha) {
                $db->rollBack();
                json_response(false, 'La ruta solo puede agrupar pedidos de la misma fecha', null, 422);
            }
            $totalPeople += (int)$request['cantidad_personas'];
        }

        $vehiculoStmt = $db->prepare('SELECT * FROM vehiculos WHERE id_vehiculo = ? FOR UPDATE');
        $vehiculoStmt->execute([(int)$data['id_vehiculo']]);
        $vehiculo = $vehiculoStmt->fetch();
        if (!$vehiculo || $vehiculo['estado'] !== 'disponible') {
            $db->rollBack();
            json_response(false, 'El vehículo no está disponible para la ruta', null, 422);
        }
        if ((int)$vehiculo['capacidad'] < $totalPeople) {
            $db->rollBack();
            json_response(false, 'El vehículo no tiene asientos suficientes para todos los pedidos de la ruta', null, 422);
        }

        $conductorStmt = $db->prepare("SELECT * FROM conductores WHERE id_conductor = ? AND estado = 'activo'");
        $conductorStmt->execute([(int)$data['id_conductor']]);
        if (!$conductorStmt->fetch()) {
            $db->rollBack();
            json_response(false, 'El conductor no existe o está inactivo', null, 422);
        }

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
            json_response(false, 'Ya existe una ruta o asignación para el mismo horario', null, 422);
        }

        $origin = clean_string($data['origen_ruta'] ?? 'Empresa JX');
        $plan = optimize_route_plan($requests, $origin);
        $codigoRuta = 'R' . date('ymdHis') . random_int(100, 999);
        $insert = $db->prepare(
            "INSERT INTO programaciones
             (id_solicitud, id_vehiculo, id_conductor, fecha_programada, hora_programada, destino, codigo_ruta, orden_ruta, origen_ruta, distancia_tramo_km, distancia_ruta_km, duracion_tramo_min, duracion_ruta_min, estado, observaciones)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'programada', ?)"
        );

        $offset = 0;
        $programacionIds = [];
        foreach ($plan['pedidos'] as $pedido) {
            $horaPedido = add_minutes_to_time($hora, $offset);
            $insert->execute([
                (int)$pedido['id_solicitud'],
                (int)$data['id_vehiculo'],
                (int)$data['id_conductor'],
                $fecha,
                $horaPedido,
                clean_string($pedido['direccion']),
                $codigoRuta,
                (int)$pedido['orden_ruta'],
                $plan['origen'],
                (float)$pedido['distancia_tramo_km'],
                (float)$plan['distancia_total_km'],
                (int)$pedido['duracion_tramo_min'],
                (int)$plan['duracion_total_min'],
                clean_string($data['observaciones'] ?? 'Ruta agrupada optimizada')
            ]);
            $programacionIds[] = (int)$db->lastInsertId();
            $offset += (int)$pedido['duracion_tramo_min'];
            $db->prepare("UPDATE solicitudes SET estado = 'programada' WHERE id_solicitud = ?")->execute([(int)$pedido['id_solicitud']]);
        }

        $db->prepare("UPDATE vehiculos SET estado = 'asignado' WHERE id_vehiculo = ?")->execute([(int)$data['id_vehiculo']]);
        $db->prepare("UPDATE cola_vehicular SET estado = 'asignado' WHERE id_vehiculo = ? AND estado = 'en_cola'")->execute([(int)$data['id_vehiculo']]);
        log_action($db, (int)$user['id_usuario'], 'programacion', 'crear_ruta', "Ruta {$codigoRuta} creada con pedidos agrupados");

        $db->commit();
        json_response(true, 'Ruta agrupada registrada correctamente', [
            'codigo_ruta' => $codigoRuta,
            'id_programaciones' => $programacionIds,
            'distancia_total_km' => $plan['distancia_total_km'],
            'duracion_total_min' => $plan['duracion_total_min']
        ], 201);
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        json_response(false, 'No se pudo registrar la ruta agrupada', null, 500);
    }
}

function programacion_suggest_vehicle(): void
{
    require_method(['GET', 'POST']);
    require_auth(['administrador', 'coordinador']);
    $data = $_SERVER['REQUEST_METHOD'] === 'GET' ? $_GET : get_json_input();
    $db = Database::connection();

    $capacidad = (int)($data['capacidad'] ?? 0);
    if (!empty($data['ids_solicitud'])) {
        $requests = route_requests($db, parse_request_ids($data['ids_solicitud']));
        $capacidad = 0;
        foreach ($requests as $request) {
            $capacidad += (int)$request['cantidad_personas'];
        }
    }
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
        json_response(false, 'Debe indicar una capacidad requerida válida', null, 422);
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

    if (!empty($programacion['codigo_ruta'])) {
        $db->prepare("UPDATE programaciones SET estado = 'en_ruta' WHERE codigo_ruta = ?")->execute([$programacion['codigo_ruta']]);
    } else {
        $db->prepare("UPDATE programaciones SET estado = 'en_ruta' WHERE id_programacion = ?")->execute([(int)$data['id_programacion']]);
    }
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

    if (!empty($programacion['codigo_ruta'])) {
        $db->prepare("UPDATE programaciones SET estado = 'cancelada' WHERE codigo_ruta = ?")->execute([$programacion['codigo_ruta']]);
        $db->prepare("UPDATE solicitudes s
            INNER JOIN programaciones p ON p.id_solicitud = s.id_solicitud
            SET s.estado = 'cancelada'
            WHERE p.codigo_ruta = ?")->execute([$programacion['codigo_ruta']]);
    } else {
        $db->prepare("UPDATE programaciones SET estado = 'cancelada' WHERE id_programacion = ?")->execute([(int)$data['id_programacion']]);
        $db->prepare("UPDATE solicitudes SET estado = 'cancelada' WHERE id_solicitud = ?")->execute([(int)$programacion['id_solicitud']]);
    }
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
        "SELECT p.id_programacion, p.codigo_ruta, p.orden_ruta, p.fecha_programada, p.hora_programada,
                p.destino, p.estado, p.distancia_ruta_km, p.duracion_ruta_min,
                s.id_solicitud, s.cantidad_personas, s.motivo,
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
         ORDER BY p.hora_programada, p.codigo_ruta, p.orden_ruta"
    );
    $stmt->execute([$fecha]);

    $grouped = [];
    foreach ($stmt->fetchAll() as $row) {
        $key = $row['codigo_ruta'] ?: 'P' . $row['id_programacion'];
        if (!isset($grouped[$key])) {
            $grouped[$key] = $row;
            $grouped[$key]['pedidos'] = [];
            $grouped[$key]['total_personas'] = 0;
        }
        $grouped[$key]['pedidos'][] = [
            'id_solicitud' => (int)$row['id_solicitud'],
            'orden_ruta' => (int)$row['orden_ruta'],
            'destino' => $row['destino'],
            'motivo' => $row['motivo'],
            'personas' => (int)$row['cantidad_personas']
        ];
        $grouped[$key]['total_personas'] += (int)$row['cantidad_personas'];
    }

    json_response(true, 'Programación pública obtenida correctamente', [
        'visible' => true,
        'fecha' => $fecha,
        'programaciones' => array_values($grouped)
    ]);
}
