const appBasePath = window.location.pathname.split('/frontend/')[0];

window.APP_CONFIG = {
  apiBase: `${window.location.origin}${appBasePath}/api-gateway/index.php`,
  publicRefreshMs: 5000,
  dashboardRefreshMs: 5000,
  programacionRefreshMs: 10000
};
