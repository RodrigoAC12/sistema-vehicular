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

El sistema no usa un backend monolítico. La comunicación se organiza así:

```text
Usuario
  -> Frontend Web
  -> API Gateway
  -> Microservicios PHP
  -> MySQL
```

El frontend consume el backend mediante Fetch API. El punto central de entrada es:

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
- Validación de horario permitido de 08:00 a 16:00.
- Gestión de vehículos y conductores.
- Cola vehicular con criterio FIFO.
- Sugerencia automática de vehículo por capacidad, disponibilidad y orden en cola.
- Programación de atenciones vehiculares.
- Registro de kilometraje inicial y final.
- Cálculo automático de kilómetros recorridos.
- Registro de retornos.
- Reingreso automático del vehículo a cola luego del retorno.
- Dashboard con indicadores y alertas.
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
3. Iniciar sesión como coordinador.
4. Revisar solicitudes pendientes.
5. Usar la opción **Sugerir vehículo** o asignar uno manualmente.
6. Asignar conductor y guardar la programación.
7. Iniciar sesión como conductor.
8. Registrar kilometraje inicial.
9. Iniciar ruta.
10. Registrar kilometraje final.
11. Registrar retorno.
12. Revisar dashboard, cola y estadísticas actualizadas.

## Módulos del sistema

- **Dashboard:** indicadores generales, alertas inteligentes y gráficos.
- **Solicitudes:** registro guiado y listado de solicitudes.
- **Vehículos:** administración de flota, estados, capacidad y kilometraje.
- **Conductores:** registro y estado de conductores.
- **Programación:** asignación de vehículos y conductores.
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
- Validaciones en backend y frontend.
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
