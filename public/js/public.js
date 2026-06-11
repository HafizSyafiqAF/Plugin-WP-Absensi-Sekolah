/* ─── Bootstrap Alpine.js (lokal) ───────────────────────────────────────────
 * Cari URL public.js untuk derive path alpine.min.js di folder yang sama.
 * public.js dijalankan di footer, listener alpine:init sudah terdaftar sebelum
 * Alpine selesai load (async), sehingga komponen Alpine.data teregistrasi tepat waktu.
 */
(function () {
  if (window.Alpine || document.querySelector('script[src*="alpine"]')) return;
  var tag = document.querySelector('script[src*="plugin-wp-absensi-sekolah/public/js/public.js"]')
         || document.querySelector('script[src*="public/js/public.js"]');
  var src = tag
    ? tag.src.replace(/public\.js(\?.*)?$/, 'alpine.min.js')
    : 'https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js';
  var s = document.createElement('script');
  s.src = src;
  document.head.appendChild(s);
}());

/* ─── API Client (public) ────────────────────────────────────────────────── */
(function () {
  'use strict';

  function getConfig()  { return window.AbsensiConfig ?? {}; }
  function getNonce()   { return getConfig().nonce ?? ''; }
  function getBase()    { return getConfig().restUrl ?? '/wp-json/absensi/v1/'; }

  async function refreshNonce() {
    try {
      const res  = await fetch('/wp-json/');
      const data = await res.json();
      const n    = data?.nonce;
      if (n && window.AbsensiConfig) window.AbsensiConfig.nonce = n;
      return n;
    } catch { return null; }
  }

  async function request(method, path, body, attempt) {
    attempt = attempt || 0;
    const url     = getBase() + path;
    const headers = { 'X-WP-Nonce': getNonce() };
    const opts    = { method, headers, credentials: 'same-origin' };
    if (body instanceof FormData) {
      opts.body = body;
    } else if (body) {
      headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(body);
    }
    const res  = await fetch(url, opts);
    if (res.status === 403 && attempt === 0) {
      await refreshNonce();
      return request(method, path, body, 1);
    }
    const data = await res.json().catch(function () { return null; });
    if (!res.ok) {
      var err = new Error(data && data.message ? data.message : 'HTTP ' + res.status);
      err.status = res.status;
      err.data   = data;
      throw err;
    }
    return data;
  }

  window.api = {
    get:    function (path)       { return request('GET',    path); },
    post:   function (path, body) { return request('POST',   path, body); },
    put:    function (path, body) { return request('PUT',    path, body); },
    delete: function (path)       { return request('DELETE', path); },
  };
})();

/* ─── RFID Shared (HID buffer / parser) ─────────────────────────────────── */
(function () {
  var TERMINATOR     = 'Enter';
  var MAX_UID_LENGTH = 32;
  var MIN_UID_LENGTH = 4;

  function normalizeUid(raw) {
    return raw.replace(/[^0-9a-fA-F]/g, '').toUpperCase();
  }

  function isValidUid(uid) {
    return uid.length >= MIN_UID_LENGTH && uid.length <= MAX_UID_LENGTH;
  }

  function createRfidListener(targetEl, callbacks) {
    var onScan    = callbacks.onScan;
    var onInvalid = callbacks.onInvalid;
    var cfg       = window.AbsensiConfig ?? {};
    var DEBOUNCE_MS = (parseInt(cfg.rfidDebounce ?? '3', 10) || 3) * 1000;
    var buffer       = '';
    var lastUid      = '';
    var lastScanTime = 0;

    function handleKeydown(e) {
      if (e.key === TERMINATOR) {
        e.preventDefault();
        var uid = normalizeUid(buffer);
        buffer = '';
        if (!isValidUid(uid)) { if (onInvalid) onInvalid(uid); return; }
        var now = Date.now();
        if (uid === lastUid && now - lastScanTime < DEBOUNCE_MS) return;
        lastUid = uid; lastScanTime = now;
        onScan(uid);
        return;
      }
      if (e.key.length === 1) {
        buffer += e.key;
        if (buffer.length > MAX_UID_LENGTH * 2) buffer = '';
      }
    }

    function handleBlur() {
      setTimeout(function () {
        var active = document.activeElement;
        var interactive = ['INPUT', 'SELECT', 'TEXTAREA', 'BUTTON', 'A'];
        if (!active || interactive.indexOf(active.tagName) === -1) targetEl.focus();
      }, 50);
    }

    targetEl.addEventListener('keydown', handleKeydown);
    targetEl.addEventListener('blur', handleBlur);
    return function cleanup() {
      targetEl.removeEventListener('keydown', handleKeydown);
      targetEl.removeEventListener('blur', handleBlur);
    };
  }

  window.createRfidListener = createRfidListener;
})();

/* ─── Alpine Components (public surfaces) ───────────────────────────────── */
document.addEventListener('alpine:init', function () {

  /* ======================================================================
     absensiSiswa — selfie + GPS (shortcode [absensi_siswa])
     ====================================================================== */
  Alpine.data('absensiSiswa', function () { return {
    sesi:      'masuk',
    step:      'idle',     // idle | camera | preview | submitting | result
    stream:    null,
    photoBlob: null,
    photoUrl:  null,
    gps:       null,       // { lat, lng, accuracy }
    gpsStatus: 'waiting',  // waiting | ok | weak | error
    gpsError:  null,
    result:    null,
    errorMsg:  null,
    isHttps:   location.protocol === 'https:' || location.hostname === 'localhost',

    get gpsAccuracyLabel() { return this.gps ? '±' + Math.round(this.gps.accuracy) + ' m' : '—'; },
    get canSubmit()        { return !!(this.photoBlob && this.gps && this.gpsStatus === 'ok' && !this.submitting); },
    get submitting()       { return this.step === 'submitting'; },

    init: function ()    { this.detectSesi(); this.startGps(); },
    destroy: function () { this.stopCamera(); if (this.stopGps) this.stopGps(); },

    detectSesi: function () {
      var cfg  = window.AbsensiConfig || {};
      var now  = new Date();
      var hh   = now.getHours() * 60 + now.getMinutes();
      var jm   = (cfg.jamMasuk  || '07:00').split(':').map(Number);
      var pm   = (cfg.jamKeluar || '15:00').split(':').map(Number);
      var mid  = Math.round(((jm[0] * 60 + jm[1]) + (pm[0] * 60 + pm[1])) / 2);
      this.sesi = hh < mid ? 'masuk' : 'pulang';
    },

    startCamera: async function () {
      if (!this.isHttps) { this.errorMsg = 'Absen butuh koneksi aman (HTTPS). Hubungi administrator.'; return; }
      this.errorMsg = null;
      this.step     = 'camera';
      try {
        this.stream = await navigator.mediaDevices.getUserMedia({
          video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } },
          audio: false,
        });
        var self = this;
        this.$nextTick(function () {
          var v = self.$refs.video;
          if (v) { v.srcObject = self.stream; v.play(); }
        });
      } catch (err) {
        this.step    = 'idle';
        this.errorMsg = err.name === 'NotAllowedError'
          ? 'Izin kamera ditolak. Aktifkan izin kamera di pengaturan browser.'
          : 'Kamera tidak dapat diakses: ' + err.message;
      }
    },

    stopCamera: function () {
      if (this.stream) { this.stream.getTracks().forEach(function (t) { t.stop(); }); this.stream = null; }
    },

    capturePhoto: function () {
      var video  = this.$refs.video;
      var canvas = this.$refs.canvas;
      if (!video || !canvas) return;
      var MAX = 1280, w = video.videoWidth, h = video.videoHeight;
      if (w > MAX) { h = Math.round(h * MAX / w); w = MAX; }
      canvas.width = w; canvas.height = h;
      canvas.getContext('2d').drawImage(video, 0, 0, w, h);
      var self = this;
      canvas.toBlob(function (blob) {
        if (self.photoUrl) URL.revokeObjectURL(self.photoUrl);
        self.photoBlob = blob;
        self.photoUrl  = URL.createObjectURL(blob);
        self.stopCamera();
        self.step = 'preview';
      }, 'image/jpeg', 0.7);
    },

    retakePhoto: function () {
      if (this.photoUrl) { URL.revokeObjectURL(this.photoUrl); this.photoUrl = null; }
      this.photoBlob = null;
      this.startCamera();
    },

    startGps: function () {
      if (!navigator.geolocation) { this.gpsStatus = 'error'; this.gpsError = 'Browser tidak mendukung GPS.'; return; }
      var self = this;
      this.watchId = navigator.geolocation.watchPosition(
        function (pos) {
          var max = parseInt((window.AbsensiConfig || {}).akurasiMax || '100', 10) || 100;
          self.gps       = { lat: pos.coords.latitude, lng: pos.coords.longitude, accuracy: pos.coords.accuracy };
          self.gpsStatus = pos.coords.accuracy <= max ? 'ok' : 'weak';
          self.gpsError  = null;
        },
        function (err) {
          self.gpsStatus = 'error';
          self.gpsError  = err.code === 1
            ? 'Izin GPS ditolak. Aktifkan lokasi di pengaturan browser.'
            : 'GPS tidak tersedia: ' + err.message;
        },
        { enableHighAccuracy: true, maximumAge: 10000, timeout: 15000 }
      );
      this.stopGps = function () { navigator.geolocation.clearWatch(self.watchId); };
    },

    submit: async function () {
      if (!this.canSubmit) return;
      if (!navigator.onLine) { this.errorMsg = 'Tidak ada koneksi internet. Foto tidak disimpan.'; return; }
      this.step = 'submitting'; this.errorMsg = null;
      try {
        var fd  = new FormData();
        fd.append('sesi', this.sesi);
        fd.append('lat',  this.gps.lat);
        fd.append('lng',  this.gps.lng);
        fd.append('accuracy', this.gps.accuracy);
        if (this.photoBlob) fd.append('foto', this.photoBlob, 'selfie.jpg');
        var cfg = window.AbsensiConfig || {};
        var res = await fetch((cfg.restUrl || '/wp-json/absensi/v1/') + 'absen/selfie', {
          method: 'POST',
          headers: { 'X-WP-Nonce': cfg.nonce || '' },
          body: fd,
        });
        var data = await res.json().catch(function () { return null; });
        if (!res.ok) {
          var code = data && data.code;
          var em = {
            sekolah_belum_diatur: 'Koordinat sekolah belum diatur. Hubungi administrator.',
            akurasi_rendah:       'Sinyal GPS terlalu lemah. Tunggu beberapa saat lalu coba lagi.',
            diluar_radius:        data && data.jarak ? 'Di luar radius sekolah (' + Math.round(data.jarak) + 'm).' : 'Di luar radius sekolah.',
            sudah_absen:          'Sudah absen masuk hari ini.',
            sudah_absen_keluar:   'Sudah absen pulang hari ini.',
            belum_absen_masuk:    'Belum absen masuk. Selesaikan absen masuk terlebih dahulu.',
            foto_korup:           'Foto tidak dapat dibaca, coba ambil ulang.',
            foto_tipe_ditolak:    'Format foto tidak didukung.',
            foto_terlalu_besar:   'Ukuran foto melebihi batas.',
          };
          throw new Error((code && em[code]) || (data && data.message) || 'HTTP ' + res.status);
        }
        if (this.photoUrl) { URL.revokeObjectURL(this.photoUrl); this.photoUrl = null; }
        this.photoBlob = null; this.gps = null;
        this.result = Object.assign({}, data, {
          sesi:        data.sesi || this.sesi,
          jam:         new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }),
          jarak_meter: data.jarak || null,
        });
        this.step = 'result';
      } catch (err) {
        this.step = 'preview'; this.errorMsg = err.message;
      }
    },

    reset: function () {
      this.step = 'idle'; this.result = null; this.errorMsg = null; this.photoBlob = null;
      if (this.photoUrl) { URL.revokeObjectURL(this.photoUrl); this.photoUrl = null; }
    },
  }; });

  /* ======================================================================
     absensiGuru — RFID scanner (shortcode [absensi_guru])
     ====================================================================== */
  Alpine.data('absensiGuru', function () { return {
    kelas:      '',
    sesi:       'masuk',
    mode:       'absen',
    kelasList:  (window.AbsensiConfig && window.AbsensiConfig.kelasList) || [],

    toasts:    [],
    todayList: [],

    enrollSearch:    '',
    enrollResults:   [],
    enrollTarget:    null,
    enrollStatus:    null,
    enrollSearching: false,

    STORAGE_KEY: 'absensi_guru_draft',

    init: function ()    { this.loadDraft(); this.detectSesi(); var self = this; this.$nextTick(function () { self.focusInput(); }); },
    destroy: function () { if (this._rfidCleanup) this._rfidCleanup(); },

    loadDraft: function () {
      try {
        var raw = sessionStorage.getItem(this.STORAGE_KEY);
        if (!raw) return;
        var d = JSON.parse(raw);
        this.kelas = d.kelas || ''; this.sesi = d.sesi || 'masuk'; this.mode = d.mode || 'absen';
      } catch (e) {}
    },

    saveDraft: function () {
      try { sessionStorage.setItem(this.STORAGE_KEY, JSON.stringify({ kelas: this.kelas, sesi: this.sesi, mode: this.mode })); } catch (e) {}
    },

    detectSesi: function () {
      var cfg = window.AbsensiConfig || {};
      var now = new Date(), hh = now.getHours() * 60 + now.getMinutes();
      var jm  = (cfg.jamMasuk  || '07:00').split(':').map(Number);
      var pm  = (cfg.jamKeluar || '15:00').split(':').map(Number);
      var mid = Math.round(((jm[0] * 60 + jm[1]) + (pm[0] * 60 + pm[1])) / 2);
      this.sesi = hh < mid ? 'masuk' : 'pulang';
    },

    focusInput: function () {
      var el = this.$refs.rfidInput;
      if (!el) return;
      if (this._rfidCleanup) this._rfidCleanup();
      var self = this;
      this._rfidCleanup = window.createRfidListener(el, {
        onScan:    function (uid) { self.handleScan(uid); },
        onInvalid: function (uid) { self.addToast({ ok: false, message: 'UID tidak valid: "' + uid + '"' }); },
      });
      el.focus();
    },

    handleScan: async function (uid) {
      if (this.mode === 'enroll') { this.handleEnrollScan(uid); return; }
      try {
        var data   = await window.api.post('absen/rfid', { rfid_uid: uid });
        var nama   = data.siswa || uid;
        var action = data.action === 'keluar' ? 'pulang' : 'masuk';
        this.addToast({ ok: true, message: '✓ ' + nama + ' — ' + (action === 'masuk' ? 'Masuk' : 'Pulang') + ' (' + (data.status || 'hadir') + ')' });
        this.todayList.unshift({ nama: nama, sesi: action, status: data.status || 'hadir', jam: new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) });
      } catch (err) {
        var msg = err.status === 429 ? 'Tap terlalu cepat, tunggu sebentar.'
                : err.status === 404 ? 'Kartu tidak dikenal (' + uid + '). Daftarkan dulu di tab Enroll.'
                : err.status === 409 ? ((err.data && err.data.message) || 'Sudah absen hari ini.')
                : err.message;
        this.addToast({ ok: false, message: msg });
      }
    },

    searchSiswa: async function () {
      if (this.enrollSearch.length < 2) return;
      this.enrollSearching = true;
      try {
        var data = await window.api.get('siswa?search=' + encodeURIComponent(this.enrollSearch));
        this.enrollResults = (data && data.data) || data || [];
      } catch (e) { this.enrollResults = []; }
      finally { this.enrollSearching = false; }
    },

    selectEnrollTarget: function (siswa) {
      this.enrollTarget = siswa; this.enrollStatus = null; this.enrollResults = []; this.enrollSearch = '';
      var self = this; this.$nextTick(function () { if (self.$refs.rfidInput) self.$refs.rfidInput.focus(); });
    },

    handleEnrollScan: async function (uid) {
      if (!this.enrollTarget) { this.addToast({ ok: false, message: 'Pilih siswa terlebih dahulu.' }); return; }
      var replace = !!this.enrollTarget.rfid_uid;
      if (replace && !confirm(this.enrollTarget.nama + ' sudah punya kartu. Ganti dengan kartu baru?')) return;
      try {
        await window.api.post('absen/rfid/enroll', { siswa_id: this.enrollTarget.id, rfid_uid: uid, replace: replace });
        this.enrollStatus = { ok: true, message: 'Kartu berhasil didaftarkan untuk ' + this.enrollTarget.nama };
        this.enrollTarget = null;
      } catch (err) {
        var code = err.data && err.data.code;
        var m = code === 'kartu_terpakai'    ? ((err.data && err.data.message) || 'Kartu sudah terdaftar untuk siswa lain.')
              : code === 'sudah_punya_kartu' ? ((this.enrollTarget ? this.enrollTarget.nama : '') + ' sudah punya kartu. Pilih siswa lagi untuk konfirmasi penggantian.')
              : err.message;
        this.enrollStatus = { ok: false, message: m };
      }
    },

    addToast: function (toast) {
      var id   = Date.now();
      var self = this;
      this.toasts.push(Object.assign({ id: id }, toast));
      setTimeout(function () { self.toasts = self.toasts.filter(function (t) { return t.id !== id; }); }, 3000);
    },

    get hadirCount() { return this.todayList.filter(function (r) { return r.status === 'hadir' || r.status === 'telat'; }).length; },
  }; });

  /* ======================================================================
     absensiOrtu — view-only riwayat anak (shortcode [absensi_ortu])
     ====================================================================== */
  Alpine.data('absensiOrtu', function () {
    // 'YYYY-MM' dalam waktu lokal (toISOString = UTC, bisa meleset di tanggal 1).
    function bulanLokal(d) {
      return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
    }
    return {
    anakList:      (window.AbsensiConfig && window.AbsensiConfig.anakList) || [],
    selectedIndex: 0,
    bulan:         bulanLokal(new Date()),
    timeline:      [],
    summary:       { hadir: 0, telat: 0, izin_sakit: 0, alpha: 0 },
    loading:       false,
    error:         null,

    get selectedAnak()  { return this.anakList[this.selectedIndex] || null; },
    get adaAnak()       { return this.anakList.length !== 0; },
    get banyakAnak()    { return this.anakList.length !== 0 && this.anakList.length !== 1; },
    get adaTimeline()   { return this.timeline.length !== 0; },

    init: function () { if (this.selectedAnak) this.load(); },

    selectAnak: function (index) {
      if (index === this.selectedIndex) return;
      this.selectedIndex = index;
      this.load();
    },

    load: async function () {
      if (!this.selectedAnak) return;
      this.loading = true; this.error = null;
      try {
        var range = this.bulanRange();
        var p = new URLSearchParams({
          siswa_id: this.selectedAnak.siswa_id,
          dari:     range[0],
          sampai:   range[1],
        });
        var res = await window.api.get('child/logs?' + p);
        this.timeline = (res && res.data) || [];
        this.summary  = this.hitungSummary(this.timeline);
      } catch (err) { this.error = err.message; }
      finally { this.loading = false; }
    },

    // /child/logs tidak punya endpoint summary — rekap dihitung dari baris timeline.
    hitungSummary: function (rows) {
      var s = { hadir: 0, telat: 0, izin_sakit: 0, alpha: 0 };
      rows.forEach(function (r) {
        if (r.status === 'hadir') s.hadir++;
        else if (r.status === 'telat') s.telat++;
        else if (r.status === 'izin' || r.status === 'sakit') s.izin_sakit++;
        else if (r.status === 'alpha') s.alpha++;
      });
      return s;
    },

    bulanRange: function () {
      var parts = this.bulan.split('-').map(Number), y = parts[0], m = parts[1];
      var last  = new Date(y, m, 0).getDate();
      return [this.bulan + '-01', this.bulan + '-' + String(last).padStart(2, '0')];
    },

    prevBulan: function () {
      var p = this.bulan.split('-').map(Number);
      this.bulan = bulanLokal(new Date(p[0], p[1] - 2, 1));
      this.load();
    },

    nextBulan: function () {
      if (this.isMaxBulan) return;
      var p = this.bulan.split('-').map(Number);
      this.bulan = bulanLokal(new Date(p[0], p[1], 1));
      this.load();
    },

    get bulanLabel() {
      var p = this.bulan.split('-').map(Number);
      return new Date(p[0], p[1] - 1, 1).toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
    },

    get isMaxBulan() { return this.bulan === bulanLokal(new Date()) || this.bulan.localeCompare(bulanLokal(new Date())) === 1; },

    statusClass: function (status) {
      var m = { hadir: 'badge-success', telat: 'badge-warning', alpha: 'badge-danger', izin: 'badge-info', sakit: 'badge-info' };
      return m[status] || 'badge-neutral';
    },

    // 'YYYY-MM-DD HH:MM:SS' → 'HH:MM' (null-safe untuk waktu_keluar kosong).
    jam: function (waktu) { return waktu ? String(waktu).slice(11, 16) : null; },

    hariLabel: function (tanggal) {
      var p = String(tanggal).slice(0, 10).split('-').map(Number);
      return new Date(p[0], p[1] - 1, p[2]).toLocaleDateString('id-ID', { weekday: 'short' });
    },

    tanggalLabel: function (tanggal) { return String(tanggal).slice(8, 10); },

    inisial: function (nama) {
      return ((nama || '?').split(' ').slice(0, 2).map(function (s) { return s[0]; }).join('')).toUpperCase();
    },
  }; });

}); // end alpine:init
