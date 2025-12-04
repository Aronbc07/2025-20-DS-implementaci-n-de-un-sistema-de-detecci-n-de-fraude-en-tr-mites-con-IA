<?php
/* src/capaModelo/modelo_dependencias.php
   Catálogo de dependencias (para combos).
*/
require_once __DIR__ . '/conexion.php';

/** Devuelve [{id, nombre}] ordenado por nombre. */
function dependencias_listar(): array {
  $cn = cn();
  $rs = $cn->query("CALL sp_dependencias_listar()");
  $rows = fetch_all_assoc($rs);
  limpiar_call($cn);
  $cn->close();
  return $rows; // cada fila: ['id'=>..., 'nombre'=>...]
}
