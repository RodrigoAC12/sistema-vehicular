const menuItems = [
  ['dashboard', 'Panel de control', 'bi-speedometer2', 'dashboard.html', ['administrador', 'coordinador']],
  ['solicitudes', 'Solicitudes', 'bi-file-earmark-text', 'solicitudes.html', ['administrador', 'coordinador', 'solicitante']],
  ['vehiculos', 'Vehículos', 'bi-truck', 'vehiculos.html', ['administrador', 'coordinador']],
  ['conductores', 'Conductores', 'bi-person-badge', 'conductores.html', ['administrador', 'coordinador']],
  ['programacion', 'Programación', 'bi-calendar-check', 'programacion.html', ['administrador', 'coordinador', 'conductor']],
  ['cola', 'Cola', 'bi-list-ol', 'cola.html', ['administrador', 'coordinador']],
  ['kilometraje', 'Kilometraje', 'bi-speedometer', 'kilometraje.html', ['administrador', 'coordinador', 'conductor']],
  ['retornos', 'Retornos', 'bi-arrow-return-left', 'retornos.html', ['administrador', 'coordinador', 'conductor']],
  ['estadisticas', 'Estadísticas', 'bi-bar-chart', 'estadisticas.html', ['administrador', 'coordinador']],
  ['usuarios', 'Usuarios', 'bi-people', 'usuarios.html', ['administrador']],
  ['panel-publico', 'Panel público', 'bi-display', 'panel-publico.html', ['administrador', 'coordinador', 'solicitante', 'conductor', 'visualizador']]
];

function getToken() {
  return localStorage.getItem('sv_token');
}

function getUser() {
  try {
    return JSON.parse(localStorage.getItem('sv_user') || '{}');
  } catch {
    return {};
  }
}

function requireSession() {
  if (!getToken()) {
    window.location.href = 'login.html';
  }
}

async function apiRequest(service, action, options = {}) {
  if (!window.APP_CONFIG?.apiBase) {
    throw new Error('No se encontró la configuración de conexión con el servidor.');
  }

  const query = new URLSearchParams({ service, action, ...(options.query || {}) });
  const requestOptions = {
    method: options.method || 'GET',
    headers: {
      'Content-Type': 'application/json',
      ...(options.auth === false ? {} : { Authorization: `Bearer ${getToken()}` })
    }
  };
  if (Object.prototype.hasOwnProperty.call(options, 'body')) {
    requestOptions.body = JSON.stringify(options.body);
  }

  let response;
  try {
    response = await fetch(`${window.APP_CONFIG.apiBase}?${query.toString()}`, requestOptions);
  } catch {
    throw new Error('No se pudo conectar con el servidor. Verifique que Apache y MySQL estén activos en XAMPP.');
  }

  const json = await response.json().catch(() => ({ success: false, message: 'Respuesta no válida del servidor', data: null }));
  if (response.status === 401) {
    localStorage.removeItem('sv_token');
    localStorage.removeItem('sv_user');
    window.location.href = 'login.html';
  }
  if (!json.success && options.throwOnError !== false) {
    throw new Error(json.message || 'Operación no completada');
  }
  return json;
}

function renderShell(active, title, subtitle = '') {
  requireSession();
  const user = getUser();
  const nav = menuItems.filter((item) => item[4].includes(user.rol)).map(([key, label, icon, href]) => `
    <a class="${key === active ? 'active' : ''}" href="${href}">
      <i class="bi ${icon}"></i><span>${label}</span>
    </a>`).join('');

  document.getElementById('app').innerHTML = `
    <div class="app-shell">
      <aside class="sidebar">
        <div class="sidebar-brand">
          <div class="brand-mark"><i class="bi bi-truck-front"></i></div>
          <div>
            <p class="sidebar-title">Sistema Vehicular</p>
            <p class="sidebar-subtitle">Microservicios PHP</p>
          </div>
        </div>
        <nav class="nav-menu">${nav}</nav>
      </aside>
      <main class="main-area">
        <header class="topbar">
          <div>
            <h1 class="page-title">${title}</h1>
            ${subtitle ? `<p class="page-subtitle">${subtitle}</p>` : ''}
          </div>
          <div class="d-flex align-items-center gap-3">
            <div class="text-end d-none d-sm-block">
              <strong>${user.nombres || 'Usuario'}</strong>
              <div class="small text-muted">${user.rol || ''}</div>
            </div>
            <button class="btn btn-outline-danger btn-sm btn-icon" id="logoutBtn">
              <i class="bi bi-box-arrow-right"></i><span>Salir</span>
            </button>
          </div>
        </header>
        <section class="content">
          <div id="alertHost"></div>
          <div id="page-content"></div>
        </section>
      </main>
    </div>`;

  document.getElementById('logoutBtn').addEventListener('click', logout);
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => new bootstrap.Tooltip(el));
}

async function logout() {
  try {
    await apiRequest('auth', 'logout', { method: 'POST', throwOnError: false });
  } finally {
    localStorage.removeItem('sv_token');
    localStorage.removeItem('sv_user');
    window.location.href = 'login.html';
  }
}

function showAlert(message, type = 'success') {
  const host = document.getElementById('alertHost');
  if (!host) return;
  host.innerHTML = `
    <div class="alert alert-${type} alert-dismissible fade show" role="alert">
      ${safeText(message, 'Operación completada')}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>`;
}

function stateBadge(state) {
  const normalized = state || 'sin_estado';
  const map = {
    pendiente: ['state-warning', 'bi-hourglass-split'],
    programada: ['state-primary', 'bi-calendar-check'],
    atendida: ['state-success', 'bi-check-circle'],
    rechazada: ['state-danger', 'bi-x-circle'],
    cancelada: ['state-muted', 'bi-slash-circle'],
    disponible: ['state-success', 'bi-check-circle'],
    asignado: ['state-warning', 'bi-person-check'],
    en_ruta: ['state-info', 'bi-geo-alt'],
    retornando: ['state-warning', 'bi-arrow-return-left'],
    mantenimiento: ['state-muted', 'bi-tools'],
    fuera_servicio: ['state-danger', 'bi-exclamation-triangle'],
    finalizada: ['state-success', 'bi-check-circle'],
    iniciado: ['state-info', 'bi-speedometer'],
    finalizado: ['state-success', 'bi-check-circle'],
    activo: ['state-success', 'bi-check-circle'],
    inactivo: ['state-muted', 'bi-pause-circle'],
    normal: ['state-muted', 'bi-file-earmark-text'],
    especial: ['state-primary', 'bi-lightning-charge'],
    no_aplica: ['state-muted', 'bi-dash-circle'],
    atender: ['state-success', 'bi-check-circle'],
    rechazar: ['state-danger', 'bi-x-circle'],
    sin_estado: ['state-muted', 'bi-dash-circle']
  };
  const [cls, icon] = map[normalized] || ['state-muted', 'bi-dot'];
  const label = stateLabel(normalized);
  return `<span class="badge-state ${cls}"><i class="bi ${icon}"></i>${label}</span>`;
}

function stateLabel(state) {
  const labels = {
    pendiente: 'Pendiente',
    programada: 'Programada',
    atendida: 'Atendida',
    rechazada: 'Rechazada',
    cancelada: 'Cancelada',
    disponible: 'Disponible',
    asignado: 'Asignado',
    en_ruta: 'En ruta',
    retornando: 'Retornando',
    mantenimiento: 'Mantenimiento',
    fuera_servicio: 'Fuera de servicio',
    finalizada: 'Finalizada',
    iniciado: 'Iniciado',
    finalizado: 'Finalizado',
    activo: 'Activo',
    inactivo: 'Inactivo',
    normal: 'Normal',
    especial: 'Especial',
    no_aplica: 'No aplica',
    atender: 'Atender',
    rechazar: 'Rechazar',
    sin_estado: 'Sin estado'
  };
  return labels[state] || safeText(state, 'Sin estado').replaceAll('_', ' ');
}

function emptyState(message, icon = 'bi-inbox') {
  return `<div class="empty-state"><i class="bi ${icon}"></i><strong>${message}</strong></div>`;
}

function formatDate(date) {
  if (!date) return '';
  return new Date(`${date}T00:00:00`).toLocaleDateString('es-PE', { year: 'numeric', month: 'short', day: '2-digit' });
}

function formatTime(time) {
  return String(time || '').slice(0, 5);
}

function formData(form) {
  return Object.fromEntries(new FormData(form).entries());
}

function confirmAction(message) {
  return window.confirm(message);
}

function toOptions(items, valueKey, labelFn, placeholder = 'Seleccione') {
  return `<option value="">${placeholder}</option>` + (items || []).map((item) => `<option value="${safeText(item[valueKey], '')}">${safeText(labelFn(item))}</option>`).join('');
}

function tableWrap(content) {
  return `<div class="table-responsive">${content}</div>`;
}

function chartColors() {
  return ['#2563EB', '#16A34A', '#F59E0B', '#DC2626', '#0EA5E9', '#6B7280', '#9333EA', '#0891B2'];
}

function safeText(value, fallback = 'Sin dato') {
  if (value === undefined || value === null || value === '') return fallback;
  return String(value);
}

function safeNumber(value, fallback = 0) {
  const number = Number(value);
  return Number.isFinite(number) ? number : fallback;
}
