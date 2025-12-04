-- ==========================================
--  SISFRAUDEIA - Base de datos inicial
--  MySQL/MariaDB - InnoDB - utf8mb4
-- ==========================================

DROP DATABASE IF EXISTS BDFraudeIA;
CREATE DATABASE BDFraudeIA
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE BDFraudeIA;

SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- =========================
-- CATÁLOGOS
-- =========================

CREATE TABLE dependencias (
  id_dependencia INT AUTO_INCREMENT PRIMARY KEY,
  codigo_dependencia VARCHAR(10) NOT NULL UNIQUE,
  nombre_dependencia VARCHAR(120) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE usuarios (
  id_usuario INT AUTO_INCREMENT PRIMARY KEY,
  id_dependencia INT NULL,
  usuario VARCHAR(40) NOT NULL UNIQUE,
  nombres VARCHAR(120) NOT NULL,
  email VARCHAR(120) NULL,
  hash_password VARCHAR(255) NULL,
  rol ENUM('admin','auditor','funcionario') NOT NULL DEFAULT 'funcionario',
  estado TINYINT(1) NOT NULL DEFAULT 1,
  creado_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_usuarios_dependencia
    FOREIGN KEY (id_dependencia) REFERENCES dependencias(id_dependencia)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE cat_tipo_documento (
  id_tipo_documento INT AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(20) NOT NULL UNIQUE,      -- DNI, RUC, CE, PAS
  nombre VARCHAR(60) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE cat_tipo_tramite (
  id_tipo_tramite INT AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(30) NOT NULL UNIQUE,      -- LIC, PER, SUB, CON, etc.
  nombre VARCHAR(120) NOT NULL,
  descripcion VARCHAR(255) NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =========================
-- NÚCLEO: TRÁMITES + VALIDACIÓN
-- =========================

CREATE TABLE tramites (
  id_tramite BIGINT AUTO_INCREMENT PRIMARY KEY,
  codigo_tramite VARCHAR(40) NOT NULL UNIQUE,     -- TRM-YYYYMMDD-00001
  id_dependencia INT NOT NULL,
  id_usuario_registra INT NULL,
  id_tipo_tramite INT NOT NULL,
  id_tipo_documento INT NOT NULL,
  solicitante_num_doc VARCHAR(20) NOT NULL,
  solicitante_nombres VARCHAR(160) NOT NULL,
  monto DECIMAL(12,2) NULL,
  fecha_tramite DATE NOT NULL,

  estado ENUM(
    'REGISTRADO',
    'VALIDACION_PENDIENTE',
    'VALIDADO',
    'OBSERVADO',
    'ANALISIS_PENDIENTE',
    'ANALIZADO',
    'CON_ALERTA',
    'EN_CASO',
    'CERRADO'
  ) NOT NULL DEFAULT 'REGISTRADO',

  -- Archivo asociado (PDF/imagen)
  archivo_nombre VARCHAR(180) NULL,
  archivo_path   VARCHAR(255) NULL,  -- ruta relativa o absoluta controlada por app
  archivo_hash   VARCHAR(64)  NULL,  -- SHA256 sugerido
  archivo_mime   VARCHAR(80)  NULL,
  archivo_tamano INT NULL,

  observaciones TEXT NULL,
  creado_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT fk_tramites_dependencia
    FOREIGN KEY (id_dependencia) REFERENCES dependencias(id_dependencia)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,

  CONSTRAINT fk_tramites_usuario
    FOREIGN KEY (id_usuario_registra) REFERENCES usuarios(id_usuario)
    ON UPDATE CASCADE
    ON DELETE SET NULL,

  CONSTRAINT fk_tramites_tipo_tramite
    FOREIGN KEY (id_tipo_tramite) REFERENCES cat_tipo_tramite(id_tipo_tramite)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,

  CONSTRAINT fk_tramites_tipo_doc
    FOREIGN KEY (id_tipo_documento) REFERENCES cat_tipo_documento(id_tipo_documento)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE INDEX idx_tramites_fecha ON tramites(fecha_tramite);
CREATE INDEX idx_tramites_estado ON tramites(estado);
CREATE INDEX idx_tramites_dep ON tramites(id_dependencia);

CREATE TABLE validaciones (
  id_validacion BIGINT AUTO_INCREMENT PRIMARY KEY,
  id_tramite BIGINT NOT NULL,
  tipo ENUM('DATOS','DOCUMENTO') NOT NULL,
  regla_codigo VARCHAR(50) NOT NULL,                 -- ej: DOC_PRESENTE, DNI_FORMATO, etc.
  resultado ENUM('OK','OBSERVADO','ERROR') NOT NULL,
  detalle VARCHAR(500) NULL,
  ejecutado_por INT NULL,
  creado_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_validaciones_tramite
    FOREIGN KEY (id_tramite) REFERENCES tramites(id_tramite)
    ON UPDATE CASCADE
    ON DELETE CASCADE,

  CONSTRAINT fk_validaciones_usuario
    FOREIGN KEY (ejecutado_por) REFERENCES usuarios(id_usuario)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_validaciones_tramite ON validaciones(id_tramite);
CREATE INDEX idx_validaciones_resultado ON validaciones(resultado);

-- =========================
-- IA: FEATURES / MODELOS / UMBRALES / ANÁLISIS
-- =========================

CREATE TABLE modelos (
  id_modelo INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  version VARCHAR(30) NOT NULL,
  tipo ENUM('clf','anomaly') NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  registrado_por INT NULL,
  creado_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_modelo_version (nombre, version),
  CONSTRAINT fk_modelos_usuario
    FOREIGN KEY (registrado_por) REFERENCES usuarios(id_usuario)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE umbrales (
  id_umbral INT AUTO_INCREMENT PRIMARY KEY,
  entorno ENUM('dev','staging','prod') NOT NULL DEFAULT 'dev',
  nivel ENUM('bajo','medio','alto') NOT NULL,
  valor DECIMAL(6,3) NOT NULL,
  modelo_version VARCHAR(30) NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE INDEX idx_umbrales_entorno ON umbrales(entorno, activo);

CREATE TABLE feature_vectors (
  id_feature BIGINT AUTO_INCREMENT PRIMARY KEY,
  id_tramite BIGINT NOT NULL,
  schema_version INT NOT NULL DEFAULT 1,
  features_kv TEXT NOT NULL, -- clave=valor;clave=valor (sin JSON)
  creado_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY uk_features_tramite (id_tramite),
  CONSTRAINT fk_features_tramite
    FOREIGN KEY (id_tramite) REFERENCES tramites(id_tramite)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE analisis (
  id_analisis BIGINT AUTO_INCREMENT PRIMARY KEY,
  id_tramite BIGINT NOT NULL,
  id_modelo INT NULL,
  score DECIMAL(6,3) NOT NULL, -- 0..100 o 0..1 (según tu estándar)
  categoria ENUM('BAJO','MEDIO','ALTO') NOT NULL,
  explicacion TEXT NULL,
  umbral_version VARCHAR(30) NULL,
  creado_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_analisis_tramite
    FOREIGN KEY (id_tramite) REFERENCES tramites(id_tramite)
    ON UPDATE CASCADE
    ON DELETE CASCADE,

  CONSTRAINT fk_analisis_modelo
    FOREIGN KEY (id_modelo) REFERENCES modelos(id_modelo)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_analisis_tramite ON analisis(id_tramite);
CREATE INDEX idx_analisis_categoria ON analisis(categoria);

-- =========================
-- ALERTAS Y CASOS
-- =========================

CREATE TABLE alertas (
  id_alerta BIGINT AUTO_INCREMENT PRIMARY KEY,
  id_tramite BIGINT NOT NULL,
  score DECIMAL(6,3) NOT NULL,
  severidad ENUM('BAJO','MEDIO','ALTO') NOT NULL,
  estado ENUM('ABIERTA','ASIGNADA','EN_INVESTIGACION','CERRADA') NOT NULL DEFAULT 'ABIERTA',
  motivos TEXT NULL,
  creada_por INT NULL,
  creada_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_alertas_tramite
    FOREIGN KEY (id_tramite) REFERENCES tramites(id_tramite)
    ON UPDATE CASCADE
    ON DELETE CASCADE,

  CONSTRAINT fk_alertas_usuario
    FOREIGN KEY (creada_por) REFERENCES usuarios(id_usuario)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_alertas_estado ON alertas(estado);
CREATE INDEX idx_alertas_severidad ON alertas(severidad);

CREATE TABLE alertas_asignaciones (
  id_asignacion BIGINT AUTO_INCREMENT PRIMARY KEY,
  id_alerta BIGINT NOT NULL,
  auditor_id INT NOT NULL,
  asignado_por INT NULL,
  asignado_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_asig_alerta
    FOREIGN KEY (id_alerta) REFERENCES alertas(id_alerta)
    ON UPDATE CASCADE
    ON DELETE CASCADE,

  CONSTRAINT fk_asig_auditor
    FOREIGN KEY (auditor_id) REFERENCES usuarios(id_usuario)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,

  CONSTRAINT fk_asig_por
    FOREIGN KEY (asignado_por) REFERENCES usuarios(id_usuario)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_asig_alerta ON alertas_asignaciones(id_alerta);

CREATE TABLE casos (
  id_caso BIGINT AUTO_INCREMENT PRIMARY KEY,
  id_alerta BIGINT NOT NULL,
  codigo_caso VARCHAR(40) NOT NULL UNIQUE, -- CAS-YYYYMMDD-00001
  estado ENUM('EN_INVESTIGACION','CERRADO') NOT NULL DEFAULT 'EN_INVESTIGACION',
  resultado ENUM('FRAUDE','FALSO_POSITIVO','SIN_CONCLUSION') NULL,
  conclusiones TEXT NULL,
  creado_por INT NULL,
  creado_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  cerrado_at DATETIME NULL,

  CONSTRAINT fk_casos_alerta
    FOREIGN KEY (id_alerta) REFERENCES alertas(id_alerta)
    ON UPDATE CASCADE
    ON DELETE CASCADE,

  CONSTRAINT fk_casos_usuario
    FOREIGN KEY (creado_por) REFERENCES usuarios(id_usuario)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_casos_estado ON casos(estado);

CREATE TABLE evidencias (
  id_evidencia BIGINT AUTO_INCREMENT PRIMARY KEY,
  id_caso BIGINT NOT NULL,
  tipo ENUM('DOCUMENTO','IMAGEN','URL','NOTA') NOT NULL,
  url_objeto VARCHAR(255) NOT NULL,
  hash_integridad VARCHAR(64) NULL,
  descripcion VARCHAR(255) NULL,
  creado_por INT NULL,
  creado_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_evidencias_caso
    FOREIGN KEY (id_caso) REFERENCES casos(id_caso)
    ON UPDATE CASCADE
    ON DELETE CASCADE,

  CONSTRAINT fk_evidencias_usuario
    FOREIGN KEY (creado_por) REFERENCES usuarios(id_usuario)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_evidencias_caso ON evidencias(id_caso);

-- =========================
-- SOPORTE: NOTIFICACIONES, AUDITORÍA, INTEGRACIONES, CACHE
-- =========================

CREATE TABLE notificaciones (
  id_notificacion BIGINT AUTO_INCREMENT PRIMARY KEY,
  tipo ENUM('correo','inapp') NOT NULL,
  destinatario VARCHAR(120) NOT NULL,
  asunto VARCHAR(200) NULL,
  payload TEXT NULL,
  estado ENUM('pendiente','enviado','error') NOT NULL DEFAULT 'pendiente',
  creado_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  enviado_at DATETIME NULL
) ENGINE=InnoDB;

CREATE TABLE auditoria_logs (
  id_log BIGINT AUTO_INCREMENT PRIMARY KEY,
  accion VARCHAR(80) NOT NULL,
  objeto_tipo VARCHAR(40) NOT NULL,
  objeto_id BIGINT NULL,
  rol VARCHAR(20) NULL,
  usuario_id INT NULL,
  metadata TEXT NULL,
  ip VARCHAR(45) NULL,
  creado_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_auditoria_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id_usuario)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_auditoria_accion ON auditoria_logs(accion, creado_at);

CREATE TABLE integraciones (
  id_integracion INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(80) NOT NULL,
  estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  endpoint VARCHAR(255) NULL,
  auth_tipo ENUM('none','basic','bearer','apikey') NOT NULL DEFAULT 'none',
  creado_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE reportes_cache (
  id_cache BIGINT AUTO_INCREMENT PRIMARY KEY,
  clave VARCHAR(160) NOT NULL UNIQUE,
  payload MEDIUMTEXT NOT NULL,
  rango_inicio DATE NULL,
  rango_fin DATE NULL,
  generado_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =========================
-- UI: ANUNCIOS + CONTACTO
-- =========================

CREATE TABLE anuncios (
  id_anuncio INT AUTO_INCREMENT PRIMARY KEY,
  tag ENUM('Importante','Alerta','Novedad','Recordatorio') NOT NULL DEFAULT 'Novedad',
  titulo VARCHAR(180) NOT NULL,
  descripcion TEXT NOT NULL,
  vigencia_desde DATE NOT NULL,
  vigencia_hasta DATE NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_por INT NULL,
  creado_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_anuncios_usuario
    FOREIGN KEY (creado_por) REFERENCES usuarios(id_usuario)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_anuncios_vigencia ON anuncios(activo, vigencia_desde, vigencia_hasta);

CREATE TABLE contacto_mensajes (
  id_contacto BIGINT AUTO_INCREMENT PRIMARY KEY,
  ticket VARCHAR(40) NOT NULL UNIQUE, -- TCK-YYYYMMDD-HHMMSS-999
  asunto VARCHAR(40) NOT NULL,
  tipo_usuario VARCHAR(20) NOT NULL,
  dependencia VARCHAR(10) NULL,
  referencia VARCHAR(40) NULL,
  nombres VARCHAR(120) NULL,
  email VARCHAR(120) NULL,
  telefono VARCHAR(40) NULL,
  mensaje TEXT NOT NULL,
  evidencia_path VARCHAR(255) NULL,
  evidencia_hash VARCHAR(64) NULL,
  estado ENUM('RECIBIDO','EN_PROCESO','ATENDIDO') NOT NULL DEFAULT 'RECIBIDO',
  creado_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =========================
-- VISTAS (DASHBOARD)
-- =========================

CREATE OR REPLACE VIEW vw_tramites_recientes AS
SELECT
  t.id_tramite, t.codigo_tramite, t.fecha_tramite, t.estado,
  d.codigo_dependencia AS dependencia,
  tt.nombre AS tipo_tramite,
  td.codigo AS tipo_documento,
  t.solicitante_num_doc, t.solicitante_nombres,
  t.archivo_nombre, t.creado_at
FROM tramites t
JOIN dependencias d ON d.id_dependencia = t.id_dependencia
JOIN cat_tipo_tramite tt ON tt.id_tipo_tramite = t.id_tipo_tramite
JOIN cat_tipo_documento td ON td.id_tipo_documento = t.id_tipo_documento
ORDER BY t.creado_at DESC;

CREATE OR REPLACE VIEW vw_alertas_por_severidad AS
SELECT
  severidad,
  COUNT(*) AS total
FROM alertas
WHERE estado <> 'CERRADA'
GROUP BY severidad;

CREATE OR REPLACE VIEW vw_kpi_resumen AS
SELECT
  (SELECT COUNT(*) FROM tramites) AS total_tramites,
  (SELECT COUNT(*) FROM tramites WHERE estado IN ('REGISTRADO','VALIDACION_PENDIENTE')) AS tramites_pendientes_validacion,
  (SELECT COUNT(*) FROM alertas WHERE estado IN ('ABIERTA','ASIGNADA','EN_INVESTIGACION')) AS alertas_pendientes,
  (SELECT COUNT(*) FROM casos WHERE estado = 'EN_INVESTIGACION') AS casos_activos;

-- =========================
-- SEMILLAS BÁSICAS (opcionales)
-- =========================
INSERT INTO dependencias(codigo_dependencia, nombre_dependencia) VALUES
('GRD','Gerencia Regional de Desarrollo'),
('GRA','Gerencia Regional de Administración'),
('OTI','Oficina de Tecnologías de la Información');

INSERT INTO cat_tipo_documento(codigo,nombre) VALUES
('DNI','Documento Nacional de Identidad'),
('RUC','Registro Único de Contribuyentes'),
('CE','Carné de Extranjería'),
('PAS','Pasaporte');

INSERT INTO cat_tipo_tramite(codigo,nombre,descripcion) VALUES
('LIC','Licencia','Trámite de licencia'),
('PER','Permiso','Trámite de permiso'),
('SUB','Subsidio','Trámite de subsidio'),
('CON','Constancia','Trámite de constancia');

INSERT INTO usuarios(id_dependencia, usuario, nombres, email, rol) VALUES
((SELECT id_dependencia FROM dependencias WHERE codigo_dependencia='OTI'), 'admin', 'Administrador del Sistema', 'admin@gorejunin.gob.pe', 'admin'),
((SELECT id_dependencia FROM dependencias WHERE codigo_dependencia='GRA'), 'auditor1', 'Auditor Principal', 'auditor1@gorejunin.gob.pe', 'auditor'),
((SELECT id_dependencia FROM dependencias WHERE codigo_dependencia='GRD'), 'gestor1', 'Gestor de Trámites', 'gestor1@gorejunin.gob.pe', 'funcionario');
