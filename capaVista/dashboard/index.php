<style>
/* Contenedor del gráfico: SIN scroll, todo cabe */
.mk-bars{
  height: 190px;
  display: grid;
  grid-auto-flow: column;
  grid-auto-columns: minmax(52px, 1fr); /* se adapta al ancho disponible */
  gap: 10px;
  align-items: end;

  padding: 14px 14px 12px 14px;
  border-radius: 16px;
  overflow: hidden; /* ✅ no se sale del cuadro */
  background: linear-gradient(135deg, rgba(29,78,216,.05), rgba(20,184,166,.05));
}

/* Item por día */
.mk-bar-item{
  min-width: 0; /* ✅ clave para que no obligue a scroll */
  height: 100%;
  display:flex;
  flex-direction:column;
  align-items:center;
  justify-content:flex-end;
  gap: 6px;
}

/* Par de barras (docs + alertas) */
.mk-bar-pair{
  width: 100%;
  max-width: 64px;      /* ✅ evita barras gigantes */
  height: 125px;
  display:flex;
  gap: 8px;
  align-items:flex-end;
  justify-content:center;
}

/* barras: se adaptan */
.mk-bar-doc,
.mk-bar-alert{
  width: clamp(10px, 20%, 18px); /* ✅ se hace más delgado si hay muchos días */
  border-radius: 10px;
}

/* docs */
.mk-bar-doc{
  border: 1px solid rgba(20,184,166,.35);
  background: rgba(20,184,166,.20);
}

/* alertas */
.mk-bar-alert{
  border: 1px solid rgba(29,78,216,.35);
  background: rgba(29,78,216,.20);
}

/* Etiquetas */
.mk-bar-label{
  font-size: 12px;
  color:#334155;
  line-height: 1;
  max-width: 100%;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis; /* ✅ si es muy angosto, no rompe */
}

.mk-bar-sub{
  font-size: 12px;
  color:#475569;
  line-height: 1;
}

/* Leyenda */
.mk-legend{
  display:flex;
  gap:12px;
  align-items:center;
  margin-top:10px;
  font-size:13px;
  color:#334155;
}
.mk-dot{
  width:10px;height:10px;border-radius:999px;display:inline-block;
}
.mk-dot-doc{ background: rgba(20,184,166,.55); border:1px solid rgba(20,184,166,.55); }
.mk-dot-alert{ background: rgba(29,78,216,.55); border:1px solid rgba(29,78,216,.55); }

/* ✅ Responsive: en pantallas muy pequeñas, permite 2 filas (evita que se amontone) */
@media (max-width: 760px){
  .mk-bars{
    grid-auto-flow: row;
    grid-template-columns: repeat(4, 1fr);
    grid-auto-rows: 1fr;
  }
  .mk-bar-pair{ height: 110px; }
}
</style>
