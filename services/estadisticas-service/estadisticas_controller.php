<?php
function handle_estadisticas_request(string $action): void
{
    switch ($action) {
        case 'resumen':
            stats_summary();
            break;
        case 'solicitudes':
            stats_requests();
            break;
        case 'vehiculos':
            stats_vehicles();
            break;
        case 'kilometraje':
            stats_mileage();
            break;
        case 'areas':
            stats_areas();
            break;
        case 'conductores':
            stats_drivers();
            break;
        default:
            json_response(false, 'Acción no encontrada en Estadísticas Service', null, 404);
    }
}

function stats_auth(): void
{
    require_auth(['administrador', 'coordinador']);
}

function stats_summary(): void
{
    require_method(['GET']);
    stats_auth();
    $db = Database::connection();

    $data = [
        'solicitudes_hoy' => (int)$db->query("SELECT COUNT(*) total FROM solicitudes WHERE fecha_servicio = CURDATE()")->fetch()['total'],
        'solicitudes_pendientes' => (int)$db->query("SELECT COUNT(*) total FROM solicitudes WHERE estado = 'pendiente'")->fetch()['total'],
        'solicitudes_programadas' => (int)$db->query("SELECT COUNT(*) total FROM solicitudes WHERE estado = 'programada'")->fetch()['total'],
        'solicitudes_atendidas' => (int)$db->query("SELECT COUNT(*) total FROM solicitudes WHERE estado = 'atendida'")->fetch()['total'],
        'solicitudes_rechazadas' => (int)$db->query("SELECT COUNT(*) total FROM solicitudes WHERE estado = 'rechazada'")->fetch()['total'],
        'pedidos_especiales' => (int)$db->query("SELECT COUNT(*) total FROM solicitudes WHERE tipo_solicitud = 'especial'")->fetch()['total'],
        'pedidos_especiales_atender' => (int)$db->query("SELECT COUNT(*) total FROM solicitudes WHERE tipo_solicitud = 'especial' AND resultado_especial = 'atender' AND estado = 'pendiente'")->fetch()['total'],
        'pedidos_especiales_rechazados' => (int)$db->query("SELECT COUNT(*) total FROM solicitudes WHERE tipo_solicitud = 'especial' AND resultado_especial = 'rechazar'")->fetch()['total'],
        'vehiculos_disponibles' => (int)$db->query("SELECT COUNT(*) total FROM vehiculos WHERE estado = 'disponible'")->fetch()['total'],
        'vehiculos_en_ruta' => (int)$db->query("SELECT COUNT(*) total FROM vehiculos WHERE estado = 'en_ruta'")->fetch()['total'],
        'vehiculos_mantenimiento' => (int)$db->query("SELECT COUNT(*) total FROM vehiculos WHERE estado = 'mantenimiento'")->fetch()['total'],
        'atenciones_finalizadas' => (int)$db->query("SELECT COUNT(*) total FROM programaciones WHERE estado = 'finalizada'")->fetch()['total'],
        'kilometros_recorridos' => (int)$db->query("SELECT COALESCE(SUM(kilometros_recorridos),0) total FROM kilometrajes WHERE estado = 'finalizado'")->fetch()['total'],
        'cola_disponible' => (int)$db->query("SELECT COUNT(*) total FROM cola_vehicular WHERE estado = 'en_cola'")->fetch()['total'],
        'pendientes_km_final' => (int)$db->query("SELECT COUNT(*) total FROM kilometrajes WHERE estado = 'iniciado'")->fetch()['total'],
    ];

    $data['alertas'] = [];
    if ($data['solicitudes_pendientes'] > 0) {
        $data['alertas'][] = 'Hay solicitudes pendientes por programar.';
    }
    if ($data['vehiculos_en_ruta'] > 0) {
        $data['alertas'][] = 'Hay vehículos en ruta pendientes de retorno.';
    }
    if ($data['cola_disponible'] > 0) {
        $data['alertas'][] = 'Hay vehículos disponibles en cola.';
    }
    if ($data['pendientes_km_final'] > 0) {
        $data['alertas'][] = 'Una atención no tiene kilometraje final registrado.';
    }
    if ($data['solicitudes_pendientes'] > $data['vehiculos_disponibles']) {
        $data['alertas'][] = 'No hay vehículos suficientes para solicitudes pendientes.';
    }
    if ($data['pedidos_especiales_atender'] > 0) {
        $data['alertas'][] = 'Hay pedidos especiales con disponibilidad para programar hoy.';
    }
    if ($data['pedidos_especiales_rechazados'] > 0) {
        $data['alertas'][] = 'Hay pedidos especiales rechazados por falta de vehículo o asientos.';
    }

    json_response(true, 'Resumen estadístico obtenido correctamente', $data);
}

function stats_group(PDO $db, string $sql): array
{
    return $db->query($sql)->fetchAll();
}

function stats_requests(): void
{
    require_method(['GET']);
    stats_auth();
    $db = Database::connection();
    json_response(true, 'Estadísticas de solicitudes obtenidas correctamente', [
        'por_estado' => stats_group($db, "SELECT estado, COUNT(*) total FROM solicitudes GROUP BY estado"),
        'por_dia' => stats_group($db, "SELECT fecha_servicio AS fecha, COUNT(*) total FROM solicitudes GROUP BY fecha_servicio ORDER BY fecha_servicio DESC LIMIT 10")
    ]);
}

function stats_vehicles(): void
{
    require_method(['GET']);
    stats_auth();
    $db = Database::connection();
    json_response(true, 'Estadísticas de vehículos obtenidas correctamente', [
        'por_estado' => stats_group($db, "SELECT estado, COUNT(*) total FROM vehiculos GROUP BY estado"),
        'mas_usados' => stats_group($db, "SELECT v.placa, CONCAT(v.marca, ' ', v.modelo) vehiculo, COUNT(p.id_programacion) total
            FROM vehiculos v LEFT JOIN programaciones p ON p.id_vehiculo = v.id_vehiculo
            GROUP BY v.id_vehiculo ORDER BY total DESC LIMIT 5")
    ]);
}

function stats_mileage(): void
{
    require_method(['GET']);
    stats_auth();
    $db = Database::connection();
    $data = stats_group($db, "SELECT v.placa, CONCAT(v.marca, ' ', v.modelo) vehiculo,
            COALESCE(SUM(k.kilometros_recorridos),0) kilometros
        FROM vehiculos v
        LEFT JOIN kilometrajes k ON k.id_vehiculo = v.id_vehiculo AND k.estado = 'finalizado'
        GROUP BY v.id_vehiculo ORDER BY kilometros DESC");
    json_response(true, 'Estadísticas de kilometraje obtenidas correctamente', $data);
}

function stats_areas(): void
{
    require_method(['GET']);
    stats_auth();
    $db = Database::connection();
    $data = stats_group($db, "SELECT a.nombre AS area, COUNT(s.id_solicitud) total
        FROM areas a
        LEFT JOIN solicitudes s ON s.id_area = a.id_area
        GROUP BY a.id_area ORDER BY total DESC");
    json_response(true, 'Estadísticas por área obtenidas correctamente', $data);
}

function stats_drivers(): void
{
    require_method(['GET']);
    stats_auth();
    $db = Database::connection();
    $data = stats_group($db, "SELECT CONCAT(u.nombres, ' ', u.apellidos) conductor, COUNT(p.id_programacion) total
        FROM conductores c
        INNER JOIN usuarios u ON u.id_usuario = c.id_usuario
        LEFT JOIN programaciones p ON p.id_conductor = c.id_conductor
        GROUP BY c.id_conductor ORDER BY total DESC");
    json_response(true, 'Estadísticas por conductor obtenidas correctamente', $data);
}
