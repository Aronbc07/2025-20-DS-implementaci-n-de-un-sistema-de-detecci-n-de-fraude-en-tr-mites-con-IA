<?php
// src/capaModelo/tramites_modelo.php
require_once __DIR__ . '/conexion.php';

final class TramitesModelo {

  public static function insertar(array $t): int {
    // OUT var para id_tramite
    $outVar = '@p_id_tramite';
    $resp = ConexionBD::callWithOut(
      'sp_tramites_insertar',
      [
        $t['dep_codigo'],
        $t['usuario_id'],
        $t['tipo_tramite_codigo'],
        $t['tipo_doc_codigo'],
        $t['num_doc'],
        $t['nombres'],
        $t['monto'],
        $t['fecha'],
        $t['archivo_nombre'],
        $t['archivo_path'],
        $t['archivo_hash'],
        $t['archivo_mime'],
        $t['archivo_tamano'],
      ],
      [$outVar]
    );

    $id = (int)($resp['out']['p_id_tramite'] ?? 0);
    if ($id <= 0) throw new Exception('No se pudo obtener el ID del trámite.');
    return $id;
  }

  public static function listarRecientes(?int $limit = 50): array {
    return ConexionBD::call('sp_tramites_listar_recientes', [$limit]);
  }

  /** sp_tramites_detalle devuelve 4 resultsets: tramite(vw), validaciones, analisis, alertas */
  public static function detalle(int $idTramite): array {
    $sets = ConexionBD::callMulti('sp_tramites_detalle', [$idTramite]);

    return [
      'tramite'      => $sets[0][0] ?? null,
      'validaciones' => $sets[1] ?? [],
      'analisis'     => $sets[2] ?? [],
      'alertas'      => $sets[3] ?? [],
    ];
  }

  public static function cambiarEstado(int $idTramite, string $estado): void {
    ConexionBD::call('sp_tramite_cambiar_estado', [$idTramite, $estado]);
  }

  public static function ejecutarValidaciones(int $idTramite, int $usuarioId): void {
    ConexionBD::call('sp_validaciones_ejecutar', [$idTramite, $usuarioId]);
  }

  public static function featuresUpsert(int $idTramite, int $schema, string $featuresKv): void {
    ConexionBD::call('sp_features_upsert', [$idTramite, $schema, $featuresKv]);
  }

  public static function registrarAnalisis(int $idTramite, int $idModelo, float $score, string $explicacion): void {
    ConexionBD::call('sp_analisis_registrar', [$idTramite, $idModelo, $score, $explicacion]);
  }
}
