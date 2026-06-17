<?php
function handle_solicitudes_request(string $action): void
{
    switch ($action) {
        case 'crear':
            solicitudes_create();
            break;
        case 'evaluar-especial':
            solicitudes_evaluate_special();
            break;
        case 'listar':
            solicitudes_list();
            break;
        case 'pendientes':
            solicitudes_pending();
            break;
        case 'dia':
            solicitudes_day();
            break;
        case 'detalle':
            solicitudes_detail();
            break;
        case 'actualizar-estado':
            solicitudes_update_status();
            break;
        default:
            json_response(false, 'Acción no encontrada en Solicitudes Service', null, 404);
    }
}

function solicitudes_base_sql(): string
{
    return "SELECT s.*, u.nombres, u.apellidos, u.email, a.nombre AS area,
                   vs.placa AS vehiculo_sugerido_placa,
                   CONCAT(vs.marca, ' ', vs.modelo) AS vehiculo_sugerido,
                   vs.capacidad AS vehiculo_sugerido_capacidad
            FROM solicitudes s
            INNER JOIN usuarios u ON u.id_usuario = s.id_usuario
            INNER JOIN areas a ON a.id_area = s.id_area
            LEFT JOIN vehiculos vs ON vs.id_vehiculo = s.id_vehiculo_sugerido";
}

function solicitudes_create(): void
{
    require_method(['POST']);
    $user = require_auth(['administrador', 'coordinador', 'solicitante']);
    $data = get_json_input();
    required_fields($data, ['id_area', 'fecha_servicio', 'hora_servicio', 'direccion', 'cantidad_personas', 'motivo']);

    $db = Database::connection();
    $idArea = (int)$data['id_area'];
    ensure_area_exists($db, $idArea);

    $tipoSolicitud = clean_string($data['tipo_solicitud'] ?? 'normal');
    if (!in_array($tipoSolicitud, ['normal', 'especial'], true)) {
        json_response(false, 'Tipo de solicitud inválido', null, 422);
    }

    $isSpecial = $tipoSolicitud === 'especial';
    if ($isSpecial && !valid_service_date_from_today((string)$data['fecha_servicio'])) {
        json_response(false, 'El pedido especial no puede tener una fecha anterior a hoy', null, 422);
    }

    if (!$isSpecial && !valid_service_date((string)$data['fecha_servicio'])) {
        json_response(false, 'La fecha del servicio debe ser como mínimo para el día siguiente', null, 422);
    }

    if (!valid_service_hour((string)$data['hora_servicio'])) {
        json_response(false, 'La hora debe estar entre 08:00 y 16:00', null, 422);
    }

    $cantidad = (int)$data['cantidad_personas'];
    if ($cantidad <= 0) {
        json_response(false, 'La cantidad de personas debe ser mayor que 0', null, 422);
    }

    $estado = 'pendiente';
    $resultadoEspecial = 'no_aplica';
    $motivoRechazo = null;
    $idVehiculoSugerido = null;
    $vehiculoSugerido = null;

    if ($isSpecial) {
        $vehiculoSugerido = find_available_vehicle_for_capacity($db, $cantidad);
        if ($vehiculoSugerido) {
            $resultadoEspecial = 'atender';
            $idVehiculoSugerido = (int)$vehiculoSugerido['id_vehiculo'];
        } else {
            $resultadoEspecial = 'rechazar';
            $estado = 'rechazada';
            $motivoRechazo = 'No hay vehículos disponibles con capacidad suficiente para atender este pedido especial.';
        }
    }

    $idUsuario = (int)$user['id_usuario'];
    if (in_array($user['rol'], ['administrador', 'coordinador'], true) && !empty($data['id_usuario'])) {
        $idUsuario = (int)$data['id_usuario'];
    }

    $stmt = $db->prepare(
        "INSERT INTO solicitudes
         (id_usuario, id_area, fecha_solicitud, fecha_servicio, hora_servicio, direccion, cantidad_personas, motivo, observaciones, tipo_solicitud, resultado_especial, motivo_rechazo, id_vehiculo_sugerido, estado)
         VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $idUsuario,
        $idArea,
        $data['fecha_servicio'],
        normalize_time((string)$data['hora_servicio']),
        clean_string($data['direccion']),
        $cantidad,
        clean_string($data['motivo']),
        clean_string($data['observaciones'] ?? ''),
        $tipoSolicitud,
        $resultadoEspecial,
        $motivoRechazo,
        $idVehiculoSugerido,
        $estado
    ]);
    log_action($db, (int)$user['id_usuario'], 'solicitudes', 'crear', $isSpecial ? 'Pedido especial evaluado y registrado' : 'Solicitud vehicular registrada');

    $response = [
        'id_solicitud' => (int)$db->lastInsertId(),
        'tipo_solicitud' => $tipoSolicitud,
        'resultado_especial' => $resultadoEspecial,
        'estado' => $estado,
        'motivo_rechazo' => $motivoRechazo
    ];
    if ($vehiculoSugerido) {
        $response['vehiculo_sugerido'] = [
            'id_vehiculo' => (int)$vehiculoSugerido['id_vehiculo'],
            'placa' => $vehiculoSugerido['placa'],
            'vehiculo' => "{$vehiculoSugerido['marca']} {$vehiculoSugerido['modelo']}",
            'capacidad' => (int)$vehiculoSugerido['capacidad']
        ];
    }

    $message = 'Solicitud registrada correctamente';
    if ($isSpecial && $resultadoEspecial === 'atender') {
        $message = 'Pedido especial atendible: hay vehículo y asientos disponibles';
    }
    if ($isSpecial && $resultadoEspecial === 'rechazar') {
        $message = 'Pedido especial registrado como rechazado por falta de disponibilidad';
    }

    json_response(true, $message, $response, 201);
}

function solicitudes_evaluate_special(): void
{
    require_method(['GET', 'POST']);
    require_auth(['administrador', 'coordinador', 'solicitante']);
    $data = $_SERVER['REQUEST_METHOD'] === 'GET' ? $_GET : get_json_input();
    required_fields($data, ['fecha_servicio', 'hora_servicio', 'cantidad_personas']);

    if (!valid_service_date_from_today((string)$data['fecha_servicio'])) {
        json_response(false, 'El pedido especial no puede tener una fecha anterior a hoy', null, 422);
    }

    if (!valid_service_hour((string)$data['hora_servicio'])) {
        json_response(false, 'La hora debe estar entre 08:00 y 16:00', null, 422);
    }

    $cantidad = (int)$data['cantidad_personas'];
    if ($cantidad <= 0) {
        json_response(false, 'La cantidad de personas debe ser mayor que 0', null, 422);
    }

    $db = Database::connection();
    $vehiculo = find_available_vehicle_for_capacity($db, $cantidad);
    if (!$vehiculo) {
        json_response(true, 'No hay vehículos disponibles con capacidad suficiente para este pedido especial', [
            'disponible' => false,
            'decision' => 'rechazar',
            'motivo_rechazo' => 'No hay vehículos disponibles con capacidad suficiente para atender este pedido especial.'
        ]);
    }

    $vehiculo['justificacion'] = "Se puede atender porque el vehículo {$vehiculo['placa']} tiene {$vehiculo['capacidad']} asientos y está disponible en cola.";
    json_response(true, 'Pedido especial atendible', [
        'disponible' => true,
        'decision' => 'atender',
        'vehiculo' => $vehiculo
    ]);
}

function solicitudes_list(): void
{
    require_method(['GET']);
    $user = require_auth(['administrador', 'coordinador', 'solicitante']);
    $db = Database::connection();

    $where = [];
    $params = [];

    if ($user['rol'] === 'solicitante') {
        $where[] = 's.id_usuario = ?';
        $params[] = $user['id_usuario'];
    }

    if (!empty($_GET['estado'])) {
        $where[] = 's.estado = ?';
        $params[] = $_GET['estado'];
    }
    if (!empty($_GET['tipo_solicitud'])) {
        $where[] = 's.tipo_solicitud = ?';
        $params[] = $_GET['tipo_solicitud'];
    }
    if (!empty($_GET['fecha'])) {
        $where[] = 's.fecha_servicio = ?';
        $params[] = $_GET['fecha'];
    }
    if (!empty($_GET['id_area'])) {
        $where[] = 's.id_area = ?';
        $params[] = (int)$_GET['id_area'];
    }

    $sql = solicitudes_base_sql();
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY s.fecha_servicio DESC, s.hora_servicio DESC';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    json_response(true, 'Solicitudes obtenidas correctamente', $stmt->fetchAll());
}

function solicitudes_pending(): void
{
    require_method(['GET']);
    require_auth(['administrador', 'coordinador']);
    $db = Database::connection();
    $stmt = $db->query(solicitudes_base_sql() . " WHERE s.estado = 'pendiente' ORDER BY s.fecha_servicio, s.hora_servicio");
    json_response(true, 'Solicitudes pendientes obtenidas correctamente', $stmt->fetchAll());
}

function solicitudes_day(): void
{
    require_method(['GET']);
    $user = require_auth(['administrador', 'coordinador', 'solicitante', 'conductor']);
    $db = Database::connection();
    $date = $_GET['fecha'] ?? date('Y-m-d');
    $sql = solicitudes_base_sql() . ' WHERE s.fecha_servicio = ?';
    $params = [$date];
    if ($user['rol'] === 'solicitante') {
        $sql .= ' AND s.id_usuario = ?';
        $params[] = $user['id_usuario'];
    }
    $sql .= ' ORDER BY s.hora_servicio';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    json_response(true, 'Solicitudes del día obtenidas correctamente', $stmt->fetchAll());
}

function solicitudes_detail(): void
{
    require_method(['GET']);
    $user = require_auth(['administrador', 'coordinador', 'solicitante']);
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        json_response(false, 'Debe indicar el id de la solicitud', null, 400);
    }

    $db = Database::connection();
    $stmt = $db->prepare(solicitudes_base_sql() . ' WHERE s.id_solicitud = ? LIMIT 1');
    $stmt->execute([$id]);
    $solicitud = $stmt->fetch();
    if (!$solicitud) {
        json_response(false, 'Solicitud no encontrada', null, 404);
    }
    if ($user['rol'] === 'solicitante' && (int)$solicitud['id_usuario'] !== (int)$user['id_usuario']) {
        json_response(false, 'No puede consultar esta solicitud', null, 403);
    }

    json_response(true, 'Detalle obtenido correctamente', $solicitud);
}

function solicitudes_update_status(): void
{
    require_method(['PUT', 'POST']);
    $user = require_auth(['administrador', 'coordinador', 'solicitante']);
    $data = get_json_input();
    required_fields($data, ['id_solicitud', 'estado']);

    $valid = ['pendiente', 'programada', 'atendida', 'rechazada', 'cancelada'];
    if (!in_array($data['estado'], $valid, true)) {
        json_response(false, 'Estado de solicitud inválido', null, 422);
    }

    $db = Database::connection();
    $stmt = $db->prepare('SELECT * FROM solicitudes WHERE id_solicitud = ?');
    $stmt->execute([(int)$data['id_solicitud']]);
    $solicitud = $stmt->fetch();
    if (!$solicitud) {
        json_response(false, 'Solicitud no encontrada', null, 404);
    }

    if ($user['rol'] === 'solicitante') {
        if ((int)$solicitud['id_usuario'] !== (int)$user['id_usuario'] || $data['estado'] !== 'cancelada') {
            json_response(false, 'El solicitante solo puede cancelar sus solicitudes', null, 403);
        }
    }

    $update = $db->prepare('UPDATE solicitudes SET estado = ? WHERE id_solicitud = ?');
    $update->execute([$data['estado'], (int)$data['id_solicitud']]);
    log_action($db, (int)$user['id_usuario'], 'solicitudes', 'estado', 'Cambio de estado de solicitud');

    json_response(true, 'Estado actualizado correctamente');
}
