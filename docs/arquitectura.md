# Arquitectura

El sistema usa una arquitectura basada en microservicios simulados en PHP puro. Cada dominio funcional vive en una carpeta propia dentro de `services/` y expone acciones REST que responden siempre en JSON.

## Componentes

- Frontend: HTML, CSS, JavaScript, Bootstrap 5, Bootstrap Icons y Chart.js.
- API Gateway: `api-gateway/index.php`, encargado de recibir `service` y `action`.
- Microservicios: controladores PHP independientes por responsabilidad.
- Base de datos: MySQL con tablas relacionales, llaves foráneas y datos iniciales.

## Flujo de comunicación

```text
Usuario
  -> Dashboard Web
  -> Fetch API / JSON
  -> API Gateway
  -> Servicio PHP correspondiente
  -> PDO / MySQL
```

## Servicios

- Auth Service: login, logout, perfil, usuarios y roles.
- Solicitudes Service: registro, listado, pendientes, detalle y cambios de estado.
- Vehículos Service: flota, disponibilidad y estados.
- Conductores Service: alta y estado de conductores.
- Programación Service: asignación, sugerencia, inicio de ruta, panel público y línea de tiempo.
- Cola Service: cola FIFO de vehículos disponibles.
- Kilometraje Service: registro inicial, final y recorrido calculado.
- Retorno Service: retorno vehicular y cierre de atención.
- Estadísticas Service: resumen, gráficos e indicadores.
- Áreas Service: catálogo de áreas institucionales.

## API Gateway

El gateway evita que el frontend conozca rutas internas de cada servicio. Ejemplo:

```text
GET /api-gateway/index.php?service=vehiculos&action=listar
```

Internamente carga el controlador correspondiente y ejecuta el manejador del servicio.

## Base de datos

La base `db_sistema_vehicular` usa tablas normalizadas para roles, usuarios, áreas, conductores, vehículos, solicitudes, programaciones, cola, kilometrajes, retornos y logs. Las acciones importantes se registran en `logs_sistema`.
