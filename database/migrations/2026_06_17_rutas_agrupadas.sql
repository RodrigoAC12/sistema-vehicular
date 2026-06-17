USE db_sistema_vehicular;

ALTER TABLE solicitudes
  ADD COLUMN hora_solicitud TIME NULL AFTER fecha_solicitud;

UPDATE solicitudes
SET hora_solicitud = '09:00:00'
WHERE hora_solicitud IS NULL;

ALTER TABLE programaciones
  ADD COLUMN codigo_ruta VARCHAR(30) NULL AFTER destino,
  ADD COLUMN orden_ruta INT NOT NULL DEFAULT 1 AFTER codigo_ruta,
  ADD COLUMN origen_ruta VARCHAR(180) NULL AFTER orden_ruta,
  ADD COLUMN distancia_tramo_km DECIMAL(8,2) NOT NULL DEFAULT 0 AFTER origen_ruta,
  ADD COLUMN distancia_ruta_km DECIMAL(8,2) NOT NULL DEFAULT 0 AFTER distancia_tramo_km,
  ADD COLUMN duracion_tramo_min INT NOT NULL DEFAULT 0 AFTER distancia_ruta_km,
  ADD COLUMN duracion_ruta_min INT NOT NULL DEFAULT 0 AFTER duracion_tramo_min;

INSERT INTO logs_sistema (id_usuario, modulo, accion, descripcion) VALUES
  (2, 'programacion', 'rutas_agrupadas', 'Migración de rutas agrupadas y optimización local');
