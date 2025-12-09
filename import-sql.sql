CREATE DATABASE IF NOT EXISTS sistema_marcaciones
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE sistema_marcaciones;

CREATE TABLE area (
  id_area     INT AUTO_INCREMENT PRIMARY KEY,
  nombre      VARCHAR(100) NOT NULL UNIQUE,
  descripcion VARCHAR(200),
  estado      TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE persona (
  id_persona     INT AUTO_INCREMENT PRIMARY KEY,
  dni            VARCHAR(15) NOT NULL UNIQUE,
  nombres        VARCHAR(100) NOT NULL,
  apellidos      VARCHAR(120) NOT NULL,
  telefono       VARCHAR(20),
  correo         VARCHAR(150),
  id_area        INT,
  estado         TINYINT(1) NOT NULL DEFAULT 1,
  fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_area) REFERENCES area(id_area)
) ENGINE=InnoDB;

CREATE TABLE rol (
  id_rol INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE usuario (
  id_usuario          INT AUTO_INCREMENT PRIMARY KEY,
  id_persona          INT NOT NULL,
  username            VARCHAR(100) NOT NULL UNIQUE,
  password            VARCHAR(100) NOT NULL,
  intentos_fallidos   TINYINT(1) NOT NULL DEFAULT 0,
  bloqueado           TINYINT(1) NOT NULL DEFAULT 0,
  fecha_ultimo_acceso DATETIME NULL,
  fecha_bloqueo       DATETIME NULL,
  FOREIGN KEY (id_persona) REFERENCES persona(id_persona)
) ENGINE=InnoDB;

CREATE TABLE usuario_rol (
  id_usuario INT NOT NULL,
  id_rol     INT NOT NULL,
  PRIMARY KEY (id_usuario, id_rol),
  FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario),
  FOREIGN KEY (id_rol)     REFERENCES rol(id_rol)
) ENGINE=InnoDB;

INSERT INTO area (nombre, descripcion) VALUES
('Ventas',            'Área de ventas'),
('Administración',    'Área administrativa'),
('Recursos Humanos',  'Gestión de personal y nómina'),
('Caja',              'Cobranza y facturación'),
('Almacén',           'Control de inventario y stock');

INSERT INTO rol (nombre) VALUES
('ADMINISTRADOR'),
('OPERATIVO');

INSERT INTO persona (dni, nombres, apellidos, telefono, correo, id_area) VALUES
('75911772', 'Ricardo Enrique', 'Prada Guerra',  '912016162', 'enrique.pdg@hotmail.com', 2),
('74816492', 'Juan Jose',       'Morales Velasquez', NULL, 'juan.morales@empresa.com', 1),
('73186556', 'Fabrizzio Hernan','Cornejo Luyo',      NULL, 'fabrizzio.cornejo@empresa.com', 3),
('70000004', 'Jenniffer',       'Rodríguez Tezen',   NULL, 'jenniffer.rodriguez@empresa.com', 4);

INSERT INTO usuario (id_persona, username, password) VALUES
(1, '75911772', '$2y$10$Jm58ekQOTMj16ND.ot6PfupyeaVxg21HrdY9hkJ/LTPeVtG6oFDY6'),
(2, '74816492', '$2y$10$Jm58ekQOTMj16ND.ot6PfupyeaVxg21HrdY9hkJ/LTPeVtG6oFDY6'),
(3, '73186556', '$2y$10$Jm58ekQOTMj16ND.ot6PfupyeaVxg21HrdY9hkJ/LTPeVtG6oFDY6'),
(4, '70000004', '$2y$10$Jm58ekQOTMj16ND.ot6PfupyeaVxg21HrdY9hkJ/LTPeVtG6oFDY6');

INSERT INTO usuario_rol (id_usuario, id_rol) VALUES
(1, 1),
(2, 1),
(3, 2),
(4, 2);

USE sistema_marcaciones;

DELIMITER //

CREATE PROCEDURE registrar_intento_fallido(IN p_username VARCHAR(100))
BEGIN
  UPDATE usuario
  SET intentos_fallidos = intentos_fallidos + 1,
      bloqueado = CASE WHEN intentos_fallidos + 1 > 3 THEN 1 ELSE bloqueado END,
      fecha_bloqueo = CASE WHEN intentos_fallidos + 1 > 3 THEN NOW() ELSE fecha_bloqueo END
  WHERE username = p_username;
END //

CREATE PROCEDURE resetear_intentos_login(IN p_username VARCHAR(100))
BEGIN
  UPDATE usuario
  SET intentos_fallidos = 0,
      bloqueado = 0,
      fecha_bloqueo = NULL
  WHERE username = p_username;
END //

DELIMITER ;


CREATE TABLE horario (
  id_horario         INT AUTO_INCREMENT PRIMARY KEY,
  nombre             VARCHAR(120) NOT NULL UNIQUE,
  entrada            TIME NOT NULL,
  inicio_refri       TIME NOT NULL,
  fin_refri          TIME NOT NULL,
  salida             TIME NOT NULL,
  tol_entrada_min    INT NOT NULL DEFAULT 0,
  tol_salida_min     INT NOT NULL DEFAULT 0,
  tol_refri_min      INT NOT NULL DEFAULT 0,
  color              VARCHAR(24),
  estado             TINYINT(1) NOT NULL DEFAULT 1,
  creado_en          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE horario_dia (
  id_horario   INT NOT NULL,
  dia_semana   TINYINT NOT NULL,
  PRIMARY KEY (id_horario, dia_semana),
  FOREIGN KEY (id_horario) REFERENCES horario(id_horario)
) ENGINE=InnoDB;

CREATE TABLE asignacion_horario (
  id_asignacion  INT AUTO_INCREMENT PRIMARY KEY,
  id_horario     INT NOT NULL,
  id_persona     INT NULL,
  id_area        INT NULL,
  fecha_inicio   DATE NOT NULL,
  fecha_fin      DATE NULL,
  prioridad      ENUM('PERSONA','AREA') NOT NULL,
  estado         TINYINT(1) NOT NULL DEFAULT 1,
  creado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_horario) REFERENCES horario(id_horario),
  FOREIGN KEY (id_persona) REFERENCES persona(id_persona),
  FOREIGN KEY (id_area) REFERENCES area(id_area),
  CHECK (
    (prioridad = 'PERSONA' AND id_persona IS NOT NULL AND id_area IS NULL) OR
    (prioridad = 'AREA' AND id_area IS NOT NULL AND id_persona IS NULL)
  )
) ENGINE=InnoDB;


CREATE TABLE excepcion_horario (
  id_excepcion      INT AUTO_INCREMENT PRIMARY KEY,
  id_horario_base   INT NOT NULL,
  id_horario_aplica INT NOT NULL,
  id_persona        INT NULL,
  id_area           INT NULL,
  fecha             DATE NOT NULL,
  creado_en         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_horario_base) REFERENCES horario(id_horario),
  FOREIGN KEY (id_horario_aplica) REFERENCES horario(id_horario),
  FOREIGN KEY (id_persona) REFERENCES persona(id_persona),
  FOREIGN KEY (id_area) REFERENCES area(id_area),
  UNIQUE KEY uq_excepcion_persona_fecha (id_persona, fecha),
  UNIQUE KEY uq_excepcion_area_fecha (id_area, fecha)
) ENGINE=InnoDB;

CREATE TABLE marcacion (
  id_marcacion      INT AUTO_INCREMENT PRIMARY KEY,
  id_persona        INT NOT NULL,
  id_horario_resuelto INT NULL,
  tipo              ENUM('ENTRADA','INICIO_REFRI','FIN_REFRI','SALIDA') NOT NULL,
  fecha             DATE NOT NULL,
  hora              TIME NOT NULL,
  creado_en         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_persona) REFERENCES persona(id_persona),
  FOREIGN KEY (id_horario_resuelto) REFERENCES horario(id_horario),
  UNIQUE KEY uq_marcacion_persona_fecha_tipo (id_persona, fecha, tipo)
) ENGINE=InnoDB;

