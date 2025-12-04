DELIMITER $$

-- =====================================
-- TRÁMITES (Registrar / Listar / Detalle / Estado)
-- =====================================

DROP PROCEDURE IF EXISTS sp_tramites_insertar $$
CREATE PROCEDURE sp_tramites_insertar(
  IN p_dep_codigo VARCHAR(10),
  IN p_usuario_id INT,
  IN p_tipo_tramite_codigo VARCHAR(30),
  IN p_tipo_doc_codigo VARCHAR(20),
  IN p_num_doc VARCHAR(20),
  IN p_nombres VARCHAR(160),
  IN p_monto DECIMAL(12,2),
  IN p_fecha DATE,
  IN p_archivo_nombre VARCHAR(180),
  IN p_archivo_path VARCHAR(255),
  IN p_archivo_hash VARCHAR(64),
  IN p_archivo_mime VARCHAR(80),
  IN p_archivo_tamano INT,
  OUT p_id_tramite BIGINT
)
BEGIN
  DECLARE v_id_dep INT;
  DECLARE v_id_tt INT;
  DECLARE v_id_td INT;
  DECLARE v_codigo VARCHAR(40);
  DECLARE v_seq INT;

  SELECT id_dependencia INTO v_id_dep
  FROM dependencias
  WHERE codigo_dependencia = p_dep_codigo AND activo = 1
  LIMIT 1;

  IF v_id_dep IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Dependencia inválida o inactiva.';
  END IF;

  SELECT id_tipo_tramite INTO v_id_tt
  FROM cat_tipo_tramite
  WHERE codigo = p_tipo_tramite_codigo AND activo = 1
  LIMIT 1;

  IF v_id_tt IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tipo de trámite inválido o inactivo.';
  END IF;

  SELECT id_tipo_documento INTO v_id_td
  FROM cat_tipo_documento
  WHERE codigo = p_tipo_doc_codigo AND activo = 1
  LIMIT 1;

  IF v_id_td IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tipo de documento inválido o inactivo.';
  END IF;

  -- Código: TRM-YYYYMMDD-00001 (secuencial por día)
  SELECT COUNT(*) + 1 INTO v_seq
  FROM tramites
  WHERE fecha_tramite = p_fecha;

  SET v_codigo = CONCAT('TRM-', DATE_FORMAT(p_fecha, '%Y%m%d'), '-', LPAD(v_seq, 5, '0'));

  INSERT INTO tramites(
    codigo_tramite, id_dependencia, id_usuario_registra,
    id_tipo_tramite, id_tipo_documento,
    solicitante_num_doc, solicitante_nombres,
    monto, fecha_tramite, estado,
    archivo_nombre, archivo_path, archivo_hash, archivo_mime, archivo_tamano
  ) VALUES (
    v_codigo, v_id_dep, p_usuario_id,
    v_id_tt, v_id_td,
    p_num_doc, p_nombres,
    p_monto, p_fecha, 'VALIDACION_PENDIENTE',
    p_archivo_nombre, p_archivo_path, p_archivo_hash, p_archivo_mime, p_archivo_tamano
  );

  SET p_id_tramite = LAST_INSERT_ID();
END $$


DROP PROCEDURE IF EXISTS sp_tramites_listar_recientes $$
CREATE PROCEDURE sp_tramites_listar_recientes(IN p_limit INT)
BEGIN
  DECLARE v_limit INT DEFAULT 50;

  IF p_limit IS NOT NULL AND p_limit > 0 THEN
    SET v_limit = LEAST(p_limit, 500);
  END IF;

  SELECT * FROM vw_tramites_recientes
  LIMIT v_limit;
END $$


DROP PROCEDURE IF EXISTS sp_tramites_detalle $$
CREATE PROCEDURE sp_tramites_detalle(IN p_id_tramite BIGINT)
BEGIN
  SELECT * FROM vw_tramites_recientes WHERE id_tramite = p_id_tramite;
  SELECT * FROM validaciones WHERE id_tramite = p_id_tramite ORDER BY creado_at ASC;
  SELECT * FROM analisis WHERE id_tramite = p_id_tramite ORDER BY creado_at DESC;
  SELECT * FROM alertas WHERE id_tramite = p_id_tramite ORDER BY creada_at DESC;
END $$


DROP PROCEDURE IF EXISTS sp_tramite_cambiar_estado $$
CREATE PROCEDURE sp_tramite_cambiar_estado(IN p_id_tramite BIGINT, IN p_estado VARCHAR(40))
BEGIN
  UPDATE tramites SET estado = p_estado WHERE id_tramite = p_id_tramite;
END $$


-- =====================================
-- VALIDACIÓN (Validar Datos)
-- =====================================

DROP PROCEDURE IF EXISTS sp_validaciones_ejecutar $$
CREATE PROCEDURE sp_validaciones_ejecutar(IN p_id_tramite BIGINT, IN p_usuario_id INT)
BEGIN
  DECLARE v_err INT DEFAULT 0;
  DECLARE v_obs INT DEFAULT 0;

  DELETE FROM validaciones WHERE id_tramite = p_id_tramite;

  -- Regla 1: Documento presente
  INSERT INTO validaciones(id_tramite,tipo,regla_codigo,resultado,detalle,ejecutado_por)
  SELECT
    t.id_tramite, 'DOCUMENTO','DOC_PRESENTE',
    CASE WHEN t.archivo_path IS NULL OR t.archivo_path = '' THEN 'ERROR' ELSE 'OK' END,
    CASE WHEN t.archivo_path IS NULL OR t.archivo_path = '' THEN 'Debe adjuntar un documento.' ELSE 'Documento adjunto.' END,
    p_usuario_id
  FROM tramites t WHERE t.id_tramite = p_id_tramite;

  -- Regla 2: Formato mínimo del documento (básico)
  INSERT INTO validaciones(id_tramite,tipo,regla_codigo,resultado,detalle,ejecutado_por)
  SELECT
    t.id_tramite, 'DATOS','DOC_NUM_FORMATO',
    CASE WHEN LENGTH(t.solicitante_num_doc) < 8 THEN 'OBSERVADO' ELSE 'OK' END,
    CASE WHEN LENGTH(t.solicitante_num_doc) < 8 THEN 'Número de documento parece incompleto.' ELSE 'Número de documento válido (validación básica).' END,
    p_usuario_id
  FROM tramites t WHERE t.id_tramite = p_id_tramite;

  SELECT COUNT(*) INTO v_err
  FROM validaciones
  WHERE id_tramite = p_id_tramite AND resultado = 'ERROR';

  SELECT COUNT(*) INTO v_obs
  FROM validaciones
  WHERE id_tramite = p_id_tramite AND resultado = 'OBSERVADO';

  IF v_err > 0 THEN
    UPDATE tramites SET estado = 'OBSERVADO' WHERE id_tramite = p_id_tramite;
  ELSEIF v_obs > 0 THEN
    UPDATE tramites SET estado = 'OBSERVADO' WHERE id_tramite = p_id_tramite;
  ELSE
    UPDATE tramites SET estado = 'VALIDADO' WHERE id_tramite = p_id_tramite;
  END IF;
END $$


-- =====================================
-- IA (Features / Registro de Score)
-- =====================================

DROP PROCEDURE IF EXISTS sp_features_upsert $$
CREATE PROCEDURE sp_features_upsert(IN p_id_tramite BIGINT, IN p_schema INT, IN p_features TEXT)
BEGIN
  INSERT INTO feature_vectors(id_tramite, schema_version, features_kv)
  VALUES(p_id_tramite, p_schema, p_features)
  ON DUPLICATE KEY UPDATE
    schema_version = VALUES(schema_version),
    features_kv = VALUES(features_kv),
    actualizado_at = CURRENT_TIMESTAMP;
END $$


DROP PROCEDURE IF EXISTS sp_analisis_registrar $$
CREATE PROCEDURE sp_analisis_registrar(
  IN p_id_tramite BIGINT,
  IN p_id_modelo INT,
  IN p_score DECIMAL(6,3),
  IN p_explicacion TEXT
)
BEGIN
  DECLARE v_cat VARCHAR(10);

  SET v_cat = CASE
    WHEN p_score >= 80 THEN 'ALTO'
    WHEN p_score >= 50 THEN 'MEDIO'
    ELSE 'BAJO'
  END;

  INSERT INTO analisis(id_tramite, id_modelo, score, categoria, explicacion)
  VALUES(p_id_tramite, p_id_modelo, p_score, v_cat, p_explicacion);

  UPDATE tramites
  SET estado = 'ANALIZADO'
  WHERE id_tramite = p_id_tramite;
END $$


-- =====================================
-- ALERTAS (Generar / Asignar / Listar)
-- =====================================

DROP PROCEDURE IF EXISTS sp_alertas_generar $$
CREATE PROCEDURE sp_alertas_generar(
  IN p_id_tramite BIGINT,
  IN p_score DECIMAL(6,3),
  IN p_motivos TEXT,
  IN p_creada_por INT,
  OUT p_id_alerta BIGINT
)
BEGIN
  DECLARE v_sev VARCHAR(10);

  SET v_sev = CASE
    WHEN p_score >= 80 THEN 'ALTO'
    WHEN p_score >= 50 THEN 'MEDIO'
    ELSE 'BAJO'
  END;

  INSERT INTO alertas(id_tramite, score, severidad, estado, motivos, creada_por)
  VALUES(p_id_tramite, p_score, v_sev, 'ABIERTA', p_motivos, p_creada_por);

  SET p_id_alerta = LAST_INSERT_ID();

  UPDATE tramites SET estado = 'CON_ALERTA' WHERE id_tramite = p_id_tramite;
END $$


DROP PROCEDURE IF EXISTS sp_alertas_asignar $$
CREATE PROCEDURE sp_alertas_asignar(
  IN p_id_alerta BIGINT,
  IN p_auditor_id INT,
  IN p_asignado_por INT
)
BEGIN
  INSERT INTO alertas_asignaciones(id_alerta, auditor_id, asignado_por)
  VALUES(p_id_alerta, p_auditor_id, p_asignado_por);

  UPDATE alertas SET estado = 'ASIGNADA' WHERE id_alerta = p_id_alerta;
END $$


DROP PROCEDURE IF EXISTS sp_alertas_listar $$
CREATE PROCEDURE sp_alertas_listar(
  IN p_estado VARCHAR(30),
  IN p_severidad VARCHAR(10),
  IN p_limit INT
)
BEGIN
  DECLARE v_limit INT DEFAULT 50;

  IF p_limit IS NOT NULL AND p_limit > 0 THEN
    SET v_limit = LEAST(p_limit, 500);
  END IF;

  SELECT a.*
  FROM alertas a
  WHERE (p_estado IS NULL OR p_estado = '' OR a.estado = p_estado)
    AND (p_severidad IS NULL OR p_severidad = '' OR a.severidad = p_severidad)
  ORDER BY a.creada_at DESC
  LIMIT v_limit;
END $$


-- =====================================
-- CASOS (Crear / Cerrar) + Evidencias
-- =====================================

DROP PROCEDURE IF EXISTS sp_casos_crear $$
CREATE PROCEDURE sp_casos_crear(
  IN p_id_alerta BIGINT,
  IN p_creado_por INT,
  OUT p_id_caso BIGINT
)
BEGIN
  DECLARE v_codigo VARCHAR(40);
  DECLARE v_seq INT;

  SELECT COUNT(*) + 1 INTO v_seq
  FROM casos
  WHERE DATE(creado_at) = CURDATE();

  SET v_codigo = CONCAT('CAS-', DATE_FORMAT(NOW(), '%Y%m%d'), '-', LPAD(v_seq, 5, '0'));

  INSERT INTO casos(id_alerta, codigo_caso, creado_por)
  VALUES(p_id_alerta, v_codigo, p_creado_por);

  SET p_id_caso = LAST_INSERT_ID();

  UPDATE alertas SET estado = 'EN_INVESTIGACION' WHERE id_alerta = p_id_alerta;
END $$


DROP PROCEDURE IF EXISTS sp_casos_cerrar $$
CREATE PROCEDURE sp_casos_cerrar(
  IN p_id_caso BIGINT,
  IN p_resultado VARCHAR(20),
  IN p_conclusiones TEXT
)
BEGIN
  UPDATE casos
  SET estado = 'CERRADO',
      resultado = p_resultado,
      conclusiones = p_conclusiones,
      cerrado_at = NOW()
  WHERE id_caso = p_id_caso;
END $$


DROP PROCEDURE IF EXISTS sp_evidencias_agregar $$
CREATE PROCEDURE sp_evidencias_agregar(
  IN p_id_caso BIGINT,
  IN p_tipo VARCHAR(20),
  IN p_url VARCHAR(255),
  IN p_hash VARCHAR(64),
  IN p_desc VARCHAR(255),
  IN p_creado_por INT
)
BEGIN
  INSERT INTO evidencias(id_caso, tipo, url_objeto, hash_integridad, descripcion, creado_por)
  VALUES(p_id_caso, p_tipo, p_url, p_hash, p_desc, p_creado_por);
END $$


-- =====================================
-- DASHBOARD (KPIs)
-- =====================================

DROP PROCEDURE IF EXISTS sp_dashboard_kpi $$
CREATE PROCEDURE sp_dashboard_kpi()
BEGIN
  SELECT * FROM vw_kpi_resumen;
  SELECT * FROM vw_alertas_por_severidad;
  SELECT estado, COUNT(*) total FROM tramites GROUP BY estado;
END $$


-- =====================================
-- INICIO (Anuncios vigentes)
-- =====================================

DROP PROCEDURE IF EXISTS sp_anuncios_listar_vigentes $$
CREATE PROCEDURE sp_anuncios_listar_vigentes(IN p_hoy DATE)
BEGIN
  SELECT *
  FROM anuncios
  WHERE activo = 1
    AND p_hoy BETWEEN vigencia_desde AND vigencia_hasta
  ORDER BY vigencia_desde DESC;
END $$


-- =====================================
-- CONTACTO (Registrar ticket)
-- =====================================

DROP PROCEDURE IF EXISTS sp_contacto_insertar $$
CREATE PROCEDURE sp_contacto_insertar(
  IN p_ticket VARCHAR(40),
  IN p_asunto VARCHAR(40),
  IN p_tipo_usuario VARCHAR(20),
  IN p_dependencia VARCHAR(10),
  IN p_referencia VARCHAR(40),
  IN p_nombres VARCHAR(120),
  IN p_email VARCHAR(120),
  IN p_telefono VARCHAR(40),
  IN p_mensaje TEXT,
  IN p_evidencia_path VARCHAR(255),
  IN p_evidencia_hash VARCHAR(64)
)
BEGIN
  INSERT INTO contacto_mensajes(
    ticket, asunto, tipo_usuario, dependencia, referencia,
    nombres, email, telefono, mensaje, evidencia_path, evidencia_hash
  )
  VALUES(
    p_ticket, p_asunto, p_tipo_usuario, p_dependencia, p_referencia,
    p_nombres, p_email, p_telefono, p_mensaje, p_evidencia_path, p_evidencia_hash
  );
END $$


-- =====================================
-- CACHE DE REPORTES (para acelerar informes)
-- =====================================

DROP PROCEDURE IF EXISTS sp_reportes_cache_upsert $$
CREATE PROCEDURE sp_reportes_cache_upsert(
  IN p_clave VARCHAR(160),
  IN p_payload MEDIUMTEXT,
  IN p_inicio DATE,
  IN p_fin DATE
)
BEGIN
  INSERT INTO reportes_cache(clave, payload, rango_inicio, rango_fin)
  VALUES(p_clave, p_payload, p_inicio, p_fin)
  ON DUPLICATE KEY UPDATE
    payload = VALUES(payload),
    rango_inicio = VALUES(rango_inicio),
    rango_fin = VALUES(rango_fin),
    generado_at = CURRENT_TIMESTAMP;
END $$

DELIMITER ;
