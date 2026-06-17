let solicitudStep = 1;
let specialAvailability = null;

document.addEventListener('DOMContentLoaded', () => {
  renderShell('solicitudes', 'Solicitudes', 'Registro guiado y seguimiento de atenciones vehiculares');
  document.getElementById('page-content').innerHTML = `
    <div class="row g-3">
      <div class="col-xl-5">
        <div class="panel">
          <div class="panel-header"><strong>Nueva solicitud</strong><i class="bi bi-file-earmark-plus"></i></div>
          <div class="panel-body">
            <div class="wizard-steps">
              <button class="wizard-step active" type="button" data-step="1">1. Área</button>
              <button class="wizard-step" type="button" data-step="2">2. Servicio</button>
              <button class="wizard-step" type="button" data-step="3">3. Confirmación</button>
            </div>
            <form id="solicitudForm">
              <section data-section="1">
                <label class="form-label">Área solicitante</label>
                <select class="form-select" name="id_area" id="areaSelect" required></select>
                <label class="form-label mt-3">Tipo de solicitud</label>
                <select class="form-select" name="tipo_solicitud" id="tipoSolicitud">
                  <option value="normal">Normal</option>
                  <option value="especial">Pedido especial</option>
                </select>
                <div class="form-help mt-1">El pedido especial puede ser para hoy, sujeto a disponibilidad de vehículo y asientos.</div>
                <div class="form-help mt-1">La solicitud queda asociada al área institucional seleccionada.</div>
              </section>
              <section data-section="2" class="d-none">
                <div class="row g-3">
                  <div class="col-sm-6">
                    <label class="form-label">Fecha del servicio</label>
                    <input class="form-control" type="date" name="fecha_servicio" id="fechaServicio" required>
                    <div class="form-help" id="fechaServicioHelp">Debe ser mínimo desde mañana.</div>
                  </div>
                  <div class="col-sm-6">
                    <label class="form-label">Hora solicitada</label>
                    <input class="form-control" type="time" name="hora_servicio" min="08:00" max="16:00" required>
                    <div class="form-help">Horario permitido: 08:00 a 16:00.</div>
                  </div>
                  <div class="col-12">
                    <label class="form-label">Dirección o destino</label>
                    <input class="form-control" name="direccion" required>
                  </div>
                  <div class="col-sm-6">
                    <label class="form-label">Cantidad de personas</label>
                    <input class="form-control" type="number" name="cantidad_personas" min="1" required>
                  </div>
                  <div class="col-sm-6 d-none" id="evaluarEspecialWrap">
                    <label class="form-label">Disponibilidad</label>
                    <button class="btn btn-outline-primary btn-icon w-100" type="button" id="evaluarEspecial">
                      <i class="bi bi-search"></i>Evaluar
                    </button>
                  </div>
                  <div class="col-12 d-none" id="especialResultado"></div>
                  <div class="col-12">
                    <label class="form-label">Motivo</label>
                    <input class="form-control" name="motivo" required>
                  </div>
                  <div class="col-12">
                    <label class="form-label">Observaciones</label>
                    <textarea class="form-control" name="observaciones" rows="2"></textarea>
                  </div>
                </div>
              </section>
              <section data-section="3" class="d-none">
                <div class="alert alert-info mb-3">
                  <i class="bi bi-info-circle me-2"></i>Revise los datos antes de registrar. El backend volverá a validar fecha, hora, área y campos obligatorios.
                </div>
              </section>
              <div class="d-flex justify-content-between mt-3">
                <button class="btn btn-outline-secondary btn-icon" type="button" id="prevStep"><i class="bi bi-arrow-left"></i>Volver</button>
                <button class="btn btn-primary btn-icon" type="button" id="nextStep">Continuar<i class="bi bi-arrow-right"></i></button>
                <button class="btn btn-success btn-icon d-none" type="submit" id="submitSolicitud"><i class="bi bi-check-circle"></i>Registrar</button>
              </div>
            </form>
          </div>
        </div>
      </div>
      <div class="col-xl-7">
        <div class="panel">
          <div class="panel-header"><strong>Solicitudes registradas</strong><i class="bi bi-table"></i></div>
          <div class="panel-body">
            <div class="search-row mb-3">
              <select class="form-select" id="estadoFiltro">
                <option value="">Todos los estados</option>
                <option value="pendiente">Pendiente</option>
                <option value="programada">Programada</option>
                <option value="atendida">Atendida</option>
                <option value="rechazada">Rechazada</option>
                <option value="cancelada">Cancelada</option>
              </select>
              <select class="form-select" id="tipoFiltro">
                <option value="">Todos los tipos</option>
                <option value="normal">Normal</option>
                <option value="especial">Pedido especial</option>
              </select>
              <input class="form-control" type="date" id="fechaFiltro">
              <select class="form-select" id="areaFiltro"></select>
              <button class="btn btn-outline-primary btn-icon" id="buscarSolicitudes"><i class="bi bi-search"></i>Buscar</button>
            </div>
            <div id="solicitudesTable"></div>
          </div>
        </div>
      </div>
    </div>
    <div class="modal fade" id="solicitudModal" tabindex="-1">
      <div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Detalle de solicitud</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" id="solicitudDetalle"></div></div></div>
    </div>`;
  bindSolicitudEvents();
  changeSolicitudStep(1);
  loadSolicitudCatalogs();
  loadSolicitudes();
});

function bindSolicitudEvents() {
  const tomorrow = new Date();
  tomorrow.setDate(tomorrow.getDate() + 1);
  document.getElementById('fechaServicio').min = tomorrow.toISOString().slice(0, 10);
  document.getElementById('tipoSolicitud').addEventListener('change', updateSolicitudMode);
  document.getElementById('evaluarEspecial').addEventListener('click', evaluateSpecialRequest);
  document.getElementById('solicitudForm').querySelectorAll('input, select, textarea').forEach((field) => {
    field.addEventListener('input', () => {
      if (['fecha_servicio', 'hora_servicio', 'cantidad_personas'].includes(field.name)) {
        resetSpecialAvailability();
      }
    });
  });
  document.getElementById('prevStep').addEventListener('click', () => changeSolicitudStep(solicitudStep - 1));
  document.getElementById('nextStep').addEventListener('click', () => changeSolicitudStep(solicitudStep + 1));
  document.querySelectorAll('.wizard-step').forEach((button) => button.addEventListener('click', () => changeSolicitudStep(Number(button.dataset.step))));
  document.getElementById('buscarSolicitudes').addEventListener('click', loadSolicitudes);
  document.getElementById('solicitudForm').addEventListener('submit', submitSolicitud);
  updateSolicitudMode();
}

function inputDateFrom(offsetDays) {
  const date = new Date();
  date.setDate(date.getDate() + offsetDays);
  return date.toISOString().slice(0, 10);
}

function updateSolicitudMode() {
  const isSpecial = document.getElementById('tipoSolicitud').value === 'especial';
  const fecha = document.getElementById('fechaServicio');
  fecha.min = isSpecial ? inputDateFrom(0) : inputDateFrom(1);
  document.getElementById('fechaServicioHelp').textContent = isSpecial
    ? 'Puede ser para hoy si hay vehículo y asientos disponibles.'
    : 'Debe ser mínimo desde mañana.';
  document.getElementById('evaluarEspecialWrap').classList.toggle('d-none', !isSpecial);
  document.getElementById('especialResultado').classList.toggle('d-none', !isSpecial);
  if (!isSpecial) {
    resetSpecialAvailability();
  }
}

function resetSpecialAvailability() {
  specialAvailability = null;
  const box = document.getElementById('especialResultado');
  if (box) {
    box.innerHTML = '';
  }
}

async function evaluateSpecialRequest() {
  const form = document.getElementById('solicitudForm');
  const data = formData(form);
  const box = document.getElementById('especialResultado');
  try {
    const result = await apiRequest('solicitudes', 'evaluar-especial', {
      method: 'POST',
      body: {
        fecha_servicio: data.fecha_servicio,
        hora_servicio: data.hora_servicio,
        cantidad_personas: data.cantidad_personas
      }
    });
    specialAvailability = result.data;
    if (result.data.disponible) {
      const v = result.data.vehiculo;
      box.innerHTML = `
        <div class="alert alert-success mb-0">
          <strong>Sí - Atiendo</strong>
          <div>${v.placa} - ${v.marca} ${v.modelo} | ${v.capacidad} asientos</div>
          <div class="small mt-1">${v.justificacion}</div>
        </div>`;
      return;
    }
    box.innerHTML = `
      <div class="alert alert-danger mb-0">
        <strong>No - Rechazo</strong>
        <div>${result.data.motivo_rechazo}</div>
      </div>`;
  } catch (error) {
    box.innerHTML = '';
    showAlert(error.message, 'danger');
  }
}

function changeSolicitudStep(step) {
  solicitudStep = Math.min(3, Math.max(1, step));
  document.querySelectorAll('[data-section]').forEach((section) => section.classList.toggle('d-none', Number(section.dataset.section) !== solicitudStep));
  document.querySelectorAll('.wizard-step').forEach((button) => button.classList.toggle('active', Number(button.dataset.step) === solicitudStep));
  document.getElementById('prevStep').disabled = solicitudStep === 1;
  document.getElementById('nextStep').classList.toggle('d-none', solicitudStep === 3);
  document.getElementById('submitSolicitud').classList.toggle('d-none', solicitudStep !== 3);
}

async function loadSolicitudCatalogs() {
  const areas = await apiRequest('areas', 'listar');
  const options = toOptions(areas.data.filter((x) => x.estado === 'activo'), 'id_area', (x) => x.nombre, 'Seleccione área');
  document.getElementById('areaSelect').innerHTML = options;
  document.getElementById('areaFiltro').innerHTML = '<option value="">Todas las áreas</option>' + areas.data.map((x) => `<option value="${x.id_area}">${x.nombre}</option>`).join('');
}

async function submitSolicitud(event) {
  event.preventDefault();
  try {
    const result = await apiRequest('solicitudes', 'crear', { method: 'POST', body: formData(event.target) });
    showAlert(result.message || 'Solicitud registrada correctamente', result.data?.resultado_especial === 'rechazar' ? 'warning' : 'success');
    event.target.reset();
    resetSpecialAvailability();
    updateSolicitudMode();
    changeSolicitudStep(1);
    loadSolicitudes();
  } catch (error) {
    showAlert(error.message, 'danger');
  }
}

async function loadSolicitudes() {
  try {
    const query = {
      estado: document.getElementById('estadoFiltro')?.value || '',
      tipo_solicitud: document.getElementById('tipoFiltro')?.value || '',
      fecha: document.getElementById('fechaFiltro')?.value || '',
      id_area: document.getElementById('areaFiltro')?.value || ''
    };
    const result = await apiRequest('solicitudes', 'listar', { query });
    const rows = result.data.map((s) => `
      <tr>
        <td>#${s.id_solicitud}</td>
        <td><strong>${s.area}</strong><div class="small text-muted">${s.nombres} ${s.apellidos}</div></td>
        <td>
          ${stateBadge(s.tipo_solicitud || 'normal')}
          ${s.tipo_solicitud === 'especial' ? `<div class="mt-1">${stateBadge(s.resultado_especial)}</div>` : ''}
          ${s.motivo_rechazo ? `<div class="small text-danger mt-1">${s.motivo_rechazo}</div>` : ''}
        </td>
        <td>${formatDate(s.fecha_servicio)}<div class="small text-muted">${formatTime(s.hora_servicio)}</div></td>
        <td>${s.direccion}</td>
        <td>${s.cantidad_personas}</td>
        <td>${stateBadge(s.estado)}</td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-primary" onclick="showSolicitud(${s.id_solicitud})" title="Ver detalle"><i class="bi bi-eye"></i></button>
          ${s.estado === 'pendiente' ? `<button class="btn btn-sm btn-outline-danger" onclick="cancelSolicitud(${s.id_solicitud})" title="Cancelar"><i class="bi bi-x-circle"></i></button>` : ''}
        </td>
      </tr>`).join('');
    document.getElementById('solicitudesTable').innerHTML = rows ? tableWrap(`<table class="table table-hover"><thead><tr><th>ID</th><th>Área</th><th>Tipo</th><th>Fecha</th><th>Destino</th><th>Personas</th><th>Estado</th><th></th></tr></thead><tbody>${rows}</tbody></table>`) : emptyState('No hay solicitudes para los filtros seleccionados');
  } catch (error) {
    showAlert(error.message, 'danger');
  }
}

async function showSolicitud(id) {
  const result = await apiRequest('solicitudes', 'detalle', { query: { id } });
  const s = result.data;
  document.getElementById('solicitudDetalle').innerHTML = `
    <div class="row g-3">
      <div class="col-md-6"><strong>Área</strong><div>${s.area}</div></div>
      <div class="col-md-6"><strong>Solicitante</strong><div>${s.nombres} ${s.apellidos}</div></div>
      <div class="col-md-6"><strong>Fecha y hora</strong><div>${formatDate(s.fecha_servicio)} ${formatTime(s.hora_servicio)}</div></div>
      <div class="col-md-6"><strong>Estado</strong><div>${stateBadge(s.estado)}</div></div>
      <div class="col-md-6"><strong>Tipo</strong><div>${stateBadge(s.tipo_solicitud || 'normal')}</div></div>
      ${s.tipo_solicitud === 'especial' ? `<div class="col-md-6"><strong>Decisión especial</strong><div>${stateBadge(s.resultado_especial)}</div></div>` : ''}
      ${s.vehiculo_sugerido ? `<div class="col-md-6"><strong>Vehículo sugerido</strong><div>${s.vehiculo_sugerido_placa} - ${s.vehiculo_sugerido} (${s.vehiculo_sugerido_capacidad} asientos)</div></div>` : ''}
      ${s.motivo_rechazo ? `<div class="col-12"><strong>Motivo de rechazo</strong><div class="text-danger">${s.motivo_rechazo}</div></div>` : ''}
      <div class="col-12"><strong>Destino</strong><div>${s.direccion}</div></div>
      <div class="col-12"><strong>Motivo</strong><div>${s.motivo}</div></div>
      <div class="col-12"><strong>Observaciones</strong><div>${s.observaciones || 'Sin observaciones'}</div></div>
    </div>`;
  new bootstrap.Modal(document.getElementById('solicitudModal')).show();
}

async function cancelSolicitud(id) {
  if (!confirmAction('¿Desea cancelar esta solicitud?')) return;
  try {
    await apiRequest('solicitudes', 'actualizar-estado', { method: 'PUT', body: { id_solicitud: id, estado: 'cancelada' } });
    showAlert('Solicitud cancelada correctamente');
    loadSolicitudes();
  } catch (error) {
    showAlert(error.message, 'danger');
  }
}
