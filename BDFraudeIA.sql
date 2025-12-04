

-- Re-crear base de datos limpia
DROP DATABASE IF EXISTS BDFraudeIA;
CREATE DATABASE BDFraudeIA CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE BDFraudeIA;

-- Sugerido (no obligatorio): asegurar juego de caracteres en la sesión
SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- =========================
-- Tablas referenciales
-- =========================
CREATE TABLE dependencias (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre     VARCHAR(120) NOT NULL,
  sigla      VARCHAR(20)  NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_dependencias_sigla (sigla)
) ENGINE=InnoDB;

CREATE TABLE usuarios (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombres         VARCHAR(120) NOT NULL,
  apellidos       VARCHAR(120) NOT NULL,
  email           VARCHAR(160) NOT NULL,
  hash_password   VARCHAR(255) NOT NULL,
  rol             ENUM('funcionario','auditor','admin') NOT NULL DEFAULT 'funcionario',
  dependencia_id  INT UNSIGNED NULL,
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at   DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_email (email),
  KEY idx_usuarios_dependencia (dependencia_id),
  CONSTRAINT fk_usuarios_dependencia
    FOREIGN KEY (dependencia_id) REFERENCES dependencias(id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================
-- Trámites y validaciones
-- =========================
CREATE TABLE tramites (
  id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tipo                 VARCHAR(80)  NOT NULL,  -- p.ej.: Licencia, Permiso, Contratación
  monto                DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  fecha                DATETIME NOT NULL,
  estado               ENUM('registrado','validado','invalido','procesado')
                        NOT NULL DEFAULT 'registrado',
  solicitante_doc      VARCHAR(20)  NULL,   -- DNI/RUC/etc.
  solicitante_tipo     VARCHAR(20)  NULL,   -- NATURAL/JURIDICA/etc.
  dependencia_id       INT UNSIGNED NOT NULL,
  funcionario_id       INT UNSIGNED NOT NULL,
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_tramites_fecha (fecha),
  KEY idx_tramites_dep_estado (dependencia_id, estado),
  KEY idx_tramites_funcionario (funcionario_id),
  CONSTRAINT fk_tramites_dependencia
    FOREIGN KEY (dependencia_id) REFERENCES dependencias(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_tramites_funcionario
    FOREIGN KEY (funcionario_id) REFERENCES usuarios(id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE validaciones (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tramite_id   BIGINT UNSIGNED NOT NULL,
  regla_codigo VARCHAR(60) NOT NULL,          -- código corto de la regla aplicada
  resultado    ENUM('ok','error') NOT NULL,   -- ok=sin hallazgo, error=hallazgo
  detalle      VARCHAR(500) NULL,             -- breve explicación del resultado
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_validaciones_tramite (tramite_id),
  CONSTRAINT fk_validaciones_tramite
    FOREIGN KEY (tramite_id) REFERENCES tramites(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================
-- IA: modelos, umbrales, features, análisis
-- =========================
CREATE TABLE modelos (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre         VARCHAR(120) NOT NULL,       -- nombre comercial del modelo
  version        VARCHAR(40)  NOT NULL,
  tipo           ENUM('clf','anomaly') NOT NULL DEFAULT 'clf',
  activo         TINYINT(1) NOT NULL DEFAULT 0,
  registrado_por INT UNSIGNED NULL,           -- usuario que registra
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_modelo_nombre_version (nombre, version),
  KEY idx_modelos_activo (activo),
  CONSTRAINT fk_modelos_usuario
    FOREIGN KEY (registrado_por) REFERENCES usuarios(id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE umbrales (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  entorno    ENUM('dev','staging','prod') NOT NULL,
  alto       DECIMAL(5,4) NOT NULL DEFAULT 0.8000, -- severidad alta si score >= alto
  medio      DECIMAL(5,4) NOT NULL DEFAULT 0.6000, -- severidad media si >= medio
  bajo       DECIMAL(5,4) NOT NULL DEFAULT 0.4000, -- severidad baja si >= bajo
  version    VARCHAR(20) NOT NULL,
  activo     TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_umbrales_entorno_version (entorno, version),
  KEY idx_umbrales_activo (activo)
) ENGINE=InnoDB;

-- Features en formato texto clave=valor para compatibilidad
CREATE TABLE feature_vectors (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tramite_id      BIGINT UNSIGNED NOT NULL,
  schema_version  VARCHAR(20) NOT NULL,  -- versión del esquema de features
  features_kv     TEXT NOT NULL,         -- ej: "monto=1500;dias=7;canal=online"
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_features_tramite (tramite_id),
  KEY idx_features_schema (schema_version),
  CONSTRAINT fk_features_tramite
    FOREIGN KEY (tramite_id) REFERENCES tramites(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE analisis (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tramite_id     BIGINT UNSIGNED NOT NULL,
  modelo_id      INT UNSIGNED NOT NULL,
  score          DECIMAL(6,5) NOT NULL,                 -- 0.00000 a 0.99999
  categoria      ENUM('bajo','medio','alto') NOT NULL,  -- mapeo por umbrales
  umbral_version VARCHAR(20) NOT NULL,                  -- versión de corte usada
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_analisis_tramite (tramite_id),
  KEY idx_analisis_fecha (created_at),
  KEY idx_analisis_categoria (categoria),
  CONSTRAINT fk_analisis_tramite
    FOREIGN KEY (tramite_id) REFERENCES tramites(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_analisis_modelo
    FOREIGN KEY (modelo_id) REFERENCES modelos(id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- =========================
-- Alertas, asignaciones, casos, evidencias
-- =========================
CREATE TABLE alertas (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tramite_id   BIGINT UNSIGNED NOT NULL,
  score        DECIMAL(6,5) NOT NULL,
  severidad    ENUM('bajo','medio','alto') NOT NULL,
  estado       ENUM('abierta','asignada','resuelta','cerrada') NOT NULL DEFAULT 'abierta',
  motivos      TEXT NULL,                     -- breve explicación de la alerta
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_alertas_estado (estado),
  KEY idx_alertas_tramite (tramite_id),
  KEY idx_alertas_fecha (created_at),
  CONSTRAINT fk_alertas_tramite
    FOREIGN KEY (tramite_id) REFERENCES tramites(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE alertas_asignaciones (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  alerta_id    BIGINT UNSIGNED NOT NULL,
  auditor_id   INT UNSIGNED NOT NULL,  -- analista/auditor asignado
  asignado_por INT UNSIGNED NULL,      -- quién asignó
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_asign_alerta (alerta_id),
  KEY idx_asign_auditor (auditor_id),
  CONSTRAINT fk_asign_alerta
    FOREIGN KEY (alerta_id) REFERENCES alertas(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_asign_auditor
    FOREIGN KEY (auditor_id) REFERENCES usuarios(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_asign_asignador
    FOREIGN KEY (asignado_por) REFERENCES usuarios(id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE casos (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  alerta_id     BIGINT UNSIGNED NOT NULL,
  auditor_id    INT UNSIGNED NOT NULL,
  estado        ENUM('en_investigacion','cerrado') NOT NULL DEFAULT 'en_investigacion',
  resultado     ENUM('fraude','descartado','inconcluso') NULL,
  conclusiones  TEXT NULL,
  cerrado_at    DATETIME NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_casos_alerta (alerta_id),
  KEY idx_casos_auditor (auditor_id),
  KEY idx_casos_estado (estado),
  CONSTRAINT fk_casos_alerta
    FOREIGN KEY (alerta_id) REFERENCES alertas(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_casos_auditor
    FOREIGN KEY (auditor_id) REFERENCES usuarios(id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE evidencias (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  caso_id          BIGINT UNSIGNED NOT NULL,
  tipo             VARCHAR(60) NOT NULL,        -- documento/imagen/audio/etc.
  url_objeto       VARCHAR(255) NOT NULL,
  hash_integridad  VARCHAR(128) NULL,
  descripcion      TEXT NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_evidencias_caso (caso_id),
  CONSTRAINT fk_evidencias_caso
    FOREIGN KEY (caso_id) REFERENCES casos(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================
-- Notificaciones, auditoría, integraciones, reportes
-- =========================
CREATE TABLE notificaciones (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tipo          ENUM('correo','inapp') NOT NULL,
  usuario_id    INT UNSIGNED NOT NULL,
  alerta_id     BIGINT UNSIGNED NULL,
  estado        ENUM('enviado','error') NOT NULL,
  detalle_error TEXT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notif_usuario (usuario_id),
  KEY idx_notif_alerta (alerta_id),
  CONSTRAINT fk_notif_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_notif_alerta
    FOREIGN KEY (alerta_id) REFERENCES alertas(id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE auditoria_logs (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id   INT UNSIGNED NULL,
  rol          ENUM('funcionario','auditor','admin') NULL,
  accion       VARCHAR(60) NOT NULL,     -- insert/update/delete/login/etc.
  objeto_tipo  VARCHAR(60) NOT NULL,     -- tabla o entidad
  objeto_id    BIGINT UNSIGNED NULL,
  ip           VARCHAR(45) NULL,
  metadata     TEXT NULL,                -- contexto adicional
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_usuario (usuario_id),
  KEY idx_audit_accion (accion),
  KEY idx_audit_objeto (objeto_tipo, objeto_id),
  CONSTRAINT fk_audit_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE integraciones (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre      VARCHAR(100) NOT NULL,     -- nombre corto de la integración
  estado      ENUM('activo','inactivo') NOT NULL DEFAULT 'inactivo',
  endpoint    VARCHAR(255) NULL,         -- URL o recurso de conexión
  auth_tipo   VARCHAR(40) NULL,          -- tipo de autenticación
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_integraciones_nombre (nombre)
) ENGINE=InnoDB;

CREATE TABLE reportes_cache (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  clave        VARCHAR(120) NOT NULL,    -- nombre único del reporte
  payload      MEDIUMTEXT NOT NULL,      -- CSV/JSON pre-generado como texto
  rango_inicio DATE NULL,
  rango_fin    DATE NULL,
  generado_at  DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_reportes_cache_clave (clave)
) ENGINE=InnoDB;

-- =============================================================
-- DATOS INICIALES PARA PRUEBAS (escenarios representativos)
-- =============================================================

-- Dependencias
INSERT INTO dependencias (nombre, sigla) VALUES ('Gerencia Regional de Desarrollo', 'GRD');
SET @dep_grd := LAST_INSERT_ID();
INSERT INTO dependencias (nombre, sigla) VALUES ('Gerencia Regional de Administración', 'GRA');
SET @dep_gra := LAST_INSERT_ID();
INSERT INTO dependencias (nombre, sigla) VALUES ('Oficina de Tecnologías de la Información', 'OTI');
SET @dep_oti := LAST_INSERT_ID();

-- Usuarios (hash_password de ejemplo *no* válido para producción)
INSERT INTO usuarios (nombres, apellidos, email, hash_password, rol, dependencia_id)
VALUES ('Admin', 'Sistema', 'admin@gorej.gob.pe', '$2y$10$hash_demo_admin', 'admin', @dep_oti);
SET @usr_admin := LAST_INSERT_ID();

INSERT INTO usuarios (nombres, apellidos, email, hash_password, rol, dependencia_id)
VALUES ('Ana', 'Auditor', 'ana.auditor@gorej.gob.pe', '$2y$10$hash_demo_ana', 'auditor', @dep_grd);
SET @usr_ana := LAST_INSERT_ID();

INSERT INTO usuarios (nombres, apellidos, email, hash_password, rol, dependencia_id)
VALUES ('Bruno', 'Funcionario', 'bruno.func@gorej.gob.pe', '$2y$10$hash_demo_bruno', 'funcionario', @dep_gra);
SET @usr_bruno := LAST_INSERT_ID();

INSERT INTO usuarios (nombres, apellidos, email, hash_password, rol, dependencia_id)
VALUES ('Carla', 'Funcionario', 'carla.func@gorej.gob.pe', '$2y$10$hash_demo_carla', 'funcionario', @dep_grd);
SET @usr_carla := LAST_INSERT_ID();

-- Umbrales (cortes de severidad por entorno)
INSERT INTO umbrales (entorno, alto, medio, bajo, version, activo)
VALUES ('dev', 0.8000, 0.6000, 0.4000, 'v1', 1);
SET @umbr_dev_v1 := LAST_INSERT_ID();
INSERT INTO umbrales (entorno, alto, medio, bajo, version, activo)
VALUES ('staging', 0.8500, 0.6500, 0.4500, 'v1', 1);
INSERT INTO umbrales (entorno, alto, medio, bajo, version, activo)
VALUES ('prod', 0.9000, 0.7000, 0.5000, 'v1', 1);

-- Modelos
INSERT INTO modelos (nombre, version, tipo, activo, registrado_por)
VALUES ('detector_basico', 'v1', 'clf', 1, @usr_admin);
SET @modelo_basico := LAST_INSERT_ID();

-- =========================
-- Trámites (varios casos)
-- =========================
-- Caso T1: monto alto, canal online, posible duplicado de documento
INSERT INTO tramites (tipo, monto, fecha, estado, solicitante_doc, solicitante_tipo,
                      dependencia_id, funcionario_id, created_at)
VALUES ('Licencia de Funcionamiento', 9500.00, '2025-10-15 10:00:00', 'registrado',
        '71234567', 'NATURAL', @dep_gra, @usr_bruno, '2025-10-15 10:00:00');
SET @t1 := LAST_INSERT_ID();

-- Caso T2: monto medio, en oficina, historial limpio
INSERT INTO tramites (tipo, monto, fecha, estado, solicitante_doc, solicitante_tipo,
                      dependencia_id, funcionario_id, created_at)
VALUES ('Permiso de Construcción', 3200.00, '2025-10-16 09:30:00', 'registrado',
        '10765432101', 'JURIDICA', @dep_grd, @usr_carla, '2025-10-16 09:30:00');
SET @t2 := LAST_INSERT_ID();

-- Caso T3: monto bajo, múltiples intentos previos del mismo documento
INSERT INTO tramites (tipo, monto, fecha, estado, solicitante_doc, solicitante_tipo,
                      dependencia_id, funcionario_id, created_at)
VALUES ('Autorización de Evento', 450.00, '2025-10-17 14:20:00', 'registrado',
        '71234567', 'NATURAL', @dep_gra, @usr_bruno, '2025-10-17 14:20:00');
SET @t3 := LAST_INSERT_ID();

-- Caso T4: contratación pública, monto muy alto
INSERT INTO tramites (tipo, monto, fecha, estado, solicitante_doc, solicitante_tipo,
                      dependencia_id, funcionario_id, created_at)
VALUES ('Contratación Pública - Servicio', 250000.00, '2025-10-18 11:45:00', 'registrado',
        '20600099999', 'JURIDICA', @dep_grd, @usr_carla, '2025-10-18 11:45:00');
SET @t4 := LAST_INSERT_ID();

-- Caso T5: licencia, datos aparentemente consistentes
INSERT INTO tramites (tipo, monto, fecha, estado, solicitante_doc, solicitante_tipo,
                      dependencia_id, funcionario_id, created_at)
VALUES ('Licencia de Funcionamiento', 1200.00, '2025-10-19 08:10:00', 'registrado',
        '74125896', 'NATURAL', @dep_grd, @usr_carla, '2025-10-19 08:10:00');
SET @t5 := LAST_INSERT_ID();

-- Caso T6: permiso con documentos observados previamente
INSERT INTO tramites (tipo, monto, fecha, estado, solicitante_doc, solicitante_tipo,
                      dependencia_id, funcionario_id, created_at)
VALUES ('Permiso Especial', 800.00, '2025-10-20 16:05:00', 'registrado',
        '10456789012', 'JURIDICA', @dep_gra, @usr_bruno, '2025-10-20 16:05:00');
SET @t6 := LAST_INSERT_ID();

-- =========================
-- Validaciones por reglas
-- =========================
INSERT INTO validaciones (tramite_id, regla_codigo, resultado, detalle, created_at)
VALUES 
(@t1, 'R_DOC_DUP', 'error', 'Documento con solicitudes previas recientes', '2025-10-15 10:05:00'),
(@t1, 'R_MONTO_ALTO', 'ok', 'Monto validado contra catálogo', '2025-10-15 10:06:00'),
(@t2, 'R_DOC_DUP', 'ok', 'Sin antecedentes de duplicidad', '2025-10-16 09:35:00'),
(@t3, 'R_FRECUENCIA', 'error', 'Múltiples intentos en 7 días', '2025-10-17 14:25:00'),
(@t4, 'R_MONTO_ALTO', 'error', 'Excede umbral de revisión manual', '2025-10-18 11:50:00'),
(@t5, 'R_CONSIST', 'ok', 'Datos consistentes', '2025-10-19 08:15:00'),
(@t6, 'R_DOC_OBS', 'error', 'Documentos observados en validación documental', '2025-10-20 16:10:00');

-- =========================
-- Features (formato clave=valor)
-- =========================
INSERT INTO feature_vectors (tramite_id, schema_version, features_kv, created_at) VALUES
(@t1, 's1', 'monto=9500;dias_desde_ultimo=3;canal=online;repeticiones_doc=2', '2025-10-15 10:07:00'),
(@t2, 's1', 'monto=3200;dias_desde_ultimo=90;canal=ventanilla;repeticiones_doc=0', '2025-10-16 09:36:00'),
(@t3, 's1', 'monto=450;dias_desde_ultimo=2;canal=online;repeticiones_doc=3', '2025-10-17 14:26:00'),
(@t4, 's1', 'monto=250000;dias_desde_ultimo=365;canal=oficio;repeticiones_doc=0', '2025-10-18 11:51:00'),
(@t5, 's1', 'monto=1200;dias_desde_ultimo=400;canal=ventanilla;repeticiones_doc=0', '2025-10-19 08:16:00'),
(@t6, 's1', 'monto=800;dias_desde_ultimo=1;canal=online;repeticiones_doc=1', '2025-10-20 16:11:00');

-- =========================
-- Análisis (score + categoría usando umbrales dev v1)
-- =========================
-- Regla de ejemplo: score >= 0.80 -> alto, >= 0.60 -> medio, >= 0.40 -> bajo, else fuera.
INSERT INTO analisis (tramite_id, modelo_id, score, categoria, umbral_version, created_at) VALUES
(@t1, @modelo_basico, 0.78, 'medio', 'v1', '2025-10-15 10:08:00'),
(@t2, @modelo_basico, 0.22, 'bajo',  'v1', '2025-10-16 09:37:00'),
(@t3, @modelo_basico, 0.66, 'medio', 'v1', '2025-10-17 14:27:00'),
(@t4, @modelo_basico, 0.92, 'alto',  'v1', '2025-10-18 11:52:00'),
(@t5, @modelo_basico, 0.18, 'bajo',  'v1', '2025-10-19 08:17:00'),
(@t6, @modelo_basico, 0.73, 'medio', 'v1', '2025-10-20 16:12:00');

-- =========================
-- Alertas derivadas del análisis
-- =========================
INSERT INTO alertas (tramite_id, score, severidad, estado, motivos, created_at) VALUES
(@t1, 0.78, 'medio', 'abierta',  'Frecuencia elevada y duplicidad de documento', '2025-10-15 10:09:00'),
(@t3, 0.66, 'medio', 'abierta',  'Intentos repetidos en pocos días',            '2025-10-17 14:28:00'),
(@t4, 0.92, 'alto',  'asignada', 'Monto muy alto; requiere revisión',            '2025-10-18 11:53:00'),
(@t6, 0.73, 'medio', 'abierta',  'Documentos observados previamente',            '2025-10-20 16:13:00');
-- Guardar IDs para asignaciones y casos
SET @a_t1 := (SELECT id FROM alertas WHERE tramite_id=@t1 ORDER BY id DESC LIMIT 1);
SET @a_t3 := (SELECT id FROM alertas WHERE tramite_id=@t3 ORDER BY id DESC LIMIT 1);
SET @a_t4 := (SELECT id FROM alertas WHERE tramite_id=@t4 ORDER BY id DESC LIMIT 1);
SET @a_t6 := (SELECT id FROM alertas WHERE tramite_id=@t6 ORDER BY id DESC LIMIT 1);

-- Asignaciones (auditor Ana; asigna Admin)
INSERT INTO alertas_asignaciones (alerta_id, auditor_id, asignado_por, created_at) VALUES
(@a_t4, @usr_ana, @usr_admin, '2025-10-18 12:00:00');

-- Caso abierto desde alerta de alto riesgo
INSERT INTO casos (alerta_id, auditor_id, estado, resultado, conclusiones, created_at) VALUES
(@a_t4, @usr_ana, 'en_investigacion', NULL, 'Se inicia investigación por monto elevado', '2025-10-18 12:05:00');
SET @caso_t4 := LAST_INSERT_ID();

-- Evidencias del caso
INSERT INTO evidencias (caso_id, tipo, url_objeto, hash_integridad, descripcion, created_at) VALUES
(@caso_t4, 'documento', 'https://ejemplo.gob/expedientes/t4/contrato.pdf', NULL, 'Contrato adjunto', '2025-10-18 12:10:00'),
(@caso_t4, 'imagen',    'https://ejemplo.gob/expedientes/t4/foto1.jpg',     NULL, 'Foto lugar obra', '2025-10-18 12:12:00');

-- =========================
-- Notificaciones y auditoría
-- =========================
INSERT INTO notificaciones (tipo, usuario_id, alerta_id, estado, detalle_error, created_at) VALUES
('inapp', @usr_ana, @a_t4, 'enviado', NULL, '2025-10-18 12:01:00'),
('correo', @usr_ana, @a_t4, 'enviado', NULL, '2025-10-18 12:01:30'),
('inapp', @usr_admin, NULL, 'error', 'No se configuró token push', '2025-10-18 12:02:00');

INSERT INTO auditoria_logs (usuario_id, rol, accion, objeto_tipo, objeto_id, ip, metadata, created_at) VALUES
(@usr_admin, 'admin', 'login', 'usuarios', @usr_admin, '10.0.0.1', 'login_ok', '2025-10-18 11:40:00'),
(@usr_admin, 'admin', 'asignar', 'alertas', @a_t4, '10.0.0.1', 'asigna a @usr_ana', '2025-10-18 12:00:00'),
(@usr_ana,   'auditor', 'abrir_caso', 'casos', @caso_t4, '10.0.0.2', 'desde alerta @a_t4', '2025-10-18 12:05:10');

-- Integraciones y reportes (opcionales para pruebas)
INSERT INTO integraciones (nombre, estado, endpoint, auth_tipo, created_at) VALUES
('PIDE', 'inactivo', 'https://pide.gob.pe/api', 'apikey', '2025-10-10 00:00:00'),
('RENIEC', 'inactivo', 'https://reniec.gob.pe/api', 'oauth2', '2025-10-10 00:00:00');

INSERT INTO reportes_cache (clave, payload, rango_inicio, rango_fin, generado_at) VALUES
('alertas_resumen_oct2025', 'tramite_id,severidad\n1,medio\n3,medio\n4,alto\n6,medio', '2025-10-01', '2025-10-31', '2025-10-21 00:00:00');

-- =============================================================
-- FIN DEL ARCHIVO
-- =============================================================
