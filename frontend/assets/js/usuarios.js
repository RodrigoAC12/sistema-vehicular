document.addEventListener('DOMContentLoaded', () => {
  renderShell('usuarios', 'Usuarios', 'Gestión básica de accesos, roles y estados');
  document.getElementById('page-content').innerHTML = `
    <div class="row g-3">
      <div class="col-xl-4">
        <div class="panel">
          <div class="panel-header"><strong>Registrar usuario</strong><i class="bi bi-person-plus"></i></div>
          <div class="panel-body">
            <form id="usuarioForm" class="row g-3">
              <div class="col-6"><label class="form-label">Nombres</label><input class="form-control" name="nombres" required></div>
              <div class="col-6"><label class="form-label">Apellidos</label><input class="form-control" name="apellidos" required></div>
              <div class="col-12"><label class="form-label">Email</label><input class="form-control" type="email" name="email" required></div>
              <div class="col-12"><label class="form-label">Password</label><input class="form-control" type="password" name="password" required></div>
              <div class="col-6"><label class="form-label">Rol</label><select class="form-select" name="id_rol" id="rolSelect" required></select></div>
              <div class="col-6"><label class="form-label">Área</label><select class="form-select" name="id_area" id="areaUsuarioSelect"></select></div>
              <div class="col-12"><button class="btn btn-primary btn-icon w-100"><i class="bi bi-save"></i>Guardar usuario</button></div>
            </form>
          </div>
        </div>
      </div>
      <div class="col-xl-8">
        <div class="panel">
          <div class="panel-header"><strong>Usuarios registrados</strong><i class="bi bi-people"></i></div>
          <div class="panel-body"><div id="usuariosTable"></div></div>
        </div>
      </div>
    </div>`;
  document.getElementById('usuarioForm').addEventListener('submit', submitUsuario);
  loadUsuariosPage();
});

async function loadUsuariosPage() {
  const [roles, areas] = await Promise.all([
    apiRequest('auth', 'roles'),
    apiRequest('areas', 'listar')
  ]);
  document.getElementById('rolSelect').innerHTML = toOptions(roles.data, 'id_rol', (r) => r.nombre, 'Rol');
  document.getElementById('areaUsuarioSelect').innerHTML = '<option value="">Sin área</option>' + areas.data.map((a) => `<option value="${a.id_area}">${a.nombre}</option>`).join('');
  loadUsuarios();
}

async function submitUsuario(event) {
  event.preventDefault();
  try {
    await apiRequest('auth', 'crear-usuario', { method: 'POST', body: formData(event.target) });
    showAlert('Usuario registrado correctamente');
    event.target.reset();
    loadUsuarios();
  } catch (error) {
    showAlert(error.message, 'danger');
  }
}

async function loadUsuarios() {
  try {
    const result = await apiRequest('auth', 'usuarios');
    const rows = result.data.map((u) => `
      <tr>
        <td><strong>${u.nombres} ${u.apellidos}</strong><div class="small text-muted">${u.email}</div></td>
        <td>${u.rol}</td>
        <td>${u.area || '-'}</td>
        <td>${stateBadge(u.estado)}</td>
        <td class="text-end">
          <button class="btn btn-sm ${u.estado === 'activo' ? 'btn-outline-secondary' : 'btn-outline-success'}" onclick="changeUsuarioEstado(${u.id_usuario}, '${u.estado === 'activo' ? 'inactivo' : 'activo'}')">
            <i class="bi ${u.estado === 'activo' ? 'bi-pause-circle' : 'bi-check-circle'}"></i>
          </button>
        </td>
      </tr>`).join('');
    document.getElementById('usuariosTable').innerHTML = rows ? tableWrap(`<table class="table table-hover"><thead><tr><th>Usuario</th><th>Rol</th><th>Área</th><th>Estado</th><th></th></tr></thead><tbody>${rows}</tbody></table>`) : emptyState('No hay usuarios registrados');
  } catch (error) {
    showAlert(error.message, 'danger');
  }
}

async function changeUsuarioEstado(id, estado) {
  try {
    await apiRequest('auth', 'actualizar-usuario', { method: 'PUT', body: { id_usuario: id, estado } });
    showAlert('Usuario actualizado correctamente');
    loadUsuarios();
  } catch (error) {
    showAlert(error.message, 'danger');
  }
}
