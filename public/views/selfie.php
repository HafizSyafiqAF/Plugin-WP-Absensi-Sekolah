<?php
defined( 'ABSPATH' ) || exit;
?>
<div class="absensi-wrapper" style="max-width:480px; margin:0 auto; font-family:sans-serif;">
    <h2>📸 Absensi Mandiri</h2>

    <div id="absensi-status"
         style="display:none; padding:12px 16px; border-radius:6px; margin-bottom:16px; font-size:14px;">
    </div>

    <div id="absensi-gps" style="font-size:13px; color:#555; margin-bottom:12px;">
        📍 Mendeteksi lokasi GPS…
    </div>

    <!-- Video preview kamera -->
    <video id="absensi-video" autoplay playsinline muted
           style="display:none; width:100%; border-radius:8px; background:#000; margin-bottom:12px;">
    </video>

    <!-- Canvas tersembunyi untuk capture -->
    <canvas id="absensi-canvas" style="display:none;"></canvas>

    <!-- Preview foto yang diambil -->
    <img id="absensi-preview" src="" alt="Preview Selfie"
         style="display:none; width:100%; border-radius:8px; margin-bottom:12px; border:2px solid #2271b1;">

    <form id="absensi-selfie-form" style="display:flex; flex-direction:column; gap:10px;">
        <button type="button" id="btn-buka-kamera"
                style="padding:12px; background:#2271b1; color:#fff; border:none; border-radius:6px; font-size:15px; cursor:pointer;">
            📷 Buka Kamera
        </button>
        <button type="button" id="btn-ambil-foto" disabled
                style="padding:12px; background:#00a32a; color:#fff; border:none; border-radius:6px; font-size:15px; cursor:pointer;">
            📸 Ambil Foto
        </button>
        <button type="button" id="btn-submit-absen" disabled
                style="padding:12px; background:#d63638; color:#fff; border:none; border-radius:6px; font-size:15px; cursor:pointer;">
            📤 Kirim Absen
        </button>
    </form>

    <p style="font-size:11px; color:#888; margin-top:16px; text-align:center;">
        ⚠️ Pastikan browser memiliki izin kamera dan lokasi. Absensi hanya diterima dalam radius
        <strong><?php echo esc_html( get_option( 'absensi_radius', 100 ) ); ?> meter</strong> dari sekolah.
    </p>
</div>
