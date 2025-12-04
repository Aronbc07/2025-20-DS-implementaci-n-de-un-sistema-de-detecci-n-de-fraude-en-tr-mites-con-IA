<?php
// src/capaControl/contacto_control.php
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../capaModelo/contacto_modelo.php';

ensure_post();

$data = [
  'ticket'        => 'CT-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2))),
  'asunto'        => trim($_POST['asunto'] ?? ''),
  'tipo_usuario'  => trim($_POST['tipo_usuario'] ?? 'Gestor'),
  'dependencia'   => trim($_POST['dependencia'] ?? ''),
  'referencia'    => trim($_POST['referencia'] ?? ''),
  'nombres'       => trim($_POST['nombres'] ?? ''),
  'email'         => trim($_POST['email'] ?? ''),
  'telefono'      => trim($_POST['telefono'] ?? ''),
  'mensaje'       => trim($_POST['mensaje'] ?? ''),
  'evidencia_path'=> null,
  'evidencia_hash'=> null,
];

if ($data['asunto'] === '' || $data['nombres'] === '' || $data['email'] === '' || $data['mensaje'] === '') {
  flash_set('error', 'Complete asunto, nombre, email y mensaje.');
  go('index.php?url=contacto');
}

try {
  ContactoModelo::insertar($data);
  flash_set('success', 'Mensaje enviado. Ticket: ' . $data['ticket']);
  go('index.php?url=contacto');
} catch (Throwable $e) {
  flash_set('error', 'No se pudo enviar: ' . $e->getMessage());
  go('index.php?url=contacto');
}
