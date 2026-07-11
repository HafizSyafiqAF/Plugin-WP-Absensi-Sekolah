/* ─── Alpine.js: CDN utama + fallback lokal ──────────────────────────────────
 * Alpine di-enqueue BE (Plugin.php, CDN, `defer`) — sumber UTAMA (sesuai CLAUDE.md).
 * FALLBACK: bila CDN GAGAL load (internet gangguan / server WP di LAN tanpa akses
 * keluar), kiosk tetap hidup dengan `alpine.min.js` LOKAL. Dicek saat `window load`
 * — script CDN yang `defer` pasti sudah selesai/gagal di titik ini, jadi:
 *   window.Alpine ADA  → CDN sukses, tak muat apa-apa (TIDAK double-load).
 *   window.Alpine TIADA → CDN gagal → suntik Alpine lokal (jaring pengaman).
 * Listener alpine:init di bawah tetap terdaftar → komponen teregistrasi saat
 * Alpine (CDN atau fallback) init. */
window.addEventListener('load', function () {
  if (window.Alpine) return;                                  // CDN sukses → cukup
  var tag = document.querySelector('script[src*="public/js/public.js"]');
  if (!tag) return;
  var s = document.createElement('script');
  s.src = tag.src.replace(/public\.js(\?.*)?$/, 'alpine.min.js');
  document.head.appendChild(s);                               // fallback: Alpine lokal
});

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
        var raw = (targetEl.value || buffer || '').trim();
        var uid = normalizeUid(raw);
        buffer = '';
        if (targetEl.value !== undefined) targetEl.value = '';
        if (!isValidUid(uid)) { if (onInvalid) onInvalid(raw); return; }
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
    _allSiswaCache:  [],
    _cacheKelas:     null,

    STORAGE_KEY: 'absensi_guru_draft',

    init: function () {
      this.loadDraft();
      this.detectSesi();
      var self = this;
      this.$nextTick(function () { self.focusInput(); });
      /* Ketika kelas berubah, bersihkan cache agar pencarian ulang mengambil data kelas yang benar */
      this.$watch('kelas', function () {
        self._allSiswaCache = [];
        self._cacheKelas = null;
        self.enrollResults = [];
        self.enrollSearch = '';
      });
    },
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

    /* Load siswa cache once (when kelas changes or first search) then filter locally */
    _loadSiswaCache: async function () {
      var query = 'siswa';
      if (this.kelas) query += '?kelas_id=' + encodeURIComponent(this.kelas);
      try {
        var res = await window.api.get(query);
        var raw = (res && res.data) || res || [];
        /* Deduplikasi: prioritas NIS (unik per siswa), fallback ke id */
        var seen = {};
        this._allSiswaCache = raw.filter(function (s) {
          var key = s.nis ? ('nis:' + s.nis) : ('id:' + s.id);
          if (seen[key]) return false;
          seen[key] = true;
          return true;
        });
        this._cacheKelas = this.kelas;
      } catch (e) { this._allSiswaCache = []; }
    },

    searchSiswa: async function () {
      var q = this.enrollSearch.trim().toLowerCase();
      /* Bersihkan hasil lama segera agar tidak tampil stale */
      this.enrollResults = [];
      if (q.length < 2) return;
      /* Reload cache if kelas changed or cache empty */
      if (this._cacheKelas !== this.kelas || this._allSiswaCache.length === 0) {
        this.enrollSearching = true;
        await this._loadSiswaCache();
        this.enrollSearching = false;
        /* Batalkan jika query sudah berubah selama async load */
        if (this.enrollSearch.trim().toLowerCase() !== q) return;
      }
      /* Filter: angka → cocokkan NIS dari awal; huruf → cocokkan nama dari awal */
      var isNumeric = /^\d+$/.test(q);
      console.log('[absensi-v2] searchSiswa q='+q+' isNumeric='+isNumeric+' cache='+this._allSiswaCache.length);
      this.enrollResults = this._allSiswaCache.filter(function (s) {
        if (s.rfid_uid) return false;
        if (isNumeric) return (s.nis || '').startsWith(q);
        return (s.nama || '').toLowerCase().startsWith(q);
      });
      console.log('[absensi-v2] results='+this.enrollResults.length);
    },

    selectEnrollTarget: function (siswa) {
      this.enrollTarget = siswa; this.enrollStatus = null; this.enrollResults = []; this.enrollSearch = '';
      var self = this; this.$nextTick(function () { if (self.$refs.rfidInput) self.$refs.rfidInput.focus(); });
    },

    handleEnrollScan: async function (uid) {
      if (!this.enrollTarget) { this.addToast({ ok: false, message: 'Pilih siswa terlebih dahulu.' }); return; }
      this.enrollStatus = null;
      try {
        await window.api.post('absen/rfid/enroll', { siswa_id: this.enrollTarget.id, rfid_uid: uid, replace: false });
        this.enrollStatus = { ok: true, message: 'Berhasil! Kartu terdaftar untuk ' + this.enrollTarget.nama };
        /* Hapus dari cache agar tidak muncul lagi di pencarian berikutnya */
        var enrolledId = this.enrollTarget.id;
        this._allSiswaCache = this._allSiswaCache.filter(function (s) { return s.id !== enrolledId; });
        var self = this;
        setTimeout(function() {
            self.enrollTarget = null;
            self.enrollStatus = null;
        }, 2000);
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


}); // end alpine:init

/* ─── Ikon Lucide (design.md §2.3) — helper render ikon (subset kiosk) ───────
 * Inline SVG, stroke 1.75, currentColor. Sama API dgn admin.
 *   <span x-html="$icon('id-card')"></span>  ·  window.absensiIcon('check-circle-2', 48)
 * Subset = ikon yang dipakai kiosk siswa/guru (design.md §10/§11). */
(function () {
  'use strict';
  var P = {
    'clipboard-check':  '<rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="m9 14 2 2 4-4"/>',
    'id-card':          '<path d="M16 10h2"/><path d="M16 14h2"/><path d="M6.17 15a3 3 0 0 1 5.66 0"/><circle cx="9" cy="11" r="2"/><rect width="20" height="14" x="2" y="5" rx="2"/>',
    'camera':           '<path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/>',
    'map-pin':          '<path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/>',
    'rotate-ccw':       '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>',
    'refresh-cw':       '<path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/>',
    'send':             '<path d="M14.536 21.686a.5.5 0 0 0 .937-.024l6.5-19a.496.496 0 0 0-.635-.635l-19 6.5a.5.5 0 0 0-.024.937l7.93 3.18a2 2 0 0 1 1.112 1.11z"/><path d="m21.854 2.147-10.94 10.939"/>',
    'check':            '<path d="M20 6 9 17l-5-5"/>',
    'check-circle-2':   '<path d="M21.801 10A10 10 0 1 1 17 3.335"/><path d="m9 11 3 3L22 4"/>',
    'x-circle':         '<circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/>',
    'alert-triangle':   '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
    'alert-circle':     '<circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/>',
    'info':             '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
    'clock':            '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
    'credit-card':      '<rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/>',
    'volume-2':         '<path d="M11 4.702a.705.705 0 0 0-1.203-.498L6.413 7.587A1.4 1.4 0 0 1 5.416 8H3a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h2.416a1.4 1.4 0 0 1 .997.413l3.383 3.384A.705.705 0 0 0 11 19.298z"/><path d="M16 9a5 5 0 0 1 0 6"/><path d="M19.364 18.364a9 9 0 0 0 0-12.728"/>',
    'volume-x':         '<path d="M11 4.702a.705.705 0 0 0-1.203-.498L6.413 7.587A1.4 1.4 0 0 1 5.416 8H3a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h2.416a1.4 1.4 0 0 1 .997.413l3.383 3.384A.705.705 0 0 0 11 19.298z"/><line x1="22" x2="16" y1="9" y2="15"/><line x1="16" x2="22" y1="9" y2="15"/>',
    'chevron-left':     '<path d="m15 18-6-6 6-6"/>',
    'chevron-right':    '<path d="m9 18 6-6-6-6"/>',
    'x':                '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
    'log-in':           '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" x2="3" y1="12" y2="12"/>',
    'help':             '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/>'
  };
  function icon(name, size) {
    var inner = P[name];
    if (!inner) { if (window.console) console.warn('[absensi] ikon tak dikenal:', name); inner = P.help; }
    var s = size || 20;
    return '<svg class="icon icon--' + name + '" width="' + s + '" height="' + s +
      '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" ' +
      'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' + inner + '</svg>';
  }
  window.absensiIcon   = icon;
  window.ABSENSI_ICONS = P;
  document.addEventListener('alpine:init', function () {
    if (window.Alpine && Alpine.magic) Alpine.magic('icon', function () { return icon; });
  });
})();

/* ─── Helper fetch: error parser + toast (kiosk) ─────────────────────────────
 * Base REST + nonce sudah di window.api (di atas). Ini melengkapi parser error
 * & toast reusable. Scope kiosk = .absensi-kiosk. (API identik dgn admin.) */
(function () {
  'use strict';
  var SCOPE = '.absensi-kiosk';

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
    if (!root) return null;
    var stack = root.querySelector('.toast-stack');
    if (!stack) { stack = document.createElement('div'); stack.className = 'toast-stack'; root.appendChild(stack); }
    return stack;
  }

  function toast(message, type, timeout) {
    type    = type || 'info';
    timeout = timeout == null ? 4000 : timeout;
    var stack = stackEl();
    if (!stack) { if (window.console) console.warn('[absensi] toast tanpa wrapper ' + SCOPE + ':', message); return; }
    var el = document.createElement('div');
    el.className = 'toast toast--' + type;
    var ico = window.absensiIcon ? window.absensiIcon(TYPE_ICON[type] || 'info', 18) : '';
    var body = document.createElement('span');
    body.className = 'toast__body';
    body.textContent = message;
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

/* ─── Komponen kiosk Siswa (pivot v2: absen by nomor_induk + selfie + GPS) ────
 * Dibangun bertahap per item TODO-FE. Kini: input nomor induk + Ambil GPS.
 * Berikutnya: indikator akurasi, kamera selfie, toggle sesi, submit, hasil, cek status.
 * Config: AbsensiConfig (restUrl, nonce, akurasiMax). Endpoint: POST /absen/selfie. */
document.addEventListener('alpine:init', function () {
  Alpine.data('kioskSiswa', function () { return {
    nomorInduk: '',
    sesi:       'masuk',       // WAJIB terpilih: masuk (default) | pulang
    gps:        null,          // { lat, lng, accuracy }
    gpsStatus:  'waiting',     // waiting | ok | weak | error
    gpsError:   null,
    submitting: false,
    result:     null,          // { ok, sesi, status, jarak, message, jam } | { ok:false, code, httpStatus, message }
    // Widget "Cek Status Hari Ini" (GET /absen/status, terpisah dari alur absen)
    statusNomor:   '',
    statusLoading: false,
    statusResult:  null,       // { sudah_absen, nama, tanggal, rekap } | null
    statusError:   null,
    isHttps:    location.protocol === 'https:' || location.hostname === 'localhost',

    init: function () { this.startGps(); },

    get gpsAccuracyLabel() { return this.gps ? '±' + Math.round(this.gps.accuracy) + ' m' : '—'; },
    /* Ambang akurasi maksimal (m) dari admin; > ini = sinyal lemah (warning). */
    get akurasiMax() { return parseInt((window.AbsensiConfig || {}).akurasiMax || '100', 10) || 100; },
    /* Bisa submit bila nomor induk terisi + GPS dapat lokasi + FOTO SELFIE diambil (wajib) + tak sedang kirim. */
    get canSubmit() { return !this.submitting && !!this.gps && !!this.photoBlob && this.nomorInduk.trim().length > 0; },

    /* ── Kartu hasil (warna peta status design.md §10) ── */
    get resultClass() {
      if (!this.result) return '';
      if (!this.result.ok) return 'kiosk-result--danger';                 // merah
      if (this.result.sesi === 'pulang') return 'kiosk-result--info';     // cyan pulang
      return this.result.status === 'telat' ? 'kiosk-result--warning'     // kuning telat
                                            : 'kiosk-result--success';    // hijau hadir
    },
    get resultIcon() {
      if (!this.result) return 'info';
      if (!this.result.ok) return 'x-circle';
      return this.result.status === 'telat' ? 'alert-triangle' : 'check-circle-2';
    },
    get resultTitle() {
      if (!this.result) return '';
      if (!this.result.ok) {
        return (this.result.code === 'belum_waktu_masuk' || this.result.code === 'belum_waktu_pulang')
          ? 'Belum Waktunya Absen' : 'Absen Ditolak';
      }
      if (this.result.sesi === 'pulang') return 'Absen Pulang Berhasil';
      return this.result.status === 'telat' ? 'Anda Terlambat' : 'Absen Berhasil';
    },

    /* One-shot getCurrentPosition; bisa diulang lewat tombol "Coba Lagi". */
    startGps: function () {
      var self = this;
      this.gpsStatus = 'waiting';
      this.gpsError  = null;
      if (!navigator.geolocation) {
        this.gpsStatus = 'error';
        this.gpsError  = 'Browser tidak mendukung GPS.';
        return;
      }
      navigator.geolocation.getCurrentPosition(
        function (pos) {
          self.gps       = { lat: pos.coords.latitude, lng: pos.coords.longitude, accuracy: pos.coords.accuracy };
          // Akurasi di atas ambang admin → 'weak' (warning), tetap dianggap dapat lokasi.
          self.gpsStatus = pos.coords.accuracy <= self.akurasiMax ? 'ok' : 'weak';
          self.gpsError  = null;
        },
        function (err) {
          self.gpsStatus = 'error';
          self.gpsError  = err.code === 1
            ? 'Izin lokasi ditolak. Aktifkan lokasi di pengaturan browser.'
            : 'GPS tidak tersedia: ' + err.message;
        },
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 10000 }
      );
    },

    /* ── Kamera selfie (opsional) — getUserMedia → capture base64 → preview ── */
    cam:       'off',          // off | live | preview
    camDenied: false,          // izin kamera ditolak (foto opsional → absen tetap bisa)
    stream:    null,
    photoBlob: null,
    photoUrl:  null,

    startCamera: function () {
      if (!this.isHttps) {
        if (window.absensiToast) window.absensiToast('Kamera butuh koneksi aman (HTTPS).', 'warning');
        return;
      }
      var self = this;
      this.camDenied = false;
      navigator.mediaDevices.getUserMedia({
        video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } },
        audio: false
      }).then(function (stream) {
        self.stream = stream;
        self.cam    = 'live';
      }).catch(function (err) {
        // Izin ditolak → note inline (foto OPSIONAL, absen tetap jalan tanpa foto).
        if (err && err.name === 'NotAllowedError') {
          self.camDenied = true;
        } else if (window.absensiToast) {
          window.absensiToast('Kamera tidak dapat diakses. Foto boleh dilewati.', 'warning');
        }
      });
    },

    stopCamera: function () {
      if (this.stream) {
        this.stream.getTracks().forEach(function (t) { t.stop(); });
        this.stream = null;
      }
    },

    capturePhoto: function () {
      var video = this.$refs.video, canvas = this.$refs.canvas;
      if (!video || !canvas) return;
      var MAX = 1280, w = video.videoWidth, h = video.videoHeight;
      if (!w || !h) return;
      if (w > MAX) { h = Math.round(h * MAX / w); w = MAX; }
      canvas.width = w; canvas.height = h;
      canvas.getContext('2d').drawImage(video, 0, 0, w, h);
      var self = this;
      canvas.toBlob(function (blob) {
        if (self.photoUrl) URL.revokeObjectURL(self.photoUrl);
        self.photoBlob = blob;
        self.photoUrl  = URL.createObjectURL(blob);
        self.stopCamera();
        self.cam = 'preview';
      }, 'image/jpeg', 0.7);
    },

    retakePhoto: function () {
      if (this.photoUrl) { URL.revokeObjectURL(this.photoUrl); this.photoUrl = null; }
      this.photoBlob = null;
      this.startCamera();
    },

    clearPhoto: function () {
      this.stopCamera();
      if (this.photoUrl) { URL.revokeObjectURL(this.photoUrl); this.photoUrl = null; }
      this.photoBlob = null;
      this.cam = 'off';
    },

    /* "Absen Lagi" — bersihkan hasil + foto + input untuk orang/absen berikutnya (GPS tetap). */
    reset: function () {
      this.result = null;
      this.clearPhoto();
      this.nomorInduk = '';
      this.sesi = 'masuk';
    },

    /* ── Widget Cek Status Hari Ini (GET /absen/status by nomor_induk) ── */
    checkStatus: async function () {
      var n = this.statusNomor.trim();
      if (!n) return;
      this.statusLoading = true;
      this.statusError   = null;
      this.statusResult  = null;
      try {
        var data = await window.api.get('absen/status?nomor_induk=' + encodeURIComponent(n));
        this.statusResult = data;   // { sudah_absen, nama, tanggal, rekap }
      } catch (err) {
        this.statusError = window.absensiApiError(err).message;   // 404/422 dsb
      } finally {
        this.statusLoading = false;
      }
    },

    /* 'YYYY-MM-DD HH:MM:SS' → 'HH:MM' (null-safe). */
    jamHM: function (w) { return w ? String(w).slice(11, 16) : '—'; },

    /* Submit ke POST /absen/selfie. Tangani semua state (201/200/4xx/5xx).
     * Sukses & error disimpan di `result` (kartu hasil dirender item "Area Hasil"). */
    submit: async function () {
      if (!this.canSubmit) return;
      if (!navigator.onLine) {
        this.result = { ok: false, code: 'offline', httpStatus: 0, message: 'Tidak ada koneksi internet. Coba lagi.' };
        return;
      }
      this.submitting = true;
      this.result     = null;
      try {
        var body = {
          nomor_induk: this.nomorInduk.trim(),
          lat:         this.gps.lat,
          lng:         this.gps.lng
        };
        if (this.gps.accuracy) body.accuracy = this.gps.accuracy;   // opsional
        if (this.sesi)         body.sesi     = this.sesi;           // '' = auto server
        if (this.photoBlob)    body.foto     = await this._blobToBase64(this.photoBlob);  // opsional (data-URL, BE strip prefix)

        var data = await window.api.post('absen/selfie', body);     // 201 masuk / 200 pulang
        this.result = {
          ok:      true,
          sesi:    data.sesi,                 // masuk | pulang
          status:  data.status || null,       // hadir | telat (hanya sesi masuk)
          jarak:   typeof data.jarak === 'number' ? data.jarak : null,
          message: data.message || '',
          jam:     new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
        };
      } catch (err) {
        // absensiApiError memetakan {code, message, data.status}; pesan server sudah Indonesia.
        var e = window.absensiApiError(err);
        this.result = { ok: false, code: e.code, httpStatus: e.status, message: e.message };
      } finally {
        this.submitting = false;
      }
    },

    _blobToBase64: function (blob) {
      return new Promise(function (resolve, reject) {
        var r = new FileReader();
        r.onload  = function () { resolve(r.result); };  // data:image/jpeg;base64,…
        r.onerror = function () { reject(r.error); };
        r.readAsDataURL(blob);
      });
    },

    destroy: function () {
      this.stopCamera();
      if (this.photoUrl) URL.revokeObjectURL(this.photoUrl);
    }
  }; });

  /* ─── Komponen kiosk Guru (RFID) — pivot v2 (design.md §11) ──────────────────
   * Dibangun bertahap per item TODO-FE. Kini: jam besar real-time + area feedback
   * besar + status idle. Berikutnya: autofokus input UID, submit POST /absen/rfid,
   * feedback nama+status, tangani 404/429/409, auto reset ke idle.
   * Config: AbsensiConfig.rfidDebounce. Endpoint: POST /absen/rfid. */
  Alpine.data('kioskGuru', function () { return {
    jam: '',                   // 'HH:MM:SS' — jam dinding berjalan
    sesi: 'masuk',             // sesi terpilih (toggle Masuk/Pulang) — dikirim ke server
    uid: '',                   // nilai field UID (x-model) — scanner HID isi / ketik manual
    fb:  null,                 // feedback tap: { ok, tone, nama, statusLabel, message } | null (idle)
    needLogin: false,          // true bila sesi WP habis (401/403) → tampil tombol login ulang
    _clockTimer: null,
    _fbTimer: null,            // timer auto-reset feedback → idle
    _busy: false,              // cegah request tumpang tindih saat tap beruntun
    // Beep opsional (design.md §11): bantu operator tanpa lihat layar. Bisa dibisukan.
    muted: (function () { try { return localStorage.getItem('absensiGuruMuted') === '1'; } catch (e) { return false; } })(),
    _audioCtx: null,

    init: function () {
      this.tick();
      this._clockTimer = setInterval(this.tick.bind(this), 1000);
      // Autofokus permanen ke input UID tersembunyi (scanner HID "mengetik" ke sini).
      this.$nextTick(function () { this.focusInput(); }.bind(this));
    },

    /* Perbarui jam dinding (tabular-nums di CSS). */
    tick: function () {
      this.jam = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    },

    /* Fokuskan input UID (dipanggil saat init, klik di mana pun, & setelah tiap tap).
     * setTimeout(0) agar aman dipanggil dari handler @blur (hindari loop sinkron). */
    focusInput: function () {
      var el = this.$refs.rfid;
      if (el) setTimeout(function () { el.focus(); }, 0);
    },

    /* Blur input: refocus HANYA bila fokus pindah ke dalam kiosk (jaga target
     * scanner HID). Bila pindah ke LUAR (mis. link navbar) → JANGAN rebut fokus,
     * biar user bisa navigasi ke halaman lain. */
    onBlur: function (e) {
      var to = e && e.relatedTarget;
      if (to && this.$root && ! this.$root.contains( to )) return;
      this.focusInput();
    },

    /* Scanner tekan Enter setelah "mengetik" UID → ambil nilai, bersihkan, refokus,
     * lalu proses tap (loop tap berikutnya siap). Submit ke server = item Kirim. */
    onEnter: function () {
      var uid = (this.uid || '').trim();
      this.uid = '';              // clear (x-model kosongkan field) → siap tap berikut
      this.focusInput();          // refocus (loop tap)
      if (!uid) return;
      this.submit(uid);
    },

    /* Kirim tap ke POST /absen/rfid { rfid_uid }. Tangani semua state (design.md §11):
     *   201 { action:'masuk', status, siswa }  → hijau/kuning "MASUK — HADIR/TELAT"
     *   200 { action:'keluar', siswa }         → info "KELUAR"
     *   404 uid_tidak_terdaftar (merah) · 429 double_tap (kuning) · 409 sudah_absen (info)
     * Hasil disimpan di this.fb (dirender panggung feedback). Auto-reset ke idle = item berikutnya. */
    submit: async function (uid) {
      if (this._busy) return;               // abaikan tap yang tumpang tindih
      if (this.needLogin) return;           // sesi habis → tap diabaikan, arahkan ke tombol login
      this._busy = true;
      try {
        var data = await window.api.post('absen/rfid', { rfid_uid: uid, sesi: this.sesi });   // 201 masuk / 200 keluar
        if (data.action === 'keluar') {
          this.fb = { ok: true, tone: 'info', nama: data.siswa || '',
                      statusLabel: 'KELUAR', message: data.message || '' };
        } else {
          var st = data.status || 'hadir';
          this.fb = { ok: true, tone: ( st === 'telat' ? 'warning' : 'success' ), nama: data.siswa || '',
                      statusLabel: 'MASUK — ' + st.toUpperCase(), message: data.message || '' };
        }
      } catch (err) {
        // absensiApiError → {code, status, message} (pesan server sudah Indonesia).
        var e = window.absensiApiError(err);
        // Sesi WP habis / nonce kedaluwarsa (endpoint RFID kini login-gated). request() sudah
        // coba refresh nonce sekali; kalau tetap gagal auth → guru harus login ulang. Deteksi
        // via KODE auth WP (bukan status 403 mentah — 403 juga dipakai gate SSL `butuh_https`
        // & gate jam `belum_waktu_*`, yang BUKAN masalah sesi). Tampilkan panggung khusus +
        // tombol login. Tap diabaikan sampai login.
        var authFail = ( e.status === 401
                      || e.code === 'rest_forbidden'
                      || e.code === 'rest_cookie_invalid_nonce' );
        if (authFail) {
          this.needLogin = true;
          this.fb = { ok: false, tone: 'danger', nama: '',
                      statusLabel: 'Sesi Habis',
                      message: 'Sesi login berakhir. Silakan login ulang untuk melanjutkan.' };
          return;   // finally tetap jalan; JANGAN auto-reset (biar tombol login menetap)
        }
        // Gagal jaringan (status 0 / offline) → pesan "coba tap lagi" (design.md §11 Error State).
        var netFail = ( e.status === 0 );
        this.fb = { ok: false, tone: this._errTone(e.code, e.status), nama: '',
                    statusLabel: this._errLabel(e.code, e.status),
                    message: netFail ? 'Gagal terhubung. Coba tap lagi.' : e.message };
      } finally {
        this._busy = false;
        this.beep( !! ( this.fb && this.fb.ok ) );   // beep sukses/gagal (opsional)
        // Sesi habis → biarkan panggung + tombol login menetap (jangan auto-reset ke idle).
        if ( ! this.needLogin ) this._scheduleReset();   // tahan ~2.5 dtk → idle "Siap scan…"
      }
    },

    /* URL login WP + redirect balik ke kiosk ini setelah sukses (BE auth_redirect
     * juga arahkan guru ke /absensi/guru; ini fallback eksplisit dari tombol). */
    get loginUrl() {
      return '/wp-login.php?redirect_to=' + encodeURIComponent( window.location.href );
    },

    /* Bisukan/aktifkan beep (persist localStorage). */
    toggleMute: function () {
      this.muted = ! this.muted;
      try { localStorage.setItem('absensiGuruMuted', this.muted ? '1' : '0'); } catch (e) {}
    },

    /* Beep pendek via WebAudio (tanpa file/aset). sukses=nada tinggi, gagal=nada rendah.
     * AudioContext dibuat lazy & di-resume (gesture tap sudah membuka izin audio). */
    beep: function (ok) {
      if (this.muted) return;
      try {
        var AC = window.AudioContext || window.webkitAudioContext;
        if (!AC) return;
        if (!this._audioCtx) this._audioCtx = new AC();
        var ctx = this._audioCtx;
        if (ctx.state === 'suspended') ctx.resume();
        var osc = ctx.createOscillator();
        var gain = ctx.createGain();
        osc.connect(gain); gain.connect(ctx.destination);
        var t = ctx.currentTime, dur = ok ? 0.18 : 0.32;
        osc.type = ok ? 'sine' : 'square';
        osc.frequency.value = ok ? 880 : 220;
        gain.gain.setValueAtTime(0.0001, t);
        gain.gain.exponentialRampToValueAtTime(0.15, t + 0.01);
        gain.gain.exponentialRampToValueAtTime(0.0001, t + dur);
        osc.start(t);
        osc.stop(t + dur + 0.02);
      } catch (e) { /* audio opsional → abaikan gagal */ }
    },

    /* Auto-reset feedback ke idle setelah jeda (design.md §11: tahan ~2–3 dtk).
     * Tap baru menjadwal ulang (clear timer lama) supaya feedback baru tak keburu hilang. */
    _scheduleReset: function () {
      clearTimeout(this._fbTimer);
      var self = this;
      this._fbTimer = setTimeout(function () { self.fb = null; }, 2500);
    },

    /* Warna error (design.md §11): double_tap kuning, sudah_absen* info, belum_absen_masuk kuning, selain itu merah. */
    _errTone: function (code, status) {
      if (code === 'double_tap'        || status === 429) return 'warning';
      if (code === 'belum_absen_masuk')                    return 'warning';
      if (code === 'sudah_absen' || code === 'sudah_absen_keluar' || status === 409) return 'info';
      return 'danger';
    },
    /* Label badge besar untuk error. */
    _errLabel: function (code, status) {
      if (code === 'uid_tidak_terdaftar' || status === 404) return 'Kartu Tidak Terdaftar';
      if (code === 'double_tap'          || status === 429) return 'Tunggu Sebentar';
      if (code === 'belum_absen_masuk')                     return 'Belum Absen Masuk';
      if (code === 'sudah_absen_keluar')                    return 'Sudah Pulang';
      if (code === 'sudah_absen'         || status === 409) return 'Sudah Absen';
      if (code === 'belum_waktu_masuk'   || code === 'belum_waktu_pulang') return 'Belum Waktunya';
      return 'Gagal';
    },

    /* Inisial nama untuk avatar feedback; error → '!'. */
    get fbInitial() {
      if (!this.fb) return '';
      if (!this.fb.ok) return '!';
      return this.fb.nama ? this.fb.nama.trim().charAt(0).toUpperCase() : '';
    },
    /* Warna panggung feedback dari tone (peta status design.md §11). */
    get fbClass() { return this.fb ? 'kioskg-feedback--' + this.fb.tone : ''; },
    /* Warna badge besar dari tone (kontrak visual badge §2.1). */
    get fbBadgeClass() {
      var m = { success: 'badge--hadir', warning: 'badge--telat', info: 'badge--pulang', danger: 'badge--alpha' };
      return this.fb ? ( m[this.fb.tone] || 'badge--alpha' ) : '';
    },

    destroy: function () {
      if (this._clockTimer) clearInterval(this._clockTimer);
      clearTimeout(this._fbTimer);
      if (this._audioCtx) { try { this._audioCtx.close(); } catch (e) {} }
    }
  }; });
});
