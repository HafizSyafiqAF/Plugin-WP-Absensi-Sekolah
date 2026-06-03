<?php
defined( 'ABSPATH' ) || exit;
?>
<div class="wrap" id="absensi-rfid-app">
    <h1>📡 Absensi RFID – Mode Guru</h1>
    <p>Pastikan scanner RFID USB sudah terpasang dan field di bawah aktif. Tap kartu siswa untuk mencatat absensi.</p>

    <div style="display:flex; gap:24px; flex-wrap:wrap; margin-top:20px;">

        <!-- Panel Scanner -->
        <div style="flex:1; min-width:300px; background:#fff; border:1px solid #ccd0d4; border-radius:6px; padding:24px;">
            <h2 style="margin-top:0">Scan Kartu</h2>

            <div id="rfid-status" style="padding:12px 16px; border-radius:4px; background:#f0f6fc; margin-bottom:16px; font-size:14px;">
                ⏳ Menunggu scan kartu…
            </div>

            <label style="display:block; font-weight:600; margin-bottom:6px;">
                Field Scan (auto-focus)
            </label>
            <input
                type="text"
                id="rfid-input"
                autocomplete="off"
                style="width:100%; padding:10px; font-size:16px; border:2px solid #2271b1; border-radius:4px;"
                placeholder="Tempelkan kartu ke scanner…"
                autofocus
            >
            <p style="font-size:12px; color:#666; margin-top:6px;">
                Field ini harus selalu aktif. Klik field jika scanner tidak terbaca.
            </p>

            <button id="btn-refocus" class="button" style="margin-top:8px;">
                🔄 Refocus Field
            </button>
        </div>

        <!-- Log Aktivitas -->
        <div style="flex:2; min-width:300px; background:#fff; border:1px solid #ccd0d4; border-radius:6px; padding:24px;">
            <h2 style="margin-top:0">Log Absensi Hari Ini</h2>
            <div id="rfid-log" style="font-family:monospace; font-size:13px; max-height:400px; overflow-y:auto;">
                <p style="color:#888;">Belum ada aktivitas.</p>
            </div>
        </div>

    </div>
</div>

<script>
(function () {
    const input    = document.getElementById('rfid-input');
    const status   = document.getElementById('rfid-status');
    const log      = document.getElementById('rfid-log');
    const refocus  = document.getElementById('btn-refocus');
    const restUrl  = AbsensiAdmin.restUrl;
    const nonce    = AbsensiAdmin.nonce;

    // Auto-refocus setiap 2 detik jika hilang fokus
    setInterval(() => input.focus(), 2000);
    refocus.addEventListener('click', () => input.focus());

    input.addEventListener('keydown', async (e) => {
        // RFID HID mengirim Enter di akhir UID
        if (e.key !== 'Enter') return;
        e.preventDefault();

        const uid = input.value.trim();
        input.value = '';

        if (!uid) return;

        setStatus('info', `⏳ Memproses UID: ${uid}…`);

        try {
            const res = await fetch(restUrl + 'absen/rfid', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce,
                },
                body: JSON.stringify({ rfid_uid: uid }),
            });

            const data = await res.json();

            if (res.ok && data.success) {
                const icon   = data.action === 'masuk' ? '✅' : '🚪';
                const warna  = data.status === 'telat'  ? '#f0c000' : '#00a32a';
                setStatus('ok', `${icon} ${data.message}`, warna);
                addLog(uid, data.siswa, data.action, data.status ?? '—');
            } else {
                setStatus('err', `⚠️ ${data.message ?? 'Error tidak diketahui.'}`);
            }
        } catch (err) {
            setStatus('err', `❌ Gagal koneksi ke server: ${err.message}`);
        }
    });

    function setStatus(type, msg, bg) {
        const colors = { info: '#f0f6fc', ok: '#edfaef', err: '#fcf0f1' };
        status.style.background = bg ?? colors[type] ?? '#f0f6fc';
        status.textContent = msg;
    }

    function addLog(uid, nama, action, statusAbsen) {
        const now  = new Date().toLocaleTimeString('id-ID');
        const item = document.createElement('div');
        item.style.cssText = 'padding:6px 0; border-bottom:1px solid #eee;';
        item.innerHTML = `<strong>${now}</strong> — <b>${nama}</b> (${uid}) &nbsp;
            <span style="color:${action === 'masuk' ? '#2271b1' : '#555'}">
                ${action === 'masuk' ? '▶ Masuk' : '◀ Keluar'}
            </span>
            <span style="color:${statusAbsen === 'telat' ? '#d63638' : '#00a32a'}">
                [${statusAbsen}]
            </span>`;

        // Bersihkan pesan default
        if (log.querySelector('p')) log.innerHTML = '';
        log.prepend(item);
    }
})();
</script>
