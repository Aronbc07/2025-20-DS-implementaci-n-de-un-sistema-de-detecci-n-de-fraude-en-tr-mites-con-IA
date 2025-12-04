<?php
// src/capaVista/dashboard/index.php

if (session_status() === PHP_SESSION_NONE) session_start();

$url = $_GET['url'] ?? 'dashboard';

if (!function_exists('h')) {
  function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('is_active')) {
  function is_active($current, $route){ return $current === $route ? 'is-active' : ''; }
}
if (!function_exists('nivel_riesgo')) {
  function nivel_riesgo($score){
    $score = (int)$score;
    if ($score >= 80) return 'ALTO';
    if ($score >= 50) return 'MEDIO';
    return 'BAJO';
  }
}
if (!function_exists('color_nivel')) {
  function color_nivel($nivel){
    return match($nivel){
      'ALTO' => 'mk-pill mk-pill-danger',
      'MEDIO' => 'mk-pill mk-pill-warn',
      default => 'mk-pill mk-pill-ok',
    };
  }
}
if (!function_exists('mask_dni')) {
  function mask_dni($dni){
    $dni = preg_replace('/\D+/', '', (string)$dni);
    if (strlen($dni) < 8) return $dni;
    return substr($dni,0,2).'******'.substr($dni,-2);
  }
}
if (!function_exists('ymd_from_any')) {
  function ymd_from_any(string $fecha): string {
    // acepta "YYYY-MM-DD" o "dd/mm/YYYY HH:ii"
    $fecha = trim($fecha);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) return $fecha;

    // dd/mm/YYYY HH:ii
    $dt = DateTime::createFromFormat('d/m/Y H:i', $fecha);
    if ($dt) return $dt->format('Y-m-d');

    // dd/mm/YYYY
    $dt = DateTime::createFromFormat('d/m/Y', $fecha);
    if ($dt) return $dt->format('Y-m-d');

    return $fecha; // fallback
  }
}

/**
 * DEMO DATA (reemplazar por BD luego)
 * Campos: id, dni, tipo, dep, score, estado, fecha (Y-m-d), auditor, validado, alerta, caso
 */
$rows = [
  ['id'=>1001,'dni'=>'70445566','tipo'=>'Licencia','dep'=>'OTI','score'=>82,'estado'=>'Con alerta','fecha'=>'2025-10-18','auditor'=>'Ana','validado'=>1,'alerta'=>1,'caso'=>0],
  ['id'=>1002,'dni'=>'70331122','tipo'=>'Permiso','dep'=>'GRD','score'=>46,'estado'=>'Validado','fecha'=>'2025-10-18','auditor'=>'—','validado'=>1,'alerta'=>0,'caso'=>0],
  ['id'=>1003,'dni'=>'44332211','tipo'=>'Subsidio','dep'=>'GRA','score'=>91,'estado'=>'En investigación','fecha'=>'2025-10-19','auditor'=>'Luis','validado'=>1,'alerta'=>1,'caso'=>1],
  ['id'=>1004,'dni'=>'11223344','tipo'=>'Licencia','dep'=>'GRD','score'=>67,'estado'=>'Con alerta','fecha'=>'2025-10-20','auditor'=>'Ana','validado'=>1,'alerta'=>1,'caso'=>0],
  ['id'=>1005,'dni'=>'99887766','tipo'=>'Constancia','dep'=>'OTI','score'=>28,'estado'=>'Registrado','fecha'=>'2025-10-20','auditor'=>'—','validado'=>0,'alerta'=>0,'caso'=>0],
  ['id'=>1006,'dni'=>'44556677','tipo'=>'Permiso','dep'=>'GRA','score'=>55,'estado'=>'Validado','fecha'=>'2025-10-21','auditor'=>'—','validado'=>1,'alerta'=>0,'caso'=>0],
  ['id'=>1007,'dni'=>'77889900','tipo'=>'Subsidio','dep'=>'GRD','score'=>88,'estado'=>'En investigación','fecha'=>'2025-10-21','auditor'=>'Luis','validado'=>1,'alerta'=>1,'caso'=>1],
];

$deps = ['' => 'Todas', 'GRD'=>'GRD', 'GRA'=>'GRA', 'OTI'=>'OTI'];
$riesgos = ['' => 'Todos', 'ALTO'=>'ALTO', 'MEDIO'=>'MEDIO', 'BAJO'=>'BAJO'];
$estados = ['' => 'Todos', 'Registrado'=>'Registrado', 'Validado'=>'Validado', 'Con alerta'=>'Con alerta', 'En investigación'=>'En investigación', 'Cerrado'=>'Cerrado'];
$auditores = ['' => 'Todos', 'Ana'=>'Ana', 'Luis'=>'Luis', '—'=>'Sin asignar'];

// Filtros
$f_q = trim($_GET['q'] ?? '');
$f_dep = $_GET['dep'] ?? '';
$f_riesgo = $_GET['riesgo'] ?? '';
$f_estado = $_GET['estado'] ?? '';
$f_auditor = $_GET['auditor'] ?? '';
$f_from = $_GET['from'] ?? '';
$f_to = $_GET['to'] ?? '';

$filtered = array_values(array_filter($rows, function($r) use ($f_q,$f_dep,$f_riesgo,$f_estado,$f_auditor,$f_from,$f_to){
  $nivel = nivel_riesgo($r['score']);

  if ($f_dep !== '' && $r['dep'] !== $f_dep) return false;
  if ($f_riesgo !== '' && $nivel !== $f_riesgo) return false;
  if ($f_estado !== '' && $r['estado'] !== $f_estado) return false;
  if ($f_auditor !== '' && $r['auditor'] !== $f_auditor) return false;

  if ($f_from !== '' && $r['fecha'] < $f_from) return false;
  if ($f_to !== '' && $r['fecha'] > $f_to) return false;

  if ($f_q !== ''){
    $hay = false;
    $hay = $hay || stripos((string)$r['id'], $f_q) !== false;
    $hay = $hay || stripos((string)$r['dni'], $f_q) !== false;
    $hay = $hay || stripos((string)$r['tipo'], $f_q) !== false;
    $hay = $hay || stripos((string)$r['dep'], $f_q) !== false;
    if (!$hay) return false;
  }

  return true;
}));

// KPIs (según filtrado)
$tot = count($filtered);
$alto = 0; $medio = 0; $bajo = 0;
$alertas = 0; $casos = 0; $validos = 0; $pendientes = 0;

foreach($filtered as $r){
  $nivel = nivel_riesgo($r['score']);
  if ($nivel==='ALTO') $alto++;
  elseif ($nivel==='MEDIO') $medio++;
  else $bajo++;

  if (!empty($r['alerta'])) $alertas++;
  if (!empty($r['caso'])) $casos++;
  if (!empty($r['validado'])) $validos++;
  if ($r['estado'] === 'Con alerta' || $r['estado']==='En investigación') $pendientes++;
}

// ===========================
// SERIES POR DÍA (SEGÚN FILTROS)
// docs = cantidad de trámites (documentos) del día
// alertas = cantidad de trámites con alerta del día
// SOLO DÍAS CON REGISTROS
// ===========================
$docsByDate = [];
$alertsByDate = [];

foreach ($filtered as $r) {
  $d = ymd_from_any((string)$r['fecha']);
  $docsByDate[$d] = ($docsByDate[$d] ?? 0) + 1;
  if (!empty($r['alerta'])) {
    $alertsByDate[$d] = ($alertsByDate[$d] ?? 0) + 1;
  }
}

// unir claves y eliminar días vacíos (por seguridad)
$allDays = array_unique(array_merge(array_keys($docsByDate), array_keys($alertsByDate)));
sort($allDays);

$series = [];
foreach ($allDays as $d) {
  $docs = (int)($docsByDate[$d] ?? 0);
  $als  = (int)($alertsByDate[$d] ?? 0);
  if ($docs === 0 && $als === 0) continue; // ✅ no mostrar días vacíos
  $series[$d] = ['docs'=>$docs, 'alertas'=>$als];
}

$maxY = 0;
foreach ($series as $d => $v) {
  $maxY = max($maxY, (int)$v['docs'], (int)$v['alertas']);
}
if ($maxY <= 0) $maxY = 1;

function pct_h(int $v, int $maxY): int {
  if ($v <= 0) return 0;
  $p = (int)round(($v / $maxY) * 100);
  // mini altura para que se note cuando es 1
  return max(6, min(100, $p));
}

// Panel derecho
$ops = [
  ['t'=>'Pico de alertas', 'd'=>'Dependencia GRD concentró más alertas en las últimas 24 horas.'],
  ['t'=>'SLA por vencer', 'd'=>'Hay casos en investigación con más de 48 horas sin actualización.'],
  ['t'=>'Documentos ilegibles', 'd'=>'Se detectaron documentos con baja legibilidad (revisar carga).'],
];

?>
<style>
/* ====== CHART (sin salirse / sin scroll horizontal) ====== */
.mk-chart-wrap{
  border: 1px solid rgba(17,24,39,.15);
  border-radius: 16px;
  padding: 14px;
  background: linear-gradient(135deg, rgba(59,130,246,.06), rgba(45,212,191,.06));
  overflow: hidden; /* ✅ evita que se salga */
}
.mk-chart-inner{
  display:flex;
  flex-wrap:wrap;          /* ✅ que baje a otra fila */
  justify-content:flex-start;
  align-items:flex-end;
  gap: 12px;               /* ✅ junto, sin separaciones enormes */
}
.mk-day{
  width: 112px;            /* ✅ tamaño controlado (no se estira a toda pantalla) */
}
.mk-barbox{
  height: 130px;
  border: 1px solid rgba(17,24,39,.12);
  border-radius: 14px;
  background: rgba(255,255,255,.55);
  padding: 10px 10px 8px;
  display:flex;
  align-items:flex-end;
  justify-content:center;
  gap: 10px;
  overflow:hidden;
}
.mk-bar{
  width: 26px;
  border-radius: 10px;
  position: relative;
}
.mk-bar-docs{
  background: rgba(45,212,191,.35);
  border: 1px solid rgba(45,212,191,.90);
}
.mk-bar-alerts{
  background: rgba(59,130,246,.30);
  border: 1px solid rgba(59,130,246,.85);
}
.mk-day-label{
  margin-top: 8px;
  text-align:center;
  font-size: 13px;
  color:#0f172a;
  font-weight: 600;
}
.mk-day-sub{
  margin-top: 2px;
  text-align:center;
  font-size: 12px;
  color:#475569;
}
.mk-legend{
  display:flex;
  gap: 14px;
  align-items:center;
  font-size: 13px;
  color:#334155;
  margin-top: 10px;
}
.mk-dot{
  width: 10px; height: 10px; border-radius: 50%;
  display:inline-block; vertical-align:middle;
  border: 1px solid rgba(17,24,39,.25);
}
.mk-dot-docs{ background: rgba(45,212,191,.55); border-color: rgba(45,212,191,.95); }
.mk-dot-alerts{ background: rgba(59,130,246,.45); border-color: rgba(59,130,246,.95); }

/* evita que un contenido grande rompa el layout en pantallas pequeñas */
@media (max-width: 700px){
  .mk-day{ width: 100px; }
  .mk-bar{ width: 22px; }
}
</style>

<div class="mk-page">
  <div class="mk-wrap">

    <!-- Header -->
    <div class="mk-header">
      <div class="mk-logo">
        <img src="img/junin_logo.png" alt="Junín"
             onerror="this.style.display='none'; document.getElementById('logoFallbackDash').style.display='flex';">
        <div id="logoFallbackDash" class="mk-logo-fallback" style="display:none;">JUNÍN</div>
      </div>
      <div class="mk-title">Sistema de Detección de Fraude en Trámites</div>
    </div>

    <!-- Tabs -->
    <div class="mk-tabsbar">
      <a class="mk-tab <?= is_active($url,'home') ?>" href="index.php?url=home">Inicio</a>
      <a class="mk-tab <?= is_active($url,'tramites.registrar') ?>" href="index.php?url=tramites.registrar">Análisis de Datos</a>
      <a class="mk-tab <?= is_active($url,'alertas.listar') ?>" href="index.php?url=alertas.listar">Alertas de Fraude</a>
      <a class="mk-tab <?= is_active($url,'dashboard') ?>" href="index.php?url=dashboard">Visualización</a>
      <a class="mk-tab <?= is_active($url,'contacto') ?>" href="index.php?url=contacto">Contacto</a>
    </div>

    <div class="mk-box mk-quote mt-12">
      “Dashboard para monitorear alertas, riesgos y casos, priorizando la investigación y reduciendo falsos positivos.”
    </div>

    <div class="mk-box mk-dash mt-12">

      <div class="mk-dash-head">
        <h2 class="mk-dash-title">Dashboard de Riesgo y Gestión de Fraude</h2>
        <div class="mk-dash-sub">Vista operativa para gestores y auditores (demo)</div>
      </div>

      <!-- KPI Cards -->
      <div class="mk-kpi-grid">
        <div class="mk-kpi-card"><div class="mk-kpi-label">Trámites (filtrado)</div><div class="mk-kpi-value"><?= (int)$tot ?></div></div>
        <div class="mk-kpi-card"><div class="mk-kpi-label">Riesgo ALTO</div><div class="mk-kpi-value"><?= (int)$alto ?></div></div>
        <div class="mk-kpi-card"><div class="mk-kpi-label">Riesgo MEDIO</div><div class="mk-kpi-value"><?= (int)$medio ?></div></div>
        <div class="mk-kpi-card"><div class="mk-kpi-label">Riesgo BAJO</div><div class="mk-kpi-value"><?= (int)$bajo ?></div></div>
        <div class="mk-kpi-card"><div class="mk-kpi-label">Alertas</div><div class="mk-kpi-value"><?= (int)$alertas ?></div></div>
        <div class="mk-kpi-card"><div class="mk-kpi-label">Casos</div><div class="mk-kpi-value"><?= (int)$casos ?></div></div>
      </div>

      <!-- Filtros -->
      <form class="mk-filterbar" method="get" action="index.php">
        <input type="hidden" name="url" value="dashboard">

        <div class="mk-filter-row">
          <div class="mk-filter-item">
            <label>Búsqueda</label>
            <input type="text" name="q" value="<?= h($f_q) ?>" placeholder="ID, DNI, tipo, dependencia">
          </div>

          <div class="mk-filter-item">
            <label>Dependencia</label>
            <select name="dep">
              <?php foreach($deps as $k=>$v): ?>
                <option value="<?= h($k) ?>" <?= $f_dep===$k?'selected':'' ?>><?= h($v) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mk-filter-item">
            <label>Riesgo</label>
            <select name="riesgo">
              <?php foreach($riesgos as $k=>$v): ?>
                <option value="<?= h($k) ?>" <?= $f_riesgo===$k?'selected':'' ?>><?= h($v) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mk-filter-item">
            <label>Estado</label>
            <select name="estado">
              <?php foreach($estados as $k=>$v): ?>
                <option value="<?= h($k) ?>" <?= $f_estado===$k?'selected':'' ?>><?= h($v) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="mk-filter-row">
          <div class="mk-filter-item">
            <label>Auditor</label>
            <select name="auditor">
              <?php foreach($auditores as $k=>$v): ?>
                <option value="<?= h($k) ?>" <?= $f_auditor===$k?'selected':'' ?>><?= h($v) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mk-filter-item">
            <label>Desde</label>
            <input type="date" name="from" value="<?= h($f_from) ?>">
          </div>

          <div class="mk-filter-item">
            <label>Hasta</label>
            <input type="date" name="to" value="<?= h($f_to) ?>">
          </div>

          <div class="mk-filter-actions">
            <button class="mk-btn mk-btn-sm" type="submit">Aplicar</button>
            <a class="mk-btn mk-btn-sm" href="index.php?url=dashboard">Limpiar</a>
            <button class="mk-btn mk-btn-sm mk-btn-disabled" type="button" title="Se implementa con BD">Exportar (PDF/Excel)</button>
          </div>
        </div>
      </form>

      <!-- Zona principal: gráfico + panel acciones -->
      <div class="mk-grid-2">

        <div class="mk-panel">
          <div class="mk-panel-title">Alertas y Documentos por día (según filtros)</div>

          <div class="mk-chart-wrap" style="margin-top:10px;">
            <?php if (count($series) === 0): ?>
              <div style="color:#475569;font-size:13px;padding:8px;">
                No hay registros para graficar con los filtros actuales.
              </div>
            <?php else: ?>
              <div class="mk-chart-inner">
                <?php foreach($series as $d => $v): ?>
                  <?php
                    $docs = (int)$v['docs'];
                    $als  = (int)$v['alertas'];
                    $hDocs = pct_h($docs, $maxY);
                    $hAls  = pct_h($als,  $maxY);
                    $label = substr($d, 5); // MM-DD
                  ?>
                  <div class="mk-day">
                    <div class="mk-barbox" title="<?= h($d) ?> | Docs: <?= $docs ?> | Alertas: <?= $als ?>">
                      <div class="mk-bar mk-bar-docs" style="height: <?= $hDocs ?>%;"></div>
                      <div class="mk-bar mk-bar-alerts" style="height: <?= $hAls ?>%;"></div>
                    </div>
                    <div class="mk-day-label"><?= h($label) ?></div>
                    <div class="mk-day-sub">Docs: <?= $docs ?> · Alertas: <?= $als ?></div>
                  </div>
                <?php endforeach; ?>
              </div>

              <div class="mk-legend">
                <span><span class="mk-dot mk-dot-docs"></span> Documentos</span>
                <span><span class="mk-dot mk-dot-alerts"></span> Alertas</span>
              </div>

              <div style="margin-top:8px;color:#475569;font-size:13px;">
                Solo se muestran días con registros (documentos o alertas); no se imprimen días vacíos.
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="mk-panel">
          <div class="mk-panel-title">Acciones rápidas</div>

          <div class="mk-quick">
            <button class="mk-btn mk-btn-sm" type="button" onclick="alert('Pendiente: Asignar auditor (BD)')">Asignar auditor</button>
            <button class="mk-btn mk-btn-sm" type="button" onclick="alert('Pendiente: Cambiar estado (BD)')">Cambiar estado</button>
            <button class="mk-btn mk-btn-sm" type="button" onclick="alert('Pendiente: Marcar falso positivo (BD)')">Falso positivo</button>
            <button class="mk-btn mk-btn-sm" type="button" onclick="alert('Pendiente: Registrar evidencia (BD)')">Registrar evidencia</button>
          </div>

          <div class="mk-panel-title mt-12">Alertas operativas</div>
          <div class="mk-oplist">
            <?php foreach($ops as $o): ?>
              <div class="mk-opitem">
                <div class="mk-op-t"><?= h($o['t']) ?></div>
                <div class="mk-op-d"><?= h($o['d']) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

      </div>

      <!-- Tabla principal -->
      <div class="mk-panel mt-12">
        <div class="mk-panel-title">Bandeja de trámites (priorización)</div>

        <div class="mk-table-wrap">
          <table class="mk-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>DNI</th>
                <th>Tipo</th>
                <th>Dependencia</th>
                <th>Score</th>
                <th>Riesgo</th>
                <th>Estado</th>
                <th>Fecha</th>
                <th>Auditor</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($filtered)===0): ?>
                <tr><td colspan="10" style="text-align:center; padding:14px;">No hay resultados con estos filtros.</td></tr>
              <?php endif; ?>

              <?php foreach($filtered as $r): ?>
                <?php $nivel = nivel_riesgo($r['score']); ?>
                <tr>
                  <td><?= (int)$r['id'] ?></td>
                  <td><?= h(mask_dni($r['dni'])) ?></td>
                  <td><?= h($r['tipo']) ?></td>
                  <td><?= h($r['dep']) ?></td>
                  <td><b><?= (int)$r['score'] ?></b></td>
                  <td><span class="<?= h(color_nivel($nivel)) ?>"><?= h($nivel) ?></span></td>
                  <td><?= h($r['estado']) ?></td>
                  <td><?= h($r['fecha']) ?></td>
                  <td><?= h($r['auditor']) ?></td>
                  <td class="mk-td-actions">
                    <button
                      type="button"
                      class="mk-btn mk-btn-xs"
                      data-row='<?= h(json_encode($r)) ?>'
                      onclick="openDetail(this)"
                    >Ver</button>

                    <button type="button" class="mk-btn mk-btn-xs" onclick="alert('Pendiente: Abrir alerta (BD)')">Abrir alerta</button>
                    <button type="button" class="mk-btn mk-btn-xs" onclick="alert('Pendiente: Asignar (BD)')">Asignar</button>
                    <button type="button" class="mk-btn mk-btn-xs" onclick="alert('Pendiente: Generar informe (BD)')">Informe</button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div><!-- /mk-dash -->

  </div><!-- /mk-wrap -->
</div><!-- /mk-page -->

<!-- Modal detalle -->
<div class="mk-modal" id="mkModal" aria-hidden="true">
  <div class="mk-modal-card">
    <div class="mk-modal-head">
      <div class="mk-modal-title">Detalle del trámite</div>
      <button class="mk-modal-x" type="button" onclick="closeDetail()">✕</button>
    </div>
    <div class="mk-modal-body" id="mkModalBody"></div>
    <div class="mk-modal-foot">
      <button class="mk-btn mk-btn-sm" type="button" onclick="alert('Pendiente: Registrar evidencia (BD)')">Registrar evidencia</button>
      <button class="mk-btn mk-btn-sm" type="button" onclick="alert('Pendiente: Generar informe (BD)')">Generar informe</button>
      <button class="mk-btn mk-btn-sm" type="button" onclick="closeDetail()">Cerrar</button>
    </div>
  </div>
</div>

<script>
function openDetail(btn){
  const raw = btn.getAttribute('data-row') || '{}';
  let r = {};
  try { r = JSON.parse(raw); } catch(e){}

  const nivel = (r.score >= 80) ? 'ALTO' : (r.score >= 50 ? 'MEDIO' : 'BAJO');

  const html = `
    <div class="mk-dl">
      <div><b>ID Trámite:</b> ${r.id ?? ''}</div>
      <div><b>DNI:</b> ${r.dni ?? ''}</div>
      <div><b>Tipo:</b> ${r.tipo ?? ''}</div>
      <div><b>Dependencia:</b> ${r.dep ?? ''}</div>
      <div><b>Score:</b> ${r.score ?? ''} (${nivel})</div>
      <div><b>Estado:</b> ${r.estado ?? ''}</div>
      <div><b>Fecha:</b> ${r.fecha ?? ''}</div>
      <div><b>Auditor:</b> ${r.auditor ?? ''}</div>
    </div>
    <hr style="border:0;border-top:1px solid rgba(0,0,0,.15);margin:12px 0;">
    <div>
      <b>Señales/criterios (demo):</b>
      <ul style="margin:8px 0 0 18px;">
        <li>Inconsistencia de datos (formato / duplicidad)</li>
        <li>Patrón inusual según tipo de trámite</li>
        <li>Coincidencias con trámites recientes</li>
      </ul>
    </div>
    <div style="margin-top:10px;color:#475569;">
      Nota: Este detalle luego se alimenta desde BD (validaciones, evidencias, bitácora).
    </div>
  `;

  document.getElementById('mkModalBody').innerHTML = html;
  const modal = document.getElementById('mkModal');
  modal.style.display = 'flex';
  modal.setAttribute('aria-hidden', 'false');
}
function closeDetail(){
  const modal = document.getElementById('mkModal');
  modal.style.display = 'none';
  modal.setAttribute('aria-hidden', 'true');
}
document.addEventListener('keydown', (e)=>{
  if(e.key === 'Escape') closeDetail();
});
</script>
