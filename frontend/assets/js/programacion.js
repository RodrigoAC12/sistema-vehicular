let selectedSuggestion = null;

document.addEventListener('DOMContentLoaded', () => {
  renderShell('programacion', 'Programación', 'Asignación de vehículos, conductores e inicio de rutas');
  document.getElementById('page-content').innerHTML = `
    <div class="row g-3">
      <div class="col-xl-4">
        <div class="panel">
          <div class="panel-header"><strong>Asignar atención</strong><i class="bi bi-calendar-plus"></i></div>
          <div class="panel-body">
            <form id="programacionForm" class="row g-3">
              <div class="col-12"><label class="form-label">Solicitud pendiente</label><select class="form-select" id="solicitudSelect" name="id_solicitud" required></select></div>
              <div class="col-12">
                <button class="btn btn-outline-primary btn-icon w-100" type="button" id="suggestVehicle"><i class="bi bi-stars"></i>Sugerir vehículo</button>
              </div>
              <div class="col-12" id="suggestionBox"></div>
              <div class="col-12"><label class="form-label">Vehículo</label><select class="form-select" id="vehiculoSelect" name="id_vehiculo" required></select></div>
              <div class="col-12"><label class="form-label">Conductor</label><select class="form-select" id="conductorSelect" name="id_conductor" required></select></div>
              <div class="col-6"><label class="form-label">Fecha</label><input class="form-control" type="date" name="fecha_programada" id="fechaProgramada"></div>
              <div class="col-6"><label class="form-label">Hora</label><input class="form-control" type="time" name="hora_programada" id="horaProgramada"></div>
              <div class="col-12"><label class="form-label">Destino</label><input class="form-control" name="destino" id="destinoProgramado"></div>
              <div class="col-12"><label class="form-label">Observaciones</label><textarea class="form-control" name="observaciones" rows="2"></textarea></div>
              <div class="col-12"><button class="btn btn-success btn-icon w-100"><i class="bi bi-check-circle"></i>Programar</button></div>
            </form>
          </div>
        </div>
        <div class="panel mt-3">
          <div class="panel-header"><strong>Ruta agrupada</strong><i class="bi bi-signpost-split"></i></div>
          <div class="panel-body">
            <form id="rutaForm" class="row g-3">
              <div class="col-12">
                <label class="form-label">Pedidos para la ruta</label>
                <select class="form-select" id="rutaSolicitudes" size="5" multiple required></select>
                <div class="form-help mt-1">Use Ctrl para seleccionar varios pedidos de la misma fecha. Se valida que los asientos alcancen.</div>
              </div>
              <div class="col-12">
                <label class="form-label">Origen</label>
                <input class="form-control" id="origenRuta" name="origen_ruta" value="Empresa JX">
              </div>
              <div class="col-12">
                <button class="btn btn-outline-primary btn-icon w-100" type="button" id="optimizeRoute"><i class="bi bi-map"></i>Optimizar ruta corta</button>
              </div>
              <div class="col-12" id="routePlanBox"></div>
              <div class="col-12"><label class="form-label">Vehículo</label><select class="form-select" id="vehiculoRutaSelect" name="id_vehiculo" required></select></div>
              <div class="col-12"><label class="form-label">Conductor</label><select class="form-select" id="conductorRutaSelect" name="id_conductor" required></select></div>
              <div class="col-6"><label class="form-label">Fecha</label><input class="form-control" type="date" name="fecha_programada" id="fechaRuta"></div>
              <div class="col-6"><label class="form-label">Hora inicio</label><input class="form-control" type="time" name="hora_programada" id="horaRuta"></div>
              <div class="col-12"><label class="form-label">Observaciones</label><textarea class="form-control" name="observaciones" rows="2">Ruta agrupada optimizada</textarea></div>
              <div class="col-12"><button class="btn btn-success btn-icon w-100"><i class="bi bi-check-circle"></i>Crear ruta</button></div>
            </form>
          </div>
        </div>
      </div>
      <div class="col-xl-8">
        <div class="panel mb-3">
          <div class="panel-header"><strong>Solicitudes pendientes</strong><i class="bi bi-hourglass-split"></i></div>
          <div class="panel-body"><div id="pendientesTable"></div></div>
        </div>
        <div class="panel">
          <div class="panel-header"><strong>Programación registrada</strong><i class="bi bi-calendar-check"></i></div>
          <div class="panel-body"><div id="programacionTable"></div></div>
        </div>
      </div>
    </div>
    <div class="modal fade" id="timelineModal" tabindex="-1">
      <div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Línea de tiempo</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" id="timelineBody"></div></div></div>
    </div>`;
  document.getElementById('programacionForm').addEventListener('submit', submitProgramacion);
  document.getElementById('rutaForm').addEventListener('submit', submitRoute);
  document.getElementById('suggestVehicle').addEventListener('click', suggestVehicle);
  document.getElementById('optimizeRoute').addEventListener('click', optimizeRoute);
  document.getElementById('solicitudSelect').addEventListener('change', fillRequestDefaults);
  document.getElementById('rutaSolicitudes').addEventListener('change', fillRouteDefaults);
  loadProgramacionCatalogs();
  loadProgramacion();
  setInterval(loadProgramacion, window.APP_CONFIG.programacionRefreshMs);
});

async function loadProgramacionCatalogs() {
  const [pendientes, vehiculos, conductores] = await Promise.all([
    apiRequest('solicitudes', 'pendientes'),
    apiRequest('vehiculos', 'disponibles'),
    apiRequest('conductores', 'activos')
  ]);
  window.pendingRequests = pendientes.data;
  document.getElementById('solicitudSelect').innerHTML = toOptions(pendientes.data, 'id_solicitud', (s) => `#${s.id_solicitud} - ${s.tipo_solicitud === 'especial' ? 'Especial - ' : ''}${s.area} - ${formatDate(s.fecha_servicio)} ${formatTime(s.hora_servicio)}`, 'Seleccione solicitud');
  document.getElementById('rutaSolicitudes').innerHTML = pendientes.data.map((s) => `<option value="${s.id_solicitud}">#${s.id_solicitud} - ${s.area} - ${formatDate(s.fecha_servicio)} ${formatTime(s.hora_servicio)} - ${s.cantidad_personas} pers.</option>`).join('');
  document.getElementById('vehiculoSelect').innerHTML = toOptions(vehiculos.data, 'id_vehiculo', (v) => `${v.placa} - ${v.marca} ${v.modelo} (${v.capacidad} pers.)`, 'Seleccione vehículo');
  document.getElementById('vehiculoRutaSelect').innerHTML = toOptions(vehiculos.data, 'id_vehiculo', (v) => `${v.placa} - ${v.marca} ${v.modelo} (${v.capacidad} pers.)`, 'Seleccione vehículo');
  document.getElementById('conductorSelect').innerHTML = toOptions(conductores.data, 'id_conductor', (c) => `${c.conductor} - ${c.licencia}`, 'Seleccione conductor');
  document.getElementById('conductorRutaSelect').innerHTML = toOptions(conductores.data, 'id_conductor', (c) => `${c.conductor} - ${c.licencia}`, 'Seleccione conductor');
  renderPendientes(pendientes.data);
}

function fillRequestDefaults() {
  const id = Number(document.getElementById('solicitudSelect').value);
  const s = (window.pendingRequests || []).find((item) => Number(item.id_solicitud) === id);
  if (!s) return;
  document.getElementById('fechaProgramada').value = s.fecha_servicio;
  document.getElementById('horaProgramada').value = formatTime(s.hora_servicio);
  document.getElementById('destinoProgramado').value = s.direccion;
}

function selectedRouteIds() {
  return Array.from(document.getElementById('rutaSolicitudes').selectedOptions).map((option) => Number(option.value)).filter(Boolean);
}

function fillRouteDefaults() {
  const ids = selectedRouteIds();
  const first = (window.pendingRequests || []).find((item) => Number(item.id_solicitud) === ids[0]);
  if (!first) return;
  document.getElementById('fechaRuta').value = first.fecha_servicio;
  document.getElementById('horaRuta').value = formatTime(first.hora_servicio);
  document.getElementById('routePlanBox').innerHTML = '';
}

function renderRoutePlan(plan) {
  const pedidos = plan.pedidos || [];
  document.getElementById('routePlanBox').innerHTML = `
    <div class="alert alert-primary mb-0">
      <div class="d-flex justify-content-between gap-2">
        <strong>Ruta corta estimada</strong>
        <span>${plan.duracion_total_min} min | ${Number(plan.distancia_total_km).toFixed(1)} km</span>
      </div>
      <ol class="mb-0 mt-2">
        ${pedidos.map((p) => `<li><strong>P${p.id_solicitud}</strong> ${p.direccion}<div class="small">${p.duracion_tramo_min} min desde el punto anterior | ${p.cantidad_personas} pers.</div></li>`).join('')}
      </ol>
    </div>`;
}

async function optimizeRoute() {
  const ids = selectedRouteIds();
  if (!ids.length) {
    showAlert('Seleccione al menos un pedido para optimizar la ruta', 'warning');
    return;
  }
  try {
    const result = await apiRequest('programacion', 'optimizar-ruta', {
      method: 'POST',
      body: {
        ids_solicitud: ids,
        origen_ruta: document.getElementById('origenRuta').value || 'Empresa JX'
      }
    });
    renderRoutePlan(result.data);
    const vehicle = await apiRequest('programacion', 'sugerir-vehiculo', { query: { ids_solicitud: ids.join(',') } });
    document.getElementById('vehiculoRutaSelect').value = vehicle.data.id_vehiculo;
  } catch (error) {
    showAlert(error.message, 'danger');
  }
}

function renderPendientes(items) {
  const rows = items.map((s) => `
    <tr>
      <td>#${s.id_solicitud}</td>
      <td><strong>${s.area}</strong><div class="small text-muted">${s.motivo}</div></td>
      <td>${stateBadge(s.tipo_solicitud || 'normal')}${s.tipo_solicitud === 'especial' ? `<div class="mt-1">${stateBadge(s.resultado_especial)}</div>` : ''}</td>
      <td>${formatDate(s.fecha_servicio)} ${formatTime(s.hora_servicio)}</td>
      <td>${s.cantidad_personas}</td>
      <td>${s.direccion}</td>
    </tr>`).join('');
  document.getElementById('pendientesTable').innerHTML = rows ? tableWrap(`<table class="table table-hover"><thead><tr><th>ID</th><th>Área</th><th>Tipo</th><th>Fecha</th><th>Pers.</th><th>Destino</th></tr></thead><tbody>${rows}</tbody></table>`) : emptyState('No hay solicitudes pendientes', 'bi-check-circle');
}

async function suggestVehicle() {
  const id = document.getElementById('solicitudSelect').value;
  if (!id) {
    showAlert('Seleccione una solicitud pendiente', 'warning');
    return;
  }
  try {
    const result = await apiRequest('programacion', 'sugerir-vehiculo', { query: { id_solicitud: id } });
    selectedSuggestion = result.data;
    document.getElementById('vehiculoSelect').value = result.data.id_vehiculo;
    document.getElementById('suggestionBox').innerHTML = `
      <div class="alert alert-primary mb-0">
        <strong>${result.data.placa} - ${result.data.marca} ${result.data.modelo}</strong>
        <div>Capacidad: ${result.data.capacidad} | Km: ${Number(result.data.kilometraje_actual).toLocaleString('es-PE')}</div>
        <div class="small mt-1">${result.data.justificacion}</div>
      </div>`;
  } catch (error) {
    showAlert(error.message, 'danger');
  }
}

async function submitProgramacion(event) {
  event.preventDefault();
  try {
    await apiRequest('programacion', 'crear', { method: 'POST', body: formData(event.target) });
    showAlert('Programación registrada correctamente');
    event.target.reset();
    document.getElementById('suggestionBox').innerHTML = '';
    selectedSuggestion = null;
    await loadProgramacionCatalogs();
    await loadProgramacion();
  } catch (error) {
    showAlert(error.message, 'danger');
  }
}

async function submitRoute(event) {
  event.preventDefault();
  const ids = selectedRouteIds();
  if (!ids.length) {
    showAlert('Seleccione al menos un pedido para crear la ruta', 'warning');
    return;
  }
  const data = formData(event.target);
  data.ids_solicitud = ids;
  try {
    const result = await apiRequest('programacion', 'crear-ruta', { method: 'POST', body: data });
    showAlert(`Ruta ${result.data.codigo_ruta} registrada correctamente`);
    event.target.reset();
    document.getElementById('origenRuta').value = 'Empresa JX';
    document.getElementById('routePlanBox').innerHTML = '';
    await loadProgramacionCatalogs();
    await loadProgramacion();
  } catch (error) {
    showAlert(error.message, 'danger');
  }
}

async function loadProgramacion() {
  try {
    const result = await apiRequest('programacion', 'listar');
    const rows = result.data.map((p) => `
      <tr>
        <td>#${p.id_programacion}</td>
        <td>${formatDate(p.fecha_programada)}<div class="small text-muted">${formatTime(p.hora_programada)}</div></td>
        <td>${p.codigo_ruta ? `<strong>${p.codigo_ruta}</strong><div class="small text-muted">Orden ${p.orden_ruta} | ${p.duracion_ruta_min} min</div>` : '<span class="text-muted">Individual</span>'}</td>
        <td><strong>${p.destino}</strong><div class="small text-muted">${p.area}</div></td>
        <td>${p.placa}<div class="small text-muted">${p.marca} ${p.modelo}</div></td>
        <td>${p.conductor}</td>
        <td>${stateBadge(p.estado)}</td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-primary" onclick="showTimeline(${p.id_programacion})" title="Línea de tiempo"><i class="bi bi-diagram-3"></i></button>
          ${p.estado === 'programada' ? `<button class="btn btn-sm btn-outline-success" onclick="startRoute(${p.id_programacion})" title="Iniciar ruta"><i class="bi bi-play-circle"></i></button>` : ''}
          ${['programada','en_ruta'].includes(p.estado) ? `<button class="btn btn-sm btn-outline-danger" onclick="cancelProgramacion(${p.id_programacion})" title="Cancelar"><i class="bi bi-x-circle"></i></button>` : ''}
        </td>
      </tr>`).join('');
    document.getElementById('programacionTable').innerHTML = rows ? tableWrap(`<table class="table table-hover"><thead><tr><th>ID</th><th>Fecha</th><th>Ruta</th><th>Destino</th><th>Vehículo</th><th>Conductor</th><th>Estado</th><th></th></tr></thead><tbody>${rows}</tbody></table>`) : emptyState('No hay programaciones registradas');
  } catch (error) {
    showAlert(error.message, 'danger');
  }
}

async function startRoute(id) {
  if (!confirmAction('¿Confirma iniciar la ruta? Debe existir kilometraje inicial.')) return;
  try {
    await apiRequest('programacion', 'iniciar-ruta', { method: 'POST', body: { id_programacion: id } });
    showAlert('Ruta iniciada correctamente');
    loadProgramacion();
  } catch (error) {
    showAlert(error.message, 'danger');
  }
}

async function cancelProgramacion(id) {
  if (!confirmAction('¿Desea cancelar esta programación?')) return;
  try {
    await apiRequest('programacion', 'cancelar', { method: 'PUT', body: { id_programacion: id } });
    showAlert('Programación cancelada');
    await loadProgramacionCatalogs();
    await loadProgramacion();
  } catch (error) {
    showAlert(error.message, 'danger');
  }
}

async function showTimeline(id) {
  const result = await apiRequest('programacion', 'detalle', { query: { id } });
  const p = result.data.programacion;
  document.getElementById('timelineBody').innerHTML = `
    <div class="mb-3">
      <strong>${p.destino}</strong>
      <div class="text-muted">${formatDate(p.fecha_programada)} ${formatTime(p.hora_programada)} | ${p.placa} | ${p.conductor}</div>
      ${p.codigo_ruta ? `<div class="small mt-1">Ruta ${p.codigo_ruta} | Orden ${p.orden_ruta} | ${p.duracion_ruta_min} min estimados</div>` : ''}
    </div>
    <ul class="timeline">
      ${result.data.timeline.map((item) => `<li><i class="bi ${item.complete ? 'bi-check-circle-fill done' : 'bi-circle'}"></i><span class="${item.complete ? 'done' : ''}">${item.label}</span></li>`).join('')}
    </ul>`;
  new bootstrap.Modal(document.getElementById('timelineModal')).show();
}
