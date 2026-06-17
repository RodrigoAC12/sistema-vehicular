const menuItems = [
  ['dashboard', 'Dashboard', 'bi-speedometer2', 'dashboard.html', ['administrador', 'coordinador']],
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
  const query = new URLSearchParams({ service, action, ...(options.query || {}) });
  const response = await fetch(`${window.APP_CONFIG.apiBase}?${query.toString()}`, {
    method: options.method || 'GET',
    headers: {
      'Content-Type': 'application/json',
      ...(options.auth === false ? {} : { Authorization: `Bearer ${getToken()}` })
    },
    body: options.body ? JSON.stringify(options.body) : undefined
  });
  const json = await response.json().catch(() => ({ success: false, message: 'Respuesta no válida del servidor', data: null }));
  if (response.status === 401) {
    localStorage.removeItem('sv_token');
    localStorage.removeItem('sv_user');
    window.location.href = 'login.html';
  }
  if (!json.success && options.throwOnError !== false) {
    throw new Error(json.message || 'Operacion no completada');
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
      ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>`;
}

function stateBadge(state) {
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
    inactivo: ['state-muted', 'bi-pause-circle']
  };
  const [cls, icon] = map[state] || ['state-muted', 'bi-dot'];
  const label = String(state || '').replaceAll('_', ' ');
  return `<span class="badge-state ${cls}"><i class="bi ${icon}"></i>${label}</span>`;
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
  return `<option value="">${placeholder}</option>` + items.map((item) => `<option value="${item[valueKey]}">${labelFn(item)}</option>`).join('');
}

function tableWrap(content) {
  return `<div class="table-responsive">${content}</div>`;
}

function chartColors() {
  return ['#2563EB', '#16A34A', '#F59E0B', '#DC2626', '#0EA5E9', '#6B7280', '#9333EA', '#0891B2'];
}
