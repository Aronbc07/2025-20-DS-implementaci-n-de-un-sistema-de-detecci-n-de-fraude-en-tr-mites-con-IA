<?php
// src/capaModelo/conexion.php

final class ConexionBD {
  private static ?PDO $pdo = null;

  public static function get(): PDO {
    if (self::$pdo instanceof PDO) return self::$pdo;

    $cfg = require __DIR__ . '/config.php';
    $db  = $cfg['db'];

    $charset = $db['charset'] ?? 'utf8mb4';

    $dsn = sprintf(
      'mysql:host=%s;dbname=%s;charset=%s',
      $db['host'],
      $db['name'],
      $charset
    );

    self::$pdo = new PDO($dsn, $db['user'], $db['pass'], [
      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    // Extra: asegurar charset/colación en XAMPP
    self::$pdo->exec("SET NAMES {$charset}");

    return self::$pdo;
  }

  /** Seguridad: nombres válidos tipo sp_xxx (evita inyección por nombre de SP) */
  private static function assertSpName(string $spName): void {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $spName)) {
      throw new InvalidArgumentException("Nombre de SP inválido: {$spName}");
    }
  }

  /** Ejecuta un SP que retorna 1 resultset */
  public static function call(string $spName, array $inParams = []): array {
    self::assertSpName($spName);
    $pdo = self::get();

    $placeholders = implode(',', array_fill(0, count($inParams), '?'));
    $sql = "CALL {$spName}(" . $placeholders . ")";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($inParams));

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // limpiar resultsets adicionales (MySQL requiere esto a veces)
    while ($stmt->nextRowset()) { /* noop */ }
    $stmt->closeCursor();

    return $rows;
  }

  /**
   * Ejecuta un SP que retorna MÚLTIPLES resultsets.
   * Devuelve: [ [rows...], [rows...], ... ]
   */
  public static function callMulti(string $spName, array $inParams = []): array {
    self::assertSpName($spName);
    $pdo = self::get();

    $placeholders = implode(',', array_fill(0, count($inParams), '?'));
    $sql = "CALL {$spName}(" . $placeholders . ")";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($inParams));

    $sets = [];
    do {
      $sets[] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } while ($stmt->nextRowset());

    $stmt->closeCursor();
    return $sets;
  }

  /**
   * Ejecuta un SP con OUT params (MySQL).
   * Ej: CALL sp_xxx(?, ?, @out_id); luego SELECT @out_id AS out_id;
   */
  public static function callWithOut(string $spName, array $inParams, array $outVars): array {
    self::assertSpName($spName);
    $pdo = self::get();

    // sanity de OUT vars
    foreach ($outVars as $v) {
      if (!preg_match('/^@[a-zA-Z0-9_]+$/', $v)) {
        throw new InvalidArgumentException("OUT var inválida: {$v} (usa formato @nombre)");
      }
    }

    $inPlace  = implode(',', array_fill(0, count($inParams), '?'));
    $outPlace = implode(',', $outVars);

    $comma = ($inPlace !== '' && $outPlace !== '') ? ',' : '';
    $sql = "CALL {$spName}(" . $inPlace . $comma . $outPlace . ")";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($inParams));

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    while ($stmt->nextRowset()) { /* limpiar */ }
    $stmt->closeCursor();

    // leer OUT vars
    $selectOut = 'SELECT ' . implode(', ', array_map(
      fn($v) => "{$v} AS " . ltrim($v, '@'),
      $outVars
    ));

    $out = $pdo->query($selectOut)->fetch(PDO::FETCH_ASSOC) ?: [];

    return ['result' => $rows, 'out' => $out];
  }
}
