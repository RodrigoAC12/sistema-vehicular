USE db_sistema_vehicular;
SET NAMES utf8mb4;

UPDATE areas SET responsable = 'Dirección General' WHERE id_area = 1;
UPDATE areas SET nombre = 'Logística', responsable = 'Coordinación Logística' WHERE id_area = 3;
UPDATE areas SET nombre = 'Administración', responsable = 'Administración Central' WHERE id_area = 5;

UPDATE usuarios SET nombres = 'Ana María' WHERE id_usuario = 1;
UPDATE usuarios SET apellidos = 'Núñez Campos' WHERE id_usuario = 3;
UPDATE usuarios SET nombres = 'Miguel Ángel', apellidos = 'Peña Soto' WHERE id_usuario = 4;
UPDATE usuarios SET apellidos = 'Quispe Huamán' WHERE id_usuario = 7;
UPDATE usuarios SET nombres = 'Lucía', apellidos = 'Ramírez León' WHERE id_usuario = 8;
UPDATE usuarios SET nombres = 'Verónica', apellidos = 'Azañero Díaz' WHERE id_usuario = 9;
UPDATE usuarios SET apellidos = 'Chávez Molina' WHERE id_usuario = 10;
UPDATE usuarios SET apellidos = 'Muñoz Herrera' WHERE id_usuario = 11;
UPDATE usuarios SET apellidos = 'Cárdenas Vega' WHERE id_usuario = 12;

UPDATE vehiculos SET observacion = 'Unidad doble cabina para visitas técnicas' WHERE id_vehiculo = 1;
UPDATE vehiculos SET observacion = 'Unidad asignada a logística operativa' WHERE id_vehiculo = 3;
UPDATE vehiculos SET observacion = 'Revisión preventiva programada' WHERE id_vehiculo = 4;
UPDATE vehiculos SET observacion = 'Unidad para supervisión de campo' WHERE id_vehiculo = 6;
UPDATE vehiculos SET observacion = 'Minibús institucional' WHERE id_vehiculo = 7;
UPDATE vehiculos SET observacion = 'Unidad pendiente de evaluación mecánica' WHERE id_vehiculo = 8;

UPDATE solicitudes
SET direccion = 'Av. Salaverry 655, Jesús María',
    observaciones = 'Documentación para mesa de partes'
WHERE id_solicitud = 3;

UPDATE solicitudes
SET motivo = 'Presentación de documentación tributaria'
WHERE id_solicitud = 4;

UPDATE solicitudes
SET motivo = 'Reunión de coordinación tecnológica',
    observaciones = 'Equipo de TI con material de presentación'
WHERE id_solicitud = 5;

UPDATE solicitudes
SET direccion = 'Almacén central - Av. Argentina 3020, Callao',
    motivo = 'Supervisión de recepción de suministros',
    observaciones = 'Ruta activa durante la mañana'
WHERE id_solicitud = 6;

UPDATE solicitudes
SET motivo = 'Verificación de conectividad institucional',
    observaciones = 'Atención cerrada sin incidencias'
WHERE id_solicitud = 7;

UPDATE solicitudes
SET direccion = 'Notaría Fernández - Jr. Lampa 879, Lima',
    motivo = 'Legalización de documentos administrativos'
WHERE id_solicitud = 8;

UPDATE solicitudes
SET motivo = 'Traslado a capacitación externa'
WHERE id_solicitud = 9;

UPDATE solicitudes
SET direccion = 'Banco de la Nación - agencia San Borja',
    motivo = 'Depósito de garantías institucionales',
    observaciones = 'Cancelada por el área solicitante'
WHERE id_solicitud = 10;

UPDATE programaciones
SET observaciones = 'Asignación confirmada para reunión de TI'
WHERE id_programacion = 2;

UPDATE programaciones
SET destino = 'Almacén central - Av. Argentina 3020, Callao'
WHERE id_programacion = 3;

UPDATE programaciones
SET observaciones = 'Servicio finalizado con cargo de atención'
WHERE id_programacion = 4;

UPDATE programaciones
SET destino = 'Notaría Fernández - Jr. Lampa 879, Lima',
    observaciones = 'Documentación legalizada y entregada a Administración'
WHERE id_programacion = 5;

UPDATE programaciones
SET destino = 'Av. Salaverry 655, Jesús María'
WHERE id_programacion = 6;

UPDATE retornos
SET observaciones = 'Retorno al punto de origen con documentación firmada'
WHERE id_retorno = 2;

UPDATE retornos
SET observaciones = 'Retorno sin observaciones, documentos entregados a Administración'
WHERE id_retorno = 3;

UPDATE logs_sistema
SET accion = 'actualización'
WHERE id_log = 11;
