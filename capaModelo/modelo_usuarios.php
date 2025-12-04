<?php
/* src/capaModelo/modelo_usuarios.php
   Catálogo de usuarios (funcionarios activos) para combos/ocultos.
*/
require_once __DIR__ . '/conexion.php';

/** Devuelve [{id, nombre}] (solo funcionarios activos). */
function usuarios_listar_funcionarios_activos(): array {
  $cn = cn();
  $rs = $cn->query("CALL sp_usuarios_listar_funcionarios_activos()");
  $rows = fetch_all_assoc($rs);
  limpiar_call($cn);
  $cn->close();
  return $rows; // ['id'=>..., 'nombre'=>...]
}
