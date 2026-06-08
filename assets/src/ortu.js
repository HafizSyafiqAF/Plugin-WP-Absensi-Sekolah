import Alpine from 'alpinejs';
import api from './apiClient.js';

/**
 * Surface: Orang tua — view-only riwayat absensi anak.
 */

Alpine.data('absensiOrtu', () => ({
  // Data anak (di-inject backend via AbsensiConfig.anakList jika multi-anak)
  anakList:      window.AbsensiConfig?.anakList ?? [],
  selectedAnak:  null,

  // Rekap per bulan
  bulan:    new Date().toISOString().slice(0, 7), // YYYY-MM
  timeline: [],
  summary:  { hadir: 0, telat: 0, izin_sakit: 0, alpha: 0 },
  loading:  false,
  error:    null,

  init() {
    if (this.anakList.length === 1) {
      this.selectedAnak = this.anakList[0];
    }
    if (this.selectedAnak) this.load();
  },

  selectAnak(anak) {
    this.selectedAnak = anak;
    this.load();
  },

  async load() {
    if (!this.selectedAnak) return;
    this.loading = true;
    this.error   = null;

    try {
      const [tgl_dari, tgl_sampai] = this.bulanRange();
      const params = new URLSearchParams({
        siswa_id: this.selectedAnak.id,
        dari:     tgl_dari,
        sampai:   tgl_sampai,
      });

      const [rekap, summary] = await Promise.all([
        api.get('laporan?' + params),
        api.get('laporan/summary?' + params),
      ]);

      this.timeline = rekap.data   ?? rekap   ?? [];
      this.summary  = summary.data ?? summary ?? this.summary;
    } catch (err) {
      this.error = err.message;
    } finally {
      this.loading = false;
    }
  },

  bulanRange() {
    const [y, m] = this.bulan.split('-').map(Number);
    const last   = new Date(y, m, 0).getDate();
    return [`${this.bulan}-01`, `${this.bulan}-${String(last).padStart(2, '0')}`];
  },

  prevBulan() {
    const [y, m] = this.bulan.split('-').map(Number);
    const d = new Date(y, m - 2, 1);
    this.bulan = d.toISOString().slice(0, 7);
    this.load();
  },

  nextBulan() {
    const [y, m] = this.bulan.split('-').map(Number);
    const d = new Date(y, m, 1);
    const max = new Date().toISOString().slice(0, 7);
    if (d.toISOString().slice(0, 7) > max) return;
    this.bulan = d.toISOString().slice(0, 7);
    this.load();
  },

  get bulanLabel() {
    const [y, m] = this.bulan.split('-').map(Number);
    return new Date(y, m - 1, 1).toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
  },

  get isMaxBulan() {
    return this.bulan >= new Date().toISOString().slice(0, 7);
  },

  statusClass(status) {
    const map = { hadir: 'status-hadir', telat: 'status-telat', alpha: 'status-alpha', izin: 'status-izin', sakit: 'status-sakit' };
    return map[status] ?? 'badge';
  },

  inisial(nama) {
    return (nama ?? '?').split(' ').slice(0, 2).map(s => s[0]).join('').toUpperCase();
  },
}));

Alpine.start();
