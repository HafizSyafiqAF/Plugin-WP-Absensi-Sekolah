# Catatan BE → FE (Gabungan: Kontrak Terbaru + Status)

> Satu file biar tak bingung. Isi: **(A)** perubahan/kontrak API terbaru, **(B)** status permintaan FE→BE, **(C)** yang BE tunggu dari FE.
> Base URL: `/wp-json/absensi/v1/` · Auth: cookie + `X-WP-Nonce` (atau Application Password). Update: 2026-06-08.
> **Stack final:** Alpine.js + Tailwind CSS **via CDN**, TANPA Vite/build.

---

# A. Perubahan / Kontrak API terbaru

## A1. Bentuk error SERAGAM (semua endpoint)
```json
{ "code": "kode_mesin", "message": "Pesan untuk user.", "data": { "status": 422 } }
```
- `code` = string mesin (switch FE) · `message` = teks ID (tampilkan) · `data.status` = HTTP status (**baru**, aditif).
- ✅ FE lama yang baca `err.code`/`err.message` **tak terdampak**.

**Khusus `PUT /settings`** (validasi gagal) — `errors` per-field di **dua** tempat:
```json
{ "code":"validasi_gagal", "message":"...", "errors":{"absensi_lat":"..."}, "data":{"status":422,"errors":{"absensi_lat":"..."}} }
```
FE boleh baca `errors` (top-level) **atau** `data.errors`.

## A2. Notifikasi WA otomatis (server-side, tanpa endpoint baru)
Anak absen (masuk/pulang, selfie/RFID) → wali otomatis dapat notif WA.
- **Tak ada perubahan request/response** absen — FE tak perlu ubah apa pun.
- Admin isi (di Settings): `absensi_wa_gateway` + `absensi_wa_token` + nomor HP ortu (user meta `billing_phone`/`absensi_no_wa`). Gateway kosong = nonaktif.

## A3. Klarifikasi (koreksi anggapan)
- **Luar radius = `403 diluar_radius`** (BUKAN 422). 422 hanya akurasi GPS jelek / koordinat invalid.
- Export: `GET /wp-json/absensi/v1/laporan/export` — namespace **`absensi/v1`** (bukan `absen/v1` → 404).

## A4. Keputusan stack: Alpine + Tailwind via CDN (NO Vite)
- ⚪ Skema **Vite dibatalkan**. Tak ada `assets/dist/`, `manifest.json`, `npm run build`, `node_modules`. Jangan andalkan path itu.
- BE enqueue file plugin (`public/js/public.js`, `admin/js/admin.js`). Alpine & Tailwind dari CDN.

## A5. Variabel localize SIAP dipakai
**`AbsensiAdmin`** (halaman admin plugin):
```js
{ restUrl, nonce, rfidDebounce,
  settings: { lat,lng,radius,jamMasuk,jamKeluar,telatMenit,akurasiMax,rfidDebounce,retensiHari,waGateway } }
```
**`AbsensiConfig`** (halaman shortcode publik, handle `absensi-public`):
```js
{ restUrl, nonce, rfidDebounce, akurasiMax,
  anakList: [ { siswa_id, nama, nis, kelas_id, nama_kelas }, ... ] }
```
- Angka **typed** (number), bukan string — `parseInt`/`Number` aman.
- `wa_token` **TIDAK** di localize (sensitif) — ambil via `GET /settings`.
- `anakList` **server-derived** (ortu→anak; non-ortu→`[]`). **Jangan** kirim daftar anak dari client (anti-IDOR).

## A6. Koreksi role/capability
- `absensi_admin` dapat **SEMUA 5 cap** absensi (bukan 3). Slug final: `absensi_admin`, `guru`, `absensi_siswa`, `orang_tua` (BUKAN `absensi_guru`/`absensi_wali`).

---

# B. Status permintaan FE→BE (Kontrak-BE §6)

| # | Permintaan | Status | Catatan |
|---|---|---|---|
| 2 | Inject `AbsensiAdmin` | ✅ | Lengkap, teruji 8/8. `wa_token` dikecualikan. |
| 3 | Inject `AbsensiConfig` | ✅ | Handle `absensi-public` + `anakList`. 1 handle cukup utk CDN. Teruji 16/16. |
| 4 | Register shortcode siswa/guru/ortu | 🟡 | Wiring BE selesai (register+gate+include), teruji 11/11. **FE sediakan view markup.** |
| 5 | Seed role & cap | ✅ | Teruji 35/35 + 38 fresh-install. `absensi_admin` semua 5 cap. |
| 1 | Enqueue asset (semula Vite) | 🔧 | Vite batal → sisa: load Alpine+Tailwind CDN (BE atau FE). |

**Gating shortcode (#4):** `[absensi_siswa]`=login · `[absensi_guru]`=cap `absensi_submit_rfid` · `[absensi_ortu]`=cap `absensi_view_child`. Tanpa view → placeholder (bukan error).

**Tak ada perubahan endpoint** (Kontrak-BE §7). Semua endpoint + localize + role/cap sudah siap & teruji.

---

# C. Yang BE TUNGGU dari FE (2 hal)

1. **File view markup** `public/views/siswa.php`, `guru.php`, `ortu.php` (Alpine + Tailwind). Begitu ada → shortcode otomatis render; `AbsensiConfig` siap dipakai di dalamnya.
2. **Keputusan: siapa load Alpine + Tailwind CDN** — BE enqueue di `Plugin.php` (~4 baris), atau FE taruh `<script>` di view? Kabari pilihannya.
