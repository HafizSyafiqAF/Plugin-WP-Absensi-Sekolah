import Alpine from 'alpinejs';
import api from './apiClient.js';
import { createRfidListener } from './rfid.js';

Alpine.data('absensiGuru', () => ({
  kelas:      '',
  sesi:       'masuk',
  mode:       'absen',
  kelasList:  window.AbsensiAdmin?.kelasList ?? [],

  toasts:   [],
  todayList: [],

  enrollSearch:    '',
  enrollResults:   [],
  enrollTarget:    null,
  enrollStatus:    null,
  enrollSearching: false,

  STORAGE_KEY: 'absensi_guru_draft',

  init() {
    this.loadDraft();
    this.detectSesi();
    this.$nextTick(() => this.focusInput());
  },

  destroy() {
    this._rfidCleanup?.();
  },

  loadDraft() {
    try {
      const raw = sessionStorage.getItem(this.STORAGE_KEY);
      if (!raw) return;
      const d = JSON.parse(raw);
      this.kelas = d.kelas ?? '';
      this.sesi  = d.sesi  ?? 'masuk';
      this.mode  = d.mode  ?? 'absen';
    } catch {}
  },

  saveDraft() {
    try {
      sessionStorage.setItem(this.STORAGE_KEY, JSON.stringify({ kelas: this.kelas, sesi: this.sesi, mode: this.mode }));
    } catch {}
  },

  detectSesi() {
    const cfg  = window.AbsensiAdmin ?? {};
    const now  = new Date();
    const hh   = now.getHours() * 60 + now.getMinutes();
    const [jH, jM] = (cfg.jamMasuk  ?? '07:00').split(':').map(Number);
    const [pH, pM] = (cfg.jamKeluar ?? '15:00').split(':').map(Number);
    const tengah = Math.round(((jH * 60 + jM) + (pH * 60 + pM)) / 2);
    this.sesi = hh < tengah ? 'masuk' : 'pulang';
  },

  focusInput() {
    const el = this.$refs.rfidInput;
    if (!el) return;
    this._rfidCleanup?.();
    this._rfidCleanup = createRfidListener(el, {
      onScan:    uid => this.handleScan(uid),
      onInvalid: uid => this.addToast({ ok: false, message: `UID tidak valid: "${uid}"` }),
    });
    el.focus();
  },

  async handleScan(uid) {
    if (this.mode === 'enroll') {
      this.handleEnrollScan(uid);
      return;
    }

    try {
      // Backend AbsensiEndpoint expects rfid_uid (bukan uid)
      const data   = await api.post('absen/rfid', { rfid_uid: uid });
      const nama   = data.siswa ?? uid;
      const action = data.action === 'keluar' ? 'pulang' : 'masuk';
      const label  = action === 'masuk' ? 'Masuk' : 'Pulang';
      this.addToast({ ok: true, message: `✓ ${nama} — ${label} (${data.status ?? 'hadir'})` });
      this.todayList.unshift({
        nama,
        sesi:   action,
        status: data.status ?? 'hadir',
        jam:    new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }),
      });
    } catch (err) {
      const msg = err.status === 429 ? 'Tap terlalu cepat, tunggu sebentar.'
                : err.status === 404 ? `Kartu tidak dikenal (${uid}). Daftarkan dulu di tab Enroll.`
                : err.status === 409 ? (err.data?.message ?? 'Sudah absen hari ini.')
                : err.message;
      this.addToast({ ok: false, message: msg });
    }
  },

  async searchSiswa() {
    if (this.enrollSearch.length < 2) return;
    this.enrollSearching = true;
    try {
      const data = await api.get(`siswa?search=${encodeURIComponent(this.enrollSearch)}`);
      this.enrollResults = data.data ?? data ?? [];
    } catch {
      this.enrollResults = [];
    } finally {
      this.enrollSearching = false;
    }
  },

  selectEnrollTarget(siswa) {
    this.enrollTarget  = siswa;
    this.enrollStatus  = null;
    this.enrollResults = [];
    this.enrollSearch  = '';
    this.$nextTick(() => this.$refs.rfidInput?.focus());
  },

  async handleEnrollScan(uid) {
    if (!this.enrollTarget) {
      this.addToast({ ok: false, message: 'Pilih siswa terlebih dahulu.' });
      return;
    }
    const replace = !!this.enrollTarget.rfid_uid;
    if (replace) {
      if (!confirm(`${this.enrollTarget.nama} sudah punya kartu. Ganti dengan kartu baru?`)) return;
    }
    try {
      // R1/K8: POST absen/rfid/enroll {siswa_id, rfid_uid, replace}
      await api.post('absen/rfid/enroll', { siswa_id: this.enrollTarget.id, rfid_uid: uid, replace });
      this.enrollStatus = { ok: true, message: `Kartu berhasil didaftarkan untuk ${this.enrollTarget.nama}` };
      this.enrollTarget = null;
    } catch (err) {
      const code = err.data?.code;
      const msg = code === 'kartu_terpakai'    ? (err.data?.message ?? 'Kartu sudah terdaftar untuk siswa lain.')
                : code === 'sudah_punya_kartu' ? `${this.enrollTarget?.nama} sudah punya kartu. Pilih siswa lagi untuk konfirmasi penggantian.`
                : err.message;
      this.enrollStatus = { ok: false, message: msg };
    }
  },

  addToast(toast) {
    const id = Date.now();
    this.toasts.push({ id, ...toast });
    setTimeout(() => { this.toasts = this.toasts.filter(t => t.id !== id); }, 3000);
  },

  get hadirCount() {
    return this.todayList.filter(r => r.status === 'hadir' || r.status === 'telat').length;
  },
}));

Alpine.start();
