# KONTRAK API — Backend → Frontend (Absensi Sekolah)

> Ringkasan semua endpoint & kontrak yang sudah jadi di backend. Dipakai FE untuk integrasi.
> **Base URL semua REST:** `/wp-json/absensi/v1/`
> Status per 2026-06-05 — semua endpoint di bawah sudah diimplementasi & lulus tes.

---

## 0. ⚠️ WAJIB DIBACA DULU

### Namespace
Semua REST pakai namespace **`absensi/v1`** — **BUKAN** `absen/v1`.
```
✅ /wp-json/absensi/v1/laporan/export
❌ /wp-json/absen/v1/laporan/export   → 404
```
Catatan: ada *path* yang diawali `/absen/...` (mis. `/absen/selfie`) — itu bagian path, **bukan** namespace. Namespace tetap `absensi/v1`.

### Auth
- **Cookie + nonce:** kirim header `X-WP-Nonce` (ambil dari `AbsensiConfig.nonce` / `AbsensiAdmin.nonce`).
- atau **Application Password** (Basic Auth) untuk tes di luar browser.

### Format angka di localize
`wp_localize_script` meng-cast angka jadi **string** (`"3"`, bukan `3`). FE **wajib `parseInt`/`Number`** saat baca `AbsensiConfig`/`AbsensiAdmin`.

### Format error (umum)
```json
{ "code": "kode_error", "message": "Pesan untuk user." }
```
HTTP status mengikuti jenis error (lihat tiap endpoint). Validasi settings pakai `{ code, message, errors: { field: "..." } }`.

---

## 1. Localize (variabel JS yang di-inject)

### `AbsensiConfig` (halaman publik: siswa/guru/ortu)
```js
{
  restUrl: "https://.../wp-json/absensi/v1/",
  nonce: "xxxx",
  rfidDebounce: "3",      // string → parseInt
  akurasiMax: "100",      // string → parseInt (meter)
  anakList: [             // anak ter-link utk ortu login; [] kalau bukan ortu
    { siswa_id, nama, nis, kelas_id, nama_kelas }
  ]
}
```

### `AbsensiAdmin` (halaman admin)
```js
{
  restUrl, nonce,
  rfidDebounce: "3",
  settings: {             // prefill form setting (wa_token TIDAK disertakan — sensitif)
    lat, lng, radius, jamMasuk, jamKeluar, telatMenit,
    akurasiMax, rfidDebounce, retensiHari, waGateway
  }
}
```
> Field di atas boleh nambah ke depan; tak akan dihapus/rename tanpa kabar.

---

## 2. Absensi (siswa & guru)

### `POST /absen/selfie` — absen mandiri siswa (selfie + GPS)
Cap: login (siswa). Body:
```json
{ "lat": -6.2, "lng": 106.8, "sesi": "masuk", "accuracy": 20, "foto": "data:image/jpeg;base64,..." }
```
- `sesi`: `masuk` | `pulang` | (kosong = auto: belum absen→masuk, sudah masuk→pulang)
- `accuracy`: meter (opsional; `>akurasiMax` → ditolak). `foto`: base64 (opsional, hanya sesi masuk).

Sukses:
- masuk → `201 { success, sesi:"masuk", status:"hadir|telat", jarak, message }`
- pulang → `200 { success, sesi:"pulang", jarak, message }`

Error: `403 butuh_https` · `422 koordinat_invalid` · `503 sekolah_belum_diatur` · `422 akurasi_rendah` · `403 diluar_radius` · `409 sudah_absen` / `sudah_absen_keluar` / `belum_absen_masuk` · `422 foto_*`.

### `POST /absen/rfid` — tap kartu (guru/admin)
Cap: guru/admin. Body `{ "rfid_uid": "A1B2C3" }` (hex).
- tap-1 → `201 { action:"masuk", status, siswa }`
- tap-2 → `200 { action:"keluar", siswa }`
- Error: `429 double_tap` · `404 uid_tidak_terdaftar` · `409 sudah_absen` · `403 butuh_https`.

### `GET /absen/status` — status absen siswa hari ini
Cap: login. → `{ sudah_absen: bool, rekap: {...}|null }`.

### `GET /absen/rfid/resolve?uid=A1B2C3` — "kartu milik siapa" (sebelum enroll)
Cap: guru/admin. → `200 { found, siswa_id, nama, nis, kelas_id, nama_kelas, uid_masked:"••••B2C3" }` · `404 uid_tidak_terdaftar` · `422 uid_kosong`. **Read-only.**

### `POST /absen/rfid/enroll` — daftarkan kartu ke siswa
Cap: `absensi_enroll_rfid`. Body `{ siswa_id, rfid_uid, replace:false }`.
→ `200 { success, siswa, uid_masked, replaced }`
Error: `422 uid_kosong` · `404 siswa_tidak_ditemukan` · `409 kartu_terpakai` (sebut pemilik) · `409 sudah_punya_kartu` (perlu `replace:true`).

---

## 3. Master Data

### Kelas — `GET/POST /kelas`, `GET/PUT/DELETE /kelas/{id}`
Cap: `can_manage` (admin/absensi_admin/guru).
- POST/PUT body: `{ nama_kelas*, tingkat?(1-99), guru_id? }`
- GET list nambah: `jumlah_siswa`, `guru_nama`.
- Error: `422 nama_wajib` · `422 guru_invalid` · `404 kelas_tidak_ada` · `409 kelas_ada_siswa` (tolak hapus kelas berisi siswa) · `422 tak_ada_perubahan`.

### Jadwal — `GET/POST /jadwal`, `GET/PUT/DELETE /jadwal/{id}`
Cap: `can_manage`. GET filter `?kelas_id=`.
- Body: `{ kelas_id*, hari*(1=Senin..7=Minggu), jam_masuk*"HH:MM", jam_keluar*"HH:MM" }`
- Jam disimpan `HH:MM:SS`; menit wajib 2 digit.
- Error: `422 kelas_invalid` · `422 hari_invalid` · `422 jam_invalid` · `422 jam_urutan` (keluar>masuk) · `409 jadwal_duplikat` (1 jadwal per kelas+hari).
- GET list nambah: `nama_kelas`.

### Wali (ortu↔anak) — `GET/POST /wali`, `DELETE /wali/{id}`
Cap: `can_manage`. GET filter `?wali_user_id=&siswa_id=`.
- POST body: `{ wali_user_id*, siswa_id* }` → `201 { id, wali_nama, siswa_nama }`.
- Error: `422 wali_invalid` · `404 siswa_tidak_ditemukan` · `409 sudah_terhubung` · `404 relasi_tidak_ada`.
- GET list item: `id, wali_user_id, wali_nama, wali_login, siswa_id, siswa_nama, nis, kelas_id, nama_kelas`.
- FE: **WaliLinker di `admin/views/siswa.php`** (R4). View ortu pakai `AbsensiConfig.anakList`.

### Siswa — `GET/POST /siswa`, `GET/PUT/DELETE /siswa/{id}` (sudah ada sebelumnya)
Cap: `can_manage`. Body `{ nis*, nama*, kelas_id*, user_id? }`.

---

## 4. Laporan & Export

### `GET /laporan` — list rekap (paginasi)
Cap: `can_view` (admin/guru/ortu). Query: `dari`, `sampai`, `preset`, `kelas_id`, `page`, `per_page`.
→ `{ data:[...], total, page, per_page, total_page }`.
Tiap row: `id, siswa_id, kelas_id, tanggal, waktu_masuk, waktu_keluar, status, mode, metode_masuk, metode_keluar, lat, lng, jarak_meter, foto_path, nama, nis, nama_kelas`.
> **Ortu auto-scoped:** hanya anak ter-link. `siswa_id` dari client diabaikan (anti-IDOR).

### `GET /laporan/summary` — agregat status
Cap: `can_view`. Query: `dari`, `sampai`, `preset`, `kelas_id`.
→ `{ dari, sampai, kelas_id, tanggal, hadir, telat, izin, sakit, alpha, total }`.
`tanggal` terisi saat rentang 1 hari, `null` saat >1 hari.

### `GET /laporan/export` — download file
Cap: `absensi_view_reports`. Query: `format=csv|xlsx|pdf` (default csv), `dari`, `sampai`, `preset`, `kelas_id`.
→ **File download** (`Content-Disposition: attachment`), bukan JSON.
- csv → `text/csv` (BOM UTF-8) · xlsx → Excel · pdf → A4 landscape.
- Error: `422 format_invalid` · `503 export_unavailable` (kalau library belum di-install — **sudah di-install**, jadi xlsx/pdf aktif).
- Contoh: `/laporan/export?format=xlsx&preset=bulanan` · `/laporan/export?format=csv&dari=2026-06-01&sampai=2026-06-07&kelas_id=3`.

### Preset rentang (berlaku di laporan/summary/export)
`preset=harian` (hari ini) · `mingguan` (Senin–Minggu pekan ini) · `bulanan` (tgl 1–akhir bulan).
**`dari`+`sampai` eksplisit menang** atas preset. Tanpa keduanya → hari ini.

---

## 5. Settings — `GET/PUT /settings`
Cap: `manage_options`. PUT **partial** (kirim field yang diubah saja).
Field: `absensi_lat`(-90..90), `absensi_lng`(-180..180), `absensi_radius`(clamp 25–500), `absensi_jam_masuk`/`absensi_jam_keluar`("HH:MM"), `absensi_telat_menit`(0–240), `absensi_akurasi_max`(1–1000), `absensi_rfid_debounce`(0–60), `absensi_retensi_hari`(0–3650), `absensi_wa_gateway`(url), `absensi_wa_token`(text).
- Sukses → `{ success, updated:{...}, settings:{...} }`.
- Invalid → `422 { code:"validasi_gagal", message, errors:{ field:"..." } }`.
- GET mengembalikan semua field termasuk `absensi_wa_token` (admin only). **Token TIDAK di-inject ke localize.**

---

## 6. Role & Capability (slug FINAL — K11)

| Role | Slug | Cap utama |
|---|---|---|
| Admin sekolah | `absensi_admin` | semua |
| Guru | `guru` | submit_rfid, enroll_rfid, view_reports |
| Siswa | `absensi_siswa` | submit_self |
| Orang tua | `orang_tua` | view_child |
| Super admin WP | `administrator` | semua cap absensi |

Cap: `absensi_submit_self`, `absensi_submit_rfid`, `absensi_enroll_rfid`, `absensi_view_reports`, `absensi_view_child`.
> ⚠️ JANGAN pakai slug plan lama `absensi_guru` / `absensi_wali`. `absensi_wali` = nama **tabel** relasi ortu, bukan role.

---

## 7. Kode error (rangkuman untuk pesan FE)

| Code | HTTP | Konteks |
|---|---|---|
| `butuh_https` | 403 | absen non-HTTPS |
| `koordinat_invalid` | 422 | GPS invalid |
| `sekolah_belum_diatur` | 503 | lat/lng sekolah kosong |
| `diluar_radius` | 403 | di luar radius sekolah |
| `akurasi_rendah` | 422 | accuracy > batas |
| `sudah_absen` / `sudah_absen_keluar` / `belum_absen_masuk` | 409 | sesi absen |
| `double_tap` | 429 | RFID tap terlalu cepat |
| `uid_tidak_terdaftar` | 404 | kartu belum di-enroll |
| `kartu_terpakai` / `sudah_punya_kartu` | 409 | enroll RFID |
| `nama_wajib` / `guru_invalid` / `kelas_ada_siswa` | 422/409 | kelas |
| `hari_invalid` / `jam_invalid` / `jam_urutan` / `jadwal_duplikat` | 422/409 | jadwal |
| `wali_invalid` / `sudah_terhubung` / `relasi_tidak_ada` | 422/409/404 | wali |
| `validasi_gagal` | 422 | settings (+`errors{}`) |
| `format_invalid` / `export_unavailable` | 422/503 | export |
| `foto_korup` / `foto_tipe_ditolak` / `foto_bukan_gambar` / `foto_terlalu_besar` | 422 | upload selfie |

---

## 8. Checklist aksi FE
- [ ] Ganti semua base URL `absen/v1` → **`absensi/v1`**.
- [ ] Export: pakai `/absensi/v1/laporan/export?format=...` (sudah jalan, xlsx/pdf aktif).
- [ ] `parseInt` nilai numerik dari `AbsensiConfig`/`AbsensiAdmin`.
- [ ] Selfie kirim `sesi` + `accuracy`.
- [ ] Pakai `preset` di FilterBar laporan.
- [ ] WaliLinker → `/wali`; view ortu → `AbsensiConfig.anakList`.
- [ ] Migrasi form PHP kelas/jadwal ke REST `/kelas` & `/jadwal` (opsional, saat siap).
- [ ] Pakai slug role final (K11) di cek role.
- [ ] Tampilkan pesan untuk kode error baru (§7).
