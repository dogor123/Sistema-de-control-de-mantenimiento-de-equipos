-- =====================================================================
-- Proyecto 15 - Sistema de control de mantenimiento de equipos
-- Base de datos MySQL / MariaDB
-- =====================================================================
SET NAMES utf8mb4;
DROP DATABASE IF EXISTS mantenimiento_db;
CREATE DATABASE mantenimiento_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mantenimiento_db;

-- ---------------------------------------------------------------------
-- Equipo
-- ---------------------------------------------------------------------
CREATE TABLE equipos (
    id      INT AUTO_INCREMENT PRIMARY KEY,
    codigo  VARCHAR(30)  NOT NULL,
    nombre  VARCHAR(100) NOT NULL,
    tipo    VARCHAR(50)  NOT NULL,
    estado  ENUM('ACTIVO','INACTIVO','EN_MANTENIMIENTO','DADO_DE_BAJA') NOT NULL DEFAULT 'ACTIVO',
    CONSTRAINT uk_equipos_codigo UNIQUE (codigo)          -- el código debe ser único
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Técnico
-- ---------------------------------------------------------------------
CREATE TABLE tecnicos (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    documento     VARCHAR(20)  NOT NULL,
    nombre        VARCHAR(100) NOT NULL,
    especialidad  VARCHAR(80)  NOT NULL,
    CONSTRAINT uk_tecnicos_documento UNIQUE (documento)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Mantenimiento (solo para equipos y técnicos existentes -> FOREIGN KEY)
-- ---------------------------------------------------------------------
CREATE TABLE mantenimientos (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    equipo_id          INT  NOT NULL,
    tecnico_id         INT  NOT NULL,
    tipo_mantenimiento ENUM('PREVENTIVO','CORRECTIVO','PREDICTIVO') NOT NULL,
    fecha              DATE NOT NULL,
    estado             ENUM('PROGRAMADO','EN_PROCESO','FINALIZADO','CANCELADO') NOT NULL,
    CONSTRAINT fk_mant_equipo  FOREIGN KEY (equipo_id)  REFERENCES equipos(id),
    CONSTRAINT fk_mant_tecnico FOREIGN KEY (tecnico_id) REFERENCES tecnicos(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Datos iniciales: técnicos registrados
-- ---------------------------------------------------------------------
INSERT INTO tecnicos (documento, nombre, especialidad) VALUES
('1112223334', 'Carlos Ramírez',  'Hardware'),
('1098765432', 'Laura Gómez',     'Redes'),
('1122334455', 'Andrés Martínez', 'Electrónica');
