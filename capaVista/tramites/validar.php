<?php
// src/capaVista/tramites/validar.php

$url = $_GET['url'] ?? 'tramites.validar';
function is_active(string $current, string $route): string { return $current === $route ? 'is-active' : ''; }

function build_row(string $regla, string $resultado, string $detalle, string $sev, string $accion): array {
  return compact('regla','resultado','detalle','sev','accion');
}

function validate_file(array $t): array {
  $rows = [];
  $global = 'APROBADO';

  if (!isset($t['file']['path_fs']) || !is_file($t['file']['path_fs'])) {
    return [
      'global' => 'RECHAZADO',
      'rows' => [
        build_row('Documento cargado', 'ERROR', 'No existe un documento para validar.', 'ALTA', 'Regrese y registre un documento.')
      ],
      'file_name' => '',
    ];
  }

  $fs   = $t['file']['path_fs'];
  $ext  = strtolower((string)($t['file']['ext'] ?? ''));
  $size = (int)($t['file']['size'] ?? @filesize($fs));
  $name = (string)($t['file']['original_name'] ?? basename($fs));

  $MAX_BYTES = 10 * 1024 * 1024;

  // 1) Tamaño
  if ($size > 0 && $size <= $MAX_BYTES) {
    $rows[] = build_row('Tamaño del archivo', 'OK', 'Tamaño: ' . number_format($size/1024, 2) . ' KB', 'BAJA', 'Continuar');
  } else {
    $rows[] = build_row('Tamaño del archivo', 'ERROR', 'Supera el máximo permitido (10MB).', 'ALTA', 'Suba un archivo más ligero.');
    $global = 'RECHAZADO';
  }

  // 2) Extensión
  $allowedExt = ['pdf','jpg','jpeg','png'];
  if (in_array($ext, $allowedExt, true)) {
    $rows[] = build_row('Tipo de archivo', 'OK', 'Extensión: .' . $ext, 'BAJA', 'Continuar');
  } else {
    $rows[] = build_row('Tipo de archivo', 'ERROR', 'Extensión no permitida: .' . $ext, 'ALTA', 'Use PDF/JPG/PNG.');
    $global = 'RECHAZADO';
  }

  // 3) MIME real
  $mime = '';
  if (class_exists('finfo')) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)($finfo->file($fs) ?: '');
  }

  $allowedMime = [
    'pdf'  => ['application/pdf'],
    'jpg'  => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png'  => ['image/png'],
  ];

  $mimeOk = $mime !== '' && isset($allowedMime[$ext]) && in_array($mime, $allowedMime[$ext], true);
  if ($mimeOk) {
    $rows[] = build_row('MIME real', 'OK', 'MIME: ' . $mime, 'BAJA', 'Continuar');
  } else {
    $rows[] = build_row('MIME real', 'ERROR', 'MIME inválido o no coincide: ' . ($mime ?: 'desconocido'), 'ALTA', 'Suba un archivo válido.');
    $global = 'RECHAZADO';
  }

  // 4) Integridad básica
  if ($ext === 'pdf') {
    $head = '';
    $fh = @fopen($fs, 'rb');
    if ($fh) {
      $head = (string)fread($fh, 5);
      fclose($fh);
    }
    if ($head === '%PDF-') {
      $rows[] = build_row('Integridad PDF', 'OK', 'Cabecera PDF correcta.', 'BAJA', 'Continuar');
    } else {
      $rows[] = build_row('Integridad PDF', 'ERROR', 'El PDF parece corrupto o no es PDF real.', 'ALTA', 'Re-subir el documento.');
      $global = 'RECHAZADO';
    }
  } else {
    $info = @getimagesize($fs);
    if ($info !== false) {
      $rows[] = build_row('Integridad imagen', 'OK', 'Resolución: ' . $info[0] . '×' . $info[1], 'BAJA', 'Continuar');
      if ($info[0] < 800 || $info[1] < 800) {
        $rows[] = build_row('Calidad (resolución)', 'OBS', 'Resolución baja: puede afectar lectura/IA.', 'MEDIA', 'Recomendado: subir una imagen más nítida.');
        if ($global !== 'RECHAZADO') $global = 'OBSERVADO';
      }
    } else {
      $rows[] = build_row('Integridad imagen', 'ERROR', 'No se puede abrir la imagen o está corrupta.', 'ALTA', 'Re-subir el documento.');
      $global = 'RECHAZADO';
    }
  }

  // 5) Huella SHA-256 (para duplicados futuros)
  $sha = @hash_file('sha256', $fs) ?: '';
  if ($sha !== '') {
    $rows[] = build_row('Huella (SHA-256)', 'OK', substr($sha, 0, 18) . '…', 'BAJA', 'Continuar');
  } else {
    $rows[] = build_row('Huella (SHA-256)', 'OBS', 'No se pudo calcular hash.', 'MEDIA', 'Continuar');
    if ($global === 'APROBADO') $global = 'OBSERVADO';
  }

  return ['global' => $global, 'rows' => $rows, 'file_name' => $name];
}

// Datos desde sesión (index.php ya bloquea el acceso sin archivo)
$tram = $_SESSION['tramite_tmp'] ?? [];
$rep  = validate_file($tram);

// Flash (si existiera)
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

function status_color(string $s): string {
  return $s === 'RECHAZADO' ? '#b91c1c' : ($s === 'OBSERVADO' ? '#b45309' : '#15803d');
}
?>
<div class="mk-page">
  <div class="mk-wrap">

    <!-- Header -->
    <div class="mk-header">
      <div class="mk-logo">
        <img src="img/junin_logo.png" alt="Junín"
             onerror="this.style.display='none'; document.getElementById('logoFallbackV').style.display='flex';">
        <div id="logoFallbackV" class="mk-logo-fallback" style="display:none;">JUNÍN</div>
      </div>
      <div class="mk-title">Sistema de Detección de Fraude en Trámites</div>
    </div>

    <!-- Tabs -->
    <div class="mk-tabsbar">
      <a class="mk-tab <?= is_active($url,'home') ?>" href="index.php?url=home">Inicio</a>
      <a class="mk-tab is-active" href="index.php?url=tramites.registrar">Análisis de Datos</a>
      <a class="mk-tab <?= is_active($url,'alertas.listar') ?>" href="index.php?url=alertas.listar">Alertas de Fraude</a>
      <a class="mk-tab <?= is_active($url,'dashboard') ?>" href="index.php?url=dashboard">Visualización</a>
      <a class="mk-tab <?= is_active($url,'contacto') ?>" href="index.php?url=contacto">Contacto</a>
    </div>

    <!-- Quote -->
    <div class="mk-box mk-quote mt-12">
      “Componente de IA que analiza grandes volúmenes de datos de los trámites para identificar patrones anómalos que podrían indicar fraude.”
    </div>

    <?php if ($flash && is_array($flash)): ?>
      <div class="mk-box" style="margin-top:12px;padding:12px;border-color:#b91c1c;">
        <?= htmlspecialchars($flash['msg'] ?? '') ?>
      </div>
    <?php endif; ?>

    <!-- Pantalla Validar (mockup) -->
    <div class="mk-stage">
      <!-- Sidebar -->
      <div class="mk-stage-sidebar">
        <a class="mk-side-item" href="index.php?url=tramites.registrar">📝 Registrar Trámite</a>
        <a class="mk-side-item is-active" href="index.php?url=tramites.validar">✅ Validar Datos</a>
        <a class="mk-side-item" href="index.php?url=tramites.analizar">🤖 Analizar Trámite IA</a>
        <a class="mk-side-item" href="index.php?url=tramites.informe">📄 Informe de Trámites</a>
      </div>

      <!-- Main -->
      <div class="mk-stage-main">

        <div class="mk-stage-msg" style="margin-bottom:12px;">
          <b>Validación del documento o trámite</b><br>
          Documento: <b><?= htmlspecialchars($rep['file_name'] ?? '') ?></b><br>
          Estado:
          <span style="font-weight:700;color:<?= status_color($rep['global']) ?>;">
            <?= htmlspecialchars($rep['global']) ?>
          </span>
        </div>

        <!-- Reporte tipo tabla -->
        <div style="overflow:auto;">
          <table style="width:100%; border-collapse:collapse; font-size:13px;">
            <thead>
              <tr>
                <th style="text-align:left; padding:8px; border-bottom:1px solid rgba(17,24,39,.25);">Regla</th>
                <th style="text-align:left; padding:8px; border-bottom:1px solid rgba(17,24,39,.25); width:90px;">Resultado</th>
                <th style="text-align:left; padding:8px; border-bottom:1px solid rgba(17,24,39,.25);">Detalle</th>
                <th style="text-align:left; padding:8px; border-bottom:1px solid rgba(17,24,39,.25); width:90px;">Severidad</th>
                <th style="text-align:left; padding:8px; border-bottom:1px solid rgba(17,24,39,.25);">Acción</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach (($rep['rows'] ?? []) as $r): ?>
                <?php
                  $res = $r['resultado'] ?? '';
                  $color = ($res === 'ERROR') ? '#b91c1c' : (($res === 'OBS') ? '#b45309' : '#15803d');
                ?>
                <tr>
                  <td style="padding:8px; border-bottom:1px solid rgba(17,24,39,.12);"><?= htmlspecialchars($r['regla'] ?? '') ?></td>
                  <td style="padding:8px; border-bottom:1px solid rgba(17,24,39,.12); font-weight:700; color:<?= $color ?>;">
                    <?= htmlspecialchars($res) ?>
                  </td>
                  <td style="padding:8px; border-bottom:1px solid rgba(17,24,39,.12);"><?= htmlspecialchars($r['detalle'] ?? '') ?></td>
                  <td style="padding:8px; border-bottom:1px solid rgba(17,24,39,.12);"><?= htmlspecialchars($r['sev'] ?? '') ?></td>
                  <td style="padding:8px; border-bottom:1px solid rgba(17,24,39,.12);"><?= htmlspecialchars($r['accion'] ?? '') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

      </div>
    </div>

    <!-- Botones abajo como mockup -->
    <div class="mk-stage-actions">
      <a class="mk-btn" href="index.php?url=tramites.registrar">Registrar Trámite</a>
      <a class="mk-btn" href="index.php?url=tramites.analizar">Analizar Trámite con IA</a>
    </div>

  </div>
</div>
