
<?php

session_start();

// Gate: si no hay documento cargado, no entra a Validar Datos
$tram = $_SESSION['tramite_tmp'] ?? null;

$ok = is_array($tram)
  && isset($tram['file']['path_fs'])
  && is_string($tram['file']['path_fs'])
  && is_file($tram['file']['path_fs']);

if (!$ok) {
  $_SESSION['flash'] = [
    'type' => 'error',
    'msg'  => 'Valide sus datos y documentos deben ser correctos. Agregue un documento antes de validar.'
  ];
  header('Location: ../../index.php?url=tramites.registrar');
  exit;
}

// Si pasa, recién muestra la vista
require_once __DIR__ . '/../capaVista/tramites/validar.php';
