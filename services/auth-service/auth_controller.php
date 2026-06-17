<?php
function handle_auth_request(string $action): void
{
    switch ($action) {
        case 'login':
            auth_login();
            break;
        case 'logout':
            auth_logout();
            break;
        case 'perfil':
            auth_profile();
            break;
        case 'usuarios':
            auth_users();
            break;
        case 'crear-usuario':
            auth_create_user();
            break;
        case 'actualizar-usuario':
            auth_update_user();
            break;
        case 'roles':
            auth_roles();
            break;
        default:
            json_response(false, 'Acción no encontrada en Auth Service', null, 404);
    }
}

function auth_login(): void
{
    require_method(['POST']);
    $data = get_json_input();
    required_fields($data, ['email', 'password']);

    $db = Database::connection();
    $stmt = $db->prepare(
        "SELECT u.*, r.nombre AS rol, a.nombre AS area
         FROM usuarios u
         INNER JOIN roles r ON r.id_rol = u.id_rol
         LEFT JOIN areas a ON a.id_area = u.id_area
         WHERE u.email = ? AND u.estado = 'activo'
         LIMIT 1"
    );
    $stmt->execute([trim($data['email'])]);
    $user = $stmt->fetch();

    if (!$user || !password_verify((string)$data['password'], $user['password'])) {
        json_response(false, 'Credenciales incorrectas', null, 401);
    }

    $token = bin2hex(random_bytes(32));
    $update = $db->prepare('UPDATE usuarios SET token = ? WHERE id_usuario = ?');
    $update->execute([$token, $user['id_usuario']]);
    log_action($db, (int)$user['id_usuario'], 'auth', 'login', 'Inicio de sesión correcto');

    json_response(true, 'Inicio de sesión correcto', [
        'token' => $token,
        'usuario' => [
            'id_usuario' => (int)$user['id_usuario'],
            'id_area' => $user['id_area'] ? (int)$user['id_area'] : null,
            'nombres' => $user['nombres'],
            'apellidos' => $user['apellidos'],
            'email' => $user['email'],
            'rol' => $user['rol'],
            'area' => $user['area']
        ]
    ]);
}

function auth_logout(): void
{
    require_method(['POST']);
    $user = require_auth();
    $db = Database::connection();
    $stmt = $db->prepare('UPDATE usuarios SET token = NULL WHERE id_usuario = ?');
    $stmt->execute([$user['id_usuario']]);
    log_action($db, (int)$user['id_usuario'], 'auth', 'logout', 'Cierre de sesión');

    json_response(true, 'Sesión cerrada correctamente');
}

function auth_profile(): void
{
    require_method(['GET']);
    $user = require_auth();
    json_response(true, 'Perfil obtenido correctamente', $user);
}

function auth_users(): void
{
    require_method(['GET']);
    require_auth(['administrador', 'coordinador']);
    $db = Database::connection();
    $stmt = $db->query(
        "SELECT u.id_usuario, u.id_rol, u.id_area, u.nombres, u.apellidos, u.email, u.estado,
                r.nombre AS rol, a.nombre AS area, u.created_at
         FROM usuarios u
         INNER JOIN roles r ON r.id_rol = u.id_rol
         LEFT JOIN areas a ON a.id_area = u.id_area
         ORDER BY u.nombres, u.apellidos"
    );

    json_response(true, 'Usuarios obtenidos correctamente', $stmt->fetchAll());
}

function auth_create_user(): void
{
    require_method(['POST']);
    $actor = require_auth(['administrador']);
    $data = get_json_input();
    required_fields($data, ['id_rol', 'nombres', 'apellidos', 'email', 'password']);

    $db = Database::connection();
    $idArea = isset($data['id_area']) && $data['id_area'] !== '' ? (int)$data['id_area'] : null;
    if ($idArea !== null) {
        ensure_area_exists($db, $idArea);
    }

    $stmt = $db->prepare(
        "INSERT INTO usuarios (id_rol, id_area, nombres, apellidos, email, password, estado)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        (int)$data['id_rol'],
        $idArea,
        clean_string($data['nombres']),
        clean_string($data['apellidos']),
        trim($data['email']),
        password_hash((string)$data['password'], PASSWORD_DEFAULT),
        $data['estado'] ?? 'activo'
    ]);
    log_action($db, (int)$actor['id_usuario'], 'usuarios', 'crear', 'Usuario creado: ' . trim($data['email']));

    json_response(true, 'Usuario registrado correctamente', ['id_usuario' => (int)$db->lastInsertId()], 201);
}

function auth_update_user(): void
{
    require_method(['PUT', 'POST']);
    $actor = require_auth(['administrador']);
    $data = get_json_input();
    required_fields($data, ['id_usuario', 'estado']);

    $estado = in_array($data['estado'], ['activo', 'inactivo'], true) ? $data['estado'] : null;
    if (!$estado) {
        json_response(false, 'Estado de usuario inválido', null, 422);
    }

    $db = Database::connection();
    $stmt = $db->prepare('UPDATE usuarios SET estado = ? WHERE id_usuario = ?');
    $stmt->execute([$estado, (int)$data['id_usuario']]);
    log_action($db, (int)$actor['id_usuario'], 'usuarios', 'estado', 'Cambio de estado de usuario');

    json_response(true, 'Usuario actualizado correctamente');
}

function auth_roles(): void
{
    require_method(['GET']);
    require_auth(['administrador', 'coordinador']);
    $db = Database::connection();
    $stmt = $db->query('SELECT id_rol, nombre, descripcion FROM roles ORDER BY id_rol');
    json_response(true, 'Roles obtenidos correctamente', $stmt->fetchAll());
}
