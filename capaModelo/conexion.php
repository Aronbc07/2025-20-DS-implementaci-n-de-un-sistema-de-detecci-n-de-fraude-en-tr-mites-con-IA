<?php
/* src/capaModelo/conexion.php
   Conexión simple a MySQL/MariaDB (XAMPP) y utilidades para CALL.
*/

if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_NAME')) define('DB_NAME', 'BDFraudeIA');

/** Abre conexión y fija charset/zonahoraria. */
function cn(): mysqli {
  $cn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
  if ($cn->connect_errno) {
    // Mensaje controlado (no exponer detalles de conexión)
    die('Error de conexión a la base de datos.');
  }
  $cn->set_charset('utf8mb4');
  // Opcional: zona horaria local (XAMPP suele aceptarlo)
  @$cn->query("SET time_zone = '-05:00'");
  return $cn;
}

/** Limpia result sets colgantes después de un CALL. */
function limpiar_call(mysqli $cn): void {
  while ($cn->more_results()) {
    $cn->next_result();
    if ($rs = $cn->store_result()) { $rs->free(); }
  }
}

/** Convierte un resultset en arreglo asociativo y lo libera. */
function fetch_all_assoc(?mysqli_result $rs): array {
  $data = [];
  if ($rs instanceof mysqli_result) {
    while ($row = $rs->fetch_assoc()) { $data[] = $row; }
    $rs->free();
  }
  return $data;
}
