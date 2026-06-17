# Sistema de Gestión de Atención Vehicular Empresarial

Sistema web para administrar solicitudes, programación, disponibilidad, kilometraje, retornos y estadísticas de atención vehicular en una organización. El proyecto está desarrollado con PHP puro y una arquitectura de microservicios simulados, listo para ejecutarse localmente en XAMPP.

## Tecnologías

- PHP puro con PDO
- APIs REST con respuestas JSON
- MySQL
- HTML, CSS y JavaScript
- Fetch API / AJAX
- Bootstrap 5
- Bootstrap Icons
- Chart.js
- XAMPP

## Arquitectura

El sistema no usa una capa de servidor monolítica. La comunicación se organiza así:

```text
Usuario
  -> Interfaz web
  -> API Gateway
  -> Microservicios PHP
  -> MySQL
```

La interfaz web consume la capa de servicios mediante Fetch API. El punto central de entrada es:

```text
api-gateway/index.php?service={servicio}&action={accion}
```

Servicios incluidos:

- Auth Service
- Solicitudes Service
- Vehículos Service
- Conductores Service
- Programación Service
- Cola Service
- Kilometraje Service
- Retorno Service
- Estadísticas Service
- Áreas Service

## Funcionalidades

- Inicio de sesión por roles.
- Registro de solicitudes vehiculares.
- Validación de fecha mínima desde el día siguiente.
- Pedidos especiales para el mismo día sujetos a disponibilidad real.
- Evaluación de asientos y vehículo disponible antes de atender o rechazar un pedido especial.
- Corte horario: solicitudes ingresadas después de las 4:00 p.m. quedan rechazadas.
- Rutas agrupadas para atender varios pedidos con un mismo vehículo y conductor.
- Optimización local de ruta corta por orden estimado entre direcciones.
- Validación de horario permitido de 08:00 a 16:00.
- Gestión de vehículos y conductores.
- Cola vehicular con criterio FIFO.
- Sugerencia automática de vehículo por capacidad, disponibilidad y orden en cola.
- Programación de atenciones vehiculares.
- Registro de kilometraje inicial y final.
- Cálculo automático de kilómetros recorridos.
- Registro de retornos.
- Reingreso automático del vehículo a cola luego del retorno.
- Panel de control con indicadores y alertas.
- Estadísticas con gráficos.
- Panel público de programación.
- Datos iniciales realistas para pruebas y presentación.

## Requisitos

- XAMPP con Apache y MySQL activos.
- PHP incluido en XAMPP.
- Navegador moderno.
- Conexión a internet para cargar Bootstrap, Bootstrap Icons y Chart.js desde CDN.

## Instalación en XAMPP

1. Descargue o clone este repositorio.
2. Copie la carpeta del proyecto dentro de `C:\xampp\htdocs`.
3. Inicie Apache y MySQL desde el panel de XAMPP.
4. Abra phpMyAdmin:

```text
http://localhost/phpmyadmin
```

5. Importe el archivo:

```text
database/db_sistema_vehicular.sql
```

Si ya tenía una base importada antes de los pedidos especiales, aplique:

```text
database/migrations/2026_06_17_pedidos_especiales.sql
database/migrations/2026_06_17_rutas_agrupadas.sql
database/migrations/2026_06_17_corregir_codificacion_datos.sql
```

6. Verifique la conexión en:

```text
services/shared/database.php
```

Configuración por defecto:

```php
$host = 'localhost';
$database = 'db_sistema_vehicular';
$user = 'root';
$password = '';
```

7. Abra el sistema:

```text
http://localhost/sistema-vehicular/frontend/login.html
```

Si la carpeta está dentro de otra carpeta, ajuste la URL según su ruta local.

## Usuarios de prueba

Todos los usuarios usan la contraseña:

```text
admin123
```

| Rol | Correo |
| --- | --- |
| Administrador | `admin@sistema.com` |
| Coordinador | `coordinador@sistema.com` |
| Solicitante | `solicitante@sistema.com` |
| Conductor | `conductor@sistema.com` |
| Visualizador | `visualizador@sistema.com` |

## Flujo principal de uso

1. Iniciar sesión como solicitante.
2. Registrar una solicitud vehicular para mañana o una fecha posterior.
3. Si es urgente, registrar un **Pedido especial** para hoy; el sistema evaluará vehículo y asientos disponibles.
4. Iniciar sesión como coordinador.
5. Revisar solicitudes pendientes y pedidos especiales atendibles.
6. Para una atención individual, usar **Sugerir vehículo** o asignar uno manualmente.
7. Para varios pedidos, seleccionar pedidos en **Ruta agrupada**, optimizar ruta corta y guardar el mismo vehículo/conductor.
8. Iniciar sesión como conductor.
9. Registrar kilometraje inicial.
10. Iniciar ruta.
11. Registrar kilometraje final.
12. Registrar retorno.
13. Revisar el panel de control, la cola y las estadísticas actualizadas.

## Módulos del sistema

- **Panel de control:** indicadores generales, alertas inteligentes y gráficos.
- **Solicitudes:** registro guiado, pedidos normales y pedidos especiales con evaluación de disponibilidad.
- **Vehículos:** administración de flota, estados, capacidad y kilometraje.
- **Conductores:** registro y estado de conductores.
- **Programación:** asignación individual, rutas agrupadas y optimización local de ruta corta.
- **Cola:** vehículos disponibles ordenados por FIFO.
- **Kilometraje:** registro inicial, final y recorrido.
- **Retornos:** cierre de atenciones y retorno a disponibilidad.
- **Estadísticas:** análisis por estados, áreas, conductores y vehículos.
- **Usuarios:** gestión básica de accesos.
- **Panel público:** visualización de programación publicada.

## Estructura del proyecto

```text
sistema-vehicular/
  api-gateway/
    index.php
    routes.php
  database/
    db_sistema_vehicular.sql
    migrations/
  docs/
    arquitectura.md
    endpoints.md
    instalacion_xampp.md
    manual_usuario.md
    reglas_negocio.md
  frontend/
    assets/
      css/
      img/
      js/
    login.html
    dashboard.html
    solicitudes.html
    vehiculos.html
    conductores.html
    programacion.html
    panel-publico.html
    retornos.html
    kilometraje.html
    cola.html
    estadisticas.html
    usuarios.html
  services/
    shared/
    auth-service/
    solicitudes-service/
    vehiculos-service/
    conductores-service/
    programacion-service/
    cola-service/
    kilometraje-service/
    retorno-service/
    estadisticas-service/
    areas-service/
```

## Seguridad implementada

- Contraseñas con `password_hash`.
- Validación con `password_verify`.
- Tokens simples por sesión.
- Middleware de autenticación.
- Control de acceso por rol.
- PDO con consultas preparadas.
- Validaciones en servidor e interfaz web.
- Respuestas JSON uniformes.

## Documentación adicional

La carpeta `docs/` contiene:

- Arquitectura del sistema.
- Listado de endpoints.
- Reglas de negocio.
- Instalación en XAMPP.
- Manual de usuario.

## Estado del proyecto

Proyecto funcional, modular y listo para presentación académica o demostración local en XAMPP.
