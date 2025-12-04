<?php
// src/capaVista/alertas/generar.php
$url = $_GET['url'] ?? 'alertas.generar';

function is_active(string $current, string $route): string { return $current === $route ? 'is-active' : ''; }
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function risk_level(int $score): string {
  if ($score >= 70) return 'ALTO';
  if ($score >= 35) return 'MEDIO';
  return 'BAJO';
}
function risk_color(string $nivel): string {
  return match ($nivel) {
    'ALTO' => '#b91c1c',
    'MEDIO' => '#b45309',
    default => '#15803d',
  };
}
function mk_id(string $prefix): string {
  return $prefix . '-' . str_pad((string)random_int(1, 999999), 6, '0', STR_PAD_LEFT);
}

if (!isset($_SESSION['alertas'])) $_SESSION['alertas'] = [];
if (!isset($_SESSION['casos'])) $_SESSION['casos'] = [];

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$ultimo = $_SESSION['tramite_tmp'] ?? null;

$demo_tramites = [
  ['id'=>'T-000120','dni'=>'71234567','tipo'=>'Solicitud','doc'=>'DNI','fecha'=>'20/10/2025 10:15','score'=>22,'estado_validacion'=>'APROBADO'],
  ['id'=>'T-000121','dni'=>'42111222','tipo'=>'Licencia','doc'=>'Recibo','fecha'=>'20/10/2025 12:10','score'=>48,'estado_validacion'=>'APROBADO'],
  ['id'=>'T-000122','dni'=>'88990011','tipo'=>'Permiso','doc'=>'Declaración','fecha'=>'21/10/2025 09:05','score'=>79,'estado_validacion'=>'APROBADO'],
];

if (is_array($ultimo) && !empty($ultimo)) {
  $dni = preg_replace('/\D+/', '', (string)($ultimo['dni'] ?? ''));
  $tipoT = (string)($ultimo['tipo_tramite'] ?? '—');
  $tipoD = (string)($ultimo['tipo_documento'] ?? '—');
  $fileN = (string)($ultimo['file']['original_name'] ?? 'documento');
  $fs    = (string)($ultimo['file']['path_fs'] ?? '');
  $sha   = ($fs && is_file($fs)) ? (string)(hash_file('sha256', $fs) ?: '') : '';
  $seed  = $sha ? hexdec(substr($sha, 0, 8)) : random_int(1,99999999);
  $score = 15 + ($seed % 71);
  $score = max(0, min(100, (int)$score));

  array_unshift($demo_tramites, [
    'id' => 'T-ULTIMO',
    'dni' => ($dni ?: '—'),
    'tipo' => $tipoT,
    'doc' => $tipoD,
    'fecha' => date('d/m/Y H:i'),
    'score' => $score,
    'estado_validacion' => ($ultimo['validado'] ?? false) ? 'APROBADO' : 'PENDIENTE',
    'archivo' => $fileN
  ]);
}

$selected_id = $_GET['tid'] ?? $demo_tramites[0]['id'];

$selected = null;
foreach ($demo_tramites as $t) {
  if ($t['id'] === $selected_id) { $selected = $t; break; }
}
if (!$selected) $selected = $demo_tramites[0];

$nivel = risk_level((int)$selected['score']);
$col   = risk_color($nivel);

$signals = [
  ['senal'=>'Coherencia de campos','valor'=>'OK','impacto'=>'Bajo'],
  ['senal'=>'Calidad del documento','valor'=>'Media','impacto'=>'Medio'],
  ['senal'=>'Duplicidad / similitud','valor'=>'Baja','impacto'=>'Bajo'],
  ['senal'=>'Patrones anómalos','valor'=>($nivel==='ALTO'?'Detectado':'No detectado'),'impacto'=>($nivel==='ALTO'?'Alto':'Bajo')],
];

$motivos_sugeridos = [
  'Inconsistencia en datos (DNI / tipo de trámite / documento)',
  'Calidad de documento insuficiente',
  'Posible duplicidad / reingreso',
  'Patrón anómalo detectado por reglas',
  'Patrón anómalo detectado por IA (referencial)',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['op'] ?? '') === 'crear_alerta') {
  $tid = (string)($_POST['tid'] ?? '');
  $sev = (string)($_POST['severidad'] ?? 'Media');
  $tipo = (string)($_POST['tipo_alerta'] ?? 'Patrón');
  $motivo = (string)($_POST['motivo'] ?? '');
  $desc = trim((string)($_POST['descripcion'] ?? ''));
  $auditor = trim((string)($_POST['auditor'] ?? ''));
  $sla = trim((string)($_POST['sla'] ?? ''));

  if ($motivo === '') {
    $_SESSION['flash'] = ['type'=>'error', 'msg'=>'Seleccione un motivo para generar la alerta.'];
    header('Location: index.php?url=alertas.generar&tid='.urlencode($tid)); exit;
  }

  $aid = mk_id('AL');
  $_SESSION['alertas'][] = [
    'id' => $aid,
    'tramite_id' => $tid,
    'dni' => (string)($_POST['dni'] ?? ''),
    'tipo_tramite' => (string)($_POST['tipo_tramite'] ?? ''),
    'fecha' => date('d/m/Y H:i'),
    'score' => (int)($_POST['score'] ?? 0),
    'nivel' => (string)($_POST['nivel'] ?? 'BAJO'),
    'severidad' => $sev,
    'tipo_alerta' => $tipo,
    'motivo' => $motivo,
    'descripcion' => $desc,
    'auditor' => $auditor,
    'sla' => $sla,
    'estado' => 'PENDIENTE',
  ];

  $_SESSION['flash'] = ['type'=>'ok', 'msg'=>"Alerta creada: {$aid} (estado: PENDIENTE)."];
  header('Location: index.php?url=alertas.generar&tid='.urlencode($tid)); exit;
}
?>
<div class="mk-page">
  <div class="mk-wrap">

    <div class="mk-header">
      <div class="mk-logo">
        <img src="img/junin_logo.png" alt="Junín"
             onerror="this.style.display='none'; document.getElementById('logoFallbackAG').style.display='flex';">
        <div id="logoFallbackAG" class="mk-logo-fallback" style="display:none;">JUNÍN</div>
      </div>
      <div class="mk-title">Sistema de Detección de Fraude en Trámites</div>
    </div>

    <div class="mk-tabsbar">
      <a class="mk-tab <?= is_active($url,'home') ?>" href="index.php?url=home">Inicio</a>
      <a class="mk-tab <?= is_active($url,'tramites.registrar') ?>" href="index.php?url=tramites.registrar">Análisis de Datos</a>
      <a class="mk-tab is-active" href="index.php?url=alertas.generar">Alertas de Fraude</a>
      <a class="mk-tab <?= is_active($url,'dashboard') ?>" href="index.php?url=dashboard">Visualización</a>
      <a class="mk-tab <?= is_active($url,'contacto') ?>" href="index.php?url=contacto">Contacto</a>
    </div>

    <div class="mk-box mk-quote mt-12">
      “Componente de IA que analiza grandes volúmenes de datos de los trámites para identificar patrones anómalos que podrían indicar fraude.”
    </div>

    <?php if ($flash): ?>
      <div class="mk-box" style="margin-top:12px;padding:10px;border-color:<?= $flash['type']==='error'?'#b91c1c':'rgba(17,24,39,.35)' ?>;">
        <b style="color:<?= $flash['type']==='error'?'#b91c1c':'#15803d' ?>;">
          <?= $flash['type']==='error'?'Error:':'OK:' ?>
        </b>
        <?= h($flash['msg']) ?>
      </div>
    <?php endif; ?>

    <div class="mk-stage">
      <div class="mk-stage-sidebar">
        <a class="mk-side-item is-active" href="index.php?url=alertas.generar">🚨 Generar alerta de fraude</a>
        <a class="mk-side-item" href="index.php?url=alertas.caso">🗂️ Gestionar caso de investigación</a>
      </div>

      <div class="mk-stage-main">
        <div class="mk-stage-msg"><b>Generar alerta de fraude</b></div>

        <div class="mk-box" style="margin-top:12px;padding:12px;">
          <b>Seleccionar trámite</b>
          <div style="overflow:auto;margin-top:10px;">
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
              <thead>
                <tr>
                  <th style="text-align:left;padding:8px;border-bottom:1px solid rgba(17,24,39,.25);">ID</th>
                  <th style="text-align:left;padding:8px;border-bottom:1px solid rgba(17,24,39,.25);">DNI</th>
                  <th style="text-align:left;padding:8px;border-bottom:1px solid rgba(17,24,39,.25);">Tipo</th>
                  <th style="text-align:left;padding:8px;border-bottom:1px solid rgba(17,24,39,.25);">Doc</th>
                  <th style="text-align:left;padding:8px;border-bottom:1px solid rgba(17,24,39,.25);">Fecha</th>
                  <th style="text-align:left;padding:8px;border-bottom:1px solid rgba(17,24,39,.25);width:90px;">Score</th>
                  <th style="text-align:left;padding:8px;border-bottom:1px solid rgba(17,24,39,.25);width:120px;">Validación</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($demo_tramites as $t): ?>
                  <?php $isSel = ($t['id'] === $selected['id']); ?>
                  <tr style="<?= $isSel ? 'background:rgba(29,78,216,.10);' : '' ?>">
                    <td style="padding:8px;border-bottom:1px solid rgba(17,24,39,.12);">
                      <a href="index.php?url=alertas.generar&tid=<?= urlencode($t['id']) ?>" style="text-decoration:none;color:#111;">
                        <?= h($t['id']) ?>
                      </a>
                    </td>
                    <td style="padding:8px;border-bottom:1px solid rgba(17,24,39,.12);"><?= h($t['dni']) ?></td>
                    <td style="padding:8px;border-bottom:1px solid rgba(17,24,39,.12);"><?= h($t['tipo']) ?></td>
                    <td style="padding:8px;border-bottom:1px solid rgba(17,24,39,.12);"><?= h($t['doc']) ?></td>
                    <td style="padding:8px;border-bottom:1px solid rgba(17,24,39,.12);"><?= h($t['fecha']) ?></td>
                    <td style="padding:8px;border-bottom:1px solid rgba(17,24,39,.12);font-weight:700;"><?= (int)$t['score'] ?></td>
                    <td style="padding:8px;border-bottom:1px solid rgba(17,24,39,.12);"><?= h($t['estado_validacion']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div style="margin-top:10px;font-size:12.5px;color:#334155;">
            Consejo: en producción solo se mostrarán trámites <b>validados</b> y sin alerta previa.
          </div>
        </div>

        <div class="mk-box" style="margin-top:12px;padding:12px;">
          <b>Resumen de riesgo</b>
          <div style="margin-top:8px;font-size:13px;">
            <div><b>ID Trámite:</b> <?= h($selected['id']) ?></div>
            <div><b>DNI:</b> <?= h($selected['dni']) ?> &nbsp; | &nbsp; <b>Tipo:</b> <?= h($selected['tipo']) ?> &nbsp; | &nbsp; <b>Documento:</b> <?= h($selected['doc']) ?></div>
            <div style="margin-top:6px;">
              <b>Nivel:</b> <span style="font-weight:700;color:<?= h($col) ?>;"><?= h($nivel) ?></span>
              &nbsp; <span style="color:#334155;">(<?= (int)$selected['score'] ?>/100)</span>
            </div>
          </div>

          <div style="margin-top:10px;">
            <b>Señales detectadas</b>
            <div style="overflow:auto;margin-top:8px;">
              <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead>
                  <tr>
                    <th style="text-align:left;padding:8px;border-bottom:1px solid rgba(17,24,39,.25);">Señal</th>
                    <th style="text-align:left;padding:8px;border-bottom:1px solid rgba(17,24,39,.25);">Valor</th>
                    <th style="text-align:left;padding:8px;border-bottom:1px solid rgba(17,24,39,.25);">Impacto</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($signals as $s): ?>
                    <tr>
                      <td style="padding:8px;border-bottom:1px solid rgba(17,24,39,.12);"><?= h($s['senal']) ?></td>
                      <td style="padding:8px;border-bottom:1px solid rgba(17,24,39,.12);"><?= h($s['valor']) ?></td>
                      <td style="padding:8px;border-bottom:1px solid rgba(17,24,39,.12);"><?= h($s['impacto']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="mk-box" style="margin-top:12px;padding:12px;">
          <b>Crear alerta</b>

          <form method="post" action="index.php?url=alertas.generar&tid=<?= urlencode($selected['id']) ?>" style="margin-top:10px;">
            <input type="hidden" name="op" value="crear_alerta">
            <input type="hidden" name="tid" value="<?= h($selected['id']) ?>">
            <input type="hidden" name="dni" value="<?= h($selected['dni']) ?>">
            <input type="hidden" name="tipo_tramite" value="<?= h($selected['tipo']) ?>">
            <input type="hidden" name="score" value="<?= (int)$selected['score'] ?>">
            <input type="hidden" name="nivel" value="<?= h($nivel) ?>">

            <div style="display:grid;grid-template-columns:200px 1fr;gap:10px;align-items:center;max-width:820px;">
              <label style="font-size:13px;text-align:right;">Severidad:</label>
              <select name="severidad" style="padding:6px 8px;border:1px solid #777;font-size:13px;">
                <option <?= ($nivel==='ALTO')?'selected':'' ?>>Alta</option>
                <option <?= ($nivel==='MEDIO')?'selected':'' ?>>Media</option>
                <option <?= ($nivel==='BAJO')?'selected':'' ?>>Baja</option>
              </select>

              <label style="font-size:13px;text-align:right;">Tipo de alerta:</label>
              <select name="tipo_alerta" style="padding:6px 8px;border:1px solid #777;font-size:13px;">
                <option>Documento</option>
                <option>Datos</option>
                <option selected>Patrón</option>
                <option>Reincidencia</option>
              </select>

              <label style="font-size:13px;text-align:right;">Motivo:</label>
              <select name="motivo" style="padding:6px 8px;border:1px solid #777;font-size:13px;">
                <option value="">Seleccione motivo</option>
                <?php foreach ($motivos_sugeridos as $m): ?>
                  <option value="<?= h($m) ?>"><?= h($m) ?></option>
                <?php endforeach; ?>
              </select>

              <label style="font-size:13px;text-align:right;">Descripción:</label>
              <textarea name="descripcion" rows="3" style="padding:6px 8px;border:1px solid #777;font-size:13px;resize:vertical;"></textarea>

              <label style="font-size:13px;text-align:right;">Auditor asignado (opcional):</label>
              <input type="text" name="auditor" placeholder="Ej: Auditor Ana" style="padding:6px 8px;border:1px solid #777;font-size:13px;">

              <label style="font-size:13px;text-align:right;">SLA / Fecha límite (opcional):</label>
              <input type="text" name="sla" placeholder="Ej: 48h / 25-10-2025" style="padding:6px 8px;border:1px solid #777;font-size:13px;">
            </div>

            <div style="margin-top:12px;display:flex;gap:10px;justify-content:flex-end;">
              <button class="mk-btn" type="submit" style="cursor:pointer;">Generar Alerta</button>
            </div>
          </form>
        </div>

        <div class="mk-box" style="margin-top:12px;padding:12px;">
          <b>Alertas creadas (DEMO)</b>
          <div style="overflow:auto;margin-top:10px;">
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
              <thead>
                <tr>
                  <th style="text-align:left;padding:8px;border-bottom:1px solid rgba(17,24,39,.25);">ID Alerta</th>
                  <th style="text-align:left;padding:8px;border-bottom:1px solid rgba(17,24,39,.25);">Trámite</th>
                  <th style="text-align:left;padding:8px;border-bottom:1px solid rgba(17,24,39,.25);">Severidad</th>
                  <th style="text-align:left;padding:8px;border-bottom:1px solid rgba(17,24,39,.25);">Nivel</th>
                  <th style="text-align:left;padding:8px;border-bottom:1px solid rgba(17,24,39,.25);">Estado</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$_SESSION['alertas']): ?>
                  <tr><td colspan="5" style="padding:10px;color:#334155;">Aún no hay alertas.</td></tr>
                <?php else: ?>
                  <?php foreach (array_reverse($_SESSION['alertas']) as $a): ?>
                    <tr>
                      <td style="padding:8px;border-bottom:1px solid rgba(17,24,39,.12);"><?= h($a['id']) ?></td>
                      <td style="padding:8px;border-bottom:1px solid rgba(17,24,39,.12);"><?= h($a['tramite_id']) ?></td>
                      <td style="padding:8px;border-bottom:1px solid rgba(17,24,39,.12);"><?= h($a['severidad']) ?></td>
                      <td style="padding:8px;border-bottom:1px solid rgba(17,24,39,.12);"><?= h($a['nivel']) ?> (<?= (int)$a['score'] ?>/100)</td>
                      <td style="padding:8px;border-bottom:1px solid rgba(17,24,39,.12);"><?= h($a['estado']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

      </div>
    </div>

    <div class="mk-stage-actions">
      <a class="mk-btn" href="index.php?url=home">Inicio</a>
      <a class="mk-btn" href="index.php?url=alertas.caso">SIGUIENTE</a>
    </div>

  </div>
</div>
