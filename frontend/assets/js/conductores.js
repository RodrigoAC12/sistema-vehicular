document.addEventListener('DOMContentLoaded', () => {
  renderShell('conductores', 'Conductores', 'Conductores asociados a usuarios del sistema');
  document.getElementById('page-content').innerHTML = `
    <div class="row g-3">
      <div class="col-xl-4">
        <div class="panel">
          <div class="panel-header"><strong>Registrar conductor</strong><i class="bi bi-person-badge"></i></div>
          <div class="panel-body">
            <form id="conductorForm" class="row g-3">
              <div class="col-12"><label class="form-label">Usuario</label><select class="form-select" name="id_usuario" id="usuarioConductorSelect" required></select></div>
              <div class="col-12"><label class="form-label">Licencia</label><input class="form-control" name="licencia" required></div>
              <div class="col-12"><label class="form-label">Teléfono</label><input class="form-control" name="telefono" required></div>
              <div class="col-12"><button class="btn btn-primary btn-icon w-100"><i class="bi bi-save"></i>Guardar conductor</button></div>
            </form>
          </div>
        </div>
      </div>
      <div class="col-xl-8">
        <div class="panel">
          <div class="panel-header"><strong>Lista de conductores</strong><i class="bi bi-table"></i></div>
          <div class="panel-body"><div id="conductoresTable"></div></div>
        </div>
      </div>
    </div>`;
  document.getElementById('conductorForm').addEventListener('submit', submitConductor);
  loadConductoresCatalog();
  loadConductores();
});

async function loadConductoresCatalog() {
  const users = await apiRequest('auth', 'usuarios');
  const candidatos = users.data.filter((u) => u.rol === 'conductor' && u.estado === 'activo');
  document.getElementById('usuarioConductorSelect').innerHTML = toOptions(candidatos, 'id_usuario', (u) => `${u.nombres} ${u.apellidos} - ${u.email}`, 'Seleccione usuario conductor');
}

async function submitConductor(event) {
  event.preventDefault();
  try {
    await apiRequest('conductores', 'crear', { method: 'POST', body: formData(event.target) });
    showAlert('Conductor registrado correctamente');
    event.target.reset();
    loadConductores();
  } catch (error) {
    showAlert(error.message, 'danger');
  }
}

async function loadConductores() {
  try {
    const result = await apiRequest('conductores', 'listar');
    const rows = result.data.map((c) => `
      <tr>
        <td><strong>${c.nombres} ${c.apellidos}</strong><div class="small text-muted">${c.email}</div></td>
        <td>${c.licencia}</td>
        <td>${c.telefono}</td>
        <td>${stateBadge(c.estado)}</td>
        <td class="text-end">
          <button class="btn btn-sm ${c.estado === 'activo' ? 'btn-outline-secondary' : 'btn-outline-success'}" onclick="changeConductorEstado(${c.id_conductor}, '${c.estado === 'activo' ? 'inactivo' : 'activo'}')">
            <i class="bi ${c.estado === 'activo' ? 'bi-pause-circle' : 'bi-check-circle'}"></i>
          </button>
        </td>
      </tr>`).join('');
    document.getElementById('conductoresTable').innerHTML = rows ? tableWrap(`<table class="table table-hover"><thead><tr><th>Conductor</th><th>Licencia</th><th>Teléfono</th><th>Estado</th><th></th></tr></thead><tbody>${rows}</tbody></table>`) : emptyState('No hay conductores registrados');
  } catch (error) {
    showAlert(error.message, 'danger');
  }
}

async function changeConductorEstado(id, estado) {
  try {
    await apiRequest('conductores', 'actualizar-estado', { method: 'PUT', body: { id_conductor: id, estado } });
    showAlert('Estado de conductor actualizado');
    loadConductores();
  } catch (error) {
    showAlert(error.message, 'danger');
  }
}
