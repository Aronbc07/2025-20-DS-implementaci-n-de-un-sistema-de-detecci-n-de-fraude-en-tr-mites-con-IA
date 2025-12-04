<?php
// src/capaVista/tramites/analizar.php

$url = $_GET['url'] ?? 'tramites.analizar';
function is_active(string $current, string $route): string { return $current === $route ? 'is-active' : ''; }

/** Helpers */
function only_digits(string $s): string { return preg_replace('/\D+/', '', $s); }
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function risk_level(int $score): string {
  if ($score >= 70) return 'ALTO';
  if ($score >= 35) return 'MEDIO';
  return 'BAJO';
}

function risk_color(string $level): string {
  return match ($level) {
    'ALTO' => '#b91c1c',
    'MEDIO' => '#b45309',
    default => '#15803d',
  };
}

/**
 * Validación mínima para permitir análisis IA.
 * (En producción esto vendrá de "Validar Datos" guardado en BD; por ahora se recalcula en sesión)
 */
function can_analyze(array $t, array &$errors): bool {
  $dni = only_digits((string)($t['dni'] ?? ''));
  if (strlen($dni) !== 8) $errors[] = 'DNI inválido (debe tener 8 dígitos).';

  if (empty($t['tipo_tramite'])) $errors[] = 'Seleccione el tipo de trámite.';
  if (empty($t['tipo_documento'])) $errors[] = 'Seleccione el tipo de documento.';

  $fs = (string)($t['file']['path_fs'] ?? '');
  if ($fs === '' || !is_file($fs)) $errors[] = 'Agregue un documento antes de analizar con IA.';

  // checks básicos del archivo
  if ($fs !== '' && is_file($fs)) {
    $ext  = strtolower((string)($t['file']['ext'] ?? pathinfo($fs, PATHINFO_EXTENSION)));
    $size = (int)($t['file']['size'] ?? filesize($fs));
    $MAX_BYTES = 10 * 1024 * 1024;

    if ($size <= 0) $errors[] = 'No se pudo leer el tamaño del archivo.';
    if ($size > $MAX_BYTES) $errors[] = 'Archivo demasiado grande (máx 10MB).';

    $allowedExt = ['pdf','jpg','jpeg','png'];
    if (!in_array($ext, $allowedExt, true)) $errors[] = 'Tipo de archivo no permitido (use PDF/JPG/PNG).';

    // MIME real
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
    if ($mime !== '' && isset($allowedMime[$ext]) && !in_array($mime, $allowedMime[$ext], true)) {
      $errors[] = 'El MIME real no coincide con el tipo de archivo.';
    }

    // integridad mínima
    if ($ext === 'pdf') {
      $head = '';
      $fh = @fopen($fs, 'rb');
      if ($fh) { $head = (string)fread($fh, 5); fclose($fh); }
      if ($head !== '%PDF-') $errors[] = 'El PDF parece corrupto o no es PDF real.';
    } else {
      if (@getimagesize($fs) === false) $errors[] = 'La imagen parece corrupta o no se puede abrir.';
    }
  }

  return count($errors) === 0;
}

/**
 * Análisis IA (DEMO): score reproducible con hash + hallazgos explicables.
 * Luego lo reemplazaremos por llamada al motor Python.
 */
function analyze_demo(array $t): array {
  $fs = (string)$t['file']['path_fs'];
  $ext = strtolower((string)($t['file']['ext'] ?? pathinfo($fs, PATHINFO_EXTENSION)));

  $sha = @hash_file('sha256', $fs) ?: '';
  $seed = $sha ? hexdec(substr($sha, 0, 8)) : random_int(1, 99999999);

  // score base 15..85 reproducible
  $base = 15 + ($seed % 71);

  $hallazgos = [];
  $evidencias = [];

  // Señales (demo) según archivo
  $size = (int)($t['file']['size'] ?? filesize($fs));
  if ($size > 4 * 1024 * 1024) {
    $base += 8;
    $hallazgos[] = 'Documento pesado: puede indicar escaneo repetido o baja compresión.';
    $evidencias[] = ['Señal'=>'Tamaño', 'Valor'=>number_format($size/1024/1024,2).' MB', 'Impacto'=>'+8'];
  } else {
    $evidencias[] = ['Señal'=>'Tamaño', 'Valor'=>number_format($size/1024,2).' KB', 'Impacto'=>'+0'];
  }

  if ($ext !== 'pdf') {
    $info = @getimagesize($fs);
    if (is_array($info)) {
      $w = (int)$info[0]; $h = (int)$info[1];
      if ($w < 900 || $h < 900) {
        $base += 10;
        $hallazgos[] = 'Resolución baja: la lectura del contenido puede perder precisión.';
        $evidencias[] = ['Señal'=>'Resolución', 'Valor'=>"$w×$h", 'Impacto'=>'+10'];
      } else {
        $evidencias[] = ['Señal'=>'Resolución', 'Valor'=>"$w×$h", 'Impacto'=>'+0'];
      }
    }
  }

  // Señales (demo) según datos del trámite
  $dni = preg_replace('/\D+/', '', (string)$t['dni']);
  if ($dni !== '' && ((int)substr($dni, -1) % 2) === 0) {
    $base += 6; // demo
    $hallazgos[] = 'Patrón estadístico atípico (señal demo para pruebas).';
    $evidencias[] = ['Señal'=>'Patrón', 'Valor'=>'Atípico', 'Impacto'=>'+6'];
  } else {
    $evidencias[] = ['Señal'=>'Patrón', 'Valor'=>'Normal', 'Impacto'=>'+0'];
  }

  // Score final y nivel
  $score = max(0, min(100, (int)$base));
  $nivel = risk_level($score);

  // Hallazgos por nivel
  if ($nivel === 'ALTO') {
    $hallazgos[] = 'Riesgo alto: se recomienda generar alerta y revisión por auditor.';
  } elseif ($nivel === 'MEDIO') {
    $hallazgos[] = 'Riesgo medio: se recomienda revisión manual antes de aprobación.';
  } else {
    $hallazgos[] = 'Riesgo bajo: no se detectan señales relevantes en esta etapa.';
  }

  if (!$sha) $sha = '(no disponible)';

  return [
    'score' => $score,
    'nivel' => $nivel,
    'sha'   => $sha,
    'hallazgos' => $hallazgos,
    'evidencias' => $evidencias,
  ];
}

/** Datos desde sesión */
$tram = $_SESSION['tramite_tmp'] ?? [];
$errors = [];
$allowed = is_array($tram) ? can_analyze($tram, $errors) : false;

$analysis = $allowed ? analyze_demo($tram) : null;
?>
<div class="mk-page">
  <div class="mk-wrap">

    <!-- Header -->
    <div class="mk-header">
      <div class="mk-logo">
        <img src="img/junin_logo.png" alt="Junín"
             onerror="this.style.display='none'; document.getElementById('logoFallbackA').style.display='flex';">
        <div id="logoFallbackA" class="mk-logo-fallback" style="display:none;">JUNÍN</div>
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

    <!-- Stage (mockup) -->
    <div class="mk-stage">
      <!-- Sidebar -->
      <div class="mk-stage-sidebar">
        <a class="mk-side-item" href="index.php?url=tramites.registrar">📝 Registrar Trámite</a>
        <a class="mk-side-item" href="index.php?url=tramites.validar">✅ Validar Datos</a>
        <a class="mk-side-item is-active" href="index.php?url=tramites.analizar">🤖 Analizar Trámite</a>
        <a class="mk-side-item" href="index.php?url=tramites.informe">📄 Informe de Trámites</a>
      </div>

      <!-- Main -->
      <div class="mk-stage-main">
        <div class="mk-stage-msg">
          <b>Analizando trámite con IA para detectar posible fraude</b>
        </div>

        <div style="margin-top:14px;">
          <?php if (!$allowed): ?>
            <div class="mk-box" style="padding:12px;border-color:#b91c1c;">
              <b style="color:#b91c1c;">No se puede analizar con IA.</b><br>
              <?php foreach ($errors as $e): ?>
                • <?= h($e) ?><br>
              <?php endforeach; ?>
              <div style="margin-top:10px;">
                <a class="mk-btn" href="index.php?url=tramites.registrar">Volver a Registrar</a>
                <a class="mk-btn" href="index.php?url=tramites.validar" style="margin-left:8px;">Ir a Validar Datos</a>
              </div>
            </div>

          <?php else: ?>
            <!-- Resumen IA -->
            <?php
              $nivel = $analysis['nivel'];
              $score = (int)$analysis['score'];
              $color = risk_color($nivel);
            ?>
            <div class="mk-box" style="padding:14px;">
              <div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap;">
                <div style="font-size:14px;">
                  <b>Resultado IA (demo):</b>
                </div>
                <div style="padding:6px 10px;border:1px solid rgba(0,0,0,.25);border-radius:10px;">
                  <span style="font-weight:700;color:<?= h($color) ?>;">RIESGO <?= h($nivel) ?></span>
                  <span style="color:#111;">(<?= h($score) ?>/100)</span>
                </div>
                <div style="font-size:12px;color:#334155;">
                  Hash: <?= h(substr((string)$analysis['sha'], 0, 18)) ?>…
                </div>
              </div>

              <div style="margin-top:10px;">
                <b>Hallazgos:</b>
                <ul style="margin:8px 0 0 18px;">
                  <?php foreach ($analysis['hallazgos'] as $hzg): ?>
                    <li><?= h($hzg) ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
            </div>

            <!-- Reporte tipo tabla -->
            <div class="mk-box" style="margin-top:12px;padding:12px;">
              <b>Reporte de señales (resumen)</b>
              <div style="overflow:auto;margin-top:10px;">
                <table style="width:100%; border-collapse:collapse; font-size:13px;">
                  <thead>
                    <tr>
                      <th style="text-align:left; padding:8px; border-bottom:1px solid rgba(17,24,39,.25);">Señal</th>
                      <th style="text-align:left; padding:8px; border-bottom:1px solid rgba(17,24,39,.25);">Valor</th>
                      <th style="text-align:left; padding:8px; border-bottom:1px solid rgba(17,24,39,.25); width:90px;">Impacto</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($analysis['evidencias'] as $ev): ?>
                      <tr>
                        <td style="padding:8px; border-bottom:1px solid rgba(17,24,39,.12);"><?= h($ev['Señal']) ?></td>
                        <td style="padding:8px; border-bottom:1px solid rgba(17,24,39,.12);"><?= h($ev['Valor']) ?></td>
                        <td style="padding:8px; border-bottom:1px solid rgba(17,24,39,.12); font-weight:700;"><?= h($ev['Impacto']) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              <div style="margin-top:10px;font-size:12.5px;color:#334155;">
                *Este análisis es DEMO (reglas/score). Más adelante se conectará el motor Python para IA real.
              </div>
            </div>
          <?php endif; ?>
        </div>

      </div>
    </div>

    <!-- Botones abajo como mockup -->
    <div class="mk-stage-actions">
      <a class="mk-btn" href="index.php?url=tramites.validar">Validar Nuevamente</a>

      <?php if ($allowed): ?>
        <a class="mk-btn" href="index.php?url=tramites.informe">Generar Informe</a>
      <?php else: ?>
        <a class="mk-btn" href="#" style="opacity:.45; pointer-events:none;">Generar Informe</a>
      <?php endif; ?>
    </div>

  </div>
</div>
