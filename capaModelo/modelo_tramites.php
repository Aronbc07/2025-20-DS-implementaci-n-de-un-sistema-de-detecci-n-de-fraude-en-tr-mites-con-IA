<?php
/* src/capaModelo/modelo_tramites.php
   Invocaciones a SP.* de trámites: combos y creación de trámite.
*/
require_once __DIR__ . '/conexion.php';

/** Lista para combo: tipos de trámite (arreglo de strings). */
function tramites_listar_tipos_tramite(): array {
  $cn = cn();
  $rs = $cn->query("CALL sp_tramites_listar_tipos_tramite()");
  $rows = fetch_all_assoc($rs);
  limpiar_call($cn);
  $cn->close();
  // Devuelve solo el valor (string) por simplicidad
  return array_map(fn($r) => $r['valor'], $rows);
}

/** Lista para combo: tipos de documento (arreglo de strings). */
function tramites_listar_tipos_documento(): array {
  $cn = cn();
  $rs = $cn->query("CALL sp_tramites_listar_tipos_documento()");
  $rows = fetch_all_assoc($rs);
  limpiar_call($cn);
  $cn->close();
  return array_map(fn($r) => $r['valor'], $rows);
}

/**
 * Crea un trámite y devuelve el ID generado (BIGINT).
 * - $fecha: 'YYYY-MM-DD HH:MM:SS' (o 'YYYY-MM-DD HH:MM')
 */
function tramites_crear(
  string $tipo,
  float  $monto,
  string $fecha,
  string $documento,
  string $tipoDoc,
  int    $dependenciaId,
  int    $funcionarioId
): int {
  $cn = cn();

  // CALL con parámetro de salida @p_id
  $stmt = $cn->prepare("CALL sp_tramites_crear(?, ?, ?, ?, ?, ?, ?, @p_id)");
  if (!$stmt) {
    $cn->close();
    return 0;
  }
  // sdsssii -> string, double, string, string, string, int, int
  $stmt->bind_param("sdsssii", $tipo, $monto, $fecha, $documento, $tipoDoc, $dependenciaId, $funcionarioId);
  $ok = $stmt->execute();
  $stmt->close();

  if (!$ok) {
    limpiar_call($cn);
    $cn->close();
    return 0;
  }

  // Leer el OUT
  $rs = $cn->query("SELECT @p_id AS id");
  $row = $rs ? $rs->fetch_assoc() : null;
  if ($rs) { $rs->free(); }
  limpiar_call($cn);
  $cn->close();

  return isset($row['id']) ? (int)$row['id'] : 0;
}

/* (Opcional, para Historial cuando lo habilites)
function tramites_historial_por_documento(string $documento, int $limite = 50): array {
  $cn = cn();
  // Si tu SP existe: sp_tramites_historial_por_documento(doc, limite)
  $stmt = $cn->prepare("CALL sp_tramites_historial_por_documento(?, ?)");
  $stmt->bind_param("si", $documento, $limite);
  $stmt->execute();
  $rs = $stmt->get_result();
  $rows = fetch_all_assoc($rs);
  $stmt->close();
  limpiar_call($cn);
  $cn->close();
  return $rows;
}
*/
