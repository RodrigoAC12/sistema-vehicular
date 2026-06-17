let kmStarted = [];

document.addEventListener('DOMContentLoaded', () => {
  renderShell('kilometraje', 'Kilometraje', 'Registro inicial, cierre y cálculo automático de recorrido');
  document.getElementById('page-content').innerHTML = `
    <div class="row g-3">
      <div class="col-lg-6">
        <div class="panel">
          <div class="panel-header"><strong>Kilometraje inicial</strong><i class="bi bi-play-circle"></i></div>
          <div class="panel-body">
            <form id="kmInicialForm" class="row g-3">
              <div class="col-12"><label class="form-label">Programación</label><select class="form-select" name="id_programacion" id="kmInicialProgramacion" required></select></div>
              <div class="col-12"><label class="form-label">Kilometraje inicial</label><input class="form-control" type="number" name="kilometraje_inicial" min="0" required></div>
              <div class="col-12"><button class="btn btn-primary btn-icon w-100"><i class="bi bi-speedometer"></i>Registrar inicial</button></div>
            </form>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="panel">
          <div class="panel-header"><strong>Kilometraje final</strong><i class="bi bi-flag"></i></div>
          <div class="panel-body">
            <form id="kmFinalForm" class="row g-3">
              <div class="col-12"><label class="form-label">Programación iniciada</label><select class="form-select" name="id_programacion" id="kmFinalProgramacion" required></select></div>
              <div class="col-12"><label class="form-label">Kilometraje final</label><input class="form-control" type="number" name="kilometraje_final" id="kmFinalInput" min="0" required></div>
              <div class="col-12"><div class="alert alert-info mb-0" id="kmPreview">Recorrido calculado: 0 km</div></div>
              <div class="col-12"><button class="btn btn-success btn-icon w-100"><i class="bi bi-calculator"></i>Registrar final</button></div>
            </form>
          </div>
        </div>
      </div>
      <div class="col-12">
        <div class="panel">
          <div class="panel-header"><strong>Historial de kilometrajes</strong><i class="bi bi-clock-history"></i></div>
          <div class="panel-body"><div id="kilometrajeTable"></div></div>
        </div>
      </div>
    </div>`;
  document.getElementById('kmInicialForm').addEventListener('submit', submitKmInicial);
  document.getElementById('kmFinalForm').addEventListener('submit', submitKmFinal);
  document.getElementById('kmFinalInput').addEventListener('input', previewKm);
  document.getElementById('kmFinalProgramacion').addEventListener('change', previewKm);
  loadKmData();
});

async function loadKmData() {
  const [programaciones, historial] = await Promise.all([
    apiRequest('programacion', 'listar'),
    apiRequest('kilometraje', 'listar')
  ]);
  const programadas = programaciones.data.filter((p) => p.estado === 'programada' && (!p.codigo_ruta || Number(p.orden_ruta) === 1));
  document.getElementById('kmInicialProgramacion').innerHTML = toOptions(programadas, 'id_programacion', (p) => `${p.codigo_ruta ? `Ruta ${p.codigo_ruta}` : `#${p.id_programacion}`} - ${p.placa} - ${p.destino}`, 'Seleccione programación');
  kmStarted = historial.data.filter((k) => k.estado === 'iniciado');
  document.getElementById('kmFinalProgramacion').innerHTML = toOptions(kmStarted, 'id_programacion', (k) => `${k.codigo_ruta ? `Ruta ${k.codigo_ruta}` : `#${k.id_programacion}`} - ${k.placa} - inicial ${k.kilometraje_inicial} km`, 'Seleccione programación');
  renderKmHistory(historial.data);
  previewKm();
}

function renderKmHistory(items) {
  const rows = items.map((k) => `
    <tr>
      <td>#${k.id_kilometraje}</td>
      <td>${k.placa}<div class="small text-muted">${k.marca} ${k.modelo}</div></td>
      <td>${k.conductor}</td>
      <td>${Number(k.kilometraje_inicial).toLocaleString('es-PE')} km</td>
      <td>${k.kilometraje_final ? Number(k.kilometraje_final).toLocaleString('es-PE') + ' km' : '-'}</td>
      <td>${k.kilometros_recorridos || 0} km</td>
      <td>${stateBadge(k.estado)}</td>
    </tr>`).join('');
  document.getElementById('kilometrajeTable').innerHTML = rows ? tableWrap(`<table class="table table-hover"><thead><tr><th>ID</th><th>Vehículo</th><th>Conductor</th><th>Inicial</th><th>Final</th><th>Recorrido</th><th>Estado</th></tr></thead><tbody>${rows}</tbody></table>`) : emptyState('No hay kilometrajes registrados');
}

async function submitKmInicial(event) {
  event.preventDefault();
  try {
    await apiRequest('kilometraje', 'registrar-inicial', { method: 'POST', body: formData(event.target) });
    showAlert('Kilometraje inicial registrado');
    event.target.reset();
    loadKmData();
  } catch (error) {
    showAlert(error.message, 'danger');
  }
}

async function submitKmFinal(event) {
  event.preventDefault();
  try {
    const result = await apiRequest('kilometraje', 'registrar-final', { method: 'POST', body: formData(event.target) });
    showAlert(`Kilometraje final registrado. Recorrido: ${result.data.kilometros_recorridos} km`);
    event.target.reset();
    loadKmData();
  } catch (error) {
    showAlert(error.message, 'danger');
  }
}

function previewKm() {
  const id = Number(document.getElementById('kmFinalProgramacion')?.value || 0);
  const finalValue = Number(document.getElementById('kmFinalInput')?.value || 0);
  const selected = kmStarted.find((k) => Number(k.id_programacion) === id);
  const recorrido = selected && finalValue > Number(selected.kilometraje_inicial) ? finalValue - Number(selected.kilometraje_inicial) : 0;
  const host = document.getElementById('kmPreview');
  if (host) host.textContent = `Recorrido calculado: ${recorrido} km`;
}
