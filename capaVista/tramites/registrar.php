<?php
// src/capaVista/tramites/registrar.php
// OJO: si te sale error de strict_types, asegúrate que este archivo NO tenga espacios/lineas antes de <?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$url = $_GET['url'] ?? 'tramites.registrar';
function is_active(string $current, string $route): string { return $current === $route ? 'is-active' : ''; }

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>

<div class="mk-page">
  <div class="mk-wrap">

    <div class="mk-header">
      <div class="mk-logo">
        <img src="img/junin_logo.png" alt="Junín"
             onerror="this.style.display='none'; document.getElementById('logoFallback2').style.display='flex';">
        <div id="logoFallback2" class="mk-logo-fallback" style="display:none;">JUNÍN</div>
      </div>
      <div class="mk-title">Sistema de Detección de Fraude en Trámites</div>
    </div>

    <div class="mk-tabsbar">
      <a class="mk-tab <?= is_active($url,'home') ?>" href="index.php?url=home">Inicio</a>
      <a class="mk-tab <?= is_active($url,'tramites.registrar') ? 'is-active' : '' ?>" href="index.php?url=tramites.registrar">Análisis de Datos</a>
      <a class="mk-tab <?= is_active($url,'alertas.listar') ?>" href="index.php?url=alertas.listar">Alertas de Fraude</a>
      <a class="mk-tab <?= is_active($url,'dashboard') ?>" href="index.php?url=dashboard">Visualización</a>
      <a class="mk-tab <?= is_active($url,'contacto') ?>" href="index.php?url=contacto">Contacto</a>
    </div>

    <div class="mk-box mk-quote mt-12">
      “Componente de IA que analiza grandes volúmenes de datos de los trámites para identificar patrones anómalos que podrían indicar fraude.”
    </div>

    <?php if ($flash && is_array($flash)): ?>
      <div class="mk-box" style="margin-top:12px;padding:12px;border-color:#b91c1c;">
        <?= htmlspecialchars($flash['msg'] ?? '') ?>
      </div>
    <?php endif; ?>

    <!-- Mensaje propio del front (JS) -->
    <div id="mkFormError" class="mk-box" style="display:none;margin-top:12px;padding:12px;border-color:#b91c1c;">
      <b>Error:</b> <span id="mkFormErrorText"></span>
    </div>

    <div class="mk-form-wrap">
      <h2 class="mk-form-title">Registrar Trámite</h2>

      <form class="mk-form" id="formTramite"
            action="index.php?url=tramites.guardar"
            method="POST" enctype="multipart/form-data">

        <!-- le dice al controlador a dónde redirigir luego -->
        <input type="hidden" name="go_to" id="go_to" value="registrar">

        <div class="mk-form-row">
          <label>Ingrese DNI:</label>
          <input id="dni" type="text" name="dni" maxlength="8" required>
        </div>

        <div class="mk-form-row">
          <label>Tipo de trámite:</label>
          <select id="tipo_tramite" name="tipo_tramite" required>
            <option value="">Seleccione trámite</option>
            <option value="LICENCIA">Licencia</option>
            <option value="PERMISO">Permiso</option>
            <option value="PAGO">Pago / Tasa</option>
            <option value="OTRO">Otro</option>
          </select>
        </div>

        <div class="mk-form-row">
          <label>Tipo de documento:</label>
          <select id="tipo_documento" name="tipo_documento" required>
            <option value="">Seleccione documento</option>
            <option value="PDF">PDF</option>
            <option value="IMG">Imagen (JPG/PNG)</option>
          </select>
        </div>

        <div class="mk-form-row">
          <label>Insertar documento:</label>
          <input id="documento" type="file" name="documento" accept=".pdf,.jpg,.jpeg,.png" required>
        </div>

        <div class="mk-form-line"></div>

        <div class="mk-form-bottom">
          <div></div>

          <div class="mk-form-actions">
            <a class="mk-btn" href="index.php?url=home">Cancelar</a>
            <button class="mk-btn mk-btn-primary" type="submit" id="btnRegistrar">Registrar</button>
          </div>

          <div class="mk-form-validate">
            <button class="mk-btn" type="button" id="btnValidarDatos">Validar Datos</button>
          </div>
        </div>

      </form>
    </div>

  </div>
</div>

<script>
(function(){
  const form = document.getElementById('formTramite');
  const goTo = document.getElementById('go_to');

  const btnValidar = document.getElementById('btnValidarDatos');
  const box = document.getElementById('mkFormError');
  const txt = document.getElementById('mkFormErrorText');

  const dni = document.getElementById('dni');
  const tipoTramite = document.getElementById('tipo_tramite');
  const tipoDoc = document.getElementById('tipo_documento');
  const file = document.getElementById('documento');

  function showError(message, el){
    txt.textContent = message;
    box.style.display = 'block';
    if (el) el.focus();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function resetBorders(){
    [dni, tipoTramite, tipoDoc, file].forEach(e => e.style.borderColor = '');
  }

  btnValidar.addEventListener('click', function(){
    resetBorders();
    box.style.display = 'none';

    const vDni = (dni.value || '').trim();
    const vTT  = (tipoTramite.value || '').trim();
    const vTD  = (tipoDoc.value || '').trim();
    const hasFile = file.files && file.files.length > 0;

    if (!/^\d{8}$/.test(vDni)) {
      dni.style.borderColor = '#b91c1c';
      return showError('Valide sus datos y documentos deben ser correctos. DNI inválido.', dni);
    }
    if (!vTT) {
      tipoTramite.style.borderColor = '#b91c1c';
      return showError('Valide sus datos y documentos deben ser correctos. Seleccione el tipo de trámite.', tipoTramite);
    }
    if (!vTD) {
      tipoDoc.style.borderColor = '#b91c1c';
      return showError('Valide sus datos y documentos deben ser correctos. Seleccione el tipo de documento.', tipoDoc);
    }
    if (!hasFile) {
      file.style.borderColor = '#b91c1c';
      return showError('Valide sus datos y documentos deben ser correctos. Agregue un documento antes de validar.', file);
    }

    // ✅ Ahora SÍ: subimos el archivo al servidor, guardamos en sesión y luego redirigimos a validar
    goTo.value = 'validar';
    form.submit();
  });
})();
</script>
