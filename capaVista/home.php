<?php
// src/capaVista/home.php

$anuncios = [
  [
    'tag' => 'Importante',
    'titulo' => 'Actualización del motor de IA',
    'fecha' => '12 de octubre 2025',
    'desc' => 'Mejora en la precisión del análisis para reducir falsos positivos.',
  ],
  [
    'tag' => 'Alerta',
    'titulo' => 'Mantenimiento programado',
    'fecha' => '20 de octubre 2025',
    'desc' => 'El sistema estará en mantenimiento de 2 a 4 a.m., puede haber interrupciones temporales.',
  ],
  [
    'tag' => 'Novedad',
    'titulo' => 'Nuevas variables incorporadas',
    'fecha' => '18 de octubre 2025',
    'desc' => 'Se añadieron nuevos criterios de evaluación para detectar fraudes complejos.',
  ],
  [
    'tag' => 'Recordatorio',
    'titulo' => 'Capacitación para gestores y auditores',
    'fecha' => '25 de octubre 2025',
    'desc' => 'Inscripciones abiertas para el taller de manejo del módulo de análisis de datos.',
  ],
];

function tag_class(string $tag): string {
  return match ($tag) {
    'Importante' => 'tag-importante',
    'Alerta' => 'tag-alerta',
    'Novedad' => 'tag-novedad',
    'Recordatorio' => 'tag-recordatorio',
    default => 'tag-default',
  };
}

$url = $_GET['url'] ?? 'home';
function is_active(string $current, string $route): string {
  return $current === $route ? 'is-active' : '';
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

    <!-- Barra de pestañas -->
    <div class="mk-tabsbar">
      <a class="mk-tab <?= is_active($url,'home') ?>" href="index.php?url=home">Inicio</a>

      <!-- ✅ Análisis de Datos debe ir al registro -->
      <a class="mk-tab <?= is_active($url,'tramites.registrar') ?>" href="index.php?url=tramites.registrar">
        Análisis de Datos
      </a>

      <a class="mk-tab <?= is_active($url,'alertas.listar') ?>" href="index.php?url=alertas.listar">Alertas de Fraude</a>
      <a class="mk-tab <?= is_active($url,'dashboard') ?>" href="index.php?url=dashboard">Visualización</a>
      <a class="mk-tab <?= is_active($url,'contacto') ?>" href="index.php?url=contacto">Contacto</a>
    </div>

    <!-- Cuerpo -->
    <div class="mk-body">
      <!-- Izquierda -->
      <div class="mk-left">
        <div class="mk-box mk-quote">
          “Componente de IA que analiza grandes volúmenes de datos de los trámites
          para identificar patrones anómalos que podrían indicar fraude.”
        </div>

        <div class="mk-box mk-illustration">
          <img src="img/inicio_ia.jpg" class="mk-banner-img" alt="Banner IA"
               onerror="this.style.display='none'; document.getElementById('imgFallback').style.display='flex';">
          <div id="imgFallback" class="mk-img-fallback" style="display:none;">
            <div>
              <b>Ilustración</b><br>
              Coloca tu imagen en <b>img/inicio_ia.jpg</b>
            </div>
          </div>
        </div>
      </div>

      <!-- Derecha -->
      <div class="mk-right">
        <div class="mk-box mk-anuncios">
          <div class="mk-anuncios-title">ANUNCIOS:</div>

          <?php foreach ($anuncios as $i => $a): ?>
            <div class="mk-anuncio-item">
              <div class="mk-anuncio-head">
                <span class="mk-tag <?= tag_class($a['tag']) ?>"><?= htmlspecialchars($a['tag']) ?></span>
                <span class="mk-anuncio-line">
                  <?= htmlspecialchars($a['titulo']) ?> – <?= htmlspecialchars($a['fecha']) ?>
                </span>
              </div>
              <div class="mk-anuncio-desc"><?= htmlspecialchars($a['desc']) ?></div>
            </div>
            <?php if ($i < count($anuncios) - 1): ?>
              <div class="mk-divider"></div>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Botones inferiores -->
    <div class="mk-actions">
      <!-- ✅ Registrar Trámite también va al mismo registro -->
      <a class="mk-btn" href="index.php?url=tramites.registrar">Registrar Trámite</a>
      <a class="mk-btn" href="index.php?url=dashboard">Dashboard</a>
      <a class="mk-btn" href="index.php?url=tramites.listar">Trámites Registrados</a>
    </div>

  </div>
</div>
