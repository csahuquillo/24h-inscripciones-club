-- ============================================================
-- 24h inscripciones — esquema (MySQL/MariaDB, InnoDB, utf8mb4)
-- ============================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS disciplina (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  nombre     VARCHAR(40)  NOT NULL,
  tipo       ENUM('deporte','social') NOT NULL,
  ambito     ENUM('adulto','infantil') NOT NULL,
  pide_nivel TINYINT(1) NOT NULL DEFAULT 0,
  activa     TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_disc (nombre, ambito)
) ENGINE=InnoDB;

-- Cuenta = SIEMPRE un adulto. password_hash NULL hasta activar (1er pago).
CREATE TABLE IF NOT EXISTS cuenta (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  email           VARCHAR(190) NOT NULL,
  username        VARCHAR(40) NULL,
  password_hash   VARCHAR(255) NULL,
  nombre_completo VARCHAR(120) NOT NULL,
  telefono        VARCHAR(30)  NOT NULL,
  rol             ENUM('user','staff','admin') NOT NULL DEFAULT 'user',
  rgpd_consent    TINYINT(1) NOT NULL DEFAULT 0,
  rgpd_consent_at DATETIME NULL,
  rgpd_consent_ip VARCHAR(45) NULL,
  rgpd_version    VARCHAR(20) NULL,
  intentos_login  INT NOT NULL DEFAULT 0,
  bloqueada_hasta DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  activated_at    DATETIME NULL,
  UNIQUE KEY uq_email (email),
  UNIQUE KEY uq_username (username)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS participante (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  cuenta_id       INT NOT NULL,
  nombre_completo VARCHAR(120) NOT NULL,
  es_socio        TINYINT(1) NOT NULL DEFAULT 0,
  num_socio       VARCHAR(10) NULL,
  edad            TINYINT UNSIGNED NULL,
  ambito          ENUM('adulto','infantil') NOT NULL,
  CONSTRAINT fk_part_cuenta FOREIGN KEY (cuenta_id) REFERENCES cuenta(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inscripcion (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  participante_id INT NOT NULL,
  disciplina_id   INT NOT NULL,
  nivel_padel     TINYINT UNSIGNED NULL,
  companero       VARCHAR(120) NULL,               -- pareja/compañero declarado (dobles, parchís…)
  sin_pareja      TINYINT(1) NOT NULL DEFAULT 0,   -- "no tengo pareja, buscadme una" (bolsa de parejas)
  cabeza_serie    TINYINT(1) NOT NULL DEFAULT 0,   -- seed en tenis/pádel (no se cruzan pronto)
  estado          ENUM('preinscrita','pagada','anulada') NOT NULL DEFAULT 'preinscrita',
  precio_eur      DECIMAL(5,2) NOT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ins_part FOREIGN KEY (participante_id) REFERENCES participante(id) ON DELETE CASCADE,
  CONSTRAINT fk_ins_disc FOREIGN KEY (disciplina_id)   REFERENCES disciplina(id),
  UNIQUE KEY uq_part_disc (participante_id, disciplina_id)
) ENGINE=InnoDB;

-- Fútbol: equipos de menores (<14). Lo formaliza un adulto (cuenta). Paga uno.
CREATE TABLE IF NOT EXISTS equipo (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  cuenta_id          INT NOT NULL,
  nombre_equipo      VARCHAR(60) NOT NULL,
  estado             ENUM('preinscrito','pagado','anulado') NOT NULL DEFAULT 'preinscrito',
  precio_total_eur   DECIMAL(6,2) NOT NULL DEFAULT 0,
  permiso_menores    TINYINT(1) NOT NULL DEFAULT 0,
  permiso_menores_at DATETIME NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_eq_cuenta FOREIGN KEY (cuenta_id) REFERENCES cuenta(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS equipo_miembro (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  equipo_id       INT NOT NULL,
  nombre_completo VARCHAR(120) NOT NULL,
  edad            TINYINT UNSIGNED NOT NULL,
  es_socio        TINYINT(1) NOT NULL DEFAULT 0,
  CONSTRAINT fk_em_eq FOREIGN KEY (equipo_id) REFERENCES equipo(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pago (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  objeto_tipo ENUM('inscripcion','equipo') NOT NULL,
  objeto_id   INT NOT NULL,
  importe_eur DECIMAL(6,2) NOT NULL,
  metodo      ENUM('tpv','efectivo') NOT NULL,
  cobrado_por INT NULL,
  pagado_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_objeto (objeto_tipo, objeto_id),
  CONSTRAINT fk_pago_staff FOREIGN KEY (cobrado_por) REFERENCES cuenta(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS socio_padron (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  num_nuevo        INT NULL,
  num_antiguo      INT NULL,
  apellidos_nombre VARCHAR(160) NOT NULL,
  norm_nombre      VARCHAR(160) NULL,
  email            VARCHAR(190) NULL,
  telefono         VARCHAR(60) NULL,
  KEY idx_nuevo (num_nuevo),
  KEY idx_norm (norm_nombre)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS grupo (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  disciplina_id INT NOT NULL,
  etiqueta      VARCHAR(10) NOT NULL,
  CONSTRAINT fk_grupo_disc FOREIGN KEY (disciplina_id) REFERENCES disciplina(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS grupo_miembro (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  grupo_id       INT NOT NULL,
  inscripcion_id INT NULL,
  equipo_id      INT NULL,
  CONSTRAINT fk_gm_grupo FOREIGN KEY (grupo_id) REFERENCES grupo(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS horario (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  disciplina_id INT NULL,
  grupo_id      INT NULL,
  cuando        DATETIME NOT NULL,
  lugar         VARCHAR(80) NULL,
  nota          VARCHAR(160) NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS rate_limit (
  bucket   CHAR(64) PRIMARY KEY,
  hits     INT NOT NULL DEFAULT 0,
  reset_at INT NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_log (
  id        BIGINT AUTO_INCREMENT PRIMARY KEY,
  accion    VARCHAR(60) NOT NULL,
  detalle   VARCHAR(500) NULL,
  ip        VARCHAR(45) NULL,
  creado_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notificacion (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  asunto    VARCHAR(160) NOT NULL,
  cuerpo    TEXT NOT NULL,
  target    VARCHAR(40) NOT NULL DEFAULT 'all',
  creada_por INT NULL,
  creada_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notificacion_dest (
  id              BIGINT AUTO_INCREMENT PRIMARY KEY,
  notificacion_id INT NOT NULL,
  email           VARCHAR(190) NOT NULL,
  estado          ENUM('pendiente','enviado','error') NOT NULL DEFAULT 'pendiente',
  message_id      VARCHAR(120) NULL,
  intentos        INT NOT NULL DEFAULT 0,
  enviado_at      DATETIME NULL,
  KEY idx_estado (estado),
  CONSTRAINT fk_nd_notif FOREIGN KEY (notificacion_id) REFERENCES notificacion(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Semilla de disciplinas (idempotente)
INSERT IGNORE INTO disciplina (nombre, tipo, ambito, pide_nivel) VALUES
 ('Tenis','deporte','adulto',0), ('Tenis doble','deporte','adulto',0),
 ('Pádel','deporte','adulto',1), ('Pádel+4','deporte','adulto',0),
 ('Frontón','deporte','adulto',0),
 ('Truc','social','adulto',0), ('Parchís','social','adulto',0),
 ('Dominó','social','adulto',0), ('Rummikub','social','adulto',0), ('Roby','social','adulto',0),
 ('Tenis','deporte','infantil',0), ('Tenis doble','deporte','infantil',0),
 ('Pádel','deporte','infantil',1), ('Frontón','deporte','infantil',0),
 ('Truc','social','infantil',0), ('Parchís','social','infantil',0),
 ('Dominó','social','infantil',0), ('Rummikub','social','infantil',0), ('Roby','social','infantil',0);
