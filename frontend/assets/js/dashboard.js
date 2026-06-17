let dashboardCharts = {};

document.addEventListener('DOMContentLoaded', () => {
  renderShell('dashboard', 'Dashboard', 'Control operativo de solicitudes, unidades y atenciones');
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
    renderMetrics(summary.data);
    renderSmartAlerts(summary.data.alertas || []);
    drawChart('requestsChart', 'doughnut', solicitudes.data.por_estado.map((x) => x.estado), solicitudes.data.por_estado.map((x) => Number(x.total)));
    drawChart('vehiclesChart', 'doughnut', vehiculos.data.por_estado.map((x) => x.estado), vehiculos.data.por_estado.map((x) => Number(x.total)));
    drawChart('areasChart', 'bar', areas.data.map((x) => x.area), areas.data.map((x) => Number(x.total)));
    drawChart('mileageChart', 'bar', kilometraje.data.map((x) => x.placa), kilometraje.data.map((x) => Number(x.kilometros)));
  } catch (error) {
    showAlert(error.message, 'danger');
  }
}

function renderMetrics(data) {
  const metrics = [
    ['Solicitudes de hoy', data.solicitudes_hoy, 'bi-calendar-day', 'bg-primary-app', 'Servicios solicitados para hoy'],
    ['Pendientes', data.solicitudes_pendientes, 'bi-hourglass-split', 'bg-warning-app', 'Requieren programación'],
    ['Programadas', data.solicitudes_programadas, 'bi-calendar-check', 'bg-info-app', 'Listas para atención'],
    ['Disponibles', data.vehiculos_disponibles, 'bi-check-circle', 'bg-success-app', 'Unidades operativas'],
    ['En ruta', data.vehiculos_en_ruta, 'bi-geo-alt', 'bg-info-app', 'Atenciones activas'],
    ['Mantenimiento', data.vehiculos_mantenimiento, 'bi-tools', 'bg-muted-app', 'No asignables'],
    ['Finalizadas', data.atenciones_finalizadas, 'bi-check2-square', 'bg-success-app', 'Atenciones cerradas'],
    ['Km recorridos', data.kilometros_recorridos, 'bi-speedometer2', 'bg-danger-app', 'Total acumulado']
  ];
  document.getElementById('metricGrid').innerHTML = metrics.map(([label, value, icon, color, text]) => `
    <div class="col-sm-6 col-xl-3">
      <div class="metric-card">
        <div class="metric-icon ${color}"><i class="bi ${icon}"></i></div>
        <div><p class="metric-value">${value}</p><p class="mb-1 fw-bold">${label}</p><p class="metric-label">${text}</p></div>
      </div>
    </div>`).join('');
}

function renderSmartAlerts(alerts) {
  const host = document.getElementById('smartAlerts');
  if (!alerts.length) {
    host.innerHTML = '<div class="alert alert-success mb-0"><i class="bi bi-check-circle me-2"></i>Operación estable sin alertas críticas.</div>';
    return;
  }
  host.innerHTML = `<div class="alert alert-warning mb-0"><strong>Alertas inteligentes</strong><ul class="mb-0 mt-2">${alerts.map((x) => `<li>${x}</li>`).join('')}</ul></div>`;
}

function drawChart(id, type, labels, data) {
  if (dashboardCharts[id]) dashboardCharts[id].destroy();
  dashboardCharts[id] = new Chart(document.getElementById(id), {
    type,
    data: {
      labels,
      datasets: [{ data, backgroundColor: chartColors(), borderWidth: 1 }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { position: type === 'doughnut' ? 'bottom' : 'top' } },
      scales: type === 'bar' ? { y: { beginAtZero: true, ticks: { precision: 0 } } } : {}
    }
  });
}
