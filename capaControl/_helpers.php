<?php
// src/capaControl/_helpers.php

function flash_set(string $type, string $msg): void {
  $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function flash_get(): ?array {
  $f = $_SESSION['flash'] ?? null;
  unset($_SESSION['flash']);
  return is_array($f) ? $f : null;
}

function go(string $to): void {
  header("Location: $to");
  exit;
}

function ensure_post(): void {
  if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    flash_set('error', 'Acción no permitida.');
    go('index.php?url=home');
  }
}

function safe_mkdir(string $dir): void {
  if (!is_dir($dir)) mkdir($dir, 0775, true);
}

function is_allowed_mime(string $mime): bool {
  // Ajustable: acepta PDFs e imágenes comunes
  $ok = [
    'application/pdf',
    'image/png',
    'image/jpeg',
    'image/jpg',
  ];
  return in_array(strtolower($mime), $ok, true);
}

function file_ext_from_name(string $name): string {
  $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
  return $ext ?: 'bin';
}
