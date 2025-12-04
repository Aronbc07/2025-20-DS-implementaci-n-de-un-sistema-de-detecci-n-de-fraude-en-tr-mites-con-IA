<?php
// src/capaModelo/contacto_modelo.php
require_once __DIR__ . '/conexion.php';

final class ContactoModelo {
  public static function insertar(array $c): void {
    ConexionBD::call('sp_contacto_insertar', [
      $c['ticket'],
      $c['asunto'],
      $c['tipo_usuario'],
      $c['dependencia'],
      $c['referencia'],
      $c['nombres'],
      $c['email'],
      $c['telefono'],
      $c['mensaje'],
      $c['evidencia_path'],
      $c['evidencia_hash'],
    ]);
  }
}
