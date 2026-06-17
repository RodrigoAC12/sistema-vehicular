document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('loginForm');
  if (!form) return;

  if (getToken()) {
    window.location.href = landingPageFor(getUser().rol);
    return;
  }

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const button = form.querySelector('button[type="submit"]');
    const message = document.getElementById('loginMessage');
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Ingresando';
    message.innerHTML = '';

    try {
      const result = await apiRequest('auth', 'login', {
        method: 'POST',
        auth: false,
        body: formData(form)
      });
      localStorage.setItem('sv_token', result.data.token);
      localStorage.setItem('sv_user', JSON.stringify(result.data.usuario));
      window.location.href = landingPageFor(result.data.usuario.rol);
    } catch (error) {
      message.innerHTML = `<div class="alert alert-danger">${error.message}</div>`;
    } finally {
      button.disabled = false;
      button.innerHTML = '<i class="bi bi-box-arrow-in-right"></i> Iniciar sesión';
    }
  });
});

function landingPageFor(role) {
  const map = {
    administrador: 'dashboard.html',
    coordinador: 'dashboard.html',
    solicitante: 'solicitudes.html',
    conductor: 'kilometraje.html',
    visualizador: 'panel-publico.html'
  };
  return map[role] || 'dashboard.html';
}
