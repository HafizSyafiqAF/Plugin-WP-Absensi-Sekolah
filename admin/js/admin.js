/**
 * Admin JS — Alpine.js components untuk halaman admin plugin.
 * File ini memuat Alpine.js lokal (admin/js/alpine.min.js) dan mendefinisikan komponen.
 * Tidak ada import/export — semua dependency (Alpine, Leaflet) tersedia sebagai global.
 */

/* ─── Bootstrap Alpine.js (lokal) ───────────────────────────────────────────
 * Cari URL admin.js untuk derive path alpine.min.js di folder yang sama.
 * admin.js dijalankan di footer, listener alpine:init sudah terdaftar sebelum
 * Alpine selesai load (async), sehingga komponen Alpine.data teregistrasi tepat waktu.
 */
(function () {
  if (window.Alpine || document.querySelector('script[src*="alpine"]')) return;
  var tag = document.querySelector('script[src*="plugin-wp-absensi-sekolah/admin/js/admin.js"]')
         || document.querySelector('script[src*="admin/js/admin.js"]');
  var src = tag
    ? tag.src.replace(/admin\.js(\?.*)?$/, 'alpine.min.js')
    : 'https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js';
  var s = document.createElement('script');
  s.src = src;
  document.head.appendChild(s);
}());

/* ─── Bootstrap Leaflet 1.9.4 (CDN) ─────────────────────────────────────────
 * Dimuat hanya jika halaman punya kontainer peta (#absensi-map).
 * Versi 1.x dipakai karena mengekspos global `L` (2.x sudah ESM-only).
 * settingsMap._boot() menunggu window.L siap sebelum init peta.
 */
(function () {
  if (!document.getElementById('absensi-map')) return;
  if (window.L || document.querySelector('script[src*="leaflet"]')) return;
  var css = document.createElement('link');
  css.rel = 'stylesheet';
  css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
  css.integrity = 'sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=';
  css.crossOrigin = '';
  document.head.appendChild(css);
  var js = document.createElement('script');
  js.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
  js.integrity = 'sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=';
  js.crossOrigin = '';
  document.head.appendChild(js);
}());

/* ─── apiClient ──────────────────────────────────────────────────────────────
 * Wrapper fetch dengan auto-inject nonce WordPress.
 * Konfig diambil dari AbsensiAdmin (admin) atau AbsensiConfig (publik).
 */
(function () {
  'use strict';

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

  async function request(path, opts = {}) {
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

  window.api = {
    get:    (path, opts = {})       => request(path, { method: 'GET', ...opts }),
    post:   (path, json, opts = {}) => request(path, { method: 'POST',   json, ...opts }),
    put:    (path, json, opts = {}) => request(path, { method: 'PUT',    json, ...opts }),
    delete: (path, opts = {})       => request(path, { method: 'DELETE', ...opts }),
  };

})();

/* ─── Alpine Components ──────────────────────────────────────────────────── */

const FILTER_KEY = 'absensi_admin_filter';

function fmtTime(dt) {
  if (!dt) return null;
  const d = new Date(dt.replace(' ', 'T') + 'Z');
  if (isNaN(d)) return dt.slice(11, 16);
  return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', hour12: false });
}

document.addEventListener('alpine:init', () => {

  Alpine.data('filterBar', () => ({
    filter:  { dateFrom: '', dateTo: '', kelas: '' },
    csOpen:  false,
    csMap:   {},

    get activePreset() {
      const today = new Date().toISOString().slice(0, 10);
      if (this.filter.dateFrom === today && this.filter.dateTo === today) return 'hariIni';
      const now = new Date();
      const day = now.getDay();
      const diff = day === 0 ? -6 : 1 - day;
      const mon = new Date(now); mon.setDate(now.getDate() + diff);
      if (this.filter.dateFrom === mon.toISOString().slice(0, 10) && this.filter.dateTo === today) return 'mingguIni';
      const first = new Date(now.getFullYear(), now.getMonth(), 1);
      if (this.filter.dateFrom === first.toISOString().slice(0, 10) && this.filter.dateTo === today) return 'bulanIni';
      return '';
    },

    init() {
      try {
        const raw = this.$el.dataset.kelasMap;
        if (raw) this.csMap = JSON.parse(raw);
      } catch {}
      this.loadFilter();
      if (!this.filter.dateFrom) {
        const now   = new Date();
        const first = new Date(now.getFullYear(), now.getMonth(), 1);
        this.filter.dateFrom = first.toISOString().slice(0, 10);
      }
      if (!this.filter.dateTo) {
        this.filter.dateTo = new Date().toISOString().slice(0, 10);
      }
    },

    loadFilter() {
      try {
        const raw = localStorage.getItem(FILTER_KEY);
        if (raw) Object.assign(this.filter, JSON.parse(raw));
      } catch {}
    },

    saveFilter() {
      try { localStorage.setItem(FILTER_KEY, JSON.stringify(this.filter)); } catch {}
    },

    apply() {
      this.saveFilter();
      window.dispatchEvent(new CustomEvent('filter-changed', { detail: { ...this.filter } }));
    },

    reset() {
      const today = new Date().toISOString().slice(0, 10);
      this.filter = { dateFrom: today, dateTo: today, kelas: '' };
      try { localStorage.removeItem(FILTER_KEY); } catch {}
      window.dispatchEvent(new CustomEvent('filter-changed', { detail: { ...this.filter } }));
    },

    presetHariIni() {
      const today = new Date().toISOString().slice(0, 10);
      this.filter.dateFrom = today;
      this.filter.dateTo   = today;
      this.apply();
    },

    presetMingguIni() {
      const now  = new Date();
      const day  = now.getDay();
      const diff = day === 0 ? -6 : 1 - day;
      const mon  = new Date(now);
      mon.setDate(now.getDate() + diff);
      this.filter.dateFrom = mon.toISOString().slice(0, 10);
      this.filter.dateTo   = new Date().toISOString().slice(0, 10);
      this.apply();
    },

    presetBulanIni() {
      const now   = new Date();
      const first = new Date(now.getFullYear(), now.getMonth(), 1);
      this.filter.dateFrom = first.toISOString().slice(0, 10);
      this.filter.dateTo   = now.toISOString().slice(0, 10);
      this.apply();
    },
  }));

  Alpine.data('rekapTable', () => ({
    rows:    [],
    loading: false,
    error:   null,
    filter:  {},
    page:    1,
    perPage: 10,

    get totalPages() { return Math.max(1, Math.ceil(this.rows.length / this.perPage)); },
    get paginatedRows() { return this.rows.slice((this.page - 1) * this.perPage, this.page * this.perPage); },

    get pageRange() {
      const t = this.totalPages, c = this.page;
      if (t <= 7) return Array.from({ length: t }, (_, i) => i + 1);
      const s = new Set([1, 2, c - 1, c, c + 1, t - 1, t].filter(p => p >= 1 && p <= t));
      const arr = [...s].sort((a, b) => a - b);
      const out = [];
      for (let i = 0; i < arr.length; i++) {
        if (i > 0 && arr[i] - arr[i - 1] > 1) out.push('…');
        out.push(arr[i]);
      }
      return out;
    },

    get summary() {
      return {
        hadir:      this.rows.filter(r => r.status === 'hadir').length,
        telat:      this.rows.filter(r => r.status === 'telat').length,
        izin_sakit: this.rows.filter(r => r.status === 'izin' || r.status === 'sakit').length,
        alpha:      this.rows.filter(r => r.status === 'alpha').length,
      };
    },

    init() {
      try {
        const saved = JSON.parse(localStorage.getItem(FILTER_KEY) ?? '{}');
        const now   = new Date();
        const first = new Date(now.getFullYear(), now.getMonth(), 1);
        this.filter = {
          dateFrom: saved.dateFrom ?? first.toISOString().slice(0, 10),
          dateTo:   saved.dateTo   ?? now.toISOString().slice(0, 10),
          kelas:    saved.kelas    ?? '',
        };
      } catch {
        const now   = new Date();
        const first = new Date(now.getFullYear(), now.getMonth(), 1);
        this.filter = { dateFrom: first.toISOString().slice(0, 10), dateTo: now.toISOString().slice(0, 10), kelas: '' };
      }
      this.load();
      window.addEventListener('filter-changed', e => {
        this.filter = e.detail;
        this.load();
      });
    },

    async load() {
      this.loading = true;
      this.error   = null;
      this.page    = 1;
      try {
        const params = new URLSearchParams();
        if (this.filter.dateFrom) params.set('dari',     this.filter.dateFrom);
        if (this.filter.dateTo)   params.set('sampai',   this.filter.dateTo);
        if (this.filter.kelas)    params.set('kelas_id', this.filter.kelas);
        const data = await window.api.get('laporan?' + params);
        this.rows  = data.data ?? [];
      } catch (err) {
        this.error = err.message;
      } finally {
        this.loading = false;
      }
    },

    statusClass(status) {
      const map = { hadir: 'status-hadir', telat: 'status-telat', alpha: 'status-alpha', izin: 'status-izin', sakit: 'status-sakit' };
      return map[status] ?? 'badge bg-gray-100 text-text-muted';
    },

    fmtTime,

    exporting: false,

    async exportCSV() {
      if (this.exporting) return;
      this.exporting = true;
      try {
        const p = new URLSearchParams();
        if (this.filter.dateFrom) p.set('dari',     this.filter.dateFrom);
        if (this.filter.dateTo)   p.set('sampai',   this.filter.dateTo);
        if (this.filter.kelas)    p.set('kelas_id', this.filter.kelas);
        p.set('per_page', '9999');

        const data = await window.api.get('laporan?' + p.toString());
        const rows = data.data ?? data ?? [];

        if (!rows.length) { alert('Tidak ada data untuk diekspor.'); return; }

        const esc = v => `"${String(v ?? '').replace(/"/g, '""')}"`;
        const header = ['Tanggal','Nama','NIS','Kelas','Status','Jam Masuk','Jam Keluar','Mode'];
        const lines  = [
          header.join(','),
          ...rows.map(r => [
            r.tanggal       ?? '',
            r.nama          ?? '',
            r.nis           ?? '',
            r.nama_kelas    ?? '',
            r.status        ?? '',
            r.waktu_masuk   ? String(r.waktu_masuk).slice(0,5)  : '',
            r.waktu_keluar  ? String(r.waktu_keluar).slice(0,5) : '',
            r.mode          ?? '',
          ].map(esc).join(',')),
        ];

        const dari   = this.filter.dateFrom ?? 'semua';
        const sampai = this.filter.dateTo   ?? '';
        const nama   = sampai && sampai !== dari ? `absensi-${dari}-sd-${sampai}` : `absensi-${dari}`;

        const blob = new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
        const url  = URL.createObjectURL(blob);
        const a    = Object.assign(document.createElement('a'), { href: url, download: nama + '.csv' });
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
      } catch (err) {
        alert('Gagal ekspor CSV: ' + err.message);
      } finally {
        this.exporting = false;
      }
    },

    async exportServer(format) {
      const p = new URLSearchParams({ format });
      if (this.filter.dateFrom) p.set('dari',     this.filter.dateFrom);
      if (this.filter.dateTo)   p.set('sampai',   this.filter.dateTo);
      if (this.filter.kelas)    p.set('kelas_id', this.filter.kelas);
      const nonce = window.AbsensiAdmin?.nonce ?? '';
      if (nonce) p.set('_wpnonce', nonce);
      const base = (window.AbsensiAdmin?.restUrl ?? '/wp-json/absensi/v1/');
      const url  = base + 'laporan/export?' + p.toString();

      try {
        const res = await fetch(url, { headers: { 'X-WP-Nonce': nonce } });
        if (!res.ok) {
          const err = await res.json().catch(() => null);
          if (res.status === 404) {
            alert('Endpoint ekspor belum tersedia di server.\n\nUntuk sementara gunakan ekspor CSV.');
          } else {
            alert('Gagal ekspor: ' + (err?.message ?? `HTTP ${res.status}`));
          }
          return;
        }
        const blob     = await res.blob();
        const ext      = format === 'xlsx' ? 'xlsx' : 'pdf';
        const dari     = this.filter.dateFrom ?? 'semua';
        const sampai   = this.filter.dateTo ?? '';
        const filename = `absensi-${dari}${sampai && sampai !== dari ? '-sd-' + sampai : ''}.${ext}`;
        const objUrl   = URL.createObjectURL(blob);
        const a        = Object.assign(document.createElement('a'), { href: objUrl, download: filename });
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(objUrl);
      } catch {
        alert('Tidak dapat terhubung ke server. Periksa koneksi internet.');
      }
    },

    printLaporan() {
      if (!this.rows.length) { alert('Tidak ada data untuk dicetak.'); return; }

      const dari    = this.filter.dateFrom ?? '';
      const sampai  = this.filter.dateTo   ?? '';
      const periode = dari ? (sampai && sampai !== dari ? `${dari} s/d ${sampai}` : dari) : 'Semua';

      const statusColor = { hadir: '#16A34A', telat: '#D97706', alpha: '#DC2626', izin: '#0891B2', sakit: '#0891B2' };
      const esc = v => String(v ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');

      const tBody = this.rows.map((r, i) => `<tr>
        <td>${i + 1}</td><td>${esc(r.tanggal)}</td>
        <td><strong>${esc(r.nama)}</strong><br><small style="color:#888">${esc(r.nis)}</small></td>
        <td>${esc(r.nama_kelas)}</td>
        <td>${fmtTime(r.waktu_masuk) ?? '—'}</td>
        <td>${fmtTime(r.waktu_keluar) ?? '—'}</td>
        <td style="color:${statusColor[r.status] ?? '#333'};font-weight:600">${esc(r.status)}</td>
      </tr>`).join('');

      const html = `<!DOCTYPE html><html lang="id"><head><meta charset="utf-8">
<title>Rekap Absensi ${esc(periode)}</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:Arial,sans-serif;font-size:11px;padding:24px;color:#111}
.hd{margin-bottom:16px;border-bottom:2px solid #4F46E5;padding-bottom:10px}
.hd h1{font-size:15px;color:#4F46E5;margin-bottom:3px}
.hd p{font-size:11px;color:#555}
table{width:100%;border-collapse:collapse;margin-top:8px}
th{background:#4F46E5;color:#fff;padding:6px 8px;text-align:left;font-size:11px}
td{padding:5px 8px;border-bottom:1px solid #e5e7eb;vertical-align:top}
tr:nth-child(even) td{background:#f9f9f9}
.ft{margin-top:12px;font-size:10px;color:#888;text-align:right}
@media print{body{padding:12px}.hd{break-after:avoid}}
</style></head><body>
<div class="hd">
  <h1>Rekap Absensi — ${esc(periode)}</h1>
  <p>${esc(this.rows.length)} rekap ditemukan · Dicetak ${new Date().toLocaleString('id-ID')}</p>
</div>
<table>
  <thead><tr><th>#</th><th>Tanggal</th><th>Siswa</th><th>Kelas</th><th>Masuk</th><th>Pulang</th><th>Status</th></tr></thead>
  <tbody>${tBody}</tbody>
</table>
<div class="ft">Laporan Absensi Sekolah — dicetak otomatis</div>
</body></html>`;

      const iframe = document.createElement('iframe');
      iframe.style.cssText = 'position:fixed;top:-9999px;left:-9999px;width:1px;height:1px;border:0;';
      document.body.appendChild(iframe);
      const doc = iframe.contentDocument ?? iframe.contentWindow.document;
      doc.open(); doc.write(html); doc.close();
      setTimeout(() => {
        iframe.contentWindow.focus();
        iframe.contentWindow.print();
        setTimeout(() => document.body.removeChild(iframe), 1000);
      }, 250);
    },
  }));

  Alpine.data('adminRfid', () => ({
    mode: 'absen',
    absenLog:    [],
    absenStatus: { type: 'idle', msg: 'Menunggu scan kartu…' },
    enrollSearch:    '',
    enrollResults:   [],
    enrollSearching: false,
    enrollTarget:    null,
    enrollUid:       '',
    enrollStatus:    null,
    enrollLoading:   false,
    _lastUid: '',
    _lastTap: 0,
    _refocusPaused: false,

    init() {
      this.$nextTick(() => this.$refs.scanner?.focus());
      setInterval(() => { if (!this._refocusPaused) this.$refs.scanner?.focus(); }, 2000);
    },

    refocus()       { this._refocusPaused = false; this.$refs.scanner?.focus(); },
    pauseRefocus()  { this._refocusPaused = true; },
    resumeRefocus() { this._refocusPaused = false; this.$refs.scanner?.focus(); },

    onScanKey(e) {
      if (e.key !== 'Enter') return;
      e.preventDefault();
      const raw = e.target.value.trim().toUpperCase();
      const uid = raw.replace(/[^0-9A-F]/g, '');
      e.target.value = '';
      if (!uid) {
        if (this.mode === 'absen') this.absenStatus = { type: 'err', msg: `UID tidak valid: "${raw}" — hanya angka 0-9 dan huruf A-F yang diterima.` };
        return;
      }
      const DEBOUNCE = (parseInt(window.AbsensiAdmin?.rfidDebounce ?? '3', 10) || 3) * 1000;
      const now = Date.now();
      if (uid === this._lastUid && now - this._lastTap < DEBOUNCE) return;
      this._lastUid = uid;
      this._lastTap = now;
      this.mode === 'absen' ? this.doAbsen(uid) : this.doEnroll(uid);
    },

    async doAbsen(uid) {
      this.absenStatus = { type: 'loading', msg: `Memproses ${uid}…` };
      try {
        const data = await window.api.post('absen/rfid', { rfid_uid: uid });
        const icon = data.action === 'masuk' ? '✅' : '🚪';
        this.absenStatus = { type: 'ok', msg: `${icon} ${data.message}` };
        this.absenLog.unshift({ uid, nama: data.siswa, action: data.action, status: data.status ?? '—', jam: new Date().toLocaleTimeString('id-ID') });
      } catch (err) {
        const msg = err.status === 429 ? 'Tap terlalu cepat, tunggu sebentar.'
                  : err.status === 404 ? `Kartu tidak dikenal (${uid}). Daftarkan di tab Enroll.`
                  : err.status === 409 ? (err.data?.message ?? 'Sudah absen hari ini.')
                  : `[${err.status ?? '?'}] ${err.message}`;
        this.absenStatus = { type: 'err', msg };
      }
    },

    async searchEnroll() {
      if (this.enrollSearch.length < 2) { this.enrollResults = []; return; }
      this.enrollSearching = true;
      try {
        const data = await window.api.get(`siswa?search=${encodeURIComponent(this.enrollSearch)}`);
        const q = this.enrollSearch.toLowerCase();
        this.enrollResults = (data.data ?? data ?? [])
          .filter(s => !s.rfid_uid)
          .filter(s => (s.nama ?? '').toLowerCase().includes(q) || (s.nis ?? '').toLowerCase().includes(q));
      } catch { this.enrollResults = []; }
      finally { this.enrollSearching = false; }
    },

    selectTarget(siswa) {
      this.enrollTarget  = siswa;
      this.enrollResults = [];
      this.enrollSearch  = '';
      this.enrollStatus  = null;
      this.enrollUid     = '';
      this.$nextTick(() => this.$refs.scanner?.focus());
    },

    clearTarget() {
      this.enrollTarget = null;
      this.enrollUid    = '';
      this.enrollStatus = null;
    },

    doEnroll(uid) {
      if (!this.enrollTarget) {
        this.enrollStatus = { ok: false, message: 'Pilih siswa dulu sebelum menempelkan kartu.' };
        return;
      }
      this.enrollUid = uid;
      this.submitEnroll();
    },

    async submitEnroll() {
      if (!this.enrollTarget || !this.enrollUid.trim()) return;
      const replace = !!this.enrollTarget.rfid_uid;
      if (replace && !confirm(`${this.enrollTarget.nama} sudah punya kartu. Ganti kartu lama?`)) return;
      this.enrollLoading = true;
      this.enrollStatus  = null;
      try {
        await window.api.post('absen/rfid/enroll', { siswa_id: this.enrollTarget.id, rfid_uid: this.enrollUid.trim().toUpperCase().replace(/[^0-9A-F]/g, ''), replace });
        this.enrollStatus = { ok: true, message: `Kartu berhasil didaftarkan untuk ${this.enrollTarget.nama}.` };
        this.enrollTarget = null;
        this.enrollUid    = '';
      } catch (err) {
        const code = err.data?.code;
        const msg  = code === 'kartu_terpakai'    ? (err.data?.message ?? 'Kartu sudah dipakai siswa lain.')
                   : code === 'sudah_punya_kartu' ? `${this.enrollTarget?.nama} sudah punya kartu.`
                   : err.message;
        this.enrollStatus = { ok: false, message: msg };
      } finally {
        this.enrollLoading = false;
      }
    },
  }));

  Alpine.data('enrollPanel', () => ({
    search:   '',
    results:  [],
    target:   null,
    uidInput: '',
    status:   null,
    loading:  false,

    async searchSiswa() {
      if (this.search.length < 2) return;
      this.loading = true;
      try {
        const data   = await window.api.get(`siswa?search=${encodeURIComponent(this.search)}`);
        this.results = data.data ?? data ?? [];
      } catch {
        this.results = [];
      } finally {
        this.loading = false;
      }
    },

    select(siswa) {
      this.target   = siswa;
      this.uidInput = '';
      this.status   = null;
      this.results  = [];
      this.search   = '';
    },

    async enroll() {
      if (!this.target || !this.uidInput.trim()) return;
      const replace = !!this.target.rfid_uid;
      if (replace && !confirm(`${this.target.nama} sudah punya kartu. Ganti?`)) return;
      this.loading = true;
      this.status  = null;
      try {
        await window.api.post('absen/rfid/enroll', { siswa_id: this.target.id, rfid_uid: this.uidInput.trim().toUpperCase(), replace });
        this.status   = { ok: true, message: `Berhasil mendaftarkan kartu untuk ${this.target.nama}` };
        this.target   = null;
        this.uidInput = '';
      } catch (err) {
        this.status = { ok: false, message: err.status === 409 ? (err.data?.message ?? 'Kartu sudah dipakai siswa lain.') : err.message };
      } finally {
        this.loading = false;
      }
    },
  }));

  Alpine.data('settingsMap', () => ({
    map:    null,
    marker: null,

    init() {
      const root = this.$el;
      this._lat = parseFloat(root.dataset.lat) || -7.250445;
      this._lng = parseFloat(root.dataset.lng) || 112.768845;
      this._boot(0);
    },

    _boot(tries) {
      const el = document.getElementById('absensi-map');
      if (!el) return;
      // Tunggu Leaflet (CDN) siap dan layout punya lebar; ~10 detik lalu menyerah.
      if ((!window.L || el.offsetWidth === 0) && tries < 100) {
        setTimeout(() => this._boot(tries + 1), 100);
        return;
      }
      if (!window.L) {
        el.innerHTML = '<p style="padding:16px;font-size:12px;color:#DC2626;">'
          + 'Gagal memuat library peta (Leaflet). Periksa koneksi internet, lalu klik Refresh peta.</p>';
        return;
      }
      if (this.map) { this.map.remove(); this.map = null; }
      this.map = L.map(el).setView([this._lat, this._lng], 16);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19,
      }).addTo(this.map);
      this.marker = L.marker([this._lat, this._lng], { draggable: true }).addTo(this.map);
      this.marker.on('dragend', () => this._updateInputs());
      this.map.on('click', e => {
        this.marker.setLatLng(e.latlng);
        this._updateInputs();
      });
      requestAnimationFrame(() =>
        requestAnimationFrame(() => this.map && this.map.invalidateSize({ animate: false }))
      );
      if (window.ResizeObserver) {
        this._ro?.disconnect();
        this._ro = new ResizeObserver(() => this.map && this.map.invalidateSize({ animate: false }));
        this._ro.observe(el);
      }
    },

    refresh() {
      if (this.map) { this.map.remove(); this.map = null; }
      this._boot(0);
    },

    _updateInputs() {
      const pos = this.marker.getLatLng();
      const lat = pos.lat.toFixed(7);
      const lng = pos.lng.toFixed(7);
      const latEl = document.querySelector('[name="absensi_lat"]');
      const lngEl = document.querySelector('[name="absensi_lng"]');
      if (latEl) latEl.value = lat;
      if (lngEl) lngEl.value = lng;
      window.dispatchEvent(new CustomEvent('map-pin-moved', { detail: { lat, lng } }));
    },
  }));

  Alpine.data('dashboardTable', () => ({ fmtTime }));

  Alpine.data('siswaManager', () => ({
    siswaList:    [],
    loading:      false,
    error:        null,
    search:       '',
    filterKelas:  '',
    page:         1,
    perPage:      10,
    kelasOptions: [],
    showModal:    false,
    editData:     null,
    saving:       false,
    saveError:    null,
    fieldErrors:  {},

    get filterKelasLabel() {
      const k = this.kelasOptions.find(o => o.id === String(this.filterKelas));
      return k ? k.nama : (window._swI18n?.semua_kelas || 'Semua Kelas');
    },

    get editKelasLabel() {
      if (!this.editData) return (window._swI18n?.pilih_kelas || '— Pilih Kelas —');
      const k = this.kelasOptions.find(o => o.id === String(this.editData?.kelas_id));
      return k ? k.nama : (window._swI18n?.pilih_kelas || '— Pilih Kelas —');
    },

    get filteredList() {
      let list = this.siswaList;
      if (this.search.trim()) {
        const q = this.search.toLowerCase();
        list = list.filter(s => (s.nama || '').toLowerCase().includes(q) || (s.nis || '').toLowerCase().includes(q));
      }
      if (this.filterKelas) {
        list = list.filter(s => String(s.kelas_id) === String(this.filterKelas));
      }
      return list;
    },

    get totalPages() { return Math.max(1, Math.ceil(this.filteredList.length / this.perPage)); },
    get paginatedList() { return this.filteredList.slice((this.page - 1) * this.perPage, this.page * this.perPage); },

    get pageRange() {
      const t = this.totalPages, c = this.page;
      if (t <= 7) return Array.from({ length: t }, (_, i) => i + 1);
      const s   = new Set([1, 2, c - 1, c, c + 1, t - 1, t].filter(p => p >= 1 && p <= t));
      const arr = [...s].sort((a, b) => a - b);
      const out = [];
      let prev  = 0;
      for (const p of arr) { if (p - prev > 1) out.push('…'); out.push(p); prev = p; }
      return out;
    },

    async loadSiswa() {
      this.loading = true;
      this.error   = null;
      try {
        const data     = await window.api.get('siswa');
        this.siswaList = Array.isArray(data) ? data : (data.data || []);
      } catch (err) {
        this.error = err.message;
      } finally {
        this.loading = false;
      }
    },

    openAdd() {
      this.editData    = { id: null, nama: '', nis: '', kelas_id: '' };
      this.saveError   = null;
      this.fieldErrors = {};
      this.showModal   = true;
    },

    openEdit(s) {
      this.editData    = { id: s.id, nama: s.nama, nis: s.nis, kelas_id: s.kelas_id || '' };
      this.saveError   = null;
      this.fieldErrors = {};
      this.showModal   = true;
    },

    validate() {
      const e    = {};
      const i18n = window._swI18n || {};
      if (!this.editData.nama?.trim())  e.nama     = i18n.nama_wajib  || 'Nama lengkap wajib diisi.';
      if (!this.editData.nis?.trim())   e.nis      = i18n.nis_wajib   || 'NIS wajib diisi.';
      if (!this.editData.kelas_id)      e.kelas_id = i18n.kelas_wajib || 'Kelas wajib dipilih.';
      this.fieldErrors = e;
      return Object.keys(e).length === 0;
    },

    async save() {
      if (!this.editData || !this.validate()) return;
      this.saving    = true;
      this.saveError = null;
      try {
        const isNew = !this.editData.id;
        const path  = isNew ? 'siswa' : 'siswa/' + this.editData.id;
        const body  = { nama: this.editData.nama.trim(), nis: this.editData.nis.trim(), kelas_id: parseInt(this.editData.kelas_id) || 0 };
        await (isNew ? window.api.post(path, body) : window.api.put(path, body));
        this.showModal = false;
        this.loadSiswa();
      } catch (err) {
        this.saveError = err.message;
      } finally {
        this.saving = false;
      }
    },

    async deleteSiswa(id, nama) {
      if (!confirm('Hapus ' + nama + '? Tindakan tidak dapat dibatalkan.')) return;
      try {
        await window.api.delete('siswa/' + id);
        this.loadSiswa();
      } catch (err) {
        alert(err.message);
      }
    },

    inisial(nama) {
      return (nama || '?').split(' ').slice(0, 2).map(w => w[0] || '').join('').toUpperCase() || '?';
    },

    openWali(id, nama) {
      window.dispatchEvent(new CustomEvent('open-wali-linker', { detail: { siswaId: id, siswaName: nama } }));
    },

    init() {
      this.kelasOptions = window._swKelasOpts || [];
      this.loadSiswa();
      this.$watch('search',      () => { this.page = 1; });
      this.$watch('filterKelas', () => { this.page = 1; });
    },
  }));

  Alpine.data('settingsForm', () => ({
    saving: false,
    saved:  false,
    errors: {},
    fields: {
      absensi_lat: '', absensi_lng: '', absensi_radius: 100,
      absensi_jam_masuk: '07:00', absensi_jam_keluar: '15:00',
      absensi_telat_menit: 15, absensi_akurasi_max: 50,
      absensi_rfid_debounce: 3, absensi_retensi_hari: 365,
      absensi_wa_gateway: '', absensi_wa_token: '',
    },
    radius: 100,

    init() {
      const el = this.$el;
      this.fields.absensi_lat           = el.dataset.lat          ?? '';
      this.fields.absensi_lng           = el.dataset.lng          ?? '';
      this.fields.absensi_radius        = parseInt(el.dataset.radius       ?? '100', 10) || 100;
      this.fields.absensi_jam_masuk     = el.dataset.jamMasuk     ?? '07:00';
      this.fields.absensi_jam_keluar    = el.dataset.jamKeluar    ?? '15:00';
      this.fields.absensi_telat_menit   = parseInt(el.dataset.telatMenit   ?? '15',  10) || 0;
      this.fields.absensi_akurasi_max   = parseInt(el.dataset.akurasiMax   ?? '50',  10) || 50;
      this.fields.absensi_rfid_debounce = parseInt(el.dataset.rfidDebounce ?? '3',   10) || 3;
      this.fields.absensi_retensi_hari  = parseInt(el.dataset.retensiHari  ?? '365', 10) || 365;
      this.fields.absensi_wa_gateway    = el.dataset.waGateway    ?? '';
      this.radius = this.fields.absensi_radius;

      const s = window.AbsensiAdmin?.settings ?? {};
      if (s.lat)          this.fields.absensi_lat           = s.lat;
      if (s.lng)          this.fields.absensi_lng           = s.lng;
      if (s.radius)       { this.fields.absensi_radius = parseInt(s.radius, 10) || this.fields.absensi_radius; this.radius = this.fields.absensi_radius; }
      if (s.jamMasuk)     this.fields.absensi_jam_masuk     = s.jamMasuk;
      if (s.jamKeluar)    this.fields.absensi_jam_keluar    = s.jamKeluar;
      if (s.telatMenit)   this.fields.absensi_telat_menit   = parseInt(s.telatMenit,   10) || this.fields.absensi_telat_menit;
      if (s.akurasiMax)   this.fields.absensi_akurasi_max   = parseInt(s.akurasiMax,   10) || this.fields.absensi_akurasi_max;
      if (s.rfidDebounce) this.fields.absensi_rfid_debounce = parseInt(s.rfidDebounce, 10) || this.fields.absensi_rfid_debounce;
      if (s.retensiHari)  this.fields.absensi_retensi_hari  = parseInt(s.retensiHari,  10) || this.fields.absensi_retensi_hari;
      if (s.waGateway)    this.fields.absensi_wa_gateway    = s.waGateway;

      window.api.get('settings').then(data => {
        if (data?.absensi_wa_token) this.fields.absensi_wa_token = data.absensi_wa_token;
      }).catch(() => {});

      window.addEventListener('map-pin-moved', e => {
        this.fields.absensi_lat = e.detail.lat;
        this.fields.absensi_lng = e.detail.lng;
      });
    },

    async save() {
      this.saving = true;
      this.errors = {};
      this.saved  = false;
      try {
        const payload = { ...this.fields, absensi_radius: this.radius };
        await window.api.put('settings', payload);
        this.fields.absensi_radius = this.radius;
        this.saved = true;
        setTimeout(() => { this.saved = false; }, 3000);
      } catch (err) {
        if (err.status === 422 && err.data?.errors) {
          this.errors = err.data.errors;
        } else {
          alert(err.message ?? 'Gagal menyimpan pengaturan.');
        }
      } finally {
        this.saving = false;
      }
    },
  }));

  Alpine.data('waliLinker', () => ({
    open:        false,
    siswaId:     null,
    siswaName:   '',
    walis:       [],
    loadingWali: false,
    search:      '',
    results:     [],
    searching:   false,
    addingId:    null,
    error:       null,

    init() {
      window.addEventListener('open-wali-linker', e => {
        this.siswaId   = e.detail.siswaId;
        this.siswaName = e.detail.siswaName;
        this.search    = '';
        this.results   = [];
        this.error     = null;
        this.open      = true;
        this.loadWali();
      });
    },

    async loadWali() {
      if (!this.siswaId) return;
      this.loadingWali = true;
      try {
        const data = await window.api.get(`wali?siswa_id=${this.siswaId}`);
        this.walis = data.data ?? data ?? [];
      } catch {
        this.walis = [];
      } finally {
        this.loadingWali = false;
      }
    },

    async searchUsers() {
      if (this.search.length < 2) { this.results = []; return; }
      this.searching = true;
      try {
        const nonce = window.AbsensiAdmin?.nonce ?? '';
        const url   = `/wp-json/wp/v2/users?search=${encodeURIComponent(this.search)}&roles=orang_tua&_fields=id,name,slug&context=edit`;
        const res   = await fetch(url, { headers: { 'X-WP-Nonce': nonce } });
        const data  = await res.json();
        this.results = Array.isArray(data) ? data : [];
      } catch {
        this.results = [];
      } finally {
        this.searching = false;
      }
    },

    async addWali(user) {
      this.addingId = user.id;
      this.error    = null;
      try {
        await window.api.post('wali', { wali_user_id: user.id, siswa_id: this.siswaId });
        await this.loadWali();
        this.search  = '';
        this.results = [];
      } catch (err) {
        const code = err.data?.code;
        this.error = code === 'sudah_terhubung' ? 'Orang tua ini sudah terhubung.'
                   : code === 'wali_invalid'    ? 'User tidak ditemukan atau bukan orang tua.'
                   : err.message;
      } finally {
        this.addingId = null;
      }
    },

    async removeWali(waliId, waliNama) {
      if (!confirm(`Lepas hubungan "${waliNama}" dari siswa ini?`)) return;
      try {
        await window.api.delete(`wali/${waliId}`);
        this.walis = this.walis.filter(w => w.id !== waliId);
      } catch (err) {
        alert(err.message);
      }
    },

    close() { this.open = false; },
  }));

  Alpine.data('jadwalManager', () => ({
    rows:      [],
    loading:   false,
    saving:    false,
    error:     null,
    showModal: false,
    isEditing: false,
    editId:    null,
    kelasList: [],
    form: { kelas_id: '', hari: 1, jam_masuk: '07:00', jam_keluar: '15:00' },
    HARI: ['', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'],

    init() {
      this.loadKelas();
      this.load();
    },

    async loadKelas() {
      try {
        const data = await window.api.get('kelas');
        this.kelasList = data.data ?? data ?? [];
      } catch {}
    },

    async load() {
      this.loading = true;
      this.error   = null;
      try {
        const data = await window.api.get('jadwal');
        this.rows = data.data ?? data ?? [];
      } catch (err) {
        this.error = err.message;
      } finally {
        this.loading = false;
      }
    },

    openAdd() {
      this.form      = { kelas_id: '', hari: 1, jam_masuk: '07:00', jam_keluar: '15:00' };
      this.editId    = null;
      this.isEditing = false;
      this.showModal = true;
    },

    openEdit(row) {
      this.form = {
        kelas_id:   String(row.kelas_id),
        hari:       row.hari,
        jam_masuk:  row.jam_masuk.slice(0, 5),
        jam_keluar: row.jam_keluar.slice(0, 5),
      };
      this.editId    = row.id;
      this.isEditing = true;
      this.showModal = true;
    },

    async save() {
      if (!this.form.kelas_id || !this.form.hari) return;
      this.saving = true;
      try {
        const body = {
          kelas_id:   parseInt(this.form.kelas_id, 10),
          hari:       parseInt(this.form.hari, 10),
          jam_masuk:  this.form.jam_masuk,
          jam_keluar: this.form.jam_keluar,
        };
        if (this.isEditing) {
          await window.api.put(`jadwal/${this.editId}`, body);
        } else {
          await window.api.post('jadwal', body);
        }
        this.showModal = false;
        await this.load();
      } catch (err) {
        const code = err.data?.code;
        alert(
          code === 'jadwal_duplikat' ? 'Jadwal untuk kelas dan hari ini sudah ada.'
          : code === 'jam_urutan'   ? 'Jam keluar harus lebih besar dari jam masuk.'
          : code === 'jam_invalid'  ? 'Format jam tidak valid. Gunakan format HH:MM.'
          : code === 'kelas_invalid'? 'Kelas tidak ditemukan.'
          : err.message
        );
      } finally {
        this.saving = false;
      }
    },

    async del(id, label) {
      if (!confirm(`Hapus jadwal "${label}"?`)) return;
      try {
        await window.api.delete(`jadwal/${id}`);
        this.rows = this.rows.filter(r => r.id !== id);
      } catch (err) {
        alert(err.message);
      }
    },

    kelasNama(kelas_id) {
      return this.kelasList.find(k => k.id == kelas_id)?.nama_kelas ?? `Kelas #${kelas_id}`;
    },
  }));

}); // end alpine:init
