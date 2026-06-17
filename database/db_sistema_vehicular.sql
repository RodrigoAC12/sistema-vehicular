CREATE DATABASE IF NOT EXISTS db_sistema_vehicular
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE db_sistema_vehicular;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS logs_sistema;
DROP TABLE IF EXISTS retornos;
DROP TABLE IF EXISTS kilometrajes;
DROP TABLE IF EXISTS cola_vehicular;
DROP TABLE IF EXISTS programaciones;
DROP TABLE IF EXISTS solicitudes;
DROP TABLE IF EXISTS vehiculos;
DROP TABLE IF EXISTS conductores;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS areas;
DROP TABLE IF EXISTS roles;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE roles (
  id_rol INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(50) NOT NULL UNIQUE,
  descripcion VARCHAR(180) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE areas (
  id_area INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL UNIQUE,
  responsable VARCHAR(120) NOT NULL,
  estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE usuarios (
  id_usuario INT AUTO_INCREMENT PRIMARY KEY,
  id_rol INT NOT NULL,
  id_area INT NULL,
  nombres VARCHAR(100) NOT NULL,
  apellidos VARCHAR(100) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  token VARCHAR(128) NULL,
  estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_usuarios_roles FOREIGN KEY (id_rol) REFERENCES roles(id_rol),
  CONSTRAINT fk_usuarios_areas FOREIGN KEY (id_area) REFERENCES areas(id_area)
) ENGINE=InnoDB;

CREATE TABLE conductores (
  id_conductor INT AUTO_INCREMENT PRIMARY KEY,
  id_usuario INT NOT NULL,
  licencia VARCHAR(40) NOT NULL UNIQUE,
  telefono VARCHAR(30) NOT NULL,
  estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_conductores_usuarios FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
) ENGINE=InnoDB;

CREATE TABLE vehiculos (
  id_vehiculo INT AUTO_INCREMENT PRIMARY KEY,
  placa VARCHAR(12) NOT NULL UNIQUE,
  marca VARCHAR(60) NOT NULL,
  modelo VARCHAR(80) NOT NULL,
  anio INT NOT NULL,
  capacidad INT NOT NULL,
  kilometraje_actual INT NOT NULL DEFAULT 0,
  estado ENUM('disponible','asignado','en_ruta','retornando','mantenimiento','fuera_servicio') NOT NULL DEFAULT 'disponible',
  observacion VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE solicitudes (
  id_solicitud INT AUTO_INCREMENT PRIMARY KEY,
  id_usuario INT NOT NULL,
  id_area INT NOT NULL,
  fecha_solicitud DATE NOT NULL,
  fecha_servicio DATE NOT NULL,
  hora_servicio TIME NOT NULL,
  direccion VARCHAR(180) NOT NULL,
  cantidad_personas INT NOT NULL,
  motivo VARCHAR(255) NOT NULL,
  observaciones TEXT NULL,
  tipo_solicitud ENUM('normal','especial') NOT NULL DEFAULT 'normal',
  resultado_especial ENUM('no_aplica','atender','rechazar') NOT NULL DEFAULT 'no_aplica',
  motivo_rechazo VARCHAR(255) NULL,
  id_vehiculo_sugerido INT NULL,
  estado ENUM('pendiente','programada','atendida','rechazada','cancelada') NOT NULL DEFAULT 'pendiente',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_solicitudes_usuarios FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario),
  CONSTRAINT fk_solicitudes_areas FOREIGN KEY (id_area) REFERENCES areas(id_area),
  CONSTRAINT fk_solicitudes_vehiculo_sugerido FOREIGN KEY (id_vehiculo_sugerido) REFERENCES vehiculos(id_vehiculo)
) ENGINE=InnoDB;

CREATE TABLE programaciones (
  id_programacion INT AUTO_INCREMENT PRIMARY KEY,
  id_solicitud INT NOT NULL,
  id_vehiculo INT NOT NULL,
  id_conductor INT NOT NULL,
  fecha_programada DATE NOT NULL,
  hora_programada TIME NOT NULL,
  destino VARCHAR(180) NOT NULL,
  estado ENUM('programada','en_ruta','finalizada','cancelada') NOT NULL DEFAULT 'programada',
  observaciones TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_programaciones_solicitudes FOREIGN KEY (id_solicitud) REFERENCES solicitudes(id_solicitud),
  CONSTRAINT fk_programaciones_vehiculos FOREIGN KEY (id_vehiculo) REFERENCES vehiculos(id_vehiculo),
  CONSTRAINT fk_programaciones_conductores FOREIGN KEY (id_conductor) REFERENCES conductores(id_conductor)
) ENGINE=InnoDB;

CREATE TABLE cola_vehicular (
  id_cola INT AUTO_INCREMENT PRIMARY KEY,
  id_vehiculo INT NOT NULL,
  `orden` INT NOT NULL,
  estado ENUM('en_cola','asignado','retirado') NOT NULL DEFAULT 'en_cola',
  fecha_ingreso DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_cola_vehiculos FOREIGN KEY (id_vehiculo) REFERENCES vehiculos(id_vehiculo)
) ENGINE=InnoDB;

CREATE TABLE kilometrajes (
  id_kilometraje INT AUTO_INCREMENT PRIMARY KEY,
  id_programacion INT NOT NULL,
  id_vehiculo INT NOT NULL,
  id_conductor INT NOT NULL,
  kilometraje_inicial INT NOT NULL,
  kilometraje_final INT NULL,
  kilometros_recorridos INT NULL,
  fecha_registro_inicial DATETIME NOT NULL,
  fecha_registro_final DATETIME NULL,
  estado ENUM('iniciado','finalizado') NOT NULL DEFAULT 'iniciado',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_km_programaciones FOREIGN KEY (id_programacion) REFERENCES programaciones(id_programacion),
  CONSTRAINT fk_km_vehiculos FOREIGN KEY (id_vehiculo) REFERENCES vehiculos(id_vehiculo),
  CONSTRAINT fk_km_conductores FOREIGN KEY (id_conductor) REFERENCES conductores(id_conductor)
) ENGINE=InnoDB;

CREATE TABLE retornos (
  id_retorno INT AUTO_INCREMENT PRIMARY KEY,
  id_programacion INT NOT NULL,
  id_vehiculo INT NOT NULL,
  id_conductor INT NOT NULL,
  hora_salida TIME NOT NULL,
  hora_retorno TIME NOT NULL,
  observaciones TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_retornos_programaciones FOREIGN KEY (id_programacion) REFERENCES programaciones(id_programacion),
  CONSTRAINT fk_retornos_vehiculos FOREIGN KEY (id_vehiculo) REFERENCES vehiculos(id_vehiculo),
  CONSTRAINT fk_retornos_conductores FOREIGN KEY (id_conductor) REFERENCES conductores(id_conductor)
) ENGINE=InnoDB;

CREATE TABLE logs_sistema (
  id_log INT AUTO_INCREMENT PRIMARY KEY,
  id_usuario INT NULL,
  modulo VARCHAR(80) NOT NULL,
  accion VARCHAR(80) NOT NULL,
  descripcion VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_logs_usuarios FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
) ENGINE=InnoDB;

INSERT INTO roles (nombre, descripcion) VALUES
('administrador', 'Acceso completo al sistema'),
('coordinador', 'Gestiona solicitudes, programación, cola, retornos y estadísticas'),
('solicitante', 'Registra y consulta sus solicitudes vehiculares'),
('conductor', 'Gestiona rutas, kilometrajes y retornos asignados'),
('visualizador', 'Consulta el panel público');

INSERT INTO areas (nombre, responsable, estado) VALUES
('Gerencia General', 'Dirección General', 'activo'),
('Recursos Humanos', 'Jefatura de RR. HH.', 'activo'),
('Logística', 'Coordinación Logística', 'activo'),
('Sistemas', 'Jefatura de TI', 'activo'),
('Administración', 'Administración Central', 'activo'),
('Contabilidad', 'Contador General', 'activo');

SET @hash_admin123 = '$2y$10$87CsqwfgHgeKOdt6Wf3CxuOwV37dA6uJTH1SVCSY1hqaOnVCnudIe';

INSERT INTO usuarios (id_rol, id_area, nombres, apellidos, email, password, estado) VALUES
(1, 1, 'Ana María', 'Valdivia Rojas', 'admin@sistema.com', @hash_admin123, 'activo'),
(2, 3, 'Ricardo', 'Salinas Paredes', 'coordinador@sistema.com', @hash_admin123, 'activo'),
(3, 4, 'Patricia', 'Núñez Campos', 'solicitante@sistema.com', @hash_admin123, 'activo'),
(4, 3, 'Miguel Ángel', 'Peña Soto', 'conductor@sistema.com', @hash_admin123, 'activo'),
(5, 1, 'Mesa', 'Operativa', 'visualizador@sistema.com', @hash_admin123, 'activo'),
(3, 2, 'Mariana', 'Torres Aguilar', 'mariana.torres@empresa.com', @hash_admin123, 'activo'),
(3, 3, 'Jorge Luis', 'Quispe Huamán', 'jorge.quispe@empresa.com', @hash_admin123, 'activo'),
(3, 6, 'Lucía', 'Ramírez León', 'lucia.ramirez@empresa.com', @hash_admin123, 'activo'),
(3, 5, 'Verónica', 'Azañero Díaz', 'veronica.azanero@empresa.com', @hash_admin123, 'activo'),
(4, 3, 'Luis Alberto', 'Chávez Molina', 'luis.chavez@empresa.com', @hash_admin123, 'activo'),
(4, 3, 'Carmen Rosa', 'Muñoz Herrera', 'carmen.munoz@empresa.com', @hash_admin123, 'activo'),
(4, 3, 'Roberto', 'Cárdenas Vega', 'roberto.cardenas@empresa.com', @hash_admin123, 'activo');

INSERT INTO conductores (id_usuario, licencia, telefono, estado) VALUES
(4, 'AII-B-10001', '999111222', 'activo'),
(10, 'AIII-C-20458', '987654321', 'activo'),
(11, 'AII-B-18422', '956778899', 'activo'),
(12, 'AII-B-19731', '944332211', 'activo');

INSERT INTO vehiculos (placa, marca, modelo, anio, capacidad, kilometraje_actual, estado, observacion) VALUES
('ABC-123', 'Toyota', 'Hilux', 2021, 5, 15238, 'disponible', 'Unidad doble cabina para visitas técnicas'),
('BCD-456', 'Hyundai', 'H1', 2020, 10, 30450, 'asignado', 'Van para traslados grupales'),
('CDE-789', 'Nissan', 'Frontier', 2022, 5, 11860, 'en_ruta', 'Unidad asignada a logística operativa'),
('DFG-321', 'Toyota', 'Corolla', 2019, 4, 42800, 'mantenimiento', 'Revisión preventiva programada'),
('ERT-654', 'Kia', 'Sportage', 2023, 5, 8236, 'disponible', 'Unidad ejecutiva'),
('FGL-908', 'Mitsubishi', 'L200', 2022, 5, 17680, 'disponible', 'Unidad para supervisión de campo'),
('HJK-245', 'Mercedes-Benz', 'Sprinter', 2021, 15, 51240, 'disponible', 'Minibús institucional'),
('LMN-732', 'Chevrolet', 'N400', 2018, 7, 63500, 'fuera_servicio', 'Unidad pendiente de evaluación mecánica');

INSERT INTO cola_vehicular (id_vehiculo, `orden`, estado) VALUES
(1, 1, 'en_cola'),
(5, 2, 'en_cola'),
(6, 3, 'en_cola'),
(7, 4, 'en_cola'),
(2, 5, 'asignado'),
(3, 6, 'asignado'),
(4, 7, 'retirado'),
(8, 8, 'retirado');

INSERT INTO solicitudes (id_usuario, id_area, fecha_solicitud, fecha_servicio, hora_servicio, direccion, cantidad_personas, motivo, observaciones, tipo_solicitud, resultado_especial, motivo_rechazo, id_vehiculo_sugerido, estado) VALUES
(6, 2, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 DAY), '09:00:00', 'Av. Salaverry 655, Jesús María', 3, 'Entrega de expedientes laborales', 'Documentación para mesa de partes', 'normal', 'no_aplica', NULL, NULL, 'pendiente'),
(8, 6, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 DAY), '11:30:00', 'SUNAT - Av. Garcilaso de la Vega 1472, Lima', 4, 'Presentación de documentación tributaria', 'Debe retornar con cargo firmado', 'normal', 'no_aplica', NULL, NULL, 'pendiente'),
(3, 4, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 DAY), '08:30:00', 'Gobierno Regional - Av. Arequipa 810, Lima', 5, 'Reunión de coordinación tecnológica', 'Equipo de TI con material de presentación', 'normal', 'no_aplica', NULL, NULL, 'programada'),
(7, 3, CURDATE(), CURDATE(), '10:00:00', 'Almacén central - Av. Argentina 3020, Callao', 2, 'Supervisión de recepción de suministros', 'Ruta activa durante la mañana', 'normal', 'no_aplica', NULL, NULL, 'programada'),
(3, 4, DATE_SUB(CURDATE(), INTERVAL 1 DAY), DATE_SUB(CURDATE(), INTERVAL 1 DAY), '09:15:00', 'Centro de datos externo - San Isidro', 2, 'Verificación de conectividad institucional', 'Atención cerrada sin incidencias', 'normal', 'no_aplica', NULL, NULL, 'atendida'),
(9, 5, DATE_SUB(CURDATE(), INTERVAL 2 DAY), DATE_SUB(CURDATE(), INTERVAL 2 DAY), '15:30:00', 'Notaría Fernández - Jr. Lampa 879, Lima', 3, 'Legalización de documentos administrativos', 'Se entregaron documentos originales', 'normal', 'no_aplica', NULL, NULL, 'atendida'),
(6, 2, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 DAY), '14:00:00', 'Centro de convenciones de Lima', 12, 'Traslado a capacitación externa', 'Solicitud rechazada por falta de unidad de alta capacidad en ese horario', 'normal', 'no_aplica', NULL, NULL, 'rechazada'),
(8, 6, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 2 DAY), '16:00:00', 'Banco de la Nación - agencia San Borja', 2, 'Depósito de garantías institucionales', 'Cancelada por el área solicitante', 'normal', 'no_aplica', NULL, NULL, 'cancelada'),
(7, 3, CURDATE(), CURDATE(), '15:00:00', 'Hospital Nacional Arzobispo Loayza - Av. Alfonso Ugarte 848, Lima', 6, 'Recojo urgente de insumos para almacén', 'Pedido especial del mismo día validado con minibús institucional disponible', 'especial', 'atender', NULL, 7, 'pendiente'),
(9, 5, CURDATE(), CURDATE(), '13:30:00', 'Aeropuerto Jorge Chávez - Callao', 18, 'Traslado urgente de comisión administrativa', 'Pedido especial rechazado al superar la capacidad disponible en cola', 'especial', 'rechazar', 'No hay vehículos disponibles con capacidad suficiente para atender este pedido especial.', NULL, 'rechazada');

INSERT INTO programaciones (id_solicitud, id_vehiculo, id_conductor, fecha_programada, hora_programada, destino, estado, observaciones) VALUES
(3, 2, 2, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '08:30:00', 'Gobierno Regional - Av. Arequipa 810, Lima', 'programada', 'Asignación confirmada para reunión de TI'),
(4, 3, 3, CURDATE(), '10:00:00', 'Almacén central - Av. Argentina 3020, Callao', 'en_ruta', 'Unidad en ruta con retorno pendiente'),
(5, 1, 1, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '09:15:00', 'Centro de datos externo - San Isidro', 'finalizada', 'Servicio finalizado con cargo de atención'),
(6, 5, 4, DATE_SUB(CURDATE(), INTERVAL 2 DAY), '15:30:00', 'Notaría Fernández - Jr. Lampa 879, Lima', 'finalizada', 'Documentación legalizada y entregada a Administración');

INSERT INTO kilometrajes (id_programacion, id_vehiculo, id_conductor, kilometraje_inicial, kilometraje_final, kilometros_recorridos, fecha_registro_inicial, fecha_registro_final, estado) VALUES
(2, 3, 3, 11840, NULL, NULL, CONCAT(CURDATE(), ' 09:48:00'), NULL, 'iniciado'),
(3, 1, 1, 15200, 15238, 38, CONCAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), ' 09:05:00'), CONCAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), ' 11:40:00'), 'finalizado'),
(4, 5, 4, 8200, 8236, 36, CONCAT(DATE_SUB(CURDATE(), INTERVAL 2 DAY), ' 15:10:00'), CONCAT(DATE_SUB(CURDATE(), INTERVAL 2 DAY), ' 17:20:00'), 'finalizado');

INSERT INTO retornos (id_programacion, id_vehiculo, id_conductor, hora_salida, hora_retorno, observaciones, created_at) VALUES
(3, 1, 1, '09:15:00', '11:45:00', 'Retorno al punto de origen con documentación firmada', CONCAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), ' 11:45:00')),
(4, 5, 4, '15:30:00', '17:25:00', 'Retorno sin observaciones, documentos entregados a Administración', CONCAT(DATE_SUB(CURDATE(), INTERVAL 2 DAY), ' 17:25:00'));

INSERT INTO logs_sistema (id_usuario, modulo, accion, descripcion) VALUES
(1, 'instalación', 'seed', 'Datos iniciales creados para operación inicial'),
(2, 'programación', 'crear', 'Programación registrada para Gobierno Regional'),
(11, 'kilometraje', 'inicial', 'Kilometraje inicial registrado para ruta a almacén central'),
(4, 'retorno', 'registrar', 'Retorno registrado desde centro de datos externo'),
(12, 'retorno', 'registrar', 'Retorno registrado desde notaría');
