document.addEventListener('DOMContentLoaded', () => {
  document.body.classList.add('public-page');
  document.getElementById('publicApp').innerHTML = `
    <header class="public-header">
      <div class="container-fluid">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
          <div class="d-flex align-items-center gap-3">
            <div class="brand-mark"><i class="bi bi-truck-front"></i></div>
            <div>
              <h1 class="h3 mb-1">Programación vehicular</h1>
              <div class="opacity-75">Panel público de atenciones programadas</div>
            </div>
          </div>
          <div class="text-end">
            <div class="small opacity-75">Actualización</div>
            <strong id="publicClock">--:--</strong>
          </div>
        </div>
      </div>
    </header>
    <main class="container-fluid py-4">
      <div id="publicMessage"></div>
      <div class="row g-3" id="publicCards"></div>
    </main>`;
  loadPublicPanel();
  setInterval(loadPublicPanel, window.APP_CONFIG.publicRefreshMs);
});

async function loadPublicPanel() {
  document.getElementById('publicClock').textContent = new Date().toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' });
  try {
    const result = await apiRequest('programacion', 'publica', { auth: false, throwOnError: false });
    if (!result.data?.visible) {
      document.getElementById('publicMessage').innerHTML = `<div class="alert alert-warning"><i class="bi bi-hourglass-split me-2"></i>${result.message}</div>`;
      document.getElementById('publicCards').innerHTML = '';
      return;
    }
    document.getElementById('publicMessage').innerHTML = `<div class="alert alert-primary"><i class="bi bi-calendar-check me-2"></i>Programación para ${formatDate(result.data.fecha)}</div>`;
    const items = result.data.programaciones || [];
    document.getElementById('publicCards').innerHTML = items.length ? items.map((p) => `
      <div class="col-md-6 col-xl-4">
        <article class="public-card p-3">
          <div class="d-flex justify-content-between align-items-start gap-3">
            <div class="public-time">${formatTime(p.hora_programada)}</div>
            ${stateBadge(p.estado)}
          </div>
          <h2 class="h5 mt-3 mb-2">${p.codigo_ruta ? `Ruta ${p.codigo_ruta}` : p.destino}</h2>
          ${p.codigo_ruta && p.pedidos?.length > 1 ? `<div class="small mb-2"><strong>Atendiendo:</strong> ${p.pedidos.map((pedido) => `P${pedido.id_solicitud}`).join(' - ')}</div>` : ''}
          ${p.codigo_ruta && p.pedidos?.length ? `<ul class="small ps-3 mb-2">${p.pedidos.map((pedido) => `<li>${pedido.destino}</li>`).join('')}</ul>` : ''}
          <div class="row g-2 small">
            <div class="col-12"><i class="bi bi-building me-1"></i>${p.area}</div>
            <div class="col-12"><i class="bi bi-person-badge me-1"></i>${p.conductor}</div>
            <div class="col-12"><i class="bi bi-truck me-1"></i>${p.placa} - ${p.marca} ${p.modelo}</div>
            ${p.duracion_ruta_min > 0 ? `<div class="col-12"><i class="bi bi-signpost-split me-1"></i>${p.duracion_ruta_min} min estimados | ${Number(p.distancia_ruta_km).toFixed(1)} km</div>` : ''}
          </div>
        </article>
      </div>`).join('') : `<div class="col-12">${emptyState('No hay programación publicada para la fecha')}</div>`;
  } catch (error) {
    document.getElementById('publicMessage').innerHTML = `<div class="alert alert-danger">${error.message}</div>`;
  }
}
