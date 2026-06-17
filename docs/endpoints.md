# Endpoints

Todas las respuestas usan:

```json
{
  "success": true,
  "message": "Operación realizada correctamente",
  "data": {}
}
```

La URL base es:

```text
/api-gateway/index.php?service={servicio}&action={accion}
```

Enviar token en endpoints protegidos:

```text
Authorization: Bearer TOKEN
```

## Auth

| Método | Servicio | Acción | Rol |
| --- | --- | --- | --- |
| POST | auth | login | Público |
| POST | auth | logout | Autenticado |
| GET | auth | perfil | Autenticado |
| GET | auth | usuarios | administrador, coordinador |
| POST | auth | crear-usuario | administrador |
| PUT | auth | actualizar-usuario | administrador |
| GET | auth | roles | administrador, coordinador |

## Áreas

| Método | Servicio | Acción | Rol |
| --- | --- | --- | --- |
| GET | areas | listar | administrador, coordinador, solicitante |
| POST | areas | crear | administrador |
| PUT | areas | actualizar | administrador |

## Solicitudes

| Método | Servicio | Acción | Rol |
| --- | --- | --- | --- |
| POST | solicitudes | crear | administrador, coordinador, solicitante |
| GET | solicitudes | listar | administrador, coordinador, solicitante |
| GET | solicitudes | pendientes | administrador, coordinador |
| GET | solicitudes | dia | administrador, coordinador, solicitante, conductor |
| GET | solicitudes | detalle | administrador, coordinador, solicitante |
| PUT | solicitudes | actualizar-estado | administrador, coordinador, solicitante |

Parámetros destacados: `id`, `estado`, `fecha`, `id_area`.

## Vehículos

| Método | Servicio | Acción | Rol |
| --- | --- | --- | --- |
| GET | vehiculos | listar | administrador, coordinador, conductor |
| GET | vehiculos | disponibles | administrador, coordinador |
| POST | vehiculos | crear | administrador, coordinador |
| PUT | vehiculos | actualizar-estado | administrador, coordinador |
| GET | vehiculos | detalle | administrador, coordinador, conductor |

## Conductores

| Método | Servicio | Acción | Rol |
| --- | --- | --- | --- |
| GET | conductores | listar | administrador, coordinador |
| GET | conductores | activos | administrador, coordinador |
| POST | conductores | crear | administrador, coordinador |
| PUT | conductores | actualizar-estado | administrador, coordinador |

## Programación

| Método | Servicio | Acción | Rol |
| --- | --- | --- | --- |
| GET | programacion | listar | administrador, coordinador, conductor |
| POST | programacion | crear | administrador, coordinador |
| GET/POST | programacion | sugerir-vehiculo | administrador, coordinador |
| POST/PUT | programacion | iniciar-ruta | administrador, coordinador, conductor |
| POST/PUT | programacion | cancelar | administrador, coordinador |
| GET | programacion | detalle | administrador, coordinador, conductor |
| GET | programacion | publica | Público |

## Cola

| Método | Servicio | Acción | Rol |
| --- | --- | --- | --- |
| GET | cola | listar | administrador, coordinador, conductor |
| POST | cola | agregar | administrador, coordinador |
| PUT | cola | retirar | administrador, coordinador |
| PUT | cola | reordenar | administrador, coordinador |
| GET | cola | siguiente | administrador, coordinador |

## Kilometraje

| Método | Servicio | Acción | Rol |
| --- | --- | --- | --- |
| GET | kilometraje | listar | administrador, coordinador, conductor |
| POST | kilometraje | registrar-inicial | administrador, coordinador, conductor |
| POST/PUT | kilometraje | registrar-final | administrador, coordinador, conductor |
| GET | kilometraje | pendientes-final | administrador, coordinador |

## Retorno

| Método | Servicio | Acción | Rol |
| --- | --- | --- | --- |
| GET | retorno | en-ruta | administrador, coordinador, conductor |
| POST | retorno | registrar | administrador, coordinador, conductor |
| GET | retorno | listar | administrador, coordinador, conductor |

## Estadísticas

| Método | Servicio | Acción | Rol |
| --- | --- | --- | --- |
| GET | estadisticas | resumen | administrador, coordinador |
| GET | estadisticas | solicitudes | administrador, coordinador |
| GET | estadisticas | vehiculos | administrador, coordinador |
| GET | estadisticas | kilometraje | administrador, coordinador |
| GET | estadisticas | areas | administrador, coordinador |
| GET | estadisticas | conductores | administrador, coordinador |
