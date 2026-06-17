let statsCharts = {};

document.addEventListener('DOMContentLoaded', () => {
  renderShell('estadisticas', 'Estadísticas', 'Indicadores operativos y análisis de uso vehicular');
  document.getElementById('page-content').innerHTML = `
    <div class="row g-3 mb-3" id="statsMetrics"></div>
    <div class="row g-3">
      <div class="col-lg-6"><div class="panel"><div class="panel-header"><strong>Solicitudes por estado</strong></div><div class="panel-body chart-box"><canvas id="statsRequests"></canvas></div></div></div>
      <div class="col-lg-6"><div class="panel"><div class="panel-header"><strong>Vehículos por estado</strong></div><div class="panel-body chart-box"><canvas id="statsVehicles"></canvas></div></div></div>
      <div class="col-lg-6"><div class="panel"><div class="panel-header"><strong>Atenciones por área</strong></div><div class="panel-body chart-box"><canvas id="statsAreas"></canvas></div></div></div>
      <div class="col-lg-6"><div class="panel"><div class="panel-header"><strong>Conductores con más atenciones</strong></div><div class="panel-body chart-box"><canvas id="statsDrivers"></canvas></div></div></div>
      <div class="col-12"><div class="panel"><div class="panel-header"><strong>Kilometraje por vehículo</strong></div><div class="panel-body chart-box"><canvas id="statsMileage"></canvas></div></div></div>
    </div>`;
  loadStats();
});

async function loadStats() {
  try {
    const [summary, requests, vehicles, mileage, areas, drivers] = await Promise.all([
      apiRequest('estadisticas', 'resumen'),
      apiRequest('estadisticas', 'solicitudes'),
      apiRequest('estadisticas', 'vehiculos'),
      apiRequest('estadisticas', 'kilometraje'),
      apiRequest('estadisticas', 'areas'),
      apiRequest('estadisticas', 'conductores')
    ]);
    renderStatsMetrics(summary.data);
    drawStats('statsRequests', 'pie', requests.data.por_estado.map((x) => x.estado), requests.data.por_estado.map((x) => Number(x.total)));
    drawStats('statsVehicles', 'pie', vehicles.data.por_estado.map((x) => x.estado), vehicles.data.por_estado.map((x) => Number(x.total)));
    drawStats('statsAreas', 'bar', areas.data.map((x) => x.area), areas.data.map((x) => Number(x.total)));
    drawStats('statsDrivers', 'bar', drivers.data.map((x) => x.conductor), drivers.data.map((x) => Number(x.total)));
    drawStats('statsMileage', 'bar', mileage.data.map((x) => x.placa), mileage.data.map((x) => Number(x.kilometros)));
  } catch (error) {
    showAlert(error.message, 'danger');
  }
}

function renderStatsMetrics(data) {
  const items = [
    ['Total finalizadas', data.atenciones_finalizadas, 'bi-check2-square', 'bg-success-app'],
    ['Rechazadas', data.solicitudes_rechazadas, 'bi-x-circle', 'bg-danger-app'],
    ['Km recorridos', data.kilometros_recorridos, 'bi-speedometer2', 'bg-primary-app'],
    ['Pendientes km final', data.pendientes_km_final, 'bi-exclamation-triangle', 'bg-warning-app']
  ];
  document.getElementById('statsMetrics').innerHTML = items.map(([label, value, icon, color]) => `
    <div class="col-sm-6 col-xl-3"><div class="metric-card"><div class="metric-icon ${color}"><i class="bi ${icon}"></i></div><div><p class="metric-value">${value}</p><p class="metric-label">${label}</p></div></div></div>`).join('');
}

function drawStats(id, type, labels, data) {
  if (statsCharts[id]) statsCharts[id].destroy();
  statsCharts[id] = new Chart(document.getElementById(id), {
    type,
    data: { labels, datasets: [{ data, backgroundColor: chartColors(), borderWidth: 1 }] },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { position: type === 'bar' ? 'top' : 'bottom' } },
      scales: type === 'bar' ? { y: { beginAtZero: true, ticks: { precision: 0 } } } : {}
    }
  });
}
