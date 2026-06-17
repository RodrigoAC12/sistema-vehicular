document.addEventListener('DOMContentLoaded', () => {
  renderShell('vehiculos', 'Vehículos', 'Registro, disponibilidad y estado operativo de unidades');
  document.getElementById('page-content').innerHTML = `
    <div class="row g-3">
      <div class="col-xl-4">
        <div class="panel">
          <div class="panel-header"><strong>Registrar vehículo</strong><i class="bi bi-truck"></i></div>
          <div class="panel-body">
            <form id="vehiculoForm" class="row g-3">
              <div class="col-6"><label class="form-label">Placa</label><input class="form-control" name="placa" required></div>
              <div class="col-6"><label class="form-label">Año</label><input class="form-control" type="number" name="anio" min="1990" required></div>
              <div class="col-6"><label class="form-label">Marca</label><input class="form-control" name="marca" required></div>
              <div class="col-6"><label class="form-label">Modelo</label><input class="form-control" name="modelo" required></div>
              <div class="col-6"><label class="form-label">Capacidad</label><input class="form-control" type="number" name="capacidad" min="1" required></div>
              <div class="col-6"><label class="form-label">Kilometraje</label><input class="form-control" type="number" name="kilometraje_actual" min="0" required></div>
              <div class="col-12">
                <label class="form-label">Estado</label>
                <select class="form-select" name="estado">
                  <option value="disponible">Disponible</option>
                  <option value="mantenimiento">Mantenimiento</option>
                  <option value="fuera_servicio">Fuera de servicio</option>
                </select>
              </div>
              <div class="col-12"><label class="form-label">Observación</label><textarea class="form-control" name="observacion" rows="2"></textarea></div>
              <div class="col-12"><button class="btn btn-primary btn-icon w-100"><i class="bi bi-save"></i>Guardar vehículo</button></div>
            </form>
          </div>
        </div>
      </div>
      <div class="col-xl-8">
        <div class="panel">
          <div class="panel-header"><strong>Flota vehicular</strong><i class="bi bi-list-check"></i></div>
          <div class="panel-body">
            <div class="d-flex gap-2 mb-3">
              <select class="form-select" id="vehiculoEstadoFiltro">
                <option value="">Todos los estados</option>
                <option value="disponible">Disponible</option>
                <option value="asignado">Asignado</option>
                <option value="en_ruta">En ruta</option>
                <option value="mantenimiento">Mantenimiento</option>
                <option value="fuera_servicio">Fuera de servicio</option>
              </select>
              <button class="btn btn-outline-primary btn-icon" id="filtrarVehiculos"><i class="bi bi-search"></i>Filtrar</button>
            </div>
            <div id="vehiculosTable"></div>
          </div>
        </div>
      </div>
    </div>`;
  document.getElementById('vehiculoForm').addEventListener('submit', submitVehiculo);
  document.getElementById('filtrarVehiculos').addEventListener('click', loadVehiculos);
  loadVehiculos();
});

async function submitVehiculo(event) {
  event.preventDefault();
  try {
    await apiRequest('vehiculos', 'crear', { method: 'POST', body: formData(event.target) });
    showAlert('Vehículo registrado correctamente');
    event.target.reset();
    loadVehiculos();
  } catch (error) {
    showAlert(error.message, 'danger');
  }
}

async function loadVehiculos() {
  try {
    const estado = document.getElementById('vehiculoEstadoFiltro')?.value || '';
    const result = await apiRequest('vehiculos', 'listar', { query: { estado } });
    const rows = result.data.map((v) => `
      <tr>
        <td><span class="fw-bold fs-6">${v.placa}</span><div class="small text-muted">${v.marca} ${v.modelo}</div></td>
        <td>${v.anio}</td>
        <td>${v.capacidad}</td>
        <td>${Number(v.kilometraje_actual).toLocaleString('es-PE')} km</td>
        <td>${stateBadge(v.estado)}</td>
        <td>${v.observacion || ''}</td>
        <td class="text-end">
          <select class="form-select form-select-sm" onchange="changeVehiculoEstado(${v.id_vehiculo}, this.value)">
            ${['disponible','mantenimiento','fuera_servicio'].map((e) => `<option value="${e}" ${v.estado === e ? 'selected' : ''}>${stateLabel(e)}</option>`).join('')}
          </select>
        </td>
      </tr>`).join('');
    document.getElementById('vehiculosTable').innerHTML = rows ? tableWrap(`<table class="table table-hover"><thead><tr><th>Vehículo</th><th>Año</th><th>Cap.</th><th>Kilometraje</th><th>Estado</th><th>Obs.</th><th></th></tr></thead><tbody>${rows}</tbody></table>`) : emptyState('No hay vehículos registrados');
  } catch (error) {
    showAlert(error.message, 'danger');
  }
}

async function changeVehiculoEstado(id, estado) {
  try {
    await apiRequest('vehiculos', 'actualizar-estado', { method: 'PUT', body: { id_vehiculo: id, estado } });
    showAlert('Estado vehicular actualizado');
    loadVehiculos();
  } catch (error) {
    showAlert(error.message, 'danger');
  }
}
