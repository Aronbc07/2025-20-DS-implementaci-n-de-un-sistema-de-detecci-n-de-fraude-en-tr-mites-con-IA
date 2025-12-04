<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

function redirect(string $to): void {
  header("Location: $to");
  exit;
}

function flash_error(string $msg): void {
  $_SESSION['flash'] = ['type' => 'error', 'msg' => $msg];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  redirect('index.php?url=tramites.registrar');
}

$dni          = trim((string)($_POST['dni'] ?? ''));
$tipoTramite  = trim((string)($_POST['tipo_tramite'] ?? ''));
$tipoDoc      = trim((string)($_POST['tipo_documento'] ?? ''));
$goTo         = trim((string)($_POST['go_to'] ?? 'registrar'));

if (!preg_match('/^\d{8}$/', $dni)) {
  flash_error('Valide sus datos y documentos deben ser correctos. DNI inválido.');
  redirect('index.php?url=tramites.registrar');
}
if ($tipoTramite === '') {
  flash_error('Valide sus datos y documentos deben ser correctos. Seleccione el tipo de trámite.');
  redirect('index.php?url=tramites.registrar');
}
if ($tipoDoc === '') {
  flash_error('Valide sus datos y documentos deben ser correctos. Seleccione el tipo de documento.');
  redirect('index.php?url=tramites.registrar');
}

/**
 * Si el POST supera post_max_size, PHP NO llena $_POST ni $_FILES.
 * Detectamos esto y damos un mensaje claro.
 */
if (empty($_POST) && empty($_FILES)) {
  flash_error('El archivo es demasiado grande para el servidor (límite de subida). Reduzca el tamaño o aumente upload_max_filesize/post_max_size en php.ini.');
  redirect('index.php?url=tramites.registrar');
}

if (!isset($_FILES['documento']) || !is_array($_FILES['documento'])) {
  flash_error('Valide sus datos y documentos deben ser correctos. Agregue un documento antes de validar.');
  redirect('index.php?url=tramites.registrar');
}

$f = $_FILES['documento'];

if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
  // Mensajes útiles
  $err = (int)($f['error'] ?? 0);
  if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
    flash_error('El archivo excede el tamaño permitido por el servidor.');
  } else {
    flash_error('Error al subir el documento.');
  }
  redirect('index.php?url=tramites.registrar');
}

$origName = (string)($f['name'] ?? '');
$tmpName  = (string)($f['tmp_name'] ?? '');
$size     = (int)($f['size'] ?? 0);

if ($tmpName === '' || !is_uploaded_file($tmpName)) {
  flash_error('Documento inválido.');
  redirect('index.php?url=tramites.registrar');
}

// Máximo lógico del sistema (ajusta si quieres)
$maxBytes = 10 * 1024 * 1024; // 10MB
if ($size <= 0 || $size > $maxBytes) {
  flash_error('El archivo supera el máximo permitido (10MB).');
  redirect('index.php?url=tramites.registrar');
}

$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
$allowedExt = ['pdf','jpg','jpeg','png'];
if (!in_array($ext, $allowedExt, true)) {
  flash_error('Extensión no permitida.');
  redirect('index.php?url=tramites.registrar');
}

// MIME real
$mime = '';
if (class_exists('finfo')) {
  $fi = new finfo(FILEINFO_MIME_TYPE);
  $mime = (string)($fi->file($tmpName) ?: '');
}

$allowedMime = [
  'pdf'  => ['application/pdf'],
  'jpg'  => ['image/jpeg'],
  'jpeg' => ['image/jpeg'],
  'png'  => ['image/png'],
];

if ($mime === '' || !isset($allowedMime[$ext]) || !in_array($mime, $allowedMime[$ext], true)) {
  flash_error('El tipo real del archivo no coincide.');
  redirect('index.php?url=tramites.registrar');
}

// Guardar archivo
$storageDir = dirname(__DIR__, 2) . '/storage/tramites';
if (!is_dir($storageDir)) {
  @mkdir($storageDir, 0775, true);
}

$safeName = 'tram_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$destFs   = $storageDir . '/' . $safeName;

if (!move_uploaded_file($tmpName, $destFs)) {
  flash_error('No se pudo guardar el documento.');
  redirect('index.php?url=tramites.registrar');
}

$_SESSION['tramite_tmp'] = [
  'dni' => $dni,
  'tipo_tramite' => $tipoTramite,
  'tipo_documento' => $tipoDoc,
  'file' => [
    'original_name' => $origName,
    'path_fs' => $destFs,
    'ext' => $ext,
    'size' => $size,
    'mime' => $mime,
  ],
  'created_at' => time(),
  'validado_global' => null,
];

if ($goTo === 'validar') {
  redirect('index.php?url=tramites.validar');
}

$_SESSION['flash'] = [
  'type' => 'ok',
  'msg'  => 'Trámite registrado temporalmente. Ahora puede presionar “Validar Datos”.'
];
redirect('index.php?url=tramites.registrar');
