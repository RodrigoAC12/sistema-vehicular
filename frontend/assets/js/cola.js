let colaActual = [];

document.addEventListener('DOMContentLoaded', () => {
  renderShell('cola', 'Cola vehicular', 'Orden FIFO de unidades disponibles');
  document.getElementById('page-content').innerHTML = `
    <div class="row g-3">
      <div class="col-lg-4">
        <div class="panel">
          <div class="panel-header"><strong>Agregar disponible</strong><i class="bi bi-plus-circle"></i></div>
          <div class="panel-body">
            <form id="colaForm" class="row g-3">
              <div class="col-12"><label class="form-label">Vehículo disponible</label><select class="form-select" id="colaVehiculoSelect" name="id_vehiculo" required></select></div>
              <div class="col-12"><button class="btn btn-primary btn-icon w-100"><i class="bi bi-list-ol"></i>Agregar a cola</button></div>
            </form>
          </div>
        </div>
      </div>
      <div class="col-lg-8">
        <div class="panel">
          <div class="panel-header"><strong>Orden actual</strong><i class="bi bi-list-ol"></i></div>
          <div class="panel-body"><div id="colaTable"></div></div>
        </div>
      </div>
    </div>`;
  document.getElementById('colaForm').addEventListener('submit', submitCola);
  loadCola();
  setInterval(loadCola, window.APP_CONFIG.publicRefreshMs);
});

async function loadCola() {
  try {
    const [cola, disponibles] = await Promise.all([
      apiRequest('cola', 'listar'),
      apiRequest('vehiculos', 'disponibles')
    ]);
    colaActual = cola.data;
    document.getElementById('colaVehiculoSelect').innerHTML = toOptions(disponibles.data, 'id_vehiculo', (v) => `${v.placa} - ${v.marca} ${v.modelo}`, 'Seleccione vehículo');
    renderCola(cola.data);
  } catch (error) {
    showAlert(error.message, 'danger');
  }
}

function renderCola(items) {
  const rows = items.map((c, index) => `
    <tr>
      <td><span class="badge text-bg-primary">${c.orden}</span></td>
      <td><strong>${c.placa}</strong><div class="small text-muted">${c.marca} ${c.modelo}</div></td>
      <td>${c.capacidad}</td>
      <td>${Number(c.kilometraje_actual).toLocaleString('es-PE')} km</td>
      <td>${stateBadge(c.estado_vehiculo)}</td>
      <td class="text-end">
        <button class="btn btn-sm btn-outline-secondary" onclick="moveQueue(${index}, -1)" title="Subir"><i class="bi bi-arrow-up"></i></button>
        <button class="btn btn-sm btn-outline-secondary" onclick="moveQueue(${index}, 1)" title="Bajar"><i class="bi bi-arrow-down"></i></button>
        <button class="btn btn-sm btn-outline-danger" onclick="removeCola(${c.id_cola})" title="Retirar"><i class="bi bi-x-circle"></i></button>
      </td>
    </tr>`).join('');
  document.getElementById('colaTable').innerHTML = rows ? tableWrap(`<table class="table table-hover"><thead><tr><th>Orden</th><th>Vehículo</th><th>Cap.</th><th>Kilometraje</th><th>Estado</th><th></th></tr></thead><tbody>${rows}</tbody></table>`) : emptyState('No hay vehículos en cola');
}

async function submitCola(event) {
  event.preventDefault();
  try {
    await apiRequest('cola', 'agregar', { method: 'POST', body: formData(event.target) });
    showAlert('Vehículo agregado a cola');
    event.target.reset();
    loadCola();
  } catch (error) {
    showAlert(error.message, 'danger');
  }
}

async function removeCola(id) {
  if (!confirmAction('¿Desea retirar este vehículo de la cola?')) return;
  try {
    await apiRequest('cola', 'retirar', { method: 'PUT', body: { id_cola: id } });
    showAlert('Vehículo retirado de cola');
    loadCola();
  } catch (error) {
    showAlert(error.message, 'danger');
  }
}

async function moveQueue(index, direction) {
  const target = index + direction;
  if (target < 0 || target >= colaActual.length) return;
  const reordered = [...colaActual];
  [reordered[index], reordered[target]] = [reordered[target], reordered[index]];
  const ordenes = reordered.map((item, i) => ({ id_cola: item.id_cola, orden: i + 1 }));
  try {
    await apiRequest('cola', 'reordenar', { method: 'PUT', body: { ordenes } });
    showAlert('Cola reordenada');
    loadCola();
  } catch (error) {
    showAlert(error.message, 'danger');
  }
}
