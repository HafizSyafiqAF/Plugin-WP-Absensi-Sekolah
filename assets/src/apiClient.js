/**
 * Wrapper fetch dengan auto-inject nonce WordPress.
 * Konfig diambil dari AbsensiConfig (publik) atau AbsensiAdmin (admin).
 */

function getConfig() {
  return window.AbsensiAdmin ?? window.AbsensiConfig ?? {};
}

let _nonce = null;

function getNonce() {
  if (_nonce) return _nonce;
  _nonce = getConfig().nonce ?? '';
  return _nonce;
}

function getRestUrl() {
  return getConfig().restUrl ?? '/wp-json/absensi/v1/';
}

async function refreshNonce() {
  try {
    const res = await fetch('/wp-admin/admin-ajax.php?action=rest-nonce');
    if (!res.ok) throw new Error('nonce refresh failed');
    _nonce = await res.text();
  } catch {
    throw new Error('Sesi habis, silakan muat ulang halaman.');
  }
}

/**
 * @param {string} path  — misal 'absen/selfie'
 * @param {RequestInit & { json?: any }} opts
 */
export async function request(path, opts = {}) {
  const { json, ...fetchOpts } = opts;
  const url = getRestUrl() + path;

  const headers = new Headers(fetchOpts.headers ?? {});
  headers.set('X-WP-Nonce', getNonce());
  if (json !== undefined) {
    headers.set('Content-Type', 'application/json');
    fetchOpts.body = JSON.stringify(json);
  }

  let res = await fetch(url, { ...fetchOpts, headers });

  // Auto-retry sekali jika nonce expired (403)
  if (res.status === 403) {
    await refreshNonce();
    headers.set('X-WP-Nonce', getNonce());
    res = await fetch(url, { ...fetchOpts, headers });
  }

  const data = await res.json().catch(() => null);

  if (!res.ok) {
    const msg = data?.message ?? `HTTP ${res.status}`;
    throw Object.assign(new Error(msg), { status: res.status, data });
  }

  return data;
}

export const api = {
  get:    (path, opts = {})      => request(path, { method: 'GET', ...opts }),
  post:   (path, json, opts = {}) => request(path, { method: 'POST',   json, ...opts }),
  put:    (path, json, opts = {}) => request(path, { method: 'PUT',    json, ...opts }),
  delete: (path, opts = {})      => request(path, { method: 'DELETE', ...opts }),
};

export default api;
