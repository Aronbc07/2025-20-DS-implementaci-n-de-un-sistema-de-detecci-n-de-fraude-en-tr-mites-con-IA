<?php
// src/capaVista/alertas/caso.php
$url = $_GET['url'] ?? 'alertas.caso';

function is_active(string $current, string $route): string { return $current === $route ? 'is-active' : ''; }
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function mk_id(string $prefix): string {
  return $prefix . '-' . str_pad((string)random_int(1, 999999), 6, '0', STR_PAD_LEFT);
}

if (!isset($_SESSION['alertas'])) $_SESSION['alertas'] = [];
if (!isset($_SESSION['casos'])) $_SESSION['casos'] = [];

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$selected_case_id = $_GET['cid'] ?? '';

/** Crear caso desde alerta */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['op'] ?? '') === 'abrir_caso') {
  $aid = (string)($_POST['alerta_id'] ?? '');
  $alerta = null;
  foreach ($_SESSION['alertas'] as $a) {
    if ($a['id'] === $aid) { $alerta = $a; break; }
  }

  if (!$alerta) {
    $_SESSION['flash'] = ['type'=>'error','msg'=>'No se encontró la alerta para abrir el caso.'];
    header('Location: index.php?url=alertas.caso'); exit;
  }

  // Evitar duplicar caso para misma alerta (MVP)
  foreach ($_SESSION['casos'] as $c) {
    if (($c['alerta_id'] ?? '') === $aid) {
      $_SESSION['flash'] = ['type'=>'error','msg'=>'Ya existe un caso para esta alerta.'];
      header('Location: index.php?url=alertas.caso&cid='.urlencode($c['id'])); exit;
    }
  }

  $cid = mk_id('CA');
  $nuevo = [
    'id' => $cid,
    'alerta_id' => $alerta['id'],
    'tramite_id' => $alerta['tramite_id'],
    'dni' => $alerta['dni'],
    'severidad' => $alerta['severidad'],
    'nivel' => $alerta['nivel'],
    'score' => $alerta['score'],
    'estado' => 'EN INVESTIGACIÓN',
    'auditor' => $alerta['auditor'] ?: 'No asignado',
    'creado' => date('d/m/Y H:i'),
    'bitacora' => [
      ['fecha'=>date('d/m/Y H:i'),'usuario'=>'Sistema','accion'=>'Caso creado', 'nota'=>'Se abrió caso de investigación desde alerta.'],
    ],
    'evidencias' => [],
    'conclusion' => '',
  ];

  $_SESSION['casos'][] = $nuevo;

  $_SESSION['flash'] = ['type'=>'ok','msg'=>"Caso creado: {$cid} (desde alerta {$alerta['id']})."];
  header('Location: index.php?url=alertas.caso&cid='.urlencode($cid)); exit;
}

/** Agregar nota a bitácora */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['op'] ?? '') === 'agregar_nota') {
  $cid = (string)($_POST['cid'] ?? '');
  $nota = trim((string)($_POST['nota'] ?? ''));

  if ($nota === '') {
    $_SESSION['flash'] = ['type'=>'error','msg'=>'Ingrese una nota para la bitácora.'];
    header('Location: index.php?url=alertas.caso&cid='.urlencode($cid)); exit;
  }

  foreach ($_SESSION['casos'] as &$c) {
    if ($c['id'] === $cid) {
      $c['bitacora'][] = ['fecha'=>date('d/m/Y H:i'),'usuario'=>'Auditor','accion'=>'Nota', 'nota'=>$nota];
      break;
    }
  }
  unset($c);

  $_SESSION['flash'] = ['type'=>'ok','msg'=>'Nota agregada a la bitácora.'];
  header('Location: index.php?url=alertas.caso&cid='.urlencode($cid)); exit;
}

/** Cerrar caso */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['op'] ?? '') === 'cerrar_caso') {
  $cid = (string)($_POST['cid'] ?? '');
  $resultado = (string)($_POST['resultado'] ?? '');
  $conclusion = trim((string)($_POST['conclusion'] ?? ''));

  if ($resultado === '' || $conclusion === '') {
    $_SESSION['flash'] = ['type'=>'error','msg'=>'Debe seleccionar resultado y escribir conclusiones para cerrar el caso.'];
    header('Location: index.php?url=alertas.caso&cid='.urlencode($cid)); exit;
  }

  foreach ($_SESSION['casos'] as &$c) {
    if ($c['id'] === $cid) {
      $c['estado'] = 'CERRADO';
      $c['resultado'] = $resultado;
      $c['conclusion'] = $conclusion;
      $c['bitacora'][] = ['fecha'=>date('d/m/Y H:i'),'usuario'=>'Auditor','accion'=>'Cierre', 'nota'=>"Resultado: {$resultado}"];
      break;
    }
  }
  unset($c);

  $_SESSION['flash'] = ['type'=>'ok','msg'=>'Caso cerrado correctamente.'];
  header('Location: index.php?url=alertas.caso&cid='.urlencode($cid)); exit;
}

/** Selección inicial: si no hay cid, toma el último caso */
if (!$selected_case_id && $_SESSION['casos']) {
  $last = end($_SESSION['casos']);
  $selected_case_id = $last['id'] ?? '';
  reset($_SESSION['casos']);
}

$selected = null;
foreach ($_SESSION['casos'] as $c) {
  if ($c['id'] === $selected_case_id) { $selected = $c; break; }
}
?>
<div class="mk-page">
  <div class="mk-wrap">

    <div class="mk-header">
      <div class="mk-logo">
        <img src="img/junin_logo.png" alt="Junín"
             onerror="this.style.display='none'; document.getElementById('logoFallbackAC').style.display='flex';">
        <div id="logoFallbackAC" class="mk-logo-fallback" style="display:none;">JUNÍN</div>
      </div>
      <div class="mk-title">Sistema de Detección de Fraude en Trámites</div>
    </div>

    <div class="mk-tabsbar">
      <a class="mk-tab <?= is_active($url,'home') ?>" href="index.php?url=home">Inicio</a>
      <a class="mk-tab <?= is_active($url,'tramites.registrar') ?>" href="index.php?url=tramites.registrar">Análisis de Datos</a>
      <a class="mk-tab is-active" href="index.php?url=alertas.caso">Alertas de Fraude</a>
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
        <a class="mk-side-item" href="index.php?url=alertas.generar">🚨 Generar alerta de fraude</a>
        <a class="mk-side-item is-active" href="index.php?url=alertas.caso">🗂️ Gestionar caso de investigación</a>
      </div>

      <div class="mk-stage-main">
        <div class="mk-stage-msg"><b>Gestionar caso de investigación</b></div>

        <!-- Crear caso desde alerta -->
        <div class="mk-box" style="margin-top:12px;padding:12px;">
          <b>Abrir caso desde una alerta</b>

          <?php if (!$_SESSION['alertas']): ?>
            <div style="margin-top:10px;color:#334155;font-size:13px;">
              Aún no hay alertas. Primero genera una alerta en “Generar alerta de fraude”.
            </div>
          <?php else: ?>
            <form method="post" action="index.php?url=alertas.caso" style="margin-top:10px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
              <input type="hidden" name="op" value="abrir_caso">
              <label style="font-size:13px;">Alerta:</label>
              <select name="alerta_id" style="padding:6px 8px;border:1px solid #777;font-size:13px;">
                <?php foreach (array_reverse($_SESSION['alertas']) as $a): ?>
                  <option value="<?= h($a['id']) ?>"><?= h($a['id']) ?> — Trámite <?= h($a['tramite_id']) ?> (<?= h($a['severidad']) ?>)</option>
                <?php endforeach; ?>
              </select>
              <button class="mk-btn" type="submit" style="cursor:pointer;">Abrir Caso</button>
            </form>
          <?php endif; ?>
        </div>

        <!-- Bandeja de casos -->
        <div class="mk-box" style="margin-top:12px;padding:12px;">
          <b>Bandeja de casos (DEMO)</b>
          <div style="overflow:auto;margin-top:10px;">
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
              <thead>
                <tr>
                  <th style="text-align:left;padding:8px;border-bottom:1px solid rgba(17,24,39,.25);">Caso</th>
                  <th style="text-align:left;padding:8px;border-bottom:1px solid rgba(17,24,39,.25);">Alerta</th>
                  <th style="text-align:left;padding:8px;border-bottom:1px solid rgba(17,24,39,.25);">Trámite</th>
                  <th style="text-align:left;padding:8px;border-bottom:1px solid rgba(17,24,39,.25);">Severidad</th>
                  <th style="text-align:left;padding:8px;border-bottom:1px solid rgba(17,24,39,.25);">Estado</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$_SESSION['casos']): ?>
                  <tr><td colspan="5" style="padding:10px;color:#334155;">Aún no hay casos creados.</td></tr>
                <?php else: ?>
                  <?php foreach (array_reverse($_SESSION['casos']) as $c): ?>
                    <?php $isSel = ($selected_case_id && $c['id']===$selected_case_id); ?>
                    <tr style="<?= $isSel ? 'background:rgba(29,78,216,.10);' : '' ?>">
                      <td style="padding:8px;border-bottom:1px solid rgba(17,24,39,.12);">
                        <a href="index.php?url=alertas.caso&cid=<?= urlencode($c['id']) ?>" style="text-decoration:none;color:#111;">
                          <?= h($c['id']) ?>
                        </a>
                      </td>
                      <td style="padding:8px;border-bottom:1px solid rgba(17,24,39,.12);"><?= h($c['alerta_id']) ?></td>
                      <td style="padding:8px;border-bottom:1px solid rgba(17,24,39,.12);"><?= h($c['tramite_id']) ?></td>
                      <td style="padding:8px;border-bottom:1px solid rgba(17,24,39,.12);"><?= h($c['severidad']) ?></td>
                      <td style="padding:8px;border-bottom:1px solid rgba(17,24,39,.12);"><?= h($c['estado']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Detalle caso -->
        <div class="mk-box" style="margin-top:12px;padding:12px;">
          <b>Detalle del caso</b>

          <?php if (!$selected): ?>
            <div style="margin-top:10px;color:#334155;font-size:13px;">
              Seleccione un caso en la bandeja para ver el detalle.
            </div>
          <?php else: ?>
            <div style="margin-top:10px;font-size:13px;">
              <div><b>Caso:</b> <?= h($selected['id']) ?> &nbsp; | &nbsp; <b>Alerta:</b> <?= h($selected['alerta_id']) ?></div>
              <div><b>Trámite:</b> <?= h($selected['tramite_id']) ?> &nbsp; | &nbsp; <b>DNI:</b> <?= h($selected['dni']) ?></div>
              <div><b>Severidad:</b> <?= h($selected['severidad']) ?> &nbsp; | &nbsp; <b>Nivel:</b> <?= h($selected['nivel']) ?> (<?= (int)$selected['score'] ?>/100)</div>
              <div><b>Auditor:</b> <?= h($selected['auditor']) ?> &nbsp; | &nbsp; <b>Estado:</b> <?= h($selected['estado']) ?></div>
              <div><b>Creado:</b> <?= h($selected['creado']) ?></div>
            </div>

            <div style="margin-top:12px;">
              <b>Bitácora</b>
              <div style="overflow:auto;margin-top:8px;">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                  <thead>
                    <tr>
                      <th style="text-align:left;padding:8px;border-bottom:1px solid rgba(17,24,39,.25);width:140px;">Fecha</th>
                      <th style="text-align:left;padding:8px;border-bottom:1px solid rgba(17,24,39,.25);width:110px;">Usuario</th>
                      <th style="text-align:left;padding:8px;border-bottom:1px solid rgba(17,24,39,.25);width:120px;">Acción</th>
                      <th style="text-align:left;padding:8px;border-bottom:1px solid rgba(17,24,39,.25);">Nota</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach (($selected['bitacora'] ?? []) as $b): ?>
                      <tr>
                        <td style="padding:8px;border-bottom:1px solid rgba(17,24,39,.12);"><?= h($b['fecha']) ?></td>
                        <td style="padding:8px;border-bottom:1px solid rgba(17,24,39,.12);"><?= h($b['usuario']) ?></td>
                        <td style="padding:8px;border-bottom:1px solid rgba(17,24,39,.12);"><?= h($b['accion']) ?></td>
                        <td style="padding:8px;border-bottom:1px solid rgba(17,24,39,.12);"><?= h($b['nota']) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>

              <form method="post" action="index.php?url=alertas.caso&cid=<?= urlencode($selected['id']) ?>" style="margin-top:10px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <input type="hidden" name="op" value="agregar_nota">
                <input type="hidden" name="cid" value="<?= h($selected['id']) ?>">
                <input type="text" name="nota" placeholder="Agregar nota a bitácora..." style="flex:1;min-width:260px;padding:6px 8px;border:1px solid #777;font-size:13px;">
                <button class="mk-btn" type="submit" style="cursor:pointer;">Agregar Nota</button>
              </form>
            </div>

            <div style="margin-top:14px;">
              <b>Cerrar caso</b>
              <form method="post" action="index.php?url=alertas.caso&cid=<?= urlencode($selected['id']) ?>" style="margin-top:8px;">
                <input type="hidden" name="op" value="cerrar_caso">
                <input type="hidden" name="cid" value="<?= h($selected['id']) ?>">

                <div style="display:grid;grid-template-columns:190px 1fr;gap:10px;align-items:center;max-width:800px;">
                  <label style="font-size:13px;text-align:right;">Resultado:</label>
                  <select name="resultado" style="padding:6px 8px;border:1px solid #777;font-size:13px;">
                    <option value="">Seleccione</option>
                    <option>Confirmado fraude</option>
                    <option>Indicio no concluyente</option>
                    <option>Falso positivo</option>
                  </select>

                  <label style="font-size:13px;text-align:right;">Conclusiones:</label>
                  <textarea name="conclusion" rows="3" style="padding:6px 8px;border:1px solid #777;font-size:13px;resize:vertical;"></textarea>
                </div>

                <div style="margin-top:10px;display:flex;gap:10px;justify-content:flex-end;">
                  <button class="mk-btn" type="submit" style="cursor:pointer;">Cerrar Caso</button>
                </div>
              </form>
            </div>

          <?php endif; ?>
        </div>

      </div>
    </div>

    <div class="mk-stage-actions">
      <a class="mk-btn" href="index.php?url=alertas.generar">Volver</a>
      <a class="mk-btn" href="index.php?url=dashboard">SIGUIENTE</a>
    </div>

  </div>
</div>
