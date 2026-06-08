import Alpine from 'alpinejs';
import api from './apiClient.js';
import L from 'leaflet';
import markerIconUrl   from 'leaflet/dist/images/marker-icon.png';
import markerIcon2xUrl from 'leaflet/dist/images/marker-icon-2x.png';
import markerShadowUrl from 'leaflet/dist/images/marker-shadow.png';

// Fix Leaflet default icon path saat bundled dengan Vite
delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
  iconUrl:       markerIconUrl,
  iconRetinaUrl: markerIcon2xUrl,
  shadowUrl:     markerShadowUrl,
});

const FILTER_KEY = 'absensi_admin_filter';

// ─── Satu-satunya sumber kebenaran untuk format jam ───────────────────────────
// Dipakai oleh rekapTable (laporan) DAN dashboardTable (dashboard).
// Ubah di sini → otomatis berlaku di kedua halaman.
function fmtTime(dt) {
  if (!dt) return null;
  const d = new Date(dt.replace(' ', 'T') + 'Z'); // interpretasi sebagai UTC
  if (isNaN(d)) return dt.slice(11, 16);           // fallback: slice langsung
  return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', hour12: false });
}

Alpine.data('filterBar', () => ({
  filter: { dateFrom: '', dateTo: '', kelas: '' },

  init() {
    this.loadFilter();
    const today = new Date().toISOString().slice(0, 10);
    if (!this.filter.dateFrom) this.filter.dateFrom = today;
    if (!this.filter.dateTo)   this.filter.dateTo   = today;
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

  // Summary dihitung dari rows — backend /laporan/summary tidak support filter tanggal
  get summary() {
    return {
      hadir:      this.rows.filter(r => r.status === 'hadir').length,
      telat:      this.rows.filter(r => r.status === 'telat').length,
      izin_sakit: this.rows.filter(r => r.status === 'izin' || r.status === 'sakit').length,
      alpha:      this.rows.filter(r => r.status === 'alpha').length,
    };
  },

  init() {
    // Ambil filter tersimpan atau default hari ini
    try {
      const saved = JSON.parse(localStorage.getItem(FILTER_KEY) ?? '{}');
      const today = new Date().toISOString().slice(0, 10);
      this.filter = {
        dateFrom: saved.dateFrom ?? today,
        dateTo:   saved.dateTo   ?? today,
        kelas:    saved.kelas    ?? '',
      };
    } catch {
      const today = new Date().toISOString().slice(0, 10);
      this.filter = { dateFrom: today, dateTo: today, kelas: '' };
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
    try {
      const params = new URLSearchParams();
      // Backend LaporanEndpoint params: dari, sampai, kelas_id
      if (this.filter.dateFrom) params.set('dari',     this.filter.dateFrom);
      if (this.filter.dateTo)   params.set('sampai',   this.filter.dateTo);
      if (this.filter.kelas)    params.set('kelas_id', this.filter.kelas);

      const data = await api.get('laporan?' + params);
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

  // Gunakan fungsi module-level — satu sumber kebenaran dengan dashboardTable
  fmtTime,

  exporting: false,

  // Ekspor CSV client-side — tidak butuh endpoint backend
  async exportCSV() {
    if (this.exporting) return;
    this.exporting = true;
    try {
      const p = new URLSearchParams();
      if (this.filter.dateFrom) p.set('dari',     this.filter.dateFrom);
      if (this.filter.dateTo)   p.set('sampai',   this.filter.dateTo);
      if (this.filter.kelas)    p.set('kelas_id', this.filter.kelas);
      p.set('per_page', '9999');

      const data = await api.get('laporan?' + p.toString());
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
          r.waktu_masuk   ? String(r.waktu_masuk).slice(0,5) : '',
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

  // Export server-side (xlsx|pdf) — cek ketersediaan dulu sebelum download
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
          alert('Endpoint ekspor belum tersedia di server.\n\nUntuk sementara gunakan ekspor CSV.\nKonfirmasi ke Backend developer bahwa /laporan/export sudah di-deploy.');
        } else {
          alert('Gagal ekspor: ' + (err?.message ?? `HTTP ${res.status}`));
        }
        return;
      }
      // Response OK → trigger download via blob
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
    window.print();
  },
}));

// Halaman Absen RFID admin — mode toggle Absen/Enroll
Alpine.data('adminRfid', () => ({
  mode: 'absen',

  // Absen
  absenLog:    [],
  absenStatus: { type: 'idle', msg: 'Menunggu scan kartu…' },

  // Enroll
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

  refocus()        { this._refocusPaused = false; this.$refs.scanner?.focus(); },
  pauseRefocus()   { this._refocusPaused = true; },
  resumeRefocus()  { this._refocusPaused = false; this.$refs.scanner?.focus(); },

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
      const data = await api.post('absen/rfid', { rfid_uid: uid });
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
      const data = await api.get(`siswa?search=${encodeURIComponent(this.enrollSearch)}`);
      this.enrollResults = data.data ?? data ?? [];
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
      await api.post('absen/rfid/enroll', { siswa_id: this.enrollTarget.id, rfid_uid: this.enrollUid.trim().toUpperCase().replace(/[^0-9A-F]/g, ''), replace });
      this.enrollStatus = { ok: true, message: `Kartu berhasil didaftarkan untuk ${this.enrollTarget.nama}.` };
      this.enrollTarget = null;
      this.enrollUid    = '';
    } catch (err) {
      const code = err.data?.code;
      const msg  = code === 'kartu_terpakai'    ? (err.data?.message ?? 'Kartu sudah dipakai siswa lain.')
                 : code === 'sudah_punya_kartu' ? `${this.enrollTarget?.nama} sudah punya kartu. Pilih siswa lagi untuk konfirmasi penggantian.`
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
      const data   = await api.get(`siswa?search=${encodeURIComponent(this.search)}`);
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
    if (replace) {
      if (!confirm(`${this.target.nama} sudah punya kartu. Ganti?`)) return;
    }
    this.loading = true;
    this.status  = null;
    try {
      // R1/K8: POST absen/rfid/enroll {siswa_id, rfid_uid, replace}
      await api.post('absen/rfid/enroll', { siswa_id: this.target.id, rfid_uid: this.uidInput.trim().toUpperCase(), replace });
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

// P-FE5: Map picker koordinat sekolah di halaman Settings
Alpine.data('settingsMap', () => ({
  map:    null,
  marker: null,

  init() {
    const root = this.$el;
    this._lat = parseFloat(root.dataset.lat) || -7.250445;
    this._lng = parseFloat(root.dataset.lng) || 112.768845;
    this._boot(0);
  },

  // Tunggu container punya dimensi nyata, baru init Leaflet
  _boot(tries) {
    const el = document.getElementById('absensi-map');
    if (!el) return;
    if (el.offsetWidth === 0 && tries < 30) {
      setTimeout(() => this._boot(tries + 1), 100);
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
    // Paksa recalculate setelah 2 frame — mengatasi transisi margin WP admin sidebar
    requestAnimationFrame(() =>
      requestAnimationFrame(() => this.map && this.map.invalidateSize({ animate: false }))
    );
    // ResizeObserver: auto-fix setiap kali container berubah ukuran (sidebar collapse/expand)
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
    // Notify settingsForm sibling — Alpine tidak reaktif terhadap DOM update langsung
    window.dispatchEvent(new CustomEvent('map-pin-moved', { detail: { lat, lng } }));
  },
}));

// Dipakai oleh dashboard.php — berbagi fmtTime yang sama dengan rekapTable
Alpine.data('dashboardTable', () => ({ fmtTime }));

// Settings form — load dari AbsensiAdmin.settings atau data-* PHP, simpan via PUT /settings
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
    // Prefill dari data-* attributes (dari PHP get_option)
    this.fields.absensi_lat           = el.dataset.lat          ?? '';
    this.fields.absensi_lng           = el.dataset.lng          ?? '';
    this.fields.absensi_radius        = parseInt(el.dataset.radius       ?? '100', 10) || 100;
    this.fields.absensi_jam_masuk     = el.dataset.jamMasuk     ?? '07:00';
    this.fields.absensi_jam_keluar    = el.dataset.jamKeluar    ?? '15:00';
    this.fields.absensi_telat_menit   = parseInt(el.dataset.telatMenit   ?? '15', 10)  || 0;
    this.fields.absensi_akurasi_max   = parseInt(el.dataset.akurasiMax   ?? '50', 10)  || 50;
    this.fields.absensi_rfid_debounce = parseInt(el.dataset.rfidDebounce ?? '3', 10)   || 3;
    this.fields.absensi_retensi_hari  = parseInt(el.dataset.retensiHari  ?? '365', 10) || 365;
    this.fields.absensi_wa_gateway    = el.dataset.waGateway    ?? '';
    this.radius = this.fields.absensi_radius;

    // Override dengan AbsensiAdmin.settings jika tersedia (lebih fresh dari PHP)
    const s = window.AbsensiAdmin?.settings ?? {};
    if (s.lat)           this.fields.absensi_lat           = s.lat;
    if (s.lng)           this.fields.absensi_lng           = s.lng;
    if (s.radius)        { this.fields.absensi_radius = parseInt(s.radius, 10) || this.fields.absensi_radius; this.radius = this.fields.absensi_radius; }
    if (s.jamMasuk)      this.fields.absensi_jam_masuk     = s.jamMasuk;
    if (s.jamKeluar)     this.fields.absensi_jam_keluar    = s.jamKeluar;
    if (s.telatMenit)    this.fields.absensi_telat_menit   = parseInt(s.telatMenit, 10)   || this.fields.absensi_telat_menit;
    if (s.akurasiMax)    this.fields.absensi_akurasi_max   = parseInt(s.akurasiMax, 10)   || this.fields.absensi_akurasi_max;
    if (s.rfidDebounce)  this.fields.absensi_rfid_debounce = parseInt(s.rfidDebounce, 10) || this.fields.absensi_rfid_debounce;
    if (s.retensiHari)   this.fields.absensi_retensi_hari  = parseInt(s.retensiHari, 10)  || this.fields.absensi_retensi_hari;
    if (s.waGateway)     this.fields.absensi_wa_gateway    = s.waGateway;

    // Ambil wa_token dari REST — tidak di-inject ke localize (sensitif)
    api.get('settings').then(data => {
      if (data?.absensi_wa_token) this.fields.absensi_wa_token = data.absensi_wa_token;
    }).catch(() => {});

    // Dengarkan event dari settingsMap saat marker digeser
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
      await api.put('settings', payload);
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

// WaliLinker — hubungkan orang tua (WP user) ke siswa via REST /wali
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
      const data = await api.get(`wali?siswa_id=${this.siswaId}`);
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
      await api.post('wali', { wali_user_id: user.id, siswa_id: this.siswaId });
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
      await api.delete(`wali/${waliId}`);
      this.walis = this.walis.filter(w => w.id !== waliId);
    } catch (err) {
      alert(err.message);
    }
  },

  close() { this.open = false; },
}));

// Jadwal per kelas — CRUD via REST /jadwal
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
      const data = await api.get('kelas');
      this.kelasList = data.data ?? data ?? [];
    } catch {}
  },

  async load() {
    this.loading = true;
    this.error   = null;
    try {
      const data = await api.get('jadwal');
      this.rows = data.data ?? data ?? [];
    } catch (err) {
      this.error = err.message;
    } finally {
      this.loading = false;
    }
  },

  openAdd() {
    this.form     = { kelas_id: '', hari: 1, jam_masuk: '07:00', jam_keluar: '15:00' };
    this.editId   = null;
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
    this.editId   = row.id;
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
        await api.put(`jadwal/${this.editId}`, body);
      } else {
        await api.post('jadwal', body);
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
      await api.delete(`jadwal/${id}`);
      this.rows = this.rows.filter(r => r.id !== id);
    } catch (err) {
      alert(err.message);
    }
  },

  kelasNama(kelas_id) {
    return this.kelasList.find(k => k.id == kelas_id)?.nama_kelas ?? `Kelas #${kelas_id}`;
  },
}));

Alpine.start();
