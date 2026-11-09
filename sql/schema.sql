-- Recambia · tabla de solicitudes
-- Ejecutar una vez en phpMyAdmin (Hostinger → Bases de datos → phpMyAdmin → SQL)

CREATE TABLE IF NOT EXISTS solicitudes (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  creada        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  estado        ENUM('nueva','presupuestada','pagada','enviada','cerrada','descartada') NOT NULL DEFAULT 'nueva',

  modo          ENUM('matricula','referencia') NOT NULL DEFAULT 'matricula',
  referencia    VARCHAR(40)  NULL,
  fabricante    VARCHAR(60)  NULL,

  matricula     VARCHAR(12)  NULL,
  bastidor      CHAR(17)     NULL,
  marca         VARCHAR(60)  NULL,
  modelo        VARCHAR(80)  NULL,
  anio          SMALLINT     NULL,
  motor         VARCHAR(60)  NULL,

  pieza         TEXT         NOT NULL,
  fotos         JSON         NULL,

  nombre        VARCHAR(80)  NOT NULL,
  email         VARCHAR(120) NOT NULL,
  telefono      VARCHAR(20)  NOT NULL,
  cp            CHAR(5)      NOT NULL,

  ip_hash       CHAR(64)     NOT NULL,
  user_agent    VARCHAR(255) NULL,
  correo_ok     TINYINT(1)   NOT NULL DEFAULT 0,

  PRIMARY KEY (id),
  KEY idx_estado (estado),
  KEY idx_referencia (referencia),
  KEY idx_ip_fecha (ip_hash, creada)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
