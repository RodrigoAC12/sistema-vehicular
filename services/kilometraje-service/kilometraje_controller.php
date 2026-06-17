<?php
function handle_kilometraje_request(string $action): void
{
    switch ($action) {
        case 'listar':
            kilometraje_list();
            break;
        case 'registrar-inicial':
            kilometraje_start();
            break;
        case 'registrar-final':
            kilometraje_finish();
            break;
        case 'pendientes-final':
            kilometraje_pending_finish();
            break;
        default:
            json_response(false, 'Acción no encontrada en Kilometraje Service', null, 404);
    }
}

function kilometraje_base_sql(): string
{
    return "SELECT k.*, p.fecha_programada, p.hora_programada, p.destino, p.estado AS estado_programacion,
                   v.placa, v.marca, v.modelo, v.kilometraje_actual,
                   c.id_usuario AS id_usuario_conductor,
                   CONCAT(u.nombres, ' ', u.apellidos) AS conductor
            FROM kilometrajes k
            INNER JOIN programaciones p ON p.id_programacion = k.id_programacion
            INNER JOIN vehiculos v ON v.id_vehiculo = k.id_vehiculo
            INNER JOIN conductores c ON c.id_conductor = k.id_conductor
            INNER JOIN usuarios u ON u.id_usuario = c.id_usuario";
}

function kilometraje_list(): void
{
    require_method(['GET']);
    $user = require_auth(['administrador', 'coordinador', 'conductor']);
    $db = Database::connection();
    $sql = kilometraje_base_sql();
    $params = [];
    if ($user['rol'] === 'conductor') {
        $sql .= ' WHERE c.id_usuario = ?';
        $params[] = $user['id_usuario'];
    }
    $sql .= ' ORDER BY k.created_at DESC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    json_response(true, 'Kilometrajes obtenidos correctamente', $stmt->fetchAll());
}

function kilometraje_start(): void
{
    require_method(['POST']);
    $user = require_auth(['administrador', 'coordinador', 'conductor']);
    $data = get_json_input();
    required_fields($data, ['id_programacion', 'kilometraje_inicial']);
    $kmInicial = (int)$data['kilometraje_inicial'];

    $db = Database::connection();
    $stmt = $db->prepare(
        "SELECT p.*, v.kilometraje_actual, c.id_usuario AS id_usuario_conductor
         FROM programaciones p
         INNER JOIN vehiculos v ON v.id_vehiculo = p.id_vehiculo
         INNER JOIN conductores c ON c.id_conductor = p.id_conductor
         WHERE p.id_programacion = ?"
    );
    $stmt->execute([(int)$data['id_programacion']]);
    $programacion = $stmt->fetch();
    if (!$programacion || $programacion['estado'] !== 'programada') {
        json_response(false, 'Solo se registra kilometraje inicial en programaciones programadas', null, 422);
    }
    if ($user['rol'] === 'conductor' && (int)$programacion['id_usuario_conductor'] !== (int)$user['id_usuario']) {
        json_response(false, 'No puede registrar kilometraje de otra asignación', null, 403);
    }
    if ($kmInicial < (int)$programacion['kilometraje_actual']) {
        json_response(false, 'El kilometraje inicial debe ser mayor o igual al kilometraje actual del vehículo', null, 422);
    }

    $exists = $db->prepare('SELECT id_kilometraje FROM kilometrajes WHERE id_programacion = ?');
    $exists->execute([(int)$data['id_programacion']]);
    if ($exists->fetch()) {
        json_response(false, 'Esta programación ya tiene kilometraje inicial registrado', null, 422);
    }

    $insert = $db->prepare(
        "INSERT INTO kilometrajes
         (id_programacion, id_vehiculo, id_conductor, kilometraje_inicial, fecha_registro_inicial, estado)
         VALUES (?, ?, ?, ?, NOW(), 'iniciado')"
    );
    $insert->execute([
        (int)$data['id_programacion'],
        (int)$programacion['id_vehiculo'],
        (int)$programacion['id_conductor'],
        $kmInicial
    ]);
    log_action($db, (int)$user['id_usuario'], 'kilometraje', 'inicial', 'Kilometraje inicial registrado');

    json_response(true, 'Kilometraje inicial registrado correctamente', ['id_kilometraje' => (int)$db->lastInsertId()], 201);
}

function kilometraje_finish(): void
{
    require_method(['POST', 'PUT']);
    $user = require_auth(['administrador', 'coordinador', 'conductor']);
    $data = get_json_input();
    required_fields($data, ['id_programacion', 'kilometraje_final']);
    $kmFinal = (int)$data['kilometraje_final'];

    $db = Database::connection();
    $stmt = $db->prepare(kilometraje_base_sql() . " WHERE k.id_programacion = ? LIMIT 1");
    $stmt->execute([(int)$data['id_programacion']]);
    $km = $stmt->fetch();
    if (!$km || $km['estado'] !== 'iniciado') {
        json_response(false, 'No existe kilometraje inicial pendiente para finalizar', null, 422);
    }
    if ($user['rol'] === 'conductor' && (int)$km['id_usuario_conductor'] !== (int)$user['id_usuario']) {
        json_response(false, 'No puede registrar kilometraje de otra asignación', null, 403);
    }
    if ($kmFinal <= (int)$km['kilometraje_inicial']) {
        json_response(false, 'El kilometraje final debe ser mayor que el kilometraje inicial', null, 422);
    }

    $recorrido = $kmFinal - (int)$km['kilometraje_inicial'];
    $update = $db->prepare(
        "UPDATE kilometrajes
         SET kilometraje_final = ?, kilometros_recorridos = ?, fecha_registro_final = NOW(), estado = 'finalizado'
         WHERE id_kilometraje = ?"
    );
    $update->execute([$kmFinal, $recorrido, (int)$km['id_kilometraje']]);
    $db->prepare('UPDATE vehiculos SET kilometraje_actual = ? WHERE id_vehiculo = ?')->execute([$kmFinal, (int)$km['id_vehiculo']]);
    log_action($db, (int)$user['id_usuario'], 'kilometraje', 'final', 'Kilometraje final registrado');

    json_response(true, 'Kilometraje final registrado correctamente', ['kilometros_recorridos' => $recorrido]);
}

function kilometraje_pending_finish(): void
{
    require_method(['GET']);
    require_auth(['administrador', 'coordinador']);
    $db = Database::connection();
    $stmt = $db->query(kilometraje_base_sql() . " WHERE k.estado = 'iniciado' ORDER BY k.created_at DESC");
    json_response(true, 'Kilometrajes pendientes de cierre obtenidos correctamente', $stmt->fetchAll());
}
