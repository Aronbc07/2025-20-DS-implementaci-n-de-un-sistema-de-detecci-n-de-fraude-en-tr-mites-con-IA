<?php
// src/capaModelo/dashboard_modelo.php
require_once __DIR__ . '/conexion.php';

final class DashboardModelo {
  public static function kpi(): array {
    $sets = ConexionBD::callMulti('sp_dashboard_kpi', []);
    return [
      'kpi'        => $sets[0] ?? [],
      'porSev'     => $sets[1] ?? [],
      'porEstado'  => $sets[2] ?? [],
    ];
  }
}
