<?php
// src/capaVista/alertas/listar.php
$url = $_GET['url'] ?? 'alertas.listar';

function is_active(string $current, string $route): string { return $current === $route ? 'is-active' : ''; }
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

if (!isset($_SESSION['alertas'])) $_SESSION['alertas'] = [];
if (!isset($_SESSION['casos'])) $_SESSION['casos'] = [];

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$alertas = array_reverse($_SESSION['alertas']);
$casos   = array_reverse($_SESSION['casos']);

$totA = count($alertas);
$totC = count($casos);

$pendA = 0;
foreach ($alertas as $a) {
  if (($a['estado'] ?? '') === 'PENDIENTE') $pendA++;
}

$abiertos = 0;
foreach ($casos as $c) {
  if (($c['estado'] ?? '') !== 'CERRADO') $abiertos++;
}
?>
<div class="mk-page">
  <div class="mk-wrap">

    <div class="mk-header">
      <div class="mk-logo">
        <img src="img/junin_logo.png" alt="Junín"
             onerror="this.style.display='none'; document.getElementById('logoFallbackAL').style.display='flex';">
        <div id="logoFallbackAL" class="mk-logo-fallback" style="display:none;">JUNÍN</div>
      </div>
      <div class="mk-title">Sistema de Detección de Fraude en Trámites</div>
    </div>

    <div class="mk-tabsbar">
      <a class="mk-tab <?= is_active($url,'home') ?>" href="index.php?url=home">Inicio</a>
      <a class="mk-tab <?= is_active($url,'tramites.registrar') ?>" href="index.php?url=tramites.registrar">Análisis de Datos</a>
      <a class="mk-tab is-active" href="index.php?url=alertas.listar">Alertas de Fraude</a>
      <a class="mk-tab <?= is_active($url,'dashboard') ?>" href="index.php?url=dashboard">Visualización</a>
      <a class="mk-tab <?= is_active($url,'contacto') ?>" href="index.php?url=contacto">Contacto</a>
    </div>

    <div class="mk-box mk-quote mt-12">
      “Gestión de alertas y casos para priorizar investigación, asignar auditores y reducir falsos positivos.”
    </div>

    <?php if ($flash): ?>
      <div class="mk-box" style="margin-top:12px;padding:10px;border-color:<?= ($flash['type'] ?? '')==='error'?'#b91c1c':'rgba(17,24,39,.35)' ?>;">
        <b style="color:<?= ($flash['type'] ?? '')==='error'?'#b91c1c':'#15803d' ?>;">
          <?= ($flash['type'] ?? '')==='error'?'Error:':'OK:' ?>
        </b>
        <?= h($flash['msg'] ?? '') ?>
      </div>
    <?php endif; ?>

    <div class="mk-stage">
      <div class="mk-stage-sidebar">
        <a class="mk-side-item" href="index.php?url=alertas.generar">🚨 Generar alerta de fraude</a>
        <a class="mk-side-item" href="index.php?url=alertas.caso">🗂️ Gestionar caso de investigación</a>
      </div>

      <div class="mk-stage-main">
        <div class="mk-stage-msg"><b>Panel de Alertas (resumen)</b></div>

        <div class="mk-box" style="margin-top:12px;padding:12px;">
          <div style="display:grid;grid-template-columns:repeat(4,minmax(160px,1fr));gap:10px;">
            <div class="mk-kpi-card"><div class="mk-kpi-label">Alertas</div><div class="mk-kpi-value"><?= (int)$totA ?></div></div>
            <div class="mk-kpi-card"><div class="mk-kpi-label">Pendientes</div><div class="mk-kpi-value"><?= (int)$pendA ?></div></div>
            <div class="mk-kpi-card"><div class="mk-kpi-label">Casos</div><div class="mk-kpi-value"><?= (int)$totC ?></div></div>
            <div class="mk-kpi-card"><div class="mk-kpi-label">Casos abiertos</div><div class="mk-kpi-value"><?= (int)$abiertos ?></div></div>
          </div>

          <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:12px;">
            <a class="mk-btn" href="index.php?url=alertas.generar">Generar alerta</a>
            <a class="mk-btn" href="index.php?url=alertas.caso">Gestionar casos</a>
          </div>
        </div>

        <div class="mk-box" style="margin-top:12px;padding:12px;">
          <b>Últimas alertas (DEMO)</b>
          <div style="overflow:auto;margin-top:10px;">
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
              <thead>
                <tr>
                  <th style="text-align:left;padding:8px;border-bottom:1px solid rgba(17,24,39,.25);">ID</th>
                  <th style="text-align:left;padding:8px;border-bottom:1px solid rgba(17,24,39,.25);">Trámite</th>
                  <th style="text-align:left;padding:8px;border-bottom:1px solid rgba(17,24,39,.25);">Severidad</th>
                  <th style="text-align:left;padding:8px;border-bottom:1px solid rgba(17,24,39,.25);">Nivel</th>
                  <th style="text-align:left;padding:8px;border-bottom:1px solid rgba(17,24,39,.25);">Estado</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$alertas): ?>
                  <tr><td colspan="5" style="padding:10px;color:#334155;">Aún no hay alertas. Cree una desde “Generar alerta de fraude”.</td></tr>
                <?php else: ?>
                  <?php foreach (array_slice($alertas, 0, 10) as $a): ?>
                    <tr>
                      <td style="padding:8px;border-bottom:1px solid rgba(17,24,39,.12);"><?= h($a['id'] ?? '') ?></td>
                      <td style="padding:8px;border-bottom:1px solid rgba(17,24,39,.12);"><?= h($a['tramite_id'] ?? '') ?></td>
                      <td style="padding:8px;border-bottom:1px solid rgba(17,24,39,.12);"><?= h($a['severidad'] ?? '') ?></td>
                      <td style="padding:8px;border-bottom:1px solid rgba(17,24,39,.12);"><?= h($a['nivel'] ?? '') ?> (<?= (int)($a['score'] ?? 0) ?>/100)</td>
                      <td style="padding:8px;border-bottom:1px solid rgba(17,24,39,.12);"><?= h($a['estado'] ?? '') ?></td>
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
      <a class="mk-btn" href="index.php?url=dashboard">SIGUIENTE</a>
    </div>

  </div>
</div>
