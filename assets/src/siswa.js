import Alpine from 'alpinejs';
import api from './apiClient.js';

/**
 * Surface: Siswa — absen mandiri via selfie + GPS di HP.
 */

Alpine.data('absensiSiswa', () => ({
  // State
  sesi:         'masuk',    // 'masuk' | 'pulang'
  step:         'idle',     // 'idle' | 'camera' | 'preview' | 'submitting' | 'result'
  stream:       null,
  photoBlob:    null,
  photoUrl:     null,
  gps:          null,       // { lat, lng, accuracy }
  gpsStatus:    'waiting',  // 'waiting' | 'ok' | 'weak' | 'error'
  gpsError:     null,
  result:       null,       // response dari server
  errorMsg:     null,
  isHttps:      location.protocol === 'https:' || location.hostname === 'localhost',

  get gpsAccuracyLabel() {
    if (!this.gps) return '—';
    return `±${Math.round(this.gps.accuracy)} m`;
  },

  get canSubmit() {
    return this.photoBlob && this.gps && this.gpsStatus === 'ok' && !this.submitting;
  },

  get submitting() {
    return this.step === 'submitting';
  },

  // --- Lifecycle ---
  init() {
    this.detectSesi();
    this.startGps();
  },

  destroy() {
    this.stopCamera();
    this.stopGps?.();
  },

  // --- Sesi auto-suggest ---
  detectSesi() {
    const config = window.AbsensiConfig ?? {};
    const now    = new Date();
    const hh     = now.getHours() * 60 + now.getMinutes();
    const [jamH, jamM] = (config.jamMasuk ?? '07:00').split(':').map(Number);
    const [pulH, pulM] = (config.jamKeluar ?? '15:00').split(':').map(Number);
    const tengah = Math.round(((jamH * 60 + jamM) + (pulH * 60 + pulM)) / 2);
    this.sesi = hh < tengah ? 'masuk' : 'pulang';
  },

  // --- Kamera ---
  async startCamera() {
    if (!this.isHttps) {
      this.errorMsg = 'Absen butuh koneksi aman (HTTPS). Hubungi administrator.';
      return;
    }
    this.errorMsg = null;
    this.step = 'camera';

    try {
      this.stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } },
        audio: false,
      });
      this.$nextTick(() => {
        const video = this.$refs.video;
        if (video) {
          video.srcObject = this.stream;
          video.play();
        }
      });
    } catch (err) {
      this.step = 'idle';
      this.errorMsg = err.name === 'NotAllowedError'
        ? 'Izin kamera ditolak. Aktifkan izin kamera di pengaturan browser.'
        : 'Kamera tidak dapat diakses: ' + err.message;
    }
  },

  stopCamera() {
    if (this.stream) {
      this.stream.getTracks().forEach(t => t.stop());
      this.stream = null;
    }
  },

  capturePhoto() {
    const video  = this.$refs.video;
    const canvas = this.$refs.canvas;
    if (!video || !canvas) return;

    const MAX = 1280;
    let w = video.videoWidth;
    let h = video.videoHeight;
    if (w > MAX) { h = Math.round(h * MAX / w); w = MAX; }

    canvas.width  = w;
    canvas.height = h;
    canvas.getContext('2d').drawImage(video, 0, 0, w, h);

    canvas.toBlob(blob => {
      if (this.photoUrl) URL.revokeObjectURL(this.photoUrl);
      this.photoBlob = blob;
      this.photoUrl  = URL.createObjectURL(blob);
      this.stopCamera();
      this.step = 'preview';
    }, 'image/jpeg', 0.7);
  },

  retakePhoto() {
    if (this.photoUrl) { URL.revokeObjectURL(this.photoUrl); this.photoUrl = null; }
    this.photoBlob = null;
    this.startCamera();
  },

  // --- GPS ---
  startGps() {
    if (!navigator.geolocation) {
      this.gpsStatus = 'error';
      this.gpsError  = 'Browser tidak mendukung GPS.';
      return;
    }

    this.watchId = navigator.geolocation.watchPosition(
      pos => {
        const akurasiMax = parseInt(window.AbsensiConfig?.akurasiMax ?? '100', 10) || 100;
        this.gps = { lat: pos.coords.latitude, lng: pos.coords.longitude, accuracy: pos.coords.accuracy };
        this.gpsStatus = pos.coords.accuracy <= akurasiMax ? 'ok' : 'weak';
        this.gpsError  = null;
      },
      err => {
        this.gpsStatus = 'error';
        this.gpsError  = err.code === 1
          ? 'Izin GPS ditolak. Aktifkan lokasi di pengaturan browser.'
          : 'GPS tidak tersedia: ' + err.message;
      },
      { enableHighAccuracy: true, maximumAge: 10000, timeout: 15000 }
    );

    this.stopGps = () => navigator.geolocation.clearWatch(this.watchId);
  },

  // --- Submit ---
  async submit() {
    if (!this.canSubmit) return;

    // Cek koneksi — jangan queue foto (privasi)
    if (!navigator.onLine) {
      this.errorMsg = 'Tidak ada koneksi internet. Periksa jaringan lalu coba lagi. Foto tidak disimpan.';
      return;
    }

    this.step     = 'submitting';
    this.errorMsg = null;

    try {
      const formData = new FormData();
      formData.append('sesi',     this.sesi);
      formData.append('lat',      this.gps.lat);
      formData.append('lng',      this.gps.lng);
      formData.append('accuracy', this.gps.accuracy);
      if (this.photoBlob) formData.append('foto', this.photoBlob, 'selfie.jpg');

      const cfg = window.AbsensiConfig ?? {};
      const res = await fetch((cfg.restUrl ?? '/wp-json/absensi/v1/') + 'absen/selfie', {
        method: 'POST',
        headers: { 'X-WP-Nonce': cfg.nonce ?? '' },
        body: formData,
      });

      const data = await res.json().catch(() => null);
      if (!res.ok) {
        const code = data?.code;
        const errorMap = {
          sekolah_belum_diatur: 'Koordinat sekolah belum diatur. Hubungi administrator.',
          akurasi_rendah:       'Sinyal GPS terlalu lemah. Tunggu beberapa saat lalu coba lagi.',
          diluar_radius:        data?.jarak ? `Di luar radius sekolah (${Math.round(data.jarak)}m).` : 'Di luar radius sekolah.',
          sudah_absen:          'Sudah absen masuk hari ini.',
          sudah_absen_keluar:   'Sudah absen pulang hari ini.',
          belum_absen_masuk:    'Belum absen masuk. Selesaikan absen masuk terlebih dahulu.',
          foto_korup:           'Foto tidak dapat dibaca, coba ambil ulang.',
          foto_tipe_ditolak:    'Format foto tidak didukung.',
          foto_bukan_gambar:    'File yang dikirim bukan gambar.',
          foto_terlalu_besar:   'Ukuran foto melebihi batas.',
        };
        const msg = (code && errorMap[code]) ?? data?.message ?? `HTTP ${res.status}`;
        throw Object.assign(new Error(msg), { status: res.status });
      }

      // Buang data sensitif dari memori
      if (this.photoUrl) { URL.revokeObjectURL(this.photoUrl); this.photoUrl = null; }
      this.photoBlob = null;
      this.gps       = null;

      this.result = {
        ...data,
        sesi: data.sesi ?? this.sesi,  // backend sekarang mengembalikan sesi
        jam:  new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }),
        jarak_meter: data.jarak ?? null,
      };
      this.step = 'result';
    } catch (err) {
      this.step     = 'preview';
      this.errorMsg = err.message;
    }
  },

  reset() {
    this.step     = 'idle';
    this.result   = null;
    this.errorMsg = null;
    this.photoBlob = null;
    if (this.photoUrl) { URL.revokeObjectURL(this.photoUrl); this.photoUrl = null; }
  },
}));

Alpine.start();
