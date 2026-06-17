USE db_sistema_vehicular;

ALTER TABLE solicitudes
  ADD COLUMN tipo_solicitud ENUM('normal','especial') NOT NULL DEFAULT 'normal' AFTER observaciones,
  ADD COLUMN resultado_especial ENUM('no_aplica','atender','rechazar') NOT NULL DEFAULT 'no_aplica' AFTER tipo_solicitud,
  ADD COLUMN motivo_rechazo VARCHAR(255) NULL AFTER resultado_especial,
  ADD COLUMN id_vehiculo_sugerido INT NULL AFTER motivo_rechazo,
  ADD CONSTRAINT fk_solicitudes_vehiculo_sugerido FOREIGN KEY (id_vehiculo_sugerido) REFERENCES vehiculos(id_vehiculo);

INSERT INTO solicitudes
  (id_usuario, id_area, fecha_solicitud, fecha_servicio, hora_servicio, direccion, cantidad_personas, motivo, observaciones, tipo_solicitud, resultado_especial, motivo_rechazo, id_vehiculo_sugerido, estado)
VALUES
  (7, 3, CURDATE(), CURDATE(), '15:00:00', 'Hospital Nacional Arzobispo Loayza - Av. Alfonso Ugarte 848, Lima', 6, 'Recojo urgente de insumos para almacén', 'Pedido especial del mismo día validado con minibús institucional disponible', 'especial', 'atender', NULL, 7, 'pendiente'),
  (9, 5, CURDATE(), CURDATE(), '13:30:00', 'Aeropuerto Jorge Chávez - Callao', 18, 'Traslado urgente de comisión administrativa', 'Pedido especial rechazado al superar la capacidad disponible en cola', 'especial', 'rechazar', 'No hay vehículos disponibles con capacidad suficiente para atender este pedido especial.', NULL, 'rechazada');

INSERT INTO logs_sistema (id_usuario, modulo, accion, descripcion) VALUES
  (2, 'solicitudes', 'pedidos_especiales', 'Migración de pedidos especiales y disponibilidad por asientos');
