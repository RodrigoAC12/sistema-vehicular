let dashboardCharts = {};

document.addEventListener('DOMContentLoaded', () => {
  renderShell('dashboard', 'Panel de control', 'Control operativo de solicitudes, unidades y atenciones');
  document.getElementById('page-content').innerHTML = `
    <div id="smartAlerts" class="mb-3"></div>
    <div class="row g-3 mb-4" id="metricGrid"></div>
    <div class="row g-3">
      <div class="col-lg-6">
        <div class="panel">
          <div class="panel-header"><strong>Solicitudes por estado</strong><i class="bi bi-file-earmark-text"></i></div>
          <div class="panel-body chart-box"><canvas id="requestsChart"></canvas></div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="panel">
          <div class="panel-header"><strong>Vehículos por estado</strong><i class="bi bi-truck"></i></div>
          <div class="panel-body chart-box"><canvas id="vehiclesChart"></canvas></div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="panel">
          <div class="panel-header"><strong>Atenciones por área</strong><i class="bi bi-building"></i></div>
          <div class="panel-body chart-box"><canvas id="areasChart"></canvas></div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="panel">
          <div class="panel-header"><strong>Kilómetros por vehículo</strong><i class="bi bi-speedometer"></i></div>
          <div class="panel-body chart-box"><canvas id="mileageChart"></canvas></div>
        </div>
      </div>
    </div>`;
  loadDashboard();
  setInterval(loadDashboard, window.APP_CONFIG.dashboardRefreshMs);
});

async function loadDashboard() {
  try {
    const [summary, solicitudes, vehiculos, areas, kilometraje] = await Promise.all([
      apiRequest('estadisticas', 'resumen'),
      apiRequest('estadisticas', 'solicitudes'),
      apiRequest('estadisticas', 'vehiculos'),
      apiRequest('estadisticas', 'areas'),
      apiRequest('estadisticas', 'kilometraje')
    ]);
    const resumen = summary.data || {};
    const solicitudesPorEstado = solicitudes.data?.por_estado || [];
    const vehiculosPorEstado = vehiculos.data?.por_estado || [];
    const areasData = areas.data || [];
    const kilometrajeData = kilometraje.data || [];

    renderMetrics(resumen);
    renderSmartAlerts(resumen.alertas || []);
    drawChart('requestsChart', 'doughnut', solicitudesPorEstado.map((x) => safeText(x.estado, 'Sin estado')), solicitudesPorEstado.map((x) => safeNumber(x.total)), 'Solicitudes');
    drawChart('vehiclesChart', 'doughnut', vehiculosPorEstado.map((x) => safeText(x.estado, 'Sin estado')), vehiculosPorEstado.map((x) => safeNumber(x.total)), 'Vehículos');
    drawChart('areasChart', 'bar', areasData.map((x) => safeText(x.area, 'Sin área')), areasData.map((x) => safeNumber(x.total)), 'Atenciones');
    drawChart('mileageChart', 'bar', kilometrajeData.map((x) => safeText(x.placa, 'Sin placa')), kilometrajeData.map((x) => safeNumber(x.kilometros)), 'Kilómetros');
  } catch (error) {
    showAlert(error.message, 'danger');
  }
}

function renderMetrics(data = {}) {
  const metrics = [
    ['Solicitudes de hoy', safeNumber(data.solicitudes_hoy), 'bi-calendar-day', 'bg-primary-app', 'Servicios solicitados para hoy'],
    ['Pendientes', safeNumber(data.solicitudes_pendientes), 'bi-hourglass-split', 'bg-warning-app', 'Requieren programación'],
    ['Programadas', safeNumber(data.solicitudes_programadas), 'bi-calendar-check', 'bg-info-app', 'Listas para atención'],
    ['Especiales', safeNumber(data.pedidos_especiales_atender), 'bi-lightning-charge', 'bg-primary-app', 'Atendibles por disponibilidad'],
    ['Especiales rechazados', safeNumber(data.pedidos_especiales_rechazados), 'bi-x-circle', 'bg-danger-app', 'Sin vehículo o asientos'],
    ['Disponibles', safeNumber(data.vehiculos_disponibles), 'bi-check-circle', 'bg-success-app', 'Unidades operativas'],
    ['En ruta', safeNumber(data.vehiculos_en_ruta), 'bi-geo-alt', 'bg-info-app', 'Atenciones activas'],
    ['Mantenimiento', safeNumber(data.vehiculos_mantenimiento), 'bi-tools', 'bg-muted-app', 'No asignables'],
    ['Finalizadas', safeNumber(data.atenciones_finalizadas), 'bi-check2-square', 'bg-success-app', 'Atenciones cerradas'],
    ['Km recorridos', safeNumber(data.kilometros_recorridos), 'bi-speedometer2', 'bg-danger-app', 'Total acumulado']
  ];
  document.getElementById('metricGrid').innerHTML = metrics.map(([label, value, icon, color, text]) => `
    <div class="col-sm-6 col-xl-3">
      <div class="metric-card">
        <div class="metric-icon ${color}"><i class="bi ${icon}"></i></div>
        <div><p class="metric-value">${value}</p><p class="mb-1 fw-bold">${label}</p><p class="metric-label">${text}</p></div>
      </div>
    </div>`).join('');
}

function renderSmartAlerts(alerts = []) {
  const host = document.getElementById('smartAlerts');
  if (!alerts.length) {
    host.innerHTML = '<div class="alert alert-success mb-0"><i class="bi bi-check-circle me-2"></i>Operación estable sin alertas críticas.</div>';
    return;
  }
  host.innerHTML = `<div class="alert alert-warning mb-0"><strong>Alertas inteligentes</strong><ul class="mb-0 mt-2">${alerts.map((x) => `<li>${safeText(x)}</li>`).join('')}</ul></div>`;
}

function drawChart(id, type, labels, data, datasetLabel = 'Total') {
  const existing = dashboardCharts[id];
  if (existing) {
    existing.data.labels = labels;
    existing.data.datasets[0].label = datasetLabel;
    existing.data.datasets[0].data = data;
    existing.update('none');
    return;
  }

  dashboardCharts[id] = new Chart(document.getElementById(id), {
    type,
    data: {
      labels,
      datasets: [{ label: datasetLabel, data, backgroundColor: chartColors(), borderWidth: 1 }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: { duration: 0 },
      plugins: { legend: { display: type === 'doughnut', position: 'bottom' } },
      scales: type === 'bar' ? { y: { beginAtZero: true, ticks: { precision: 0 } } } : {}
    }
  });
}
