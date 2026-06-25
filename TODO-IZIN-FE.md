# TODO — Fitur Izin/Sakit + Bukti + Konfirmasi Guru (FRONTEND)

> Scope: **frontend saja** (markup view + JS). Endpoint sudah/akan disediakan BE.
> Tak ada perubahan var localize `AbsensiConfig`/`AbsensiAdmin`.

## Pekerjaan FE
- [ ] **Halaman siswa** (`public/views/siswa.php` + `public/js/public.js`) — tombol **"Ajukan Izin/Sakit"** → form: pilih tipe (izin/sakit), alasan (text), upload file bukti (foto **atau PDF**). Kirim file sebagai **base64** ke `POST /absen/izin`. Tampilkan hasil "menunggu konfirmasi".
- [ ] **Absensi guru** (`public/views/guru.php` + `public/js/public.js`) — papan kehadiran per **kelas + tanggal**:
  - Ambil roster: `GET /siswa?kelas_id=`.
  - Ambil status hari ini: `GET /laporan?dari=<today>&sampai=<today>&kelas_id=`.
  - Gabung: siswa **tanpa baris rekap = "belum absen / alpha"**.
- [ ] **Ubah status** — dropdown/pilihan status per siswa (hadir/alpha/izin/sakit) → `POST /absen/status`.
- [ ] **Bukti izin/sakit** — kalau `bukti_status='menunggu'`: tampilkan link **`bukti_url`** (buka surat) + tombol **Setuju/Tolak** → `POST /absen/status` dgn `bukti_status` (setuju/tolak). Setuju → status jadi izin/sakit.

## Kontrak API (dari BE)
### `POST /absen/izin` — siswa ajukan (perlu login)
Request body:
```json
{ "tipe": "izin|sakit", "alasan": "teks", "bukti": "<base64 file>" }
```
Response `201`:
```json
{ "status": "menunggu", "tipe": "izin|sakit", "bukti_url": "https://.../bukti.pdf" }
```
Error: `409 sudah_absen` (siswa sudah absen masuk), `404` (akun belum ke-link ke data siswa), `422` (tipe/bukti tak valid).

### `POST /absen/status` — guru/admin ubah status (perlu role guru/admin)
Request body:
```json
{ "siswa_id": 12, "tanggal": "2026-06-24", "status": "hadir|telat|izin|sakit|alpha", "bukti_status": "setuju|tolak" }
```
(`tanggal` opsional → default hari ini; `bukti_status` opsional → cuma saat konfirmasi izin/sakit.)
Response `200`:
```json
{ "siswa_id": 12, "tanggal": "2026-06-24", "status": "izin", "bukti_status": "setuju" }
```

### Field bukti di `GET /laporan` (untuk papan guru)
Tiap baris kini punya tambahan: `izin_tipe` (izin|sakit|null), `bukti_status` (menunggu|setuju|tolak|null), `bukti_url` (string|null).

## Catatan
- File bukti dikirim **base64** (sama pola seperti selfie). Maks 5MB. Tipe diterima: JPG, PNG, PDF.
- `bukti_url` = URL acak (nama file di-random) — buka langsung untuk lihat surat.
