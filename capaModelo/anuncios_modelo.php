<?php
// src/capaModelo/anuncios_modelo.php
require_once __DIR__ . '/conexion.php';

final class AnunciosModelo {
  public static function vigentes(?string $hoy = null): array {
    $hoy = $hoy ?: date('Y-m-d');
    return ConexionBD::call('sp_anuncios_listar_vigentes', [$hoy]);
  }
}
