/**
 * Absensi Sekolah – Frontend Siswa
 * Selfie via getUserMedia + Geolocation API → POST ke WP REST API
 */
(function () {
  "use strict";

  const form      = document.getElementById("absensi-selfie-form");
  if (!form) return;

  const video     = document.getElementById("absensi-video");
  const canvas    = document.getElementById("absensi-canvas");
  const btnKamera = document.getElementById("btn-buka-kamera");
  const btnFoto   = document.getElementById("btn-ambil-foto");
  const btnSubmit = document.getElementById("btn-submit-absen");
  const preview   = document.getElementById("absensi-preview");
  const statusEl  = document.getElementById("absensi-status");
  const gpsEl     = document.getElementById("absensi-gps");

  let stream     = null;
  let fotoBase64 = null;
  let currentLat = null;
  let currentLng = null;

  // ─── 1. Ambil Koordinat GPS ───────────────────────────────────────────────
  if (!navigator.geolocation) {
    setStatus("error", "Browser tidak mendukung Geolocation.");
  } else {
    navigator.geolocation.watchPosition(
      (pos) => {
        currentLat = pos.coords.latitude;
        currentLng = pos.coords.longitude;
        const acc  = Math.round(pos.coords.accuracy);
        if (gpsEl) gpsEl.textContent = `📍 GPS: ${currentLat.toFixed(6)}, ${currentLng.toFixed(6)} (±${acc}m)`;
      },
      (err) => {
        if (gpsEl) gpsEl.textContent = `⚠️ GPS gagal: ${err.message}`;
      },
      { enableHighAccuracy: true, timeout: 10000 }
    );
  }

  // ─── 2. Buka Kamera ───────────────────────────────────────────────────────
  if (btnKamera) {
    btnKamera.addEventListener("click", async () => {
      try {
        stream = await navigator.mediaDevices.getUserMedia({
          video: { facingMode: "user", width: 640, height: 480 },
          audio: false,
        });
        video.srcObject = stream;
        video.style.display = "block";
        btnFoto.disabled = false;
        btnKamera.textContent = "🔄 Ganti Kamera";
        setStatus("info", "Kamera aktif. Posisikan wajah Anda lalu klik 'Ambil Foto'.");
      } catch (err) {
        setStatus("error", `Gagal mengakses kamera: ${err.message}`);
      }
    });
  }

  // ─── 3. Ambil Foto (capture canvas) ──────────────────────────────────────
  if (btnFoto) {
    btnFoto.addEventListener("click", () => {
      if (!stream) return;
      const ctx = canvas.getContext("2d");
      canvas.width  = video.videoWidth  || 640;
      canvas.height = video.videoHeight || 480;
      ctx.drawImage(video, 0, 0);
      fotoBase64 = canvas.toDataURL("image/jpeg", 0.85);

      // Tampilkan preview
      preview.src = fotoBase64;
      preview.style.display = "block";
      btnSubmit.disabled = false;
      setStatus("info", "Foto berhasil diambil. Klik 'Kirim Absen' untuk melanjutkan.");
    });
  }

  // ─── 4. Submit Absensi ────────────────────────────────────────────────────
  if (btnSubmit) {
    btnSubmit.addEventListener("click", async () => {
      if (!fotoBase64) {
        setStatus("error", "Silakan ambil foto selfie terlebih dahulu.");
        return;
      }
      if (!currentLat || !currentLng) {
        setStatus("error", "Lokasi GPS belum terdeteksi. Harap tunggu beberapa detik.");
        return;
      }

      btnSubmit.disabled = true;
      btnSubmit.textContent = "⏳ Mengirim…";
      setStatus("info", "Mengirim data absensi…");

      try {
        const res = await fetch(AbsensiConfig.restUrl + "absen/selfie", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-WP-Nonce": AbsensiConfig.nonce,
          },
          body: JSON.stringify({
            lat:  currentLat,
            lng:  currentLng,
            foto: fotoBase64,
          }),
        });

        const data = await res.json();

        if (res.ok && data.success) {
          stopKamera();
          setStatus(
            data.status === "telat" ? "warning" : "success",
            `✅ ${data.message} (jarak: ${data.jarak}m dari sekolah)`
          );
          btnSubmit.style.display = "none";
          btnFoto.style.display   = "none";
          btnKamera.style.display = "none";
        } else {
          setStatus("error", `❌ ${data.message ?? "Terjadi kesalahan."}`);
          btnSubmit.disabled  = false;
          btnSubmit.textContent = "📤 Kirim Absen";
        }
      } catch (err) {
        setStatus("error", `Gagal terhubung ke server: ${err.message}`);
        btnSubmit.disabled  = false;
        btnSubmit.textContent = "📤 Kirim Absen";
      }
    });
  }

  // ─── Helper ───────────────────────────────────────────────────────────────
  function stopKamera() {
    if (stream) {
      stream.getTracks().forEach((t) => t.stop());
      stream = null;
    }
    video.style.display = "none";
  }

  function setStatus(type, msg) {
    if (!statusEl) return;
    const colors = {
      info:    "#e7f3fe",
      success: "#edfaef",
      warning: "#fff8e1",
      error:   "#fce4e4",
    };
    statusEl.style.background = colors[type] ?? "#fff";
    statusEl.textContent = msg;
    statusEl.style.display = "block";
  }
})();
