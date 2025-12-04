<?php
// src/capaModelo/alertas_modelo.php
require_once __DIR__ . '/conexion.php';

final class AlertasModelo {

  public static function listar(?string $estado = '', ?string $severidad = '', ?int $limit = 50): array {
    return ConexionBD::call('sp_alertas_listar', [$estado, $severidad, $limit]);
  }

  public static function generar(int $idTramite, float $score, string $motivos, int $creadaPor): int {
    $outVar = '@p_id_alerta';
    $resp = ConexionBD::callWithOut(
      'sp_alertas_generar',
      [$idTramite, $score, $motivos, $creadaPor],
      [$outVar]
    );

    $id = (int)($resp['out']['p_id_alerta'] ?? 0);
    if ($id <= 0) throw new Exception('No se pudo obtener el ID de la alerta.');
    return $id;
  }

  public static function asignar(int $idAlerta, int $auditorId, int $asignadoPor): void {
    ConexionBD::call('sp_alertas_asignar', [$idAlerta, $auditorId, $asignadoPor]);
  }
}
