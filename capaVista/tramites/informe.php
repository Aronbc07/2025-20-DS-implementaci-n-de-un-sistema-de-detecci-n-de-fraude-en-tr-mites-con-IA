<?php
// src/capaVista/tramites/informe.php

$url = $_GET['url'] ?? 'tramites.informe';
function is_active(string $current, string $route): string { return $current === $route ? 'is-active' : ''; }
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function to_int($v, $def=0): int { return is_numeric($v) ? (int)$v : $def; }

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

/** Genera un ID demo */
function demo_id(): string {
  return 'T' . str_pad((string)random_int(1,9999), 4, '0', STR_PAD_LEFT);
}

/** Genera dataset DEMO */
function demo_rows(array $latest = null): array {
  $rows = [];

  // Si existe última sesión, lo incluimos como primer registro (para que el usuario lo vea)
  if (is_array($latest) && !empty($latest)) {
    $dni = preg_replace('/\D+/', '', (string)($latest['dni'] ?? ''));
    $tipoT = (string)($latest['tipo_tramite'] ?? '—');
    $tipoD = (string)($latest['tipo_documento'] ?? '—');
    $fileN = (string)($latest['file']['original_name'] ?? 'documento');
    $sha   = '';
    $fs    = (string)($latest['file']['path_fs'] ?? '');
    if ($fs && is_file($fs)) $sha = (string)(hash_file('sha256', $fs) ?: '');
    $seed  = $sha ? hexdec(substr($sha, 0, 8)) : random_int(1,99999999);
    $score = 15 + ($seed % 71);
    $score = max(0, min(100, (int)$score));
    $nivel = risk_level($score);

    $rows[] = [
      'id' => 'TULT-0001',
      'fecha' => date('d/m/Y H:i'),
      'dni' => $dni ?: '—',
      'tipo_tramite' => $tipoT,
      'tipo_doc' => $tipoD,
      'archivo' => $fileN,
      'validacion' => 'APROBADO',
      'score' => $score,
      'nivel' => $nivel,
      'estado' => ($nivel === 'ALTO') ? 'ALERTA' : 'EN REVISIÓN',
      'motivo' => ($nivel === 'ALTO')
          ? 'Señales elevadas en documento/datos (demo).'
          : 'Revisión preventiva (demo).',
    ];
  }

  // DEMO adicionales
  $base = [
    ['dni'=>'71234567','tipo_tramite'=>'Solicitud','tipo_doc'=>'DNI','archivo'=>'solicitud_001.pdf','validacion'=>'APROBADO','score'=>22,'estado'=>'APROBADO','motivo'=>'Sin señales relevantes.'],
    ['dni'=>'42111222','tipo_tramite'=>'Licencia','tipo_doc'=>'Recibo','archivo'=>'lic_044.jpg','validacion'=>'OBSERVADO','score'=>48,'estado'=>'EN REVISIÓN','motivo'=>'Calidad de imagen baja.'],
    ['dni'=>'88990011','tipo_tramite'=>'Permiso','tipo_doc'=>'Declaración','archivo'=>'permiso_009.pdf','validacion'=>'APROBADO','score'=>79,'estado'=>'ALERTA','motivo'=>'Patrón anómalo / posible duplicidad (demo).'],
    ['dni'=>'10101010','tipo_tramite'=>'Constancia','tipo_doc'=>'Formulario','archivo'=>'form_120.png','validacion'=>'RECHAZADO','score'=>0,'estado'=>'RECHAZADO','motivo'=>'Documento no válido.'],
  ];

  foreach ($base as $b) {
    $nivel = ($b['validacion']==='RECHAZADO') ? '—' : risk_level((int)$b['score']);
    $rows[] = [
      'id' => demo_id(),
      'fecha' => date('d/m/Y', strtotime('-'.random_int(0,7).' days')) . ' ' . str_pad((string)random_int(8,18),2,'0',STR_PAD_LEFT) . ':' . str_pad((string)random_int(0,59),2,'0',STR_PAD_LEFT),
      'dni' => $b['dni'],
      'tipo_tramite' => $b['tipo_tramite'],
      'tipo_doc' => $b['tipo_doc'],
      'archivo' => $b['archivo'],
      'validacion' => $b['validacion'],
      'score' => (int)$b['score'],
      'nivel' => $nivel,
      'estado' => $b['estado'],
      'motivo' => $b['motivo'],
    ];
  }

  return $rows;
}

/** Dataset + filtros (MVP) */
$latest = $_SESSION['tramite_tmp'] ?? null;
$rows = demo_rows(is_array($latest) ? $latest : null);

$f_dni   = trim((string)($_GET['dni'] ?? ''));
$f_nivel = trim((string)($_GET['nivel'] ?? ''));       // ALTO|MEDIO|BAJO
$f_estado= trim((string)($_GET['estado'] ?? ''));      // APROBADO|EN REVISIÓN|ALERTA|RECHAZADO

if ($f_dni !== '' || $f_nivel !== '' || $f_estado !== '') {
  $rows = array_values(array_filter($rows, function($r) use ($f_dni,$f_nivel,$f_estado){
    if ($f_dni !== '' && strpos((string)$r['dni'], preg_replace('/\D+/', '', $f_dni)) === false) return false;
    if ($f_nivel !== '' && (string)$r['nivel'] !== $f_nivel) return false;
    if ($f_estado !== '' && (string)$r['estado'] !== $f_estado) return false;
    return true;
  }));
}

$kpi_total = count($rows);
$kpi_alertas = count(array_filter($rows, fn($r)=>($r['estado'] ?? '')==='ALERTA'));
$kpi_revision = count(array_filter($rows, fn($r)=>($r['estado'] ?? '')==='EN REVISIÓN'));
$kpi_aprob = count(array_filter($rows, fn($r)=>($r['estado'] ?? '')==='APROBADO'));
$kpi_rech = count(array_filter($rows, fn($r)=>($r['estado'] ?? '')==='RECHAZADO'));
?>
<div class="mk-page">
  <div class="mk-wrap">

    <!-- Header -->
    <div class="mk-header">
      <div class="mk-logo">
        <img src="img/junin_logo.png" alt="Junín"
             onerror="this.style.display='none'; document.getElementById('logoFallbackI').style.display='flex';">
        <div id="logoFallbackI" class="mk-logo-fallback" style="display:none;">JUNÍN</div>
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
        <a class="mk-side-item" href="index.php?url=tramites.analizar">🤖 Analizar Trámite</a>
        <a class="mk-side-item is-active" href="index.php?url=tramites.informe">📄 Informe de Trámites</a>
      </div>

      <!-- Main -->
      <div class="mk-stage-main">

        <div class="mk-stage-msg">
          <b>Informe de Trámites (Reporte)</b>
        </div>

        <!-- KPIs simples -->
        <div class="mk-box" style="margin-top:12px;padding:12px;">
          <div style="display:flex;gap:10px;flex-wrap:wrap;font-size:13px;">
            <div><b>Total:</b> <?= (int)$kpi_total ?></div>
            <div><b style="color:#b91c1c;">Alertas:</b> <?= (int)$kpi_alertas ?></div>
            <div><b style="color:#b45309;">En revisión:</b> <?= (int)$kpi_revision ?></div>
            <div><b style="color:#15803d;">Aprobados:</b> <?= (int)$kpi_aprob ?></div>
            <div><b>Rechazados:</b> <?= (int)$kpi_rech ?></div>
          </div>
          <div style="margin-top:8px;font-size:12.5px;color:#334155;">
            *Reporte DEMO (sin base de datos aún). Luego se listará desde BD con filtros reales.
          </div>
        </div>

        <!-- Filtros -->
        <form method="get" action="index.php" class="mk-box" style="margin-top:12px;padding:12px;">
          <input type="hidden" name="url" value="tramites.informe">
          <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
            <div>
              <label style="font-size:12px;display:block;margin-bottom:6px;">DNI</label>
              <input type="text" name="dni" value="<?= h($f_dni) ?>" style="padding:6px 8px;border:1px solid #777;font-size:13px;width:180px;">
            </div>

            <div>
              <label style="font-size:12px;display:block;margin-bottom:6px;">Nivel</label>
              <select name="nivel" style="padding:6px 8px;border:1px solid #777;font-size:13px;width:160px;">
                <option value="">Todos</option>
                <option value="ALTO" <?= $f_nivel==='ALTO'?'selected':'' ?>>ALTO</option>
                <option value="MEDIO" <?= $f_nivel==='MEDIO'?'selected':'' ?>>MEDIO</option>
                <option value="BAJO" <?= $f_nivel==='BAJO'?'selected':'' ?>>BAJO</option>
              </select>
            </div>

            <div>
              <label style="font-size:12px;display:block;margin-bottom:6px;">Estado</label>
              <select name="estado" style="padding:6px 8px;border:1px solid #777;font-size:13px;width:180px;">
                <option value="">Todos</option>
                <option value="ALERTA" <?= $f_estado==='ALERTA'?'selected':'' ?>>ALERTA</option>
                <option value="EN REVISIÓN" <?= $f_estado==='EN REVISIÓN'?'selected':'' ?>>EN REVISIÓN</option>
                <option value="APROBADO" <?= $f_estado==='APROBADO'?'selected':'' ?>>APROBADO</option>
                <option value="RECHAZADO" <?= $f_estado==='RECHAZADO'?'selected':'' ?>>RECHAZADO</option>
              </select>
            </div>

            <div style="display:flex;gap:8px;">
              <button class="mk-btn" type="submit" style="cursor:pointer;">Filtrar</button>
              <a class="mk-btn" href="index.php?url=tramites.informe">Limpiar</a>
            </div>
          </div>
        </form>

        <!-- Tabla -->
        <div class="mk-box" style="margin-top:12px;padding:12px;">
          <b>Listado</b>
          <div style="overflow:auto;margin-top:10px;">
            <table style="width:100%; border-collapse:collapse; font-size:13px;">
              <thead>
                <tr>
                  <th style="text-align:left; padding:8px; border-bottom:1px solid rgba(17,24,39,.25);">ID</th>
                  <th style="text-align:left; padding:8px; border-bottom:1px solid rgba(17,24,39,.25);">Fecha</th>
                  <th style="text-align:left; padding:8px; border-bottom:1px solid rgba(17,24,39,.25);">DNI</th>
                  <th style="text-align:left; padding:8px; border-bottom:1px solid rgba(17,24,39,.25);">Trámite</th>
                  <th style="text-align:left; padding:8px; border-bottom:1px solid rgba(17,24,39,.25);">Documento</th>
                  <th style="text-align:left; padding:8px; border-bottom:1px solid rgba(17,24,39,.25);">Validación</th>
                  <th style="text-align:left; padding:8px; border-bottom:1px solid rgba(17,24,39,.25); width:90px;">Riesgo</th>
                  <th style="text-align:left; padding:8px; border-bottom:1px solid rgba(17,24,39,.25); width:110px;">Estado</th>
                  <th style="text-align:left; padding:8px; border-bottom:1px solid rgba(17,24,39,.25);">Motivo</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$rows): ?>
                  <tr>
                    <td colspan="9" style="padding:10px; color:#334155;">No hay registros para mostrar.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($rows as $r): ?>
                    <?php
                      $nivel = (string)($r['nivel'] ?? '—');
                      $color = ($nivel==='—') ? '#111' : risk_color($nivel);
                      $score = (int)($r['score'] ?? 0);
                    ?>
                    <tr>
                      <td style="padding:8px; border-bottom:1px solid rgba(17,24,39,.12);"><?= h($r['id']) ?></td>
                      <td style="padding:8px; border-bottom:1px solid rgba(17,24,39,.12);"><?= h($r['fecha']) ?></td>
                      <td style="padding:8px; border-bottom:1px solid rgba(17,24,39,.12);"><?= h($r['dni']) ?></td>
                      <td style="padding:8px; border-bottom:1px solid rgba(17,24,39,.12);"><?= h($r['tipo_tramite']) ?></td>
                      <td style="padding:8px; border-bottom:1px solid rgba(17,24,39,.12);"><?= h($r['tipo_doc']) ?> / <?= h($r['archivo']) ?></td>
                      <td style="padding:8px; border-bottom:1px solid rgba(17,24,39,.12);"><?= h($r['validacion']) ?></td>
                      <td style="padding:8px; border-bottom:1px solid rgba(17,24,39,.12); font-weight:700; color:<?= h($color) ?>;">
                        <?= h($nivel) ?>
                        <?php if ($nivel !== '—'): ?>
                          <span style="color:#334155;font-weight:400;">(<?= (int)$score ?>/100)</span>
                        <?php endif; ?>
                      </td>
                      <td style="padding:8px; border-bottom:1px solid rgba(17,24,39,.12);"><?= h($r['estado']) ?></td>
                      <td style="padding:8px; border-bottom:1px solid rgba(17,24,39,.12);"><?= h($r['motivo']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <div style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;">
            <button class="mk-btn" type="button" onclick="window.print()">Imprimir</button>
            <span style="font-size:12.5px;color:#334155;">
              Consejo: cuando tengamos BD, aquí también podrás exportar a PDF/Excel con SP.
            </span>
          </div>
        </div>

      </div>
    </div>

    <!-- Botones abajo (igual mockup: izquierda/derecha) -->
    <div class="mk-stage-actions">
      <a class="mk-btn" href="index.php?url=tramites.registrar">Registrar Trámite</a>
      <a class="mk-btn" href="index.php?url=tramites.analizar">Analizar Trámite con IA</a>
    </div>

  </div>
</div>
