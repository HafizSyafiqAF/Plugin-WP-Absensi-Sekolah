/**
 * Admin JS — Alpine.js components untuk halaman admin plugin.
 * Alpine.js di-enqueue terpisah oleh PHP (Plugin.php). File ini hanya mendefinisikan komponen.
 * Tidak ada import/export — semua dependency (Alpine, Leaflet) tersedia sebagai global.
 */

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
    const ajaxUrl = window.ajaxurl ?? '/wp-admin/admin-ajax.php';
    try {
      const res = await fetch(`${ajaxUrl}?action=rest-nonce`);
      if (!res.ok) throw new Error('nonce refresh failed');
      _nonce = await res.text();
    } catch {
      throw Object.assign(new Error('Sesi habis, silakan muat ulang halaman.'), { status: 403 });
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

    let res  = await fetch(url, { ...fetchOpts, headers });
    let data = await res.json().catch(() => null);

    /* Auto-retry sekali HANYA bila 403-nya benar-benar nonce kedaluwarsa.
     * 403 juga dipakai untuk penolakan bisnis (mis. di luar radius) — retry buta
     * di situ menembak endpoint dua kali dan bisa memicu rate-limit, sehingga
     * pesan aslinya tertimpa "Terlalu cepat" (bug yang terjadi di kiosk siswa). */
    const nonceKedaluwarsa = data && (data.code === 'rest_cookie_invalid_nonce' || data.code === 'rest_nonce_invalid');
    if (res.status === 403 && nonceKedaluwarsa) {
      await refreshNonce();
      headers.set('X-WP-Nonce', getNonce());
      res  = await fetch(url, { ...fetchOpts, headers });
      data = await res.json().catch(() => null);
    }

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

/* Sentinel filter Tipe (usersManager) untuk "user tanpa group" — dipisah dari '' yang
 * berarti "semua tipe". Nilainya sengaja tak mungkin bentrok dengan tipe group asli. */
const TIPE_NONE = '__tanpa_group__';

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
                  : err.status === 403 ? (err.message ?? 'Sesi habis, silakan muat ulang halaman.')
                  : err.message ?? `Gagal menghubungi server (${err.status ?? 'unknown'}).`;
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
    search:          '',
    filterKelas:   '',
    filterOpen:    false,
    editKelasOpen: false,
    page:            1,
    perPage:      10,
    kelasOptions: [],
    showModal:    false,
    editData:     { id: null, nama: '', nis: '', kelas_id: '' },
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
      this.editData      = { id: null, nama: '', nis: '', kelas_id: '' };
      this.saveError     = null;
      this.fieldErrors   = {};
      this.editKelasOpen = false;
      this.showModal     = true;
    },

    openEdit(s) {
      this.editData      = { id: s.id, nama: s.nama, nis: s.nis, kelas_id: s.kelas_id || '' };
      this.saveError     = null;
      this.fieldErrors   = {};
      this.editKelasOpen = false;
      this.showModal     = true;
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

      // Token WA sengaja TIDAK di-prefill dari server (rahasia — GET /settings tak lagi
      // mengembalikan nilainya, hanya flag absensi_wa_token_set).

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

  /* ─── Jadwal & Hari Libur (design.md §7 — halaman Jadwal, v2.2.0) ────────────
   * Dua tab dalam satu halaman karena keduanya menjawab satu pertanyaan: "kapan absensi berlaku".
   * Keduanya jadi masukan mesin ALPHA (KehadiranHelper):
   *   - Jadwal per group → HARI AKTIF group itu (group tanpa jadwal → Sen–Jum + Jadwal Default).
   *   - Hari libur       → tanggal yang tak dihitung sama sekali (tak ada alpha).
   * Endpoint: /jadwal (CRUD per group+hari), /libur (CRUD rentang tanggal), /group (opsi).
   * (Manager lama pra-pivot dibuang: masih memanggil endpoint `kelas` yang sudah 404.) */
  Alpine.data('jadwalManager', () => ({
    tab: 'jadwal',            // 'jadwal' | 'libur'

    // ── Data ──
    groups:  [],
    jadwal:  [],              // GET /jadwal (bawa nama_group)
    libur:   [],              // GET /libur
    loading: false,
    error:   false,

    HARI: ['', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'],

    // ── Tab Jadwal: satu group dilihat sekaligus 7 hari ──
    groupId: '',              // group yang sedang diatur
    baris:   {},              // { [hari]: { aktif, jam_masuk, jam_keluar, id } } — form 7 hari
    simpanHari: 0,            // hari yang sedang disimpan (0 = tak ada)
    jadwalError: '',

    // ── Tab Libur ──
    formLibur:  { tanggal_mulai: '', tanggal_selesai: '', keterangan: '' },
    liburBusy:  false,
    liburError: '',
    hapusLiburId: 0,

    init() {
      // Tabel 7 hari SELALU dirender (x-show hanya menyembunyikan, tak mencegah render x-for) →
      // baris[h] harus sudah ada sejak awal, kalau tidak Alpine melempar "reading 'aktif'".
      this.isiBaris();
      this.loadGroups();
      this.loadJadwal();
      this.loadLibur();
    },

    async loadGroups() {
      try { this.groups = await window.api.get('group') || []; }
      catch (e) { this.groups = []; }
    },

    async loadJadwal() {
      this.loading = true; this.error = false;
      try {
        this.jadwal = await window.api.get('jadwal') || [];
        this.isiBaris();
      } catch (e) {
        this.error = true; this.jadwal = [];
      } finally {
        this.loading = false;
      }
    },

    async loadLibur() {
      try { this.libur = await window.api.get('libur') || []; }
      catch (e) { this.libur = []; }
    },

    /* Bangun form 7 hari untuk group terpilih dari jadwal yang termuat.
       Hari yang belum punya jadwal → kosong (tak aktif). */
    isiBaris() {
      var gid = String(this.groupId);
      var out = {};
      for (var h = 1; h <= 7; h++) {
        var j = this.jadwal.filter(function (x) {
          return String(x.group_id) === gid && Number(x.hari) === h;
        })[0];
        out[h] = j
          ? { aktif: true,  id: j.id, jam_masuk: String(j.jam_masuk).slice(0, 5), jam_keluar: String(j.jam_keluar).slice(0, 5) }
          : { aktif: false, id: null, jam_masuk: '07:00', jam_keluar: '15:00' };
      }
      this.baris = out;
    },
    gantiGroup() { this.jadwalError = ''; this.isiBaris(); },

    /* Group yang SUDAH punya jadwal → dipakai untuk keterangan "memakai Jadwal Default" di toolbar. */
    punyaJadwal(gid) {
      return this.jadwal.some(function (j) { return String(j.group_id) === String(gid); });
    },
    get groupTerpilih() {
      var gid = String(this.groupId);
      return this.groups.filter(function (g) { return String(g.id) === gid; })[0] || null;
    },

    /* Simpan satu hari: belum ada → POST, sudah ada → PUT. Toggle mati → DELETE. */
    async simpanBaris(h) {
      if (! this.groupId || this.simpanHari) return;
      var b = this.baris[h];
      this.simpanHari = h; this.jadwalError = '';
      try {
        if (! b.aktif) {
          if (b.id) { await window.api.delete('jadwal/' + b.id); }
        } else {
          var body = {
            group_id:   parseInt(this.groupId, 10),
            hari:       h,
            jam_masuk:  b.jam_masuk,
            jam_keluar: b.jam_keluar,
          };
          if (b.id) { await window.api.put('jadwal/' + b.id, body); }
          else      { await window.api.post('jadwal', body); }
        }
        window.absensiToast('Jadwal ' + this.HARI[h] + ' disimpan.', 'success');
        await this.loadJadwal();
      } catch (err) {
        // 409 jadwal_duplikat · 422 jam_urutan/jam_invalid → tampilkan pesan BE apa adanya.
        this.jadwalError = window.absensiApiError(err).message;
      } finally {
        this.simpanHari = 0;
      }
    },

    // ── Hari libur ──
    get liburValid() { return !! this.formLibur.tanggal_mulai; },

    async tambahLibur() {
      if (! this.liburValid || this.liburBusy) return;
      this.liburBusy = true; this.liburError = '';
      try {
        var body = {
          tanggal_mulai: this.formLibur.tanggal_mulai,
          keterangan:    this.formLibur.keterangan,
        };
        // Kosong → BE anggap libur sehari (selesai = mulai).
        if (this.formLibur.tanggal_selesai) { body.tanggal_selesai = this.formLibur.tanggal_selesai; }
        await window.api.post('libur', body);
        window.absensiToast('Hari libur ditambahkan.', 'success');
        this.formLibur = { tanggal_mulai: '', tanggal_selesai: '', keterangan: '' };
        await this.loadLibur();
      } catch (err) {
        this.liburError = window.absensiApiError(err).message;   // 422 rentang_terbalik / tanggal_invalid
      } finally {
        this.liburBusy = false;
      }
    },

    async hapusLibur(l) {
      if (this.hapusLiburId) return;
      this.hapusLiburId = l.id; this.liburError = '';
      try {
        await window.api.delete('libur/' + l.id);
        window.absensiToast('Hari libur dihapus.', 'success');
        await this.loadLibur();
      } catch (err) {
        this.liburError = window.absensiApiError(err).message;
      } finally {
        this.hapusLiburId = 0;
      }
    },

    /* '2026-08-17' → '17 Agu 2026'. Libur sehari tampil satu tanggal saja. */
    tglPendek(t) {
      if (! t) return '—';
      var B = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
      var p = String(t).slice(0, 10).split('-');
      return Number(p[2]) + ' ' + B[Number(p[1]) - 1] + ' ' + p[0];
    },
    rentangTeks(l) {
      return l.tanggal_mulai === l.tanggal_selesai
        ? this.tglPendek(l.tanggal_mulai)
        : this.tglPendek(l.tanggal_mulai) + ' – ' + this.tglPendek(l.tanggal_selesai);
    },
    /* Jumlah hari yang dicakup satu baris libur (inklusif). */
    jumlahHari(l) {
      var a = new Date(l.tanggal_mulai + 'T00:00:00');
      var b = new Date(l.tanggal_selesai + 'T00:00:00');
      return Math.round((b - a) / 86400000) + 1;
    },
  }));

  /* ─── Users manager (design.md §5) — pivot v2 ────────────────────────────────
   * Dibangun bertahap per item TODO-FE. Kini: aksi header + filter bar (search/group).
   * Berikutnya: tabel (GET /users) + pagination, modal form/bind/import, hapus, state.
   * Endpoint: /users CRUD, /users/{id}/rfid, /users/import; opsi group GET /group. */
  Alpine.data('usersManager', () => ({
    // ── Filter ──
    // Catatan kontrak BE: GET /users = array polos, hanya honor `group_id` (server);
    // TAK ADA param `search` & TAK ADA pagination server → search + paging = client-side.
    search:  '',          // filter client (nama/nomor_induk)
    groupId: '',          // param `group_id` (server-side)
    tipeFilter: '',       // filter client by tipe group; '' = semua, TIPE_NONE = tanpa group
    groups:  [],          // opsi group (GET /group) → { id, nama, tipe, jumlah_user }
    page:    1,           // halaman aktif (client)
    perPage: 8,           // 8 baris per halaman (ikut acuan desain)

    // ── Menu aksi per baris (kebab "…") + dropdown Import/Export ──
    rowMenu:        null,  // id user yang menunya terbuka; null = tertutup
    importMenuOpen: false,
    exportMenuOpen: false,

    // ── Data tabel ──
    users:   [],          // baris GET /users (u.* + nama_group + tipe_group)
    loading: false,
    error:   false,

    // ── Modal Form (tambah/edit) ──
    modalOpen: false,
    editing:   null,      // id user saat edit; null = tambah
    saving:    false,
    form:      { nomor_induk: '', nama: '', group_id: '', rfid_uid: '' },
    /* Tipe = milik GROUP (tak ada kolom tipe di absensi_users) → radio ini BUKAN
     * field yang disimpan, tapi penyaring daftar grup di dropdown. Tipe user
     * tetap ikut grup yang dipilih. */
    formTipe:  '',        // filter tipe di modal; '' = semua (tanpa preset)
    formTipeMenuOpen: false, // dropdown Tipe modal buka/tutup
    formError: '',        // pesan error tingkat form (409/lainnya)
    fieldErr:  {},        // { nomor_induk:true, nama:true, rfid_uid:true } → tandai field

    // ── Modal Bind RFID ──
    // Catatan BE (set_rfid): TAK ada param `replace`/`sudah_punya_kartu`; kartu user sendiri
    // ditimpa diam-diam, hanya blok bila UID milik user lain (409 kartu_terpakai). Maka guard
    // "Ganti kartu" = CLIENT-side (deteksi dari u.rfid_uid); `replace` dikirim utk forward-compat.
    bindOpen:    false,
    bindUser:    null,    // { id, nama, rfid_uid }
    bindUid:     '',
    bindReplace: false,
    binding:     false,
    bindError:   '',

    // ── Modal Import Excel (user absensi) ──
    importOpen:     false,
    importing:      false,
    importFile:     null,   // File terpilih
    importFileName: '',
    importResult:   null,   // { imported, gagal, errors:[{baris,pesan}] }
    importError:    '',     // 503 spreadsheet_absen / 422 header/baris/dll

    // ── Modal Import Akun Guru (WP user role guru) → POST /guru/import ──
    importGuruOpen:     false,
    importGuruBusy:     false,
    importGuruFile:     null,
    importGuruFileName: '',
    importGuruResult:   null,   // { imported, gagal, errors:[{baris,pesan}] }
    importGuruError:    '',     // 503 vendor absen / 422 header/baris

    // ── Konfirmasi Hapus ──
    delOpen:  false,
    delUser:  null,         // { id, nama }
    deleting: false,

    // ── Checklist / aksi massal ──
    // `selected` = id user tercentang; bertahan lintas halaman & filter (id, bukan indeks baris),
    // jadi bisa centang di hal. 1, pindah hal. 2, centang lagi, lalu hapus sekali jalan.
    selected:      [],
    bulkDelOpen:   false,
    bulkDeleting:  false,

    init() { this.loadGroups(); this.loadUsers(); },

    /* Muat opsi group untuk Select (GET /group). Gagal → biarkan kosong (filter tetap jalan). */
    async loadGroups() {
      try { this.groups = await window.api.get('group') || []; }
      catch (e) { this.groups = []; }
    },

    /* Ambil daftar user. group_id difilter server; search + paging diproses client. */
    async loadUsers() {
      this.loading = true; this.error = false;
      try {
        var url = this.groupId ? ('users?group_id=' + encodeURIComponent(this.groupId)) : 'users';
        this.users = await window.api.get(url) || [];
        this.pangkasSelected();   // buang centang yang usernya sudah tak ada di data baru
      } catch (e) {
        this.error = true; this.users = [];
      } finally {
        this.loading = false;
      }
    },

    /* Reset filter ke kondisi awal lalu refetch (group berubah → server refetch). */
    resetFilter() {
      this.search = ''; this.groupId = ''; this.tipeFilter = ''; this.page = 1;
      this.loadUsers();
    },

    /* Opsi dropdown Tipe (filter) — sumbernya DAFTAR GROUP, bukan daftar user: `tipe` milik group
     * (BE v2.1.0 VARCHAR bebas, tanpa preset). Dulu dibangun dari user yang termuat, tapi itu bikin
     * filter KOSONG selama belum ada user — padahal group + tipenya sudah dibuat. Kalau sebuah tipe
     * belum punya user, filternya sah-sah saja menghasilkan tabel kosong.
     * User tanpa group (group_id=0) → sentinel TIPE_NONE, kalau tidak mereka lenyap dari tiap filter. */
    get tipeOptions() {
      var opts = this.tipeGroupOptions.map(function (t) {
        return { value: t, label: t.charAt(0).toUpperCase() + t.slice(1) };
      });
      var adaTanpaGroup = this.users.some(function (u) { return ! String(u.tipe_group || '').trim(); });
      if (adaTanpaGroup) { opts.push({ value: TIPE_NONE, label: 'Tanpa Group' }); }
      return opts;
    },

    /* Opsi dropdown Grup di TOOLBAR — ikut filter Tipe yang sedang dipilih. Kalau tidak, memilih
       tipe "Guru" tetap menampilkan grup bertipe lain (mis. dua "5B" beda tipe) → membingungkan.
       Label sengaja nama polos (tanpa tempelan tipe) sesuai permintaan; ambiguitas nama kembar
       diselesaikan lewat penyempitan by Tipe ini. */
    get groupsForFilter() {
      var t = this.tipeFilter;
      if (! t || t === TIPE_NONE) return this.groups;
      return this.groups.filter(function (g) { return String(g.tipe || '').trim() === t; });
    },
    /* Ganti filter Tipe → buang pilihan Grup yang tak lagi cocok (muat ulang, filter grup server-side). */
    gantiTipeFilter() {
      this.page = 1;
      if (! this.groupId) return;
      var masih = this.groupsForFilter.some((g) => String(g.id) === String(this.groupId));
      if (! masih) { this.groupId = ''; this.loadUsers(); }
    },

    // ── Turunan client-side: search + tipe + pagination (bersusun) ──
    get filteredUsers() {
      var q = this.search.trim().toLowerCase();
      var t = this.tipeFilter;
      return this.users.filter(function (u) {
        if (t) {
          var tu = String(u.tipe_group || '').trim();
          if (t === TIPE_NONE ? tu !== '' : tu !== t) return false;
        }
        if (!q) return true;
        return String(u.nama || '').toLowerCase().indexOf(q) !== -1
            || String(u.nomor_induk || '').toLowerCase().indexOf(q) !== -1;
      });
    },
    get totalFiltered() { return this.filteredUsers.length; },
    get totalPages()    { return Math.max(1, Math.ceil(this.totalFiltered / this.perPage)); },
    get pagedUsers()    {
      var p = Math.min(this.page, this.totalPages);
      var start = (p - 1) * this.perPage;
      return this.filteredUsers.slice(start, start + this.perPage);
    },
    get pageStart() { return this.totalFiltered ? ((Math.min(this.page, this.totalPages) - 1) * this.perPage) + 1 : 0; },
    get pageEnd()   { return Math.min(Math.min(this.page, this.totalPages) * this.perPage, this.totalFiltered); },
    /* Deret tombol halaman dgn elipsis '…' (windowing bila banyak). */
    get pageWindow() {
      var tp = this.totalPages, cur = Math.min(this.page, tp), out = [];
      if (tp <= 7) { for (var i = 1; i <= tp; i++) out.push(i); return out; }
      out.push(1);
      var lo = Math.max(2, cur - 1), hi = Math.min(tp - 1, cur + 1);
      if (lo > 2) out.push('…');
      for (var j = lo; j <= hi; j++) out.push(j);
      if (hi < tp - 1) out.push('…');
      out.push(tp);
      return out;
    },
    goPage(p) { if (typeof p === 'number' && p >= 1 && p <= this.totalPages) this.page = p; },

    // ── Util tampilan ──
    inisial(nama) {
      var parts = String(nama || '?').trim().split(/\s+/).slice(0, 2).map(function (s) { return s.charAt(0); });
      return (parts.join('') || '?').toUpperCase();
    },
    maskRfid(uid) {
      if (!uid) return '';
      var s = String(uid);
      return s.length <= 4 ? s : '••••' + s.slice(-4);
    },
    tipeBadge(tipe) {
      return ({ kelas: 'badge--kelas', guru: 'badge--guru', staff: 'badge--staff' })[tipe] || 'badge--kelas';
    },
    tipeLabel(tipe) {
      return ({ kelas: 'Kelas', guru: 'Guru', staff: 'Staff' })[tipe] || (tipe || '');
    },
    /* Warna avatar: DETERMINISTIK dari nama (hash) — user yang sama selalu dapat
     * warna sama di tiap render/halaman. Dekoratif, bukan penanda status. */
    avatarTone(nama) {
      var s = String(nama || ''), h = 0;
      for (var i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) % 997;
      return 'table__avatar--t' + ((h % 6) + 1);
    },
    /* Label nomor induk: SATU field untuk NIS maupun NIP (tipe group kini bebas,
       jadi heuristik kelas→NIS tak lagi berlaku). */
    nomorLabel() { return 'NIS/NIP'; },

    // ── Menu aksi per baris ("…") ──
    toggleRowMenu(id) { this.rowMenu = this.rowMenu === id ? null : id; },
    closeRowMenu()    { this.rowMenu = null; },
    /* Tutup menu dulu, baru jalankan aksinya (menu tak menggantung di atas modal). */
    menuAksi(fn, u)   { this.rowMenu = null; this[fn](u); },

    // ── Modal Form: buka/tutup/simpan ──
    _resetForm() {
      this.form = { nomor_induk: '', nama: '', group_id: '', rfid_uid: '' };
      this.formTipe = '';   // '' = semua tipe (tak ada preset; tipe datang dari data group)
      this.formTipeMenuOpen = false;
      this.formError = ''; this.fieldErr = {};
    },

    /* Opsi dropdown Tipe pada MODAL — MURNI dari tipe daftar GROUP (tanpa preset). Belum ada group → kosong.
       Beda dari `tipeOptions` di atas: itu untuk dropdown FILTER (dibangun dari tipe_group milik USERS
       + sentinel Tanpa Group). Jangan disatukan — nama harus beda, kalau tidak getter-nya saling timpa. */
    get tipeGroupOptions() {
      var dipakai = this.groups.map(function (g) { return g.tipe; }).filter(Boolean);
      return Array.from(new Set(dipakai)).sort(function (a, b) { return a.localeCompare(b, 'id'); });
    },
    /* Label tombol dropdown Tipe modal: '' → "Semua Tipe", selain itu label tipe-nya. */
    get formTipeLabel() {
      return this.formTipe ? this.tipeLabel(this.formTipe) : 'Semua Tipe';
    },
    /* Opsi grup pada modal, disaring sesuai Tipe terpilih. formTipe '' = tampilkan semua. */
    get groupsByTipe() {
      var t = this.formTipe;
      if (! t) return this.groups;
      return this.groups.filter(function (g) { return g.tipe === t; });
    },
    /* Ganti Tipe → kosongkan pilihan grup yang tak lagi cocok. */
    gantiTipe(t) {
      this.formTipe = t;
      var masih = this.groupsByTipe.some((g) => String(g.id) === String(this.form.group_id));
      if (! masih) this.form.group_id = '';
    },

    /* Buka modal Tambah User (form kosong). */
    openCreate() {
      this._resetForm();
      this.editing = null;
      this.modalOpen = true;
      this._focusById('uf-nama');
    },

    /* Buka modal Edit User (form terisi dari baris; radio Tipe ikut tipe grup user). */
    openEdit(u) {
      this._resetForm();
      this.editing = u.id;
      this.form = {
        nomor_induk: u.nomor_induk || '',
        nama:        u.nama || '',
        group_id:    u.group_id ? String(u.group_id) : '',
        rfid_uid:    u.rfid_uid || '',   // tak ada di form (bind lewat menu baris) — nilai lama dipertahankan
      };
      this.formTipe = u.tipe_group || '';
      this.modalOpen = true;
      this._focusById('uf-nama');
    },

    closeModal() { this.modalOpen = false; },

    _focusById(id) {
      this.$nextTick(function () { var el = document.getElementById(id); if (el) el.focus(); });
    },

    /* Focus-trap modal (a11y): Tab di elemen terakhir → balik ke pertama, & sebaliknya.
     * Dipasang @keydown.tab pada tiap .modal. Esc & aria-* sudah di markup. */
    trapFocus(e) {
      var root = e.currentTarget;
      var els = root.querySelectorAll(
        'a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])'
      );
      if (!els.length) return;
      var first = els[0], last = els[els.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    },

    /* Wajib nomor_induk & nama terisi (design.md §5: Simpan disabled bila kosong). */
    get canSave() { return !this.saving && this.form.nomor_induk.trim() !== '' && this.form.nama.trim() !== ''; },

    /* Simpan (POST tambah / PUT edit). Tangani 422 (field wajib) & 409 (duplikat). */
    async save() {
      if (!this.canSave) return;
      this.saving = true; this.formError = ''; this.fieldErr = {};
      try {
        var body = { nomor_induk: this.form.nomor_induk.trim(), nama: this.form.nama.trim() };
        body.group_id = this.form.group_id ? parseInt(this.form.group_id, 10) : 0;
        var uid = this.form.rfid_uid.trim();
        if (uid) body.rfid_uid = uid;                 // omit bila kosong → hindari bentrok UNIQUE ''
        if (this.editing) await window.api.put('users/' + this.editing, body);
        else              await window.api.post('users', body);
        window.absensiToast(this.editing ? 'User diperbarui.' : 'User ditambahkan.', 'success');
        this.modalOpen = false;
        this.loadUsers();
      } catch (err) {
        var e = window.absensiApiError(err);
        if (e.status === 422) {
          this.fieldErr = { nomor_induk: true, nama: true };   // field wajib (server)
        } else if (e.status === 409) {
          this.fieldErr = { nomor_induk: true, rfid_uid: true }; // duplikat (BE generik: salah satu unik)
        }
        this.formError = e.message;                            // pesan tetap tampil di form
      } finally {
        this.saving = false;
      }
    },

    // ── Modal Bind RFID: buka/tutup/simpan ──
    /* Buka modal bind; input UID auto-focus supaya scanner (HID) langsung mengisi. */
    openBind(u) {
      this.bindUser = { id: u.id, nama: u.nama, rfid_uid: u.rfid_uid || '' };
      this.bindUid = ''; this.bindReplace = false; this.bindError = '';
      this.bindOpen = true;
      this.$nextTick(function () { if (this.$refs.binduid) this.$refs.binduid.focus(); }.bind(this));
    },
    closeBind() { this.bindOpen = false; },
    /* User sudah punya kartu → wajib centang "Ganti" dulu (guard client, cegah timpa tak sengaja). */
    get bindHasCard() { return !!(this.bindUser && this.bindUser.rfid_uid); },
    get canBind() { return !this.binding && this.bindUid.trim() !== '' && (!this.bindHasCard || this.bindReplace); },

    /* Simpan bind (POST /users/{id}/rfid). Tangani 409 kartu_terpakai / 422 uid_kosong. */
    async saveBind() {
      if (!this.canBind) return;
      this.binding = true; this.bindError = '';
      try {
        var body = { rfid_uid: this.bindUid.trim() };
        if (this.bindReplace) body.replace = true;      // BE abaikan; forward-compat
        await window.api.post('users/' + this.bindUser.id + '/rfid', body);
        window.absensiToast('Kartu terpasang.', 'success');
        this.bindOpen = false;
        this.loadUsers();
      } catch (err) {
        this.bindError = window.absensiApiError(err).message;   // 409 kartu_terpakai / 422 uid_kosong
      } finally {
        this.binding = false;
      }
    },

    // ── Modal Import Excel: buka/tutup/jalankan ──
    openImport() {
      this.importFile = null; this.importFileName = '';
      this.importResult = null; this.importError = '';
      this.importOpen = true;
    },
    closeImport() { this.importOpen = false; },
    onImportFile(e) {
      var f = e.target.files && e.target.files[0];
      this.importFile = f || null;
      this.importFileName = f ? f.name : '';
      this.importResult = null; this.importError = '';
    },

    /* Kirim .xlsx sbg base64 (window.api = JSON; BE terima param `file`). 200 {imported,gagal,errors}. */
    async runImport() {
      if (!this.importFile || this.importing) return;
      this.importing = true; this.importError = ''; this.importResult = null;
      try {
        var dataUrl = await this._fileToBase64(this.importFile);
        var data = await window.api.post('users/import', { file: dataUrl });
        this.importResult = data;                        // { imported, gagal, errors }
        if (data && data.imported > 0) {
          window.absensiToast(data.imported + ' user diimpor.', 'success');
          this.loadUsers();
        }
      } catch (err) {
        this.importError = window.absensiApiError(err).message;   // 503 / 422 header/baris
      } finally {
        this.importing = false;
      }
    },
    _fileToBase64(file) {
      return new Promise(function (resolve, reject) {
        var r = new FileReader();
        r.onload  = function () { resolve(r.result); };  // data:...;base64,XXX (BE strip prefix)
        r.onerror = function () { reject(r.error); };
        r.readAsDataURL(file);
      });
    },

    // ── Modal Import Akun Guru: buka/tutup/pilih/jalankan (POST /guru/import) ──
    // Beda dari Import Excel (user absensi): ini buat WP user role `guru`.
    // Kolom xlsx: `username` WAJIB + `nama`/`password`/`email` opsional.
    openImportGuru() {
      this.importGuruFile = null; this.importGuruFileName = '';
      this.importGuruResult = null; this.importGuruError = '';
      this.importGuruOpen = true;
    },
    closeImportGuru() { this.importGuruOpen = false; },
    onImportGuruFile(e) {
      var f = e.target.files && e.target.files[0];
      this.importGuruFile = f || null;
      this.importGuruFileName = f ? f.name : '';
      this.importGuruResult = null; this.importGuruError = '';
    },
    async runImportGuru() {
      if (!this.importGuruFile || this.importGuruBusy) return;
      this.importGuruBusy = true; this.importGuruError = ''; this.importGuruResult = null;
      try {
        var dataUrl = await this._fileToBase64(this.importGuruFile);
        var data = await window.api.post('guru/import', { file: dataUrl });
        this.importGuruResult = data;                    // { imported, gagal, errors }
        if (data && data.imported > 0) {
          window.absensiToast(data.imported + ' akun guru dibuat.', 'success');
        }
      } catch (err) {
        this.importGuruError = window.absensiApiError(err).message;   // 503 vendor / 422 header/baris
      } finally {
        this.importGuruBusy = false;
      }
    },

    // Unduh kredensial akun guru baru (dari hasil import) sebagai CSV. Dibangun client-side
    // dari importGuruResult.kredensial — password ada HANYA di respons ini (tak tersimpan di DB).
    downloadGuruCredentials() {
      var list = (this.importGuruResult && this.importGuruResult.kredensial) || [];
      if (!list.length) return;
      var esc = function (v) {
        v = (v == null ? '' : String(v));
        return /[",\r\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v;
      };
      var lines = ['Username,Password,Nama,Email'];
      list.forEach(function (k) {
        lines.push([k.username, k.password, k.nama, k.email].map(esc).join(','));
      });
      var blob = new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
      var url  = URL.createObjectURL(blob);
      var a    = document.createElement('a');
      a.href = url; a.download = 'akun-guru-baru.csv';
      document.body.appendChild(a); a.click(); a.remove();
      URL.revokeObjectURL(url);
      window.absensiToast('Daftar akun diunduh. Simpan baik-baik.', 'success');
    },

    // ── Export data user / akun guru (unduh xlsx) ──
    // Endpoint stream file + butuh X-WP-Nonce → pakai fetch blob (bukan anchor polos)
    // supaya bisa tangani 503 (vendor absen) / error. Mirror pola export laporan.
    exportUsers() { this._downloadExport('users/export?format=xlsx', 'data-user.xlsx'); },
    exportGuru()  { this._downloadExport('guru/export?format=xlsx', 'akun-guru.xlsx'); },
    async _downloadExport(path, fallbackName) {
      var cfg = window.AbsensiAdmin || {};
      var url = (cfg.restUrl || '/wp-json/absensi/v1/') + path;
      try {
        var res = await fetch(url, { headers: { 'X-WP-Nonce': cfg.nonce || '' } });
        if (!res.ok) {
          var err = await res.json().catch(function () { return null; });
          var msg = (err && err.message) || ('Export gagal (HTTP ' + res.status + ').');
          window.absensiToast(msg, res.status === 503 ? 'warning' : 'error');
          return;
        }
        var blob = await res.blob();
        var fname = fallbackName;
        var m = (res.headers.get('Content-Disposition') || '').match(/filename="?([^";]+)"?/);
        if (m) fname = m[1];
        var objUrl = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = objUrl; a.download = fname;
        document.body.appendChild(a); a.click(); a.remove();
        URL.revokeObjectURL(objUrl);
        window.absensiToast('File diunduh: ' + fname, 'success');
      } catch (e) {
        window.absensiToast('Export gagal. Periksa koneksi lalu coba lagi.', 'error');
      }
    },

    // ── Konfirmasi Hapus: buka/tutup/jalankan ──
    confirmDelete(u) { this.delUser = { id: u.id, nama: u.nama }; this.delOpen = true; },
    closeDelete() { this.delOpen = false; },

    /* ── Checklist (pilih banyak → aksi massal) ──────────────────────────────
     * Centang disimpan sebagai ID, bukan indeks baris: aman saat ganti halaman/filter/urut.
     * Header checkbox = halaman AKTIF saja (bukan seluruh hasil filter) — biar tak ada yang
     * ikut terhapus tanpa terlihat; untuk semuanya ada tombol "Pilih semua N hasil". */
    isSelected(id) { return this.selected.indexOf(id) !== -1; },
    toggleSelect(id) {
      var i = this.selected.indexOf(id);
      if (i === -1) this.selected.push(id);
      else this.selected.splice(i, 1);
    },
    get selectedCount() { return this.selected.length; },
    get pageIds() { return this.pagedUsers.map(function (u) { return u.id; }); },
    get allPageSelected() {
      var ids = this.pageIds;
      return ids.length > 0 && ids.every((id) => this.isSelected(id));
    },
    /* Sebagian tercentang → checkbox header jadi indeterminate (bukan checked penuh). */
    get somePageSelected() {
      return ! this.allPageSelected && this.pageIds.some((id) => this.isSelected(id));
    },
    toggleSelectPage() {
      var ids = this.pageIds;
      if (this.allPageSelected) {
        this.selected = this.selected.filter(function (id) { return ids.indexOf(id) === -1; });
      } else {
        ids.forEach((id) => { if (! this.isSelected(id)) this.selected.push(id); });
      }
    },
    selectAllFiltered() { this.selected = this.filteredUsers.map(function (u) { return u.id; }); },
    clearSelection() { this.selected = []; },
    /* Sinkron centang dengan data terbaru: id yang hilang (terhapus / kena filter server) dibuang,
     * supaya tak pernah mengirim id yang tak kelihatan di tabel. */
    pangkasSelected() {
      var ada = this.users.map(function (u) { return u.id; });
      this.selected = this.selected.filter(function (id) { return ada.indexOf(id) !== -1; });
    },

    // ── Hapus massal → POST /users/bulk-delete { ids } (satu request, bukan N) ──
    confirmBulkDelete() { if (this.selectedCount) this.bulkDelOpen = true; },
    closeBulkDelete() { this.bulkDelOpen = false; },
    async runBulkDelete() {
      if (! this.selectedCount || this.bulkDeleting) return;
      this.bulkDeleting = true;
      try {
        var data = await window.api.post('users/bulk-delete', { ids: this.selected });
        var n = (data && data.deleted) || 0;
        var r = (data && data.rekap_dihapus) || 0;
        window.absensiToast(n + ' user dihapus' + (r ? ' (' + r + ' baris absensi ikut terhapus)' : '') + '.', 'success');
        this.bulkDelOpen = false;
        this.clearSelection();
        this.page = 1;
        this.loadUsers();
      } catch (err) {
        window.absensiToastError(err);   // gagal → toast merah, modal tetap terbuka
      } finally {
        this.bulkDeleting = false;
      }
    },

    async runDelete() {
      if (!this.delUser || this.deleting) return;
      this.deleting = true;
      try {
        await window.api.delete('users/' + this.delUser.id);
        window.absensiToast('User dihapus.', 'success');
        this.delOpen = false;
        this.loadUsers();
      } catch (err) {
        window.absensiToastError(err);   // gagal → toast merah, modal tetap (design.md §5)
      } finally {
        this.deleting = false;
      }
    },
  }));

  /* ─── Group manager (design.md §6) — pivot v2 ────────────────────────────────
   * Dibangun bertahap per item TODO-FE. Kini: aksi header (Tambah Group).
   * Berikutnya: tabel (GET /group) + modal form + hapus (409 group_ada_user) + state.
   * Endpoint: /group CRUD. */
  /* ─── Laporan manager (design.md §8) — pivot v2 ──────────────────────────────
   * Dibangun bertahap per item TODO-FE. Kini: aksi header (dropdown Export).
   * Berikutnya: summary cards, filter server + pill status client, tabel + pagination,
   * export (unduh + 503), state. Endpoint: /laporan, /laporan/summary, /laporan/export.
   * Label pakai "Group" & "Nomor Induk". */
  /* ─── Settings manager (design.md §9) — pivot v2 ─────────────────────────────
   * Dibangun bertahap per item TODO-FE. Kini: 5 card + prefill (GET /settings).
   * Berikutnya: map picker, Simpan (PUT /settings) + 422.
   * Prefill: GET /settings; fallback AbsensiAdmin.settings. Token TAK di-prefill (sensitif). */
  Alpine.data('settingsManager', () => ({
    form: {
      lat: '', lng: '', radius: 100, akurasi_max: 100,
      jam_masuk: '07:00', jam_keluar: '15:00', telat_menit: 15,
      rfid_debounce: 3, retensi_hari: 90,
      wa_gateway: '', wa_token: '',   // wa_token tak di-prefill (placeholder ••••)
    },
    hasToken:  false,   // server sudah punya token → placeholder ••••
    loading:   false,
    error:     false,
    saving:    false,
    fieldErr:  {},

    init() { this.loadSettings(); },

    /* Petakan respons /settings (key absensi_*) → form. */
    _apply(d) {
      if (!d) return;
      this.form.lat           = d.absensi_lat ?? '';
      this.form.lng           = d.absensi_lng ?? '';
      this.form.radius        = d.absensi_radius ?? 100;
      this.form.akurasi_max   = d.absensi_akurasi_max ?? 100;
      this.form.jam_masuk     = d.absensi_jam_masuk || '07:00';
      this.form.jam_keluar    = d.absensi_jam_keluar || '15:00';
      this.form.telat_menit   = d.absensi_telat_menit ?? 15;
      this.form.rfid_debounce = d.absensi_rfid_debounce ?? 3;
      this.form.retensi_hari  = d.absensi_retensi_hari ?? 90;
      this.form.wa_gateway    = d.absensi_wa_gateway || '';
      this.hasToken           = !!d.absensi_wa_token_set;   // flag boolean; token mentah tak dikirim server
    },

    /* Prefill GET /settings; gagal → fallback AbsensiAdmin.settings. */
    async loadSettings() {
      this.loading = true; this.error = false;
      try {
        this._apply(await window.api.get('settings'));
      } catch (e) {
        var cfg = (window.AbsensiAdmin && window.AbsensiAdmin.settings) || null;
        if (cfg) { this._apply(cfg); } else { this.error = true; }
      } finally {
        this.loading = false;
      }
    },

    /* Map picker (design.md §9) — versi tanpa lib peta (BE belum enqueue Leaflet):
     * isi lat/lng dari GPS perangkat operator (biasanya di sekolah). */
    locating: false,
    useMyLocation() {
      if (!navigator.geolocation) { window.absensiToast('Perangkat tak mendukung GPS.', 'warning'); return; }
      var self = this;
      this.locating = true;
      navigator.geolocation.getCurrentPosition(
        function (pos) {
          self.form.lat = Number(pos.coords.latitude.toFixed(6));
          self.form.lng = Number(pos.coords.longitude.toFixed(6));
          self.locating = false;
          window.absensiToast('Lokasi terisi dari GPS perangkat.', 'success');
        },
        function (err) {
          self.locating = false;
          window.absensiToast(err && err.code === 1 ? 'Izin lokasi ditolak.' : 'Gagal ambil lokasi.', 'error');
        },
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 10000 }
      );
    },
    /* Link peta (OSM) untuk verifikasi titik saat ini. */
    get mapUrl() {
      var lat = this.form.lat, lng = this.form.lng;
      if (lat === '' || lng === '' || lat == null || lng == null) return '';
      return 'https://www.openstreetmap.org/?mlat=' + lat + '&mlon=' + lng + '#map=17/' + lat + '/' + lng;
    },

    /* Jam pulang wajib lebih malam dari jam masuk. Jam terbalik bikin mesin alpha menganggap hari
       sudah selesai sebelum dimulai (semua ditandai Alpha) + gate pulang bolong. Shift lintas hari
       belum didukung BE. Dicek di sini juga supaya salah ketik ketahuan SEBELUM kirim. */
    get jamTerbalik() {
      var m = this.form.jam_masuk, k = this.form.jam_keluar;
      return !! (m && k && k <= m);
    },

    /* Simpan (PUT /settings). Partial update; token dikirim hanya bila diisi (ganti).
     * 422 → tandai field bermasalah (errors keyed absensi_*) + toast. */
    async save() {
      if (this.saving) return;
      if (this.jamTerbalik) {
        this.fieldErr = { absensi_jam_masuk: true, absensi_jam_keluar: true };
        window.absensiToast('Jam pulang harus lebih malam dari jam masuk.', 'error');
        return;
      }
      this.saving = true; this.fieldErr = {};
      try {
        var f = this.form;
        var body = {
          absensi_lat:           Number(f.lat) || 0,
          absensi_lng:           Number(f.lng) || 0,
          absensi_radius:        parseInt(f.radius, 10) || 0,
          absensi_akurasi_max:   parseInt(f.akurasi_max, 10) || 0,
          absensi_jam_masuk:     f.jam_masuk,
          absensi_jam_keluar:    f.jam_keluar,
          absensi_telat_menit:   parseInt(f.telat_menit, 10) || 0,
          absensi_rfid_debounce: parseInt(f.rfid_debounce, 10) || 0,
          absensi_retensi_hari:  parseInt(f.retensi_hari, 10) || 0,
          absensi_wa_gateway:    f.wa_gateway,
        };
        if (f.wa_token && f.wa_token.trim()) body.absensi_wa_token = f.wa_token.trim();
        var data = await window.api.put('settings', body);
        if (data && data.settings) this._apply(data.settings);   // sync nilai ter-clamp server
        this.form.wa_token = '';                                  // bersihkan field token
        window.absensiToast('Pengaturan disimpan.', 'success');
      } catch (err) {
        var e = window.absensiApiError(err);
        if (e.status === 422) {
          var errs = (err && err.data && err.data.errors) || {};
          var fe = {};
          Object.keys(errs).forEach(function (k) { fe[k] = true; });
          this.fieldErr = fe;
          window.absensiToast(e.message || 'Sebagian field tidak valid.', 'error');
        } else {
          window.absensiToast(e.message, 'error');
        }
      } finally {
        this.saving = false;
      }
    },
  }));

  /* ─── Dashboard manager (design.md §4) — pivot v2 ────────────────────────────
   * Isi: 6 KPI (populasi + kehadiran hari ini, dengan delta vs pekan lalu),
   * grafik tren kehadiran mingguan, aksi cepat, absensi terbaru.
   * Sumber (semua endpoint yang SUDAH ada):
   *   - /laporan/summary                → hadir/telat/izin/sakit/alpha hari ini
   *   - /laporan/summary?dari=&sampai=  → hari yang sama pekan lalu (delta) & tiap hari (tren)
   *   - /users, /group                  → array polos, total = .length (BE tak sedia field total)
   *   - /laporan?per_page=6             → absensi terbaru */
  Alpine.data('dashboardManager', () => ({
    stats:      { totalUser: 0, totalGroup: 0, hadir: 0, telat: 0, izin: 0, sakit: 0, alpha: 0 },
    prev:       { hadir: 0, telat: 0, izin: 0, sakit: 0, alpha: 0 },  // hari yg sama pekan lalu → delta
    recent:     [],       // absensi terbaru (GET /laporan, limit kecil)
    loading:    false,
    error:      false,

    // ── Tren kehadiran mingguan (Sen–Jum) ──
    week:         'ini',  // 'ini' | 'lalu'
    trend:        [],     // [{ label, tanggal, value }]
    trendLoading: false,
    trendError:   false,

    // 6 KPI: dua populasi (tanpa histori → sub-label statis) + empat status hari ini.
    // `naikBaik`: arah yang dianggap membaik (hadir naik = hijau; telat/izin/alpha naik = merah).
    statCards: [
      { key: 'totalUser',  label: 'Total User',  icon: 'users',          tone: 'primary', sub: 'Terdaftar' },
      { key: 'totalGroup', label: 'Total Group', icon: 'layers',         tone: 'primary', sub: 'Group aktif' },
      { key: 'hadir',      label: 'Hadir',       icon: 'check-circle-2', tone: 'success', naikBaik: true },
      { key: 'telat',      label: 'Telat',       icon: 'clock',          tone: 'warning', naikBaik: false },
      { key: 'izin',       label: 'Izin',        icon: 'file-text',      tone: 'info',    naikBaik: false },
      { key: 'alpha',      label: 'Alpha',       icon: 'x-circle',       tone: 'danger',  naikBaik: false },
    ],

    init() { this.loadStats(); this.loadTrend(); },
    /* Refresh semua data (KPI + tren + tabel). */
    refresh() { this.loadStats(); this.loadTrend(); },

    /* ── Delta vs pekan lalu (hari yang sama) ──
     * Basis 0 → tak ada persentase yang bermakna: pakai null (UI tampilkan “—”). */
    deltaPct(key) {
      var cur = this.stats[key] || 0, prev = this.prev[key] || 0;
      if (! prev) return cur ? null : 0;
      return Math.round(((cur - prev) / prev) * 1000) / 10;   // 1 desimal
    },
    deltaText(key) {
      var d = this.deltaPct(key);
      if (d === null) return '—';
      return (d > 0 ? '+' : '') + d + '%';
    },
    /* Panah ikut ARAH (naik/turun); warnanya ikut MAKNA (deltaTone). */
    deltaIcon(key) {
      var d = this.deltaPct(key);
      return d === null || d === 0 ? 'minus' : (d > 0 ? 'trending-up' : 'trending-down');
    },
    /* Warna delta ikut MAKNA, bukan tanda: telat/izin/alpha naik = buruk (merah). */
    deltaTone(key) {
      var d = this.deltaPct(key);
      if (d === null || d === 0) return 'is-flat';
      var card = this.statCards.find(function (c) { return c.key === key; }) || {};
      var membaik = card.naikBaik ? d > 0 : d < 0;
      return membaik ? 'is-good' : 'is-bad';
    },

    /* Muat KPI + tabel terbaru (paralel). */
    async loadStats() {
      this.loading = true; this.error = false;
      var lalu = this.ymd(this.geser(new Date(), -7));   // hari yg sama pekan lalu
      try {
        var r = await Promise.all([
          window.api.get('laporan/summary'),
          window.api.get('users'),
          window.api.get('group'),
          window.api.get('laporan?per_page=6&page=1'),
          window.api.get('laporan/summary?dari=' + lalu + '&sampai=' + lalu),
        ]);
        var sum = r[0] || {}, users = r[1] || [], groups = r[2] || [], recent = r[3] || {}, old = r[4] || {};
        this.stats = {
          totalUser:  Array.isArray(users)  ? users.length  : 0,
          totalGroup: Array.isArray(groups) ? groups.length : 0,
          hadir: sum.hadir || 0, telat: sum.telat || 0, izin: sum.izin || 0,
          sakit: sum.sakit || 0, alpha: sum.alpha || 0,
        };
        this.prev = {
          hadir: old.hadir || 0, telat: old.telat || 0, izin: old.izin || 0,
          sakit: old.sakit || 0, alpha: old.alpha || 0,
        };
        this.recent = (recent && recent.data) || [];
      } catch (e) {
        this.error = true;
      } finally {
        this.loading = false;
      }
    },

    /* Muat tren seminggu penuh (Sen–Min) minggu terpilih: satu /laporan/summary per hari
     * (paralel). Sabtu/Minggu ikut supaya sekolah/kampus yang masuk akhir pekan tak kehilangan
     * datanya (hari libur → 0, wajar). DUA seri: hadir & telat (dipisah, sesuai acuan desain). */
    async loadTrend() {
      this.trendLoading = true; this.trendError = false;
      var senin = this.senin(new Date());
      if (this.week === 'lalu') senin = this.geser(senin, -7);
      var hari = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
      var tgl  = hari.map((_, i) => this.ymd(this.geser(senin, i)));
      try {
        var res = await Promise.all(
          tgl.map((t) => window.api.get('laporan/summary?dari=' + t + '&sampai=' + t))
        );
        this.trend = res.map(function (s, i) {
          s = s || {};
          return { label: hari[i], tanggal: tgl[i], hadir: s.hadir || 0, telat: s.telat || 0 };
        });
      } catch (e) {
        this.trendError = true; this.trend = [];
      } finally {
        this.trendLoading = false;
      }
    },
    gantiMinggu(v) { this.week = v; this.loadTrend(); },

    // ── Geometri grafik garis (viewBox 0 0 600 200; area plot x 46→588, y 14→164) ──
    /* Skala Y: maksimum = 4 × step "enak" (1/2/5/10/25/50/100/…), supaya kelima label
     * sumbu (0 · 25% · 50% · 75% · 100%) selalu jatuh di angka BULAT — bukan 9/7/5/2. */
    get trendMax() {
      var m = 0;
      this.trend.forEach(function (d) { m = Math.max(m, d.hadir, d.telat); });
      if (m <= 0) return 4;
      var steps = [1, 2, 5, 10, 25, 50, 100, 250, 500, 1000, 2500, 5000, 10000];
      for (var i = 0; i < steps.length; i++) {
        if (steps[i] * 4 >= m) return steps[i] * 4;
      }
      return Math.ceil(m / 4) * 4;
    },
    get trendTotal() {
      var t = 0;
      this.trend.forEach(function (d) { t += d.hadir + d.telat; });
      return t;
    },
    /* Ada catatan minggu terpilih? (garis/area/titik hanya digambar bila ada.) */
    get hasTrend() { return this.trendTotal > 0; },
    get trendAria() {
      var t = this.trend.map(function (d) { return d.label + ': hadir ' + d.hadir + ', telat ' + d.telat; }).join('; ');
      return 'Tren kehadiran mingguan — ' + (t || 'belum ada data');
    },
    /* Titik seri `key` (hadir|telat) untuk tiap hari. */
    points(key) {
      var self = this, n = this.trend.length;
      if (! n) return [];
      var x0 = 46, x1 = 588, y0 = 14, y1 = 164;
      return this.trend.map(function (d, i) {
        var x = n === 1 ? (x0 + x1) / 2 : x0 + (i * (x1 - x0)) / (n - 1);
        var y = y1 - (d[key] / self.trendMax) * (y1 - y0);
        return { x: Math.round(x * 10) / 10, y: Math.round(y * 10) / 10, label: d.label, value: d[key], tanggal: d.tanggal };
      });
    },
    line(key) {
      return this.points(key).map(function (p, i) { return (i ? 'L' : 'M') + p.x + ' ' + p.y; }).join(' ');
    },
    /* Area hanya utk seri hadir (seri utama) — telat cukup garis. */
    get areaHadir() {
      var p = this.points('hadir');
      if (! p.length) return '';
      return this.line('hadir') + ' L' + p[p.length - 1].x + ' 164 L' + p[0].x + ' 164 Z';
    },
    /* Titik ke-i seri `key`, AMAN dibinding ke atribut SVG (placeholder angka bila kosong). */
    pt(key, i) {
      return this.points(key)[i] || { x: 46, y: 164, label: '', value: 0, tanggal: '' };
    },
    /* 5 label sumbu Y: 0 · 25% · 50% · 75% · 100% dari maksimum. */
    get trendTicks() {
      var m = this.trendMax, out = [];
      for (var i = 4; i >= 0; i--) out.push({ v: (m / 4) * i, y: 14 + ((4 - i) / 4) * 150 });
      return out;
    },
    fmtY(v) { return String(Math.round(v)); },

    // ── Tingkat kehadiran hari ini (kartu Aksi Cepat) ──
    /* Total tercatat hari ini = semua status (hadir+telat+izin+sakit+alpha). */
    get totalHariIni() {
      var s = this.stats;
      return (s.hadir || 0) + (s.telat || 0) + (s.izin || 0) + (s.sakit || 0) + (s.alpha || 0);
    },
    /* % hadir dari total tercatat. 0 bila belum ada catatan (bukan 100%). */
    get rateHadir() {
      var t = this.totalHariIni;
      return t ? Math.round(((this.stats.hadir || 0) / t) * 1000) / 10 : 0;
    },

    // ── Format ──
    fmtNum(n) { return Number(n || 0).toLocaleString('id-ID'); },
    /* Tanggal hari ini, ejaan Indonesia — subjudul header. */
    get tanggalHariIni() {
      try {
        return new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
      } catch (e) { return ''; }
    },

    // ── Util tanggal (lokal, tanpa dependensi) ──
    ymd(d) {
      var m = String(d.getMonth() + 1).padStart(2, '0'), t = String(d.getDate()).padStart(2, '0');
      return d.getFullYear() + '-' + m + '-' + t;
    },
    geser(d, hari) { var n = new Date(d.getTime()); n.setDate(n.getDate() + hari); return n; },
    senin(d) {
      var n = new Date(d.getTime());
      var w = n.getDay();                      // 0=Minggu
      n.setDate(n.getDate() - (w === 0 ? 6 : w - 1));
      return n;
    },

    // ── Util tampilan (Absensi Terbaru) ──
    jamHM(w) { return w ? String(w).slice(11, 16) : '—'; },
    statusBadge(s) { return ({ hadir: 'badge--hadir', telat: 'badge--telat', izin: 'badge--izin', sakit: 'badge--sakit', alpha: 'badge--alpha' })[s] || ''; },
    statusLabel(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : '—'; },
    inisial(nama) {
      var p = String(nama || '?').trim().split(/\s+/).slice(0, 2).map(function (x) { return x.charAt(0); });
      return (p.join('') || '?').toUpperCase();
    },
  }));

  Alpine.data('laporanManager', () => ({
    exportOpen: false,    // dropdown format export

    // ── Filter server ──
    // Dikirim ke /laporan & /laporan/summary: dari, sampai, preset, group_id (+ page/per_page tabel).
    filter: { dari: '', sampai: '', preset: '', group_id: '', tipe: '' },
    groups:  [],          // opsi Select Group (GET /group)
    page:    1,           // halaman tabel (pagination item Tabel)
    // Halaman pendek → daftar tak memanjang, tombol Next dipakai. Export TIDAK ikut
    // per_page (doExport pakai _filterQuery saja) → file tetap memuat semua baris
    // sesuai filter, lintas halaman.
    perPage: 15,          // per_page ke /laporan

    // ── Summary (GET /laporan/summary) ──
    summary:        { hadir: 0, telat: 0, izin: 0, sakit: 0, alpha: 0, total: 0 },
    summaryLoading: false,
    summaryError:   false,

    // ── Tabel rows (GET /laporan) + filter status client ──
    rows:           [],   // baris rekap halaman aktif (GET /laporan .data)
    total:          0,    // total baris (server)
    totalPage:      1,    // total_page (server)
    laporanLoading: false,
    laporanError:   false,
    statusFilter: '',     // '' = Semua; else saring rows client-side
    statusPills: [
      { key: '',      label: 'Semua' },
      { key: 'hadir', label: 'Hadir' },
      { key: 'telat', label: 'Telat' },
      { key: 'izin',  label: 'Izin' },
      { key: 'sakit', label: 'Sakit' },
      { key: 'alpha', label: 'Alpha' },
    ],
    /* Baris setelah saring status (client — BE tak punya param status). */
    get filteredRows() {
      if (!this.statusFilter) return this.rows;
      var s = this.statusFilter;
      return this.rows.filter(function (r) { return r.status === s; });
    },

    // 6 KPI (design.md §8): Total + 5 status. `share`: tampilkan "% dari total".
    sumCards: [
      { key: 'total', label: 'Total', icon: 'users',          tone: 'primary' },
      { key: 'hadir', label: 'Hadir', icon: 'check-circle-2', tone: 'success', share: true },
      { key: 'telat', label: 'Telat', icon: 'clock',          tone: 'warning', share: true },
      { key: 'izin',  label: 'Izin',  icon: 'file-text',      tone: 'info',    share: true },
      { key: 'sakit', label: 'Sakit', icon: 'alert-circle',   tone: 'purple',  share: true },
      { key: 'alpha', label: 'Alpha', icon: 'x-circle',       tone: 'danger',  share: true },
    ],
    /* Porsi status atas total tercatat pada rentang filter (0 bila belum ada data). */
    sharePct(key) {
      var t = this.summary.total || 0;
      return t ? Math.round(((this.summary[key] || 0) / t) * 100) : 0;
    },
    fmtNum(n) { return Number(n || 0).toLocaleString('id-ID'); },

    // ── Grafik Kehadiran Mingguan (Sen–Min, seminggu penuh) ──
    // Satu /laporan/summary per hari (paralel), ikut filter group. Mengikuti minggu
    // dari tanggal `sampai` (atau hari ini bila filter tanggal kosong). Sabtu/Minggu ikut
    // supaya sekolah/kampus yang masuk akhir pekan tak kehilangan datanya (libur → 0).
    trend:        [],     // [{ label, tanggal, hadir }]
    trendLoading: false,
    trendError:   false,

    async loadTrend() {
      this.trendLoading = true; this.trendError = false;
      var acuan = this.filter.sampai ? new Date(this.filter.sampai + 'T00:00:00') : new Date();
      if (isNaN(acuan.getTime())) acuan = new Date();
      var senin = this.senin(acuan);
      var hari  = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
      var tgl   = hari.map((_, i) => this.ymd(this.geser(senin, i)));
      var grup  = this.filter.group_id ? '&group_id=' + encodeURIComponent(this.filter.group_id) : '';
      try {
        var res = await Promise.all(
          tgl.map((t) => window.api.get('laporan/summary?dari=' + t + '&sampai=' + t + grup))
        );
        this.trend = res.map(function (s, i) {
          s = s || {};
          return { label: hari[i], tanggal: tgl[i], hadir: s.hadir || 0 };
        });
      } catch (e) {
        this.trendError = true; this.trend = [];
      } finally {
        this.trendLoading = false;
      }
    },

    // Geometri grafik (viewBox 0 0 600 200; plot x 46→588, y 14→164) — sama pola Dashboard.
    get trendMax() {
      var m = 0;
      this.trend.forEach(function (d) { m = Math.max(m, d.hadir); });
      if (m <= 0) return 4;
      var steps = [1, 2, 5, 10, 25, 50, 100, 250, 500, 1000, 2500, 5000, 10000];
      for (var i = 0; i < steps.length; i++) { if (steps[i] * 4 >= m) return steps[i] * 4; }
      return Math.ceil(m / 4) * 4;
    },
    get trendTotal() { return this.trend.reduce(function (t, d) { return t + d.hadir; }, 0); },
    get hasTrend()   { return this.trendTotal > 0; },
    get trendAria()  {
      var t = this.trend.map(function (d) { return d.label + ' ' + d.hadir; }).join(', ');
      return 'Grafik kehadiran mingguan: ' + (t || 'belum ada data');
    },
    // Geometri: kartu Laporan melebar penuh → viewBox lebar-pendek (1200×200) supaya
    // grafik tidak jadi tinggi sekali. Plot: x 50→1180, y 14→160.
    get trendPoints() {
      var self = this, n = this.trend.length;
      if (! n) return [];
      var x0 = 50, x1 = 1180, y0 = 14, y1 = 160;
      return this.trend.map(function (d, i) {
        var x = x0 + (i * (x1 - x0)) / (n - 1);
        var y = y1 - (d.hadir / self.trendMax) * (y1 - y0);
        return { x: Math.round(x * 10) / 10, y: Math.round(y * 10) / 10, label: d.label, value: d.hadir, tanggal: d.tanggal };
      });
    },
    get trendLine() {
      return this.trendPoints.map(function (p, i) { return (i ? 'L' : 'M') + p.x + ' ' + p.y; }).join(' ');
    },
    get trendArea() {
      var p = this.trendPoints;
      if (! p.length) return '';
      return this.trendLine + ' L' + p[p.length - 1].x + ' 160 L' + p[0].x + ' 160 Z';
    },
    pt(i) { return this.trendPoints[i] || { x: 50, y: 160, label: '', value: 0, tanggal: '' }; },
    get trendTicks() {
      var m = this.trendMax, out = [];
      for (var i = 4; i >= 0; i--) out.push({ v: (m / 4) * i, y: 14 + ((4 - i) / 4) * 146 });
      return out;
    },
    fmtY(v) { return String(Math.round(v)); },
    ymd(d) {
      var m = String(d.getMonth() + 1).padStart(2, '0'), t = String(d.getDate()).padStart(2, '0');
      return d.getFullYear() + '-' + m + '-' + t;
    },
    geser(d, hari) { var n = new Date(d.getTime()); n.setDate(n.getDate() + hari); return n; },
    senin(d) {
      var n = new Date(d.getTime()), w = n.getDay();
      n.setDate(n.getDate() - (w === 0 ? 6 : w - 1));
      return n;
    },

    // ── Util baris tabel ──
    /* Tanggal "07 Okt 2026" dari 'YYYY-MM-DD'. */
    tglPendek(t) {
      if (! t) return '—';
      var d = new Date(String(t) + 'T00:00:00');
      if (isNaN(d.getTime())) return t;
      try { return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }); }
      catch (e) { return t; }
    },
    /* Menit telat = jam masuk − jam masuk sekolah (dari Pengaturan). null bila tak relevan. */
    menitTelat(r) {
      if (! r || r.status !== 'telat' || ! r.waktu_masuk) return null;
      var cfg = (window.AbsensiAdmin && window.AbsensiAdmin.settings) || {};
      var jm  = String(cfg.jamMasuk || '07:00').split(':');
      var jam = Number(String(r.waktu_masuk).slice(11, 13)), menit = Number(String(r.waktu_masuk).slice(14, 16));
      var selisih = (jam * 60 + menit) - (Number(jm[0]) * 60 + Number(jm[1]));
      return selisih > 0 ? selisih : null;
    },
    jarakTeks(r) { return (r && r.jarak_meter !== null && r.jarak_meter !== undefined) ? (r.jarak_meter + 'm') : '—'; },
    inisial(nama) {
      var p = String(nama || '?').trim().split(/\s+/).slice(0, 2).map(function (s) { return s.charAt(0); });
      return (p.join('') || '?').toUpperCase();
    },
    avatarTone(nama) {
      var s = String(nama || ''), h = 0;
      for (var i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) % 997;
      return 'table__avatar--t' + ((h % 6) + 1);
    },

    // ── Modal Detail baris (dari data yang SUDAH ada di baris; tak ada endpoint detail) ──
    detailOpen: false,
    detailRow:  null,
    openDetail(r)  { this.detailRow = r; this.detailOpen = true; },
    closeDetail()  { this.detailOpen = false; },
    metodeLabel(m) { return ({ selfie: 'Selfie', rfid: 'RFID', manual: 'Manual' })[m] || '—'; },

    /* ── Ubah status (koreksi admin) → POST /rekap/status ────────────────────
     * Baris ALPHA itu VIRTUAL (id = null, virtual = true): tak ada barisnya di DB. Maka kirimannya
     * SELALU kunci alami (user_id + tanggal), bukan id — BE yang memutuskan INSERT (alpha) atau
     * UPDATE (baris sudah ada). FE tak perlu bercabang.
     * "Batalkan penyesuaian" (DELETE) hanya untuk baris buatan admin (mode manual) → baris kembali
     * dihitung otomatis. Rekap selfie/RFID tak boleh dihapus (BE balas 409). */
    statusOpen:  false,
    statusRow:   null,   // baris yang sedang dikoreksi
    statusPilih: '',     // status baru
    statusNote:  '',     // catatan
    statusBusy:  false,
    statusError: '',
    statusOpsi: [
      { value: 'hadir', label: 'Hadir' },
      { value: 'telat', label: 'Telat' },
      { value: 'izin',  label: 'Izin'  },
      { value: 'sakit', label: 'Sakit' },
      { value: 'alpha', label: 'Alpha' },
    ],

    openStatus(r) {
      this.statusRow   = r;
      this.statusPilih = r.status || '';
      this.statusNote  = r.catatan || '';
      this.statusError = '';
      this.statusOpen  = true;
      this.detailOpen  = false;   // dipanggil juga dari modal Detail
    },
    closeStatus() { this.statusOpen = false; },

    /* Baris hasil koreksi admin (bukan bukti selfie/RFID) → boleh dibatalkan. */
    get statusBisaBatal() {
      return !! (this.statusRow && this.statusRow.id && this.statusRow.mode === 'manual');
    },

    async simpanStatus() {
      if (! this.statusRow || ! this.statusPilih || this.statusBusy) return;
      this.statusBusy = true; this.statusError = '';
      try {
        await window.api.post('rekap/status', {
          user_id: this.statusRow.user_id,
          tanggal: this.statusRow.tanggal,
          status:  this.statusPilih,
          catatan: this.statusNote,
        });
        window.absensiToast('Status diperbarui.', 'success');
        this.statusOpen = false;
        this.loadSummary(); this.loadLaporan(); this.loadTrend();
      } catch (err) {
        this.statusError = window.absensiApiError(err).message;
      } finally {
        this.statusBusy = false;
      }
    },

    async batalkanStatus() {
      if (! this.statusBisaBatal || this.statusBusy) return;
      this.statusBusy = true; this.statusError = '';
      try {
        await window.api.delete('rekap/' + this.statusRow.id);
        window.absensiToast('Penyesuaian dibatalkan.', 'success');
        this.statusOpen = false;
        this.loadSummary(); this.loadLaporan(); this.loadTrend();
      } catch (err) {
        this.statusError = window.absensiApiError(err).message;   // 409 bila bukan manual
      } finally {
        this.statusBusy = false;
      }
    },

    // Format export tersedia (design.md §8): CSV/XLSX/PDF.
    exportFormats: [
      { key: 'csv',  label: 'CSV' },
      { key: 'xlsx', label: 'Excel (XLSX)' },
      { key: 'pdf',  label: 'PDF' },
    ],

    init() { this.loadGroups(); this.loadSummary(); this.loadLaporan(); this.loadTrend(); },

    /* Opsi group untuk Select (GET /group). Gagal → kosong (filter lain tetap jalan). */
    async loadGroups() {
      try { this.groups = await window.api.get('group') || []; }
      catch (e) { this.groups = []; }
    },

    /* Opsi filter Tipe — dari daftar GROUP (tipe = string bebas, tanpa preset). Belum ada group → kosong. */
    get tipeOptions() {
      var dipakai = this.groups.map(function (g) { return String(g.tipe || '').trim(); }).filter(Boolean);
      return Array.from(new Set(dipakai))
        .sort(function (a, b) { return a.localeCompare(b, 'id'); })
        .map(function (t) { return { value: t, label: t.charAt(0).toUpperCase() + t.slice(1) }; });
    },
    /* Opsi dropdown Grup — ikut Tipe yang dipilih (nama grup boleh kembar antar tipe, mis. dua "5B"). */
    get groupsForFilter() {
      var t = this.filter.tipe;
      if (! t) return this.groups;
      return this.groups.filter(function (g) { return String(g.tipe || '').trim() === t; });
    },
    /* Ganti Tipe → buang pilihan Grup yang tak lagi cocok. */
    onTipeChange() {
      if (! this.filter.group_id) return;
      var masih = this.groupsForFilter.some((g) => String(g.id) === String(this.filter.group_id));
      if (! masih) this.filter.group_id = '';
    },

    /* Preset dipilih → kosongkan dari/sampai agar preset efektif (BE: dari+sampai > preset). */
    onPresetChange() { if (this.filter.preset) { this.filter.dari = ''; this.filter.sampai = ''; } },
    /* Tanggal manual diketik → kosongkan preset. */
    onDateChange() { if (this.filter.dari || this.filter.sampai) this.filter.preset = ''; },

    /* Terapkan filter: kembali ke halaman 1 lalu refetch summary + tabel + grafik. */
    applyFilter() { this.page = 1; this.loadSummary(); this.loadLaporan(); this.loadTrend(); },
    /* Reset semua filter ke default (rentang server = hari ini). */
    resetFilter() {
      this.filter = { dari: '', sampai: '', preset: '', group_id: '', tipe: '' };
      this.applyFilter();
    },

    /* Rakit query string dari filter aktif (abaikan yang kosong). Dipakai tabel, summary, DAN export
       → filter Tipe otomatis ikut ke file export. */
    _filterQuery() {
      var p = [];
      if (this.filter.dari)     p.push('dari=' + encodeURIComponent(this.filter.dari));
      if (this.filter.sampai)   p.push('sampai=' + encodeURIComponent(this.filter.sampai));
      if (this.filter.preset)   p.push('preset=' + encodeURIComponent(this.filter.preset));
      if (this.filter.tipe)     p.push('tipe=' + encodeURIComponent(this.filter.tipe));
      if (this.filter.group_id) p.push('group_id=' + encodeURIComponent(this.filter.group_id));
      return p.join('&');
    },

    /* Muat ringkasan (GET /laporan/summary + filter). */
    async loadSummary() {
      this.summaryLoading = true; this.summaryError = false;
      try {
        var q = this._filterQuery();
        this.summary = await window.api.get('laporan/summary' + (q ? '?' + q : ''));
      } catch (e) {
        this.summaryError = true;
      } finally {
        this.summaryLoading = false;
      }
    },

    /* Ambil baris tabel rekap (GET /laporan + filter + page/per_page). Pagination server. */
    async loadLaporan() {
      this.laporanLoading = true; this.laporanError = false;
      try {
        var q  = this._filterQuery();
        var qs = 'per_page=' + this.perPage + '&page=' + this.page + (q ? '&' + q : '');
        var data = await window.api.get('laporan?' + qs);   // { data, total, page, per_page, total_page }
        this.rows      = (data && data.data) || [];
        this.total     = (data && data.total) || 0;
        this.totalPage = (data && data.total_page) || 1;
        this.page      = (data && data.page) || this.page;
      } catch (e) {
        this.laporanError = true; this.rows = [];
      } finally {
        this.laporanLoading = false;
      }
    },
    /* Pindah halaman (server-side) → refetch. */
    goPage(p) { if (typeof p === 'number' && p >= 1 && p <= this.totalPage && p !== this.page) { this.page = p; this.loadLaporan(); } },
    /* Deret tombol halaman dgn elipsis '…'. */
    get pageWindow() {
      var tp = this.totalPage, cur = Math.min(this.page, tp), out = [];
      if (tp <= 7) { for (var i = 1; i <= tp; i++) out.push(i); return out; }
      out.push(1);
      var lo = Math.max(2, cur - 1), hi = Math.min(tp - 1, cur + 1);
      if (lo > 2) out.push('…');
      for (var j = lo; j <= hi; j++) out.push(j);
      if (hi < tp - 1) out.push('…');
      out.push(tp);
      return out;
    },
    get pageStart() { return this.total ? ((Math.min(this.page, this.totalPage) - 1) * this.perPage) + 1 : 0; },
    get pageEnd()   { return Math.min(Math.min(this.page, this.totalPage) * this.perPage, this.total); },

    // ── Util tampilan tabel ──
    jamHM(w) { return w ? String(w).slice(11, 16) : '—'; },
    statusBadge(s) { return ({ hadir: 'badge--hadir', telat: 'badge--telat', izin: 'badge--izin', sakit: 'badge--sakit', alpha: 'badge--alpha' })[s] || ''; },
    statusLabel(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : '—'; },

    /* Unduh export (GET /laporan/export?format=…+filter). Endpoint stream file (butuh
     * X-WP-Nonce) → pakai fetch blob (bukan anchor polos) supaya bisa tangani 503/422.
     * 503 export_unavailable (xlsx/pdf tanpa vendor) → toast saran CSV. */
    async doExport(format) {
      this.exportOpen = false;
      var cfg = window.AbsensiAdmin || {};
      var q   = this._filterQuery();
      var url = (cfg.restUrl || '/wp-json/absensi/v1/') + 'laporan/export?format=' +
                encodeURIComponent(format) + (q ? '&' + q : '');
      try {
        var res = await fetch(url, { headers: { 'X-WP-Nonce': cfg.nonce || '' } });
        if (!res.ok) {
          var err = await res.json().catch(function () { return null; });
          var msg = (err && err.message) || ('Export gagal (HTTP ' + res.status + ').');
          if (res.status === 503) { window.absensiToast(msg + ' Gunakan format CSV.', 'warning'); return; }
          window.absensiToast(msg, 'error');
          return;
        }
        var blob = await res.blob();
        var fname = 'laporan.' + format;
        var m = (res.headers.get('Content-Disposition') || '').match(/filename="?([^";]+)"?/);
        if (m) fname = m[1];
        var objUrl = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = objUrl; a.download = fname;
        document.body.appendChild(a); a.click(); a.remove();
        URL.revokeObjectURL(objUrl);
        window.absensiToast('File diunduh: ' + fname, 'success');
      } catch (e) {
        window.absensiToast('Export gagal. Periksa koneksi lalu coba lagi.', 'error');
      }
    },
  }));

  Alpine.data('groupManager', () => ({
    groups:  [],          // baris GET /group (g.* + jumlah_user)
    loading: false,
    error:   false,

    // ── Filter & paging (semua client-side; BE /group tak punya search/paging) ──
    search:       '',
    tipeFilter:   '',     // '' = semua tipe
    tipeMenuOpen: false,  // dropdown filter tipe (menggantikan pill-tabs sebaris)
    page:         1,
    perPage:      8,

    // ── Modal Form (tambah/edit) ──
    modalOpen: false,
    editing:   null,      // id group saat edit; null = tambah
    saving:    false,
    form:      { nama: '', tipe: '' },   // tipe WAJIB, tanpa default — admin ketik sendiri
    formError: '',
    fieldErr:  {},

    // ── Konfirmasi Hapus ──
    delOpen:  false,
    delGroup: null,       // { id, nama, jumlah_user }
    deleting: false,
    delError: '',

    // ── Modal Anggota Grup (klik baris / angka Members) ──
    // Dulu expand inline; diganti modal karena satu kelas bisa puluhan murid.
    anggotaOpen:    false,
    anggotaGroup:   null,  // { id, nama, tipe, jumlah_user }
    anggotaSearch:  '',
    anggotaPage:    1,
    anggotaPerPage: 10,
    groupUsers:   {},     // { [groupId]: [ {id,nama,nomor_induk,rfid_uid} ] } — cache
    usersLoading: {},     // { [groupId]: bool }
    usersError:   {},     // { [groupId]: bool }

    init() { this.loadGroups(); },

    /* Ambil daftar group (GET /group). Tiap baris bawa jumlah_user. */
    async loadGroups() {
      this.loading = true; this.error = false;
      this.groupUsers = {};   // buang cache expand (jumlah/anggota bisa berubah setelah edit)
      try { this.groups = await window.api.get('group') || []; }
      catch (e) { this.error = true; this.groups = []; }
      finally { this.loading = false; }
    },

    // Peta tipe → badge/label (design.md §6: Kelas primary, Guru purple, Staff hijau).
    // Tipe kustom (v2.1.0, di luar 3 bawaan) → badge netral, bukan warna "kelas".
    tipeBadge(t) { return ({ kelas: 'badge--kelas', guru: 'badge--guru', staff: 'badge--staff' })[t] || 'badge--neutral'; },
    tipeLabel(t) { return ({ kelas: 'Kelas', guru: 'Guru', staff: 'Staff' })[t] || (t || 'Lainnya'); },

    // ── Ringkasan (kartu kiri) — semua dihitung dari daftar group, bukan endpoint baru ──
    get totalGroup()  { return this.groups.length; },
    get totalMember() { return this.groups.reduce(function (t, g) { return t + (g.jumlah_user || 0); }, 0); },
    /* Distribusi group per tipe — MURNI dari data (tanpa tipe bawaan). Urut: terbanyak dulu,
       lalu abjad. Belum ada group → array kosong (chart & tab tipe ikut kosong). */
    get distribusi() {
      var self = this, hitung = {};
      this.groups.forEach(function (g) {
        var t = g.tipe || 'lainnya';
        hitung[t] = (hitung[t] || 0) + 1;
      });
      var tipe = Object.keys(hitung).sort(function (a, b) {
        return (hitung[b] - hitung[a]) || a.localeCompare(b);
      });
      var maks = Math.max.apply(null, [1].concat(tipe.map(function (t) { return hitung[t]; })));
      return tipe.map(function (t) {
        return { tipe: t, label: self.tipeLabel(t), jumlah: hitung[t], pct: Math.round((hitung[t] / maks) * 100) };
      });
    },
    /* Opsi filter tipe: "Semua" + tipe yang benar-benar ada datanya (+ jumlah group per tipe).
       Dulu dirender sebagai pill sebaris; kini dropdown — tipe = string bebas, jumlahnya bisa
       banyak dan barisnya meluber. Isi datanya sama, cuma cara tampilnya beda. */
    get tipeTabs() {
      return [{ tipe: '', label: 'Semua Tipe', jumlah: this.groups.length }].concat(
        this.distribusi.map(function (d) { return { tipe: d.tipe, label: d.label, jumlah: d.jumlah }; })
      );
    },
    /* Opsi yang sedang aktif → dipakai sebagai label tombol dropdown. */
    get tipeAktif() {
      var t = this.tipeFilter;
      var found = this.tipeTabs.filter(function (o) { return o.tipe === t; })[0];
      return found || { tipe: '', label: 'Semua Tipe', jumlah: this.groups.length };
    },

    // ── Filter + paging (client) ──
    get filteredGroups() {
      var q = this.search.trim().toLowerCase(), t = this.tipeFilter;
      return this.groups.filter(function (g) {
        if (t && (g.tipe || 'lainnya') !== t) return false;
        return ! q || String(g.nama || '').toLowerCase().indexOf(q) !== -1;
      });
    },
    get totalFiltered() { return this.filteredGroups.length; },
    get totalPages()    { return Math.max(1, Math.ceil(this.totalFiltered / this.perPage)); },
    get pagedGroups()   {
      var p = Math.min(this.page, this.totalPages);
      return this.filteredGroups.slice((p - 1) * this.perPage, (p - 1) * this.perPage + this.perPage);
    },
    get pageStart() { return this.totalFiltered ? ((Math.min(this.page, this.totalPages) - 1) * this.perPage) + 1 : 0; },
    get pageEnd()   { return Math.min(Math.min(this.page, this.totalPages) * this.perPage, this.totalFiltered); },
    get pageWindow() {
      var tp = this.totalPages, cur = Math.min(this.page, tp), out = [];
      if (tp <= 7) { for (var i = 1; i <= tp; i++) out.push(i); return out; }
      out.push(1);
      var lo = Math.max(2, cur - 1), hi = Math.min(tp - 1, cur + 1);
      if (lo > 2) out.push('…');
      for (var j = lo; j <= hi; j++) out.push(j);
      if (hi < tp - 1) out.push('…');
      out.push(tp);
      return out;
    },
    goPage(p) { if (typeof p === 'number' && p >= 1 && p <= this.totalPages) this.page = p; },
    pilihTipe(t) { this.tipeFilter = t; this.page = 1; },

    /* Saran <datalist> tipe — MURNI dari tipe yang sudah dipakai di data (tanpa bawaan).
       Belum ada group → kosong, admin wajib mengetik tipe baru. */
    get tipeSuggestions() {
      var dipakai = this.groups.map(function (g) { return g.tipe; }).filter(Boolean);
      return Array.from(new Set(dipakai)).sort(function (a, b) { return a.localeCompare(b); });
    },

    /* Toggle expand baris group → tampil daftar user di bawahnya. Klik ulang = tutup.
       Fetch user (GET /users?group_id) sekali lalu di-cache. */
    /* Buka modal Anggota Grup. Menggantikan expand inline: kelas bisa berisi puluhan
     * murid → daftar panjang akan mendorong tabel ke bawah. Di modal ada cari +
     * pagination sendiri, tabel grup tetap ringkas. Data tetap GET /users?group_id. */
    openAnggota(g) {
      this.anggotaGroup  = g;
      this.anggotaSearch = '';
      this.anggotaPage   = 1;
      this.anggotaOpen   = true;
      if (this.groupUsers[g.id] === undefined) this.loadGroupUsers(g.id);
    },
    closeAnggota() { this.anggotaOpen = false; },

    // ── Turunan daftar anggota di modal (cari + paging, client) ──
    get anggotaId()      { return this.anggotaGroup ? this.anggotaGroup.id : 0; },
    get anggotaLoading() { return !! this.usersLoading[this.anggotaId]; },
    get anggotaError()   { return !! this.usersError[this.anggotaId]; },
    get anggotaSemua()   { return this.groupUsers[this.anggotaId] || []; },
    get anggotaFiltered() {
      var q = this.anggotaSearch.trim().toLowerCase();
      if (! q) return this.anggotaSemua;
      return this.anggotaSemua.filter(function (u) {
        return String(u.nama || '').toLowerCase().indexOf(q) !== -1
            || String(u.nomor_induk || '').toLowerCase().indexOf(q) !== -1;
      });
    },
    get anggotaTotal()     { return this.anggotaFiltered.length; },
    get anggotaTotalPages(){ return Math.max(1, Math.ceil(this.anggotaTotal / this.anggotaPerPage)); },
    get anggotaPaged() {
      var p = Math.min(this.anggotaPage, this.anggotaTotalPages);
      return this.anggotaFiltered.slice((p - 1) * this.anggotaPerPage, (p - 1) * this.anggotaPerPage + this.anggotaPerPage);
    },
    get anggotaStart() { return this.anggotaTotal ? ((Math.min(this.anggotaPage, this.anggotaTotalPages) - 1) * this.anggotaPerPage) + 1 : 0; },
    get anggotaEnd()   { return Math.min(Math.min(this.anggotaPage, this.anggotaTotalPages) * this.anggotaPerPage, this.anggotaTotal); },
    goAnggotaPage(p) { if (p >= 1 && p <= this.anggotaTotalPages) this.anggotaPage = p; },
    /* Inisial, warna avatar, mask UID — samakan tampilannya dengan halaman Users. */
    inisial(nama) {
      var p = String(nama || '?').trim().split(/\s+/).slice(0, 2).map(function (s) { return s.charAt(0); });
      return (p.join('') || '?').toUpperCase();
    },
    avatarTone(nama) {
      var s = String(nama || ''), h = 0;
      for (var i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) % 997;
      return 'table__avatar--t' + ((h % 6) + 1);
    },
    maskRfid(uid) {
      if (! uid) return '';
      var s = String(uid);
      return s.length <= 4 ? s : '••••' + s.slice(-4);
    },
    async loadGroupUsers(id) {
      this.usersLoading = Object.assign({}, this.usersLoading, { [id]: true });
      this.usersError   = Object.assign({}, this.usersError, { [id]: false });
      try {
        var rows = await window.api.get('users?group_id=' + id);
        this.groupUsers = Object.assign({}, this.groupUsers, { [id]: rows || [] });
      } catch (e) {
        this.usersError = Object.assign({}, this.usersError, { [id]: true });
        this.groupUsers = Object.assign({}, this.groupUsers, { [id]: [] });
      } finally {
        this.usersLoading = Object.assign({}, this.usersLoading, { [id]: false });
      }
    },

    // ── Modal Form: buka/tutup/simpan ──
    /* Tipe = input teks BEBAS + saran <datalist> dari data (tanpa preset). WAJIB diisi:
     * BE menolak tipe kosong (422 tipe_wajib) — tak ada lagi default 'kelas'. */
    _resetForm() {
      this.form = { nama: '', tipe: '' };
      this.formError = ''; this.fieldErr = {};
    },
    openCreate() {
      this._resetForm(); this.editing = null; this.modalOpen = true;
      this._focusById('gf-nama');
    },
    openEdit(g) {
      this._resetForm();
      this.editing = g.id;
      this.form = { nama: g.nama || '', tipe: g.tipe || '' };
      this.modalOpen = true;
      this._focusById('gf-nama');
    },
    closeModal() { this.modalOpen = false; },
    get canSave() {
      return !this.saving && this.form.nama.trim() !== '' && this.form.tipe.trim() !== '';
    },

    /* Simpan (POST tambah / PUT edit). Tangani 422 nama_wajib / tipe_wajib. */
    async save() {
      if (!this.canSave) return;
      this.saving = true; this.formError = ''; this.fieldErr = {};
      try {
        var body = { nama: this.form.nama.trim(), tipe: this.form.tipe.trim() };
        if (this.editing) await window.api.put('group/' + this.editing, body);
        else              await window.api.post('group', body);
        window.absensiToast(this.editing ? 'Group diperbarui.' : 'Group ditambahkan.', 'success');
        this.modalOpen = false;
        this.loadGroups();
      } catch (err) {
        var e = window.absensiApiError(err);
        if (e.status === 422) {
          if (e.code === 'tipe_wajib')      this.fieldErr = { tipe: true };
          else if (e.code === 'nama_wajib') this.fieldErr = { nama: true };
        }
        this.formError = e.message;
      } finally {
        this.saving = false;
      }
    },

    _focusById(id) {
      this.$nextTick(function () { var el = document.getElementById(id); if (el) el.focus(); });
    },
    /* Focus-trap modal (a11y, design.md §6): Tab berputar dalam modal. */
    trapFocus(e) {
      var root = e.currentTarget;
      var els = root.querySelectorAll(
        'a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])'
      );
      if (!els.length) return;
      var first = els[0], last = els[els.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    },

    // ── Konfirmasi Hapus: buka/tutup/jalankan ──
    confirmDelete(g) {
      this.delGroup = { id: g.id, nama: g.nama, jumlah_user: g.jumlah_user || 0 };
      this.delError = '';
      this.delOpen = true;
    },
    closeDelete() { this.delOpen = false; },
    /* Proaktif (design.md §6): group masih punya user → cegah hapus sebelum kirim. */
    get delHasUsers() { return !!(this.delGroup && this.delGroup.jumlah_user > 0); },

    async runDelete() {
      if (!this.delGroup || this.deleting || this.delHasUsers) return;
      this.deleting = true; this.delError = '';
      try {
        await window.api.delete('group/' + this.delGroup.id);
        window.absensiToast('Group dihapus.', 'success');
        this.delOpen = false;
        this.loadGroups();
      } catch (err) {
        this.delError = window.absensiApiError(err).message;   // 409 group_ada_user (pesan bawa N)
      } finally {
        this.deleting = false;
      }
    },
  }));

}); // end alpine:init

/* ─── Ikon Lucide (design.md §2.3) — helper render ikon ──────────────────────
 * Inline SVG, stroke 1.75, currentColor (ikut warna teks konteks).
 * Pakai:
 *   Statis/markup:  <span x-html="$icon('users')"></span>
 *   Ukuran:         $icon('search', 16)   // 20 default · 16 inline/tabel · 18 adornment
 *   Non-Alpine:     window.absensiIcon('trash-2', 16)
 * Nama = nama Lucide (design.md §2.3). Set admin = menu + aksi lengkap. */
(function () {
  'use strict';
  var P = {
    // Menu (dipakai sbg ikon quick-action / header section; sidebar wp-admin pakai dashicon BE)
    'layout-dashboard': '<rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>',
    'users':            '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    'layers':           '<path d="M12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83z"/><path d="M2 12a1 1 0 0 0 .58.91l8.6 3.91a2 2 0 0 0 1.65 0l8.58-3.9A1 1 0 0 0 22 12"/><path d="M2 17a1 1 0 0 0 .58.91l8.6 3.91a2 2 0 0 0 1.65 0l8.58-3.9A1 1 0 0 0 22 17"/>',
    'bar-chart-3':      '<path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/>',
    'settings':         '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>',
    // Aksi & utility
    'clipboard-check':  '<rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="m9 14 2 2 4-4"/>',
    'id-card':          '<path d="M16 10h2"/><path d="M16 14h2"/><path d="M6.17 15a3 3 0 0 1 5.66 0"/><circle cx="9" cy="11" r="2"/><rect width="20" height="14" x="2" y="5" rx="2"/>',
    'plus':             '<path d="M5 12h14"/><path d="M12 5v14"/>',
    'square-pen':       '<path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.874a2 2 0 0 1 .506-.852z"/>',
    'trash-2':          '<path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/>',
    'credit-card':      '<rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/>',
    'upload':           '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/>',
    'user-plus':        '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/>',
    'file-spreadsheet': '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M8 13h2"/><path d="M14 13h2"/><path d="M8 17h2"/><path d="M14 17h2"/>',
    'download':         '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/>',
    'chevron-down':     '<path d="m6 9 6 6 6-6"/>',
    'refresh-cw':       '<path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/>',
    'search':           '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
    'filter':           '<polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>',
    'camera':           '<path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/>',
    'map-pin':          '<path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/>',
    'rotate-ccw':       '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>',
    'send':             '<path d="M14.536 21.686a.5.5 0 0 0 .937-.024l6.5-19a.496.496 0 0 0-.635-.635l-19 6.5a.5.5 0 0 0-.024.937l7.93 3.18a2 2 0 0 1 1.112 1.11z"/><path d="m21.854 2.147-10.94 10.939"/>',
    'check':            '<path d="M20 6 9 17l-5-5"/>',
    'check-circle-2':   '<path d="M21.801 10A10 10 0 1 1 17 3.335"/><path d="m9 11 3 3L22 4"/>',
    'x-circle':         '<circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/>',
    'alert-triangle':   '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
    'alert-circle':     '<circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/>',
    'info':             '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
    'clock':            '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
    'calendar':         '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>',
    'chevron-left':     '<path d="m15 18-6-6 6-6"/>',
    'chevron-right':    '<path d="m9 18 6-6-6-6"/>',
    'more-horizontal':  '<circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/>',
    'x':                '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
    'help':             '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/>',
    // Tren & delta (Dashboard)
    'arrow-up':         '<path d="m5 12 7-7 7 7"/><path d="M12 19V5"/>',
    'arrow-down':       '<path d="M12 5v14"/><path d="m19 12-7 7-7-7"/>',
    'save':             '<path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"/><path d="M7 3v4a1 1 0 0 0 1 1h7"/>',
    'log-in':           '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" x2="3" y1="12" y2="12"/>',
    'log-out':          '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/>',
    'trending-up':      '<path d="M16 7h6v6"/><path d="m22 7-8.5 8.5-5-5L2 17"/>',
    'trending-down':    '<path d="M16 17h6v-6"/><path d="m22 17-8.5-8.5-5 5L2 7"/>',
    'minus':            '<path d="M5 12h14"/>',
    'scan-line':        '<path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/><path d="M7 12h10"/>',
    'file-text':        '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>'
  };
  function icon(name, size) {
    var inner = P[name];
    if (!inner) { if (window.console) console.warn('[absensi] ikon tak dikenal:', name); inner = P.help; }
    var s = size || 20;
    return '<svg class="icon icon--' + name + '" width="' + s + '" height="' + s +
      '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" ' +
      'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' + inner + '</svg>';
  }
  window.absensiIcon  = icon;
  window.ABSENSI_ICONS = P;
  document.addEventListener('alpine:init', function () {
    if (window.Alpine && Alpine.magic) Alpine.magic('icon', function () { return icon; });
  });
})();

/* ─── Helper fetch: error parser + toast (design.md §2.9 Toast) ──────────────
 * Base REST + inject X-WP-Nonce sudah di window.api (di atas). Ini melengkapi:
 *   absensiApiError(err) → { code, status, message }  (dari {code,message,data.status})
 *   absensiToast(msg, type, timeout)                  (render komponen .toast)
 *   absensiToastError(err)                            (parse + toast merah)
 * type: success | error | warning | info. Scope admin = .absensi-app. */
(function () {
  'use strict';
  var SCOPE = '.absensi-app';

  var DEFAULT_MSG = {
    0:   'Tak dapat terhubung ke server. Periksa koneksi.',
    403: 'Sesi habis. Muat ulang halaman lalu coba lagi.',
    404: 'Data tidak ditemukan.',
    409: 'Data bentrok dengan yang sudah ada.',
    422: 'Input tidak valid.',
    429: 'Terlalu cepat. Tunggu sebentar lalu coba lagi.',
    503: 'Layanan sedang tidak tersedia.'
  };

  function apiError(err) {
    var status  = (err && err.status) || 0;
    var data    = (err && err.data) || {};
    var code    = data.code || '';
    var message = data.message || (err && err.message) || DEFAULT_MSG[status] || ('Terjadi kesalahan (HTTP ' + status + ').');
    return { code: code, status: status, message: message };
  }

  var TYPE_ICON = { success: 'check-circle-2', error: 'x-circle', warning: 'alert-triangle', info: 'info' };

  function stackEl() {
    var root = document.querySelector(SCOPE);
    if (!root) return null;                       // markup plugin belum ada di DOM
    var stack = root.querySelector('.toast-stack');
    if (!stack) {
      stack = document.createElement('div');
      stack.className = 'toast-stack';
      root.appendChild(stack);
    }
    return stack;
  }

  function toast(message, type, timeout) {
    type    = type || 'info';
    timeout = timeout == null ? 4000 : timeout;
    var stack = stackEl();
    if (!stack) { if (window.console) console.warn('[absensi] toast tanpa wrapper ' + SCOPE + ':', message); return; }

    var el = document.createElement('div');
    el.className = 'toast toast--' + type;
    var iconName = TYPE_ICON[type] || 'info';
    var ico = window.absensiIcon ? window.absensiIcon(iconName, 18) : '';
    var body = document.createElement('span');
    body.className = 'toast__body';
    body.textContent = message;                    // textContent = aman dari XSS
    el.innerHTML = '<span class="toast__icon">' + ico + '</span>';
    el.appendChild(body);
    var close = document.createElement('button');
    close.className = 'toast__close'; close.type = 'button'; close.setAttribute('aria-label', 'Tutup');
    close.innerHTML = window.absensiIcon ? window.absensiIcon('x', 16) : '×';
    var timer;
    function dismiss() { clearTimeout(timer); if (el.parentNode) el.parentNode.removeChild(el); }
    close.addEventListener('click', dismiss);
    el.appendChild(close);
    stack.appendChild(el);
    if (timeout > 0) timer = setTimeout(dismiss, timeout);
    return dismiss;
  }

  function toastError(err) { var e = apiError(err); toast(e.message, 'error'); return e; }

  window.absensiApiError   = apiError;
  window.absensiToast      = toast;
  window.absensiToastError = toastError;
})();
