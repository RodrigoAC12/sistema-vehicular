document.addEventListener('DOMContentLoaded', () => {
  renderShell('retornos', 'Retornos', 'Cierre de atenciones y reingreso de unidades a cola');
  document.getElementById('page-content').innerHTML = `
    <div class="row g-3">
      <div class="col-lg-5">
        <div class="panel">
          <div class="panel-header"><strong>Registrar retorno</strong><i class="bi bi-arrow-return-left"></i></div>
          <div class="panel-body">
            <form id="retornoForm" class="row g-3">
              <div class="col-12"><label class="form-label">Vehículo en ruta</label><select class="form-select" name="id_programacion" id="retornoProgramacion" required></select></div>
              <div class="col-12"><label class="form-label">Observaciones</label><textarea class="form-control" name="observaciones" rows="3"></textarea></div>
              <div class="col-12"><button class="btn btn-success btn-icon w-100"><i class="bi bi-check-circle"></i>Confirmar retorno</button></div>
            </form>
          </div>
        </div>
      </div>
      <div class="col-lg-7">
        <div class="panel mb-3">
          <div class="panel-header"><strong>Vehículos en ruta</strong><i class="bi bi-geo-alt"></i></div>
          <div class="panel-body"><div id="enRutaTable"></div></div>
        </div>
        <div class="panel">
          <div class="panel-header"><strong>Historial de retornos</strong><i class="bi bi-clock-history"></i></div>
          <div class="panel-body"><div id="retornosTable"></div></div>
        </div>
      </div>
    </div>`;
  document.getElementById('retornoForm').addEventListener('submit', submitRetorno);
  loadRetornos();
});

async function loadRetornos() {
  const [enRuta, retornos] = await Promise.all([
    apiRequest('retorno', 'en-ruta'),
    apiRequest('retorno', 'listar')
  ]);
  document.getElementById('retornoProgramacion').innerHTML = toOptions(enRuta.data, 'id_programacion', (p) => `#${p.id_programacion} - ${p.placa} - ${p.destino}`, 'Seleccione atención en ruta');
  renderEnRuta(enRuta.data);
  renderRetornos(retornos.data);
}

function renderEnRuta(items) {
  const rows = items.map((p) => `
    <tr>
      <td>#${p.id_programacion}</td>
      <td>${p.placa}<div class="small text-muted">${p.marca} ${p.modelo}</div></td>
      <td>${p.conductor}</td>
      <td>${p.destino}</td>
      <td>${stateBadge(p.estado)}</td>
    </tr>`).join('');
  document.getElementById('enRutaTable').innerHTML = rows ? tableWrap(`<table class="table table-hover"><thead><tr><th>ID</th><th>Vehículo</th><th>Conductor</th><th>Destino</th><th>Estado</th></tr></thead><tbody>${rows}</tbody></table>`) : emptyState('No hay vehículos en ruta');
}

function renderRetornos(items) {
  const rows = items.map((r) => `
    <tr>
      <td>#${r.id_retorno}</td>
      <td>${r.placa}<div class="small text-muted">${r.marca} ${r.modelo}</div></td>
      <td>${r.conductor}</td>
      <td>${formatTime(r.hora_salida)} - ${formatTime(r.hora_retorno)}</td>
      <td>${r.observaciones || ''}</td>
    </tr>`).join('');
  document.getElementById('retornosTable').innerHTML = rows ? tableWrap(`<table class="table table-hover"><thead><tr><th>ID</th><th>Vehículo</th><th>Conductor</th><th>Horario</th><th>Obs.</th></tr></thead><tbody>${rows}</tbody></table>`) : emptyState('No hay retornos registrados');
}

async function submitRetorno(event) {
  event.preventDefault();
  if (!confirmAction('¿Confirma el retorno de la unidad?')) return;
  try {
    await apiRequest('retorno', 'registrar', { method: 'POST', body: formData(event.target) });
    showAlert('Retorno registrado. Vehículo disponible y reingresado a cola.');
    event.target.reset();
    loadRetornos();
  } catch (error) {
    showAlert(error.message, 'danger');
  }
}
