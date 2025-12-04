<?php
// src/capaVista/contacto.php

$url = $_GET['url'] ?? 'contacto';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function is_active(string $current, string $route): string { return $current === $route ? 'is-active' : ''; }

$subjects = [
  '' => 'Seleccione asunto',
  'soporte'   => 'Soporte técnico',
  'alerta'    => 'Consulta de alerta',
  'incidente' => 'Reporte de incidente',
  'acceso'    => 'Solicitud de acceso',
  'feedback'  => 'Sugerencia / Mejora',
];

$userTypes = [
  '' => 'Seleccione tipo',
  'gestor' => 'Gestor',
  'auditor' => 'Auditor',
  'admin' => 'Administrador',
];

$deps = [
  '' => 'Seleccione dependencia (opcional)',
  'GRD' => 'GRD',
  'GRA' => 'GRA',
  'OTI' => 'OTI',
];

$status = [
  'estado' => 'OK', // OK | MANT | INC
  'linea'  => 'Sistema operativo (servicios disponibles).',
  'nota'   => 'Si existe mantenimiento programado se mostrará aquí.',
];

$faqs = [
  ['q' => '¿Por qué no puedo “Analizar con IA”?', 'a' => 'Primero debes validar datos y documentos del trámite. Si hay observaciones, el sistema bloquea el análisis.'],
  ['q' => '¿Qué significa Riesgo Alto/Medio/Bajo?', 'a' => 'Es una clasificación del score de riesgo para priorizar revisión, no una sanción automática.'],
  ['q' => '¿Qué debo adjuntar como evidencia?', 'a' => 'Documento del trámite, captura del error, y cualquier sustento adicional que ayude a auditoría.'],
  ['q' => '¿Cómo registro un incidente de seguridad?', 'a' => 'Selecciona “Reporte de incidente” e incluye fecha/hora, módulo afectado y detalle del hecho.'],
];

$errors = [];
$ok = false;
$ticket = null;
$savedFile = null;

$form = [
  'subject' => $_POST['subject'] ?? '',
  'user_type' => $_POST['user_type'] ?? '',
  'dep' => $_POST['dep'] ?? '',
  'ref' => trim($_POST['ref'] ?? ''),
  'name' => trim($_POST['name'] ?? ''),
  'email' => trim($_POST['email'] ?? ''),
  'phone' => trim($_POST['phone'] ?? ''),
  'message' => trim($_POST['message'] ?? ''),
];

function make_ticket(): string {
  $rand = random_int(100, 999);
  return 'TCK-' . date('Ymd-His') . '-' . $rand;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Validaciones mínimas (sin BD)
  if ($form['subject'] === '') $errors[] = 'Seleccione un asunto.';
  if ($form['user_type'] === '') $errors[] = 'Seleccione el tipo de usuario.';
  if ($form['message'] === '' || mb_strlen($form['message']) < 10) $errors[] = 'Ingrese un mensaje (mínimo 10 caracteres).';

  if ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'El correo no tiene un formato válido.';
  }

  // Validación y guardado de adjunto (opcional)
  if (empty($errors)) {
    $ticket = make_ticket();

    if (!empty($_FILES['evidence']['name'])) {
      $f = $_FILES['evidence'];

      if ($f['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'No se pudo subir el archivo (error de carga).';
      } else {
        $maxBytes = 8 * 1024 * 1024; // 8MB
        if ($f['size'] > $maxBytes) $errors[] = 'El archivo excede 8MB.';

        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
        if (!in_array($ext, $allowed, true)) $errors[] = 'Adjunto no permitido. Use PDF/JPG/PNG.';

        if (empty($errors)) {
          // storage/contacto/
          $root = dirname(__DIR__, 2); // .../ (raíz del proyecto)
          $dir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'contacto';

          if (!is_dir($dir)) {
            // intenta crear (en XAMPP normalmente funciona)
            @mkdir($dir, 0777, true);
          }

          if (is_dir($dir) && is_writable($dir)) {
            $safeName = $ticket . '_evidence.' . $ext;
            $dest = $dir . DIRECTORY_SEPARATOR . $safeName;

            if (move_uploaded_file($f['tmp_name'], $dest)) {
              $savedFile = 'storage/contacto/' . $safeName; // ruta relativa
            } else {
              $errors[] = 'No se pudo guardar el archivo en el servidor.';
            }
          } else {
            $errors[] = 'No existe o no es escribible la carpeta storage/contacto/.';
          }
        }
      }
    }

    if (empty($errors)) {
      $ok = true;

      // Limpia el formulario si todo ok (opcional)
      // $form = ['subject'=>'','user_type'=>'','dep'=>'','ref'=>'','name'=>'','email'=>'','phone'=>'','message'=>''];
    }
  }
}
?>

<div class="mk-page">
  <div class="mk-wrap">

    <!-- Header -->
    <div class="mk-header">
      <div class="mk-logo">
        <img src="img/junin_logo.png" alt="Junín"
             onerror="this.style.display='none'; document.getElementById('logoFallback').style.display='flex';">
        <div id="logoFallback" class="mk-logo-fallback" style="display:none;">JUNÍN</div>
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
      “Canal oficial para soporte, consultas de alertas, reportes de incidentes y solicitudes relacionadas al sistema.”
    </div>

    <div class="mk-body">
      <!-- Izquierda: Formulario -->
      <div class="mk-left">
        <div class="mk-box mk-form-wrap">
          <div class="mk-form-title">Contacto</div>

          <?php if ($ok): ?>
            <div class="mk-alert mk-alert-ok">
              <b>Solicitud registrada correctamente.</b><br>
              Código de ticket: <b><?= h($ticket) ?></b><br>
              <?php if ($savedFile): ?>
                Adjunto guardado en: <b><?= h($savedFile) ?></b>
              <?php endif; ?>
            </div>
            <div class="mk-form-actions" style="margin-top:12px;">
              <a class="mk-btn mk-btn-primary" href="index.php?url=contacto">Nueva solicitud</a>
              <a class="mk-btn" href="index.php?url=home">Volver al inicio</a>
            </div>
          <?php else: ?>

            <?php if (!empty($errors)): ?>
              <div class="mk-alert mk-alert-err">
                <b>Revise lo siguiente:</b>
                <ul style="margin:8px 0 0 18px;">
                  <?php foreach($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
                </ul>
              </div>
            <?php endif; ?>

            <form class="mk-form" method="post" enctype="multipart/form-data" action="index.php?url=contacto">
              <div class="mk-form-row">
                <label>Asunto:</label>
                <select name="subject">
                  <?php foreach($subjects as $k=>$v): ?>
                    <option value="<?= h($k) ?>" <?= $form['subject']===$k?'selected':'' ?>><?= h($v) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="mk-form-row">
                <label>Tipo de usuario:</label>
                <select name="user_type">
                  <?php foreach($userTypes as $k=>$v): ?>
                    <option value="<?= h($k) ?>" <?= $form['user_type']===$k?'selected':'' ?>><?= h($v) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="mk-form-row">
                <label>Dependencia:</label>
                <select name="dep">
                  <?php foreach($deps as $k=>$v): ?>
                    <option value="<?= h($k) ?>" <?= $form['dep']===$k?'selected':'' ?>><?= h($v) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="mk-form-row">
                <label>N° Trámite/Alerta/Caso:</label>
                <input type="text" name="ref" placeholder="Ej: TRM-1023 / ALT-88 / CAS-15" value="<?= h($form['ref']) ?>">
              </div>

              <div class="mk-form-row">
                <label>Nombres (opcional):</label>
                <input type="text" name="name" placeholder="Nombre completo" value="<?= h($form['name']) ?>">
              </div>

              <div class="mk-form-row">
                <label>Correo (opcional):</label>
                <input type="text" name="email" placeholder="correo@institucion.gob.pe" value="<?= h($form['email']) ?>">
              </div>

              <div class="mk-form-row">
                <label>Teléfono/Anexo (opcional):</label>
                <input type="text" name="phone" placeholder="Ej: 064-xxxx / anexo 123" value="<?= h($form['phone']) ?>">
              </div>

              <div class="mk-form-row" style="align-items:flex-start;">
                <label>Mensaje:</label>
                <textarea name="message" rows="5" style="width:260px; padding:8px; border:1px solid #777; font-size:13px; border-radius:10px;"><?= h($form['message']) ?></textarea>
              </div>

              <div class="mk-form-row">
                <label>Adjuntar evidencia:</label>
                <input type="file" name="evidence" accept=".pdf,.jpg,.jpeg,.png">
              </div>

              <div class="mk-form-line"></div>

              <div class="mk-form-actions">
                <a class="mk-btn" href="index.php?url=home">Cancelar</a>
                <button class="mk-btn mk-btn-primary" type="submit">Enviar solicitud</button>
              </div>

              <div style="margin-top:10px; font-size:12.5px; color:#475569; text-align:center;">
                Al enviar, aceptas el uso interno de la información para fines de soporte y auditoría.
              </div>
            </form>
          <?php endif; ?>
        </div>
      </div>

      <!-- Derecha: info -->
      <div class="mk-right">
        <div class="mk-box mk-anuncios">
          <div class="mk-anuncios-title">CANALES OFICIALES</div>
          <div class="mk-anuncio-item">
            <div class="mk-anuncio-line"><b>Mesa de ayuda TI:</b> soporte.ti@gorejunin.gob.pe</div>
          </div>
          <div class="mk-divider"></div>
          <div class="mk-anuncio-item">
            <div class="mk-anuncio-line"><b>Teléfono / Anexo:</b> (064) 123456 / Anexo 101</div>
          </div>
          <div class="mk-divider"></div>
          <div class="mk-anuncio-item">
            <div class="mk-anuncio-line"><b>Oficina responsable:</b> OTI / Unidad de Integridad</div>
          </div>
          <div class="mk-divider"></div>
          <div class="mk-anuncio-item">
            <div class="mk-anuncio-line"><b>Horario:</b> Lun–Vie 08:00 a 17:00</div>
          </div>
        </div>

        <div class="mk-box mk-anuncios mt-12">
          <div class="mk-anuncios-title">ESTADO DEL SISTEMA</div>
          <div class="mk-anuncio-item">
            <span class="mk-tag tag-default"><?= h($status['estado']) ?></span>
            <div class="mk-anuncio-desc"><?= h($status['linea']) ?></div>
            <div class="mk-anuncio-desc" style="margin-top:6px;"><?= h($status['nota']) ?></div>
          </div>
        </div>

        <div class="mk-box mk-anuncios mt-12">
          <div class="mk-anuncios-title">PREGUNTAS FRECUENTES</div>
          <?php foreach($faqs as $i => $f): ?>
            <div class="mk-anuncio-item">
              <div class="mk-anuncio-line"><b><?= h($f['q']) ?></b></div>
              <div class="mk-anuncio-desc"><?= h($f['a']) ?></div>
            </div>
            <?php if ($i < count($faqs)-1): ?><div class="mk-divider"></div><?php endif; ?>
          <?php endforeach; ?>
        </div>

        <div class="mk-box mk-anuncios mt-12">
          <div class="mk-anuncios-title">POLÍTICA Y CUMPLIMIENTO</div>
          <div class="mk-anuncio-item">
            <div class="mk-anuncio-desc">
              • Uso exclusivo para fines de control interno, auditoría y soporte.<br>
              • Acceso restringido a reportes de fraude.<br>
              • Acciones registradas en bitácora (auditoría).<br>
              • Cumplimiento de la Ley de Protección de Datos Personales.
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>
