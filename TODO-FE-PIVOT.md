# TODO FE — Pivot Kiosk Tanpa Login (Users/Group)

> Scope **frontend** (markup/JS/CSS + view PHP). Konsumsi kontrak REST dari BE. Model: tanpa akun/role publik; absen kiosk tanpa login.
> **Serahan BE→FE:** [HANDOFF-FE.md](HANDOFF-FE.md) (kontrak resmi). **Acuan:** [design.md](design.md) (visual + rincian halaman) · [RINCIAN-HALAMAN.md](RINCIAN-HALAMAN.md) (elemen fungsional) · [CLAUDE.md](CLAUDE.md) (arsitektur/REST) · [CLAUDE.local.md](CLAUDE.local.md) (aturan peran FE).
> ⚠️ Buang UI lama: surface ortu, login per-role, nav role-based. Relabel "kelas"→"group", "NIS"→"Nomor Induk (NIS/NIP)".
> **Konsep data (HANDOFF §6):** `absensi_users` = siswa + guru + staff **satu tabel**, dibedakan via `group.tipe`; guru absen lewat kiosk yang sama (tap RFID / nomor induk).

---

## Status & Urutan

✅ **BE SELESAI** — semua endpoint v2 live (`/absen/*` publik, `/users`,`/group`,`/jadwal`,`/laporan`,`/settings` admin). **FE tak keblok lagi:** markup **dan** wire live bisa langsung. Design system fix di [design.md](design.md).

**Urutan disarankan:** Fondasi (design system) → Kiosk publik → Admin (Users→Group→Laporan→Dashboard→Settings→RFID) → Pembersihan.

**Aturan tes tiap item** (CLAUDE.local.fe.md §9): buka halaman/shortcode di browser → console bersih → Network (request field benar, tiap response 2xx/4xx/5xx ditangani) → render sesuai design.md. Sertakan bukti. Kiosk butuh **HTTPS** (kamera+GPS).

---

## Fondasi — Design System (kerjakan DULUAN, dipakai semua halaman)

Implementasi [design.md §2](design.md) sebagai komponen reusable (Alpine + Tailwind CDN, no build).

- [x] **Base & scope CSS** — wrapper `.absensi-app` untuk admin; scope semua style di bawahnya (jangan bocor ke wp-admin; preflight Tailwind sudah OFF dari BE). Kiosk: layout fullscreen sendiri. ✅ 2026-07-07 (16/16, [tests/_fe_css_scope_test.php](tests/_fe_css_scope_test.php), [UC](tests/manual/UC-fe-css-base-scope.md)) — `admin.css`/`public.css` ditulis ulang: token design.md di `:root`, admin scoped `.absensi-app`, kiosk fullscreen `.absensi-kiosk`; buang bocoran lama (`*{}` global + `#wpwrap/.wrap` !important).
- [x] **Token warna** — set palette design.md §2.1 (Primary biru `#2563EB`, status hadir/telat/izin/sakit/alpha, ungu **khusus sakit**). Badge status + peta warna. ✅ 2026-07-07 (52/52, [tests/_fe_token_badge_test.php](tests/_fe_token_badge_test.php), [UC](tests/manual/UC-fe-token-badge.md)) — kelas `.badge--{hadir,telat,izin,sakit,alpha}` (status) + `.badge--{kelas,guru,staff}` (tipe group) di `admin.css`; status + `.badge--lg` di `public.css` (feedback kiosk). Peta warna dikunci test; ungu `#8B5CF6` cuma Sakit/Guru, `--c-primary` tetap biru.
- [x] **Tipografi** — skala §2.2 (Inter; Display/Heading/Sub/Body/Caption/Button/Table). Kiosk: nama/status/jam besar. ✅ 2026-07-07 (35/35, [tests/_fe_typography_test.php](tests/_fe_typography_test.php), [UC](tests/manual/UC-fe-typography.md)) — kelas `.t-{display,h1,h2,h3,body,body-strong,caption,caption-med,button,th,td}` + `.u-num` di `admin.css`; font-feature cv02/03/04; kiosk nama 36→48/status 28→32/jam 40→56 tabular.
- [x] **Ikon** — set Lucide (§2.3), helper render ikon. ✅ 2026-07-07 (67/67, [tests/_fe_icon_test.php](tests/_fe_icon_test.php), [UC](tests/manual/UC-fe-icon.md)) — inline SVG (stroke 1.75, currentColor); helper `window.absensiIcon(name,size)` + Alpine magic `$icon`; registry lengkap di `admin.js`, subset kiosk di `public.js`; CSS `.icon` scoped. Fallback `help`+warn utk nama tak dikenal. node --check OK.
- [x] **Komponen UI reusable** (§2.9): Button (primary/outline/ghost/danger + sm/md/lg + loading), Input, Select, Date Picker, Search, **Filter Pill Tabs**, Table, Card, Stat Card, Modal, Badge, Toast, Alert, Pagination, Empty State, Error State, Loading/Skeleton. ✅ 2026-07-07 (89/89, [tests/_fe_components_test.php](tests/_fe_components_test.php), [UC](tests/manual/UC-fe-components.md)) — library CSS scoped di `admin.css` (set lengkap) + subset kiosk di `public.css`; state via kelas (`.is-active/.is-loading/.input--error`); animasi keyframes `absensi-*`; semua pakai token, 0 bocor (scope 16/16).
- [x] **Helper fetch** — util panggil REST: base `AbsensiConfig/AbsensiAdmin.restUrl`, inject `X-WP-Nonce` untuk endpoint admin, parser error `{code,message,data.status}` → toast/inline. ✅ 2026-07-07 (46/46, [tests/_fe_fetch_test.php](tests/_fe_fetch_test.php), [UC](tests/manual/UC-fe-fetch.md)) — base `window.api` (sudah ada) + `window.absensiApiError` (parse code/status/message + default per status) + `window.absensiToast`/`absensiToastError` (komponen `.toast`, ikon per type, textContent anti-XSS, auto-dismiss). Scoped admin/kiosk. node --check OK.

---

## Public — Kiosk Absensi Siswa (`public/views/siswa.php`)

Ref: [design.md §10](design.md) + RINCIAN A.1. Layout **fullscreen** (bukan dashboard). Endpoint: `POST /absen/selfie`, `GET /absen/status`. Config: `AbsensiConfig` (`restUrl, nonce, akurasiMax`).

- [x] Markup fullscreen kiosk (header judul + kartu alur di tengah, max-width ~480px). ✅ 2026-07-07 (17/17, [tests/_fe_kiosk_siswa_shell_test.php](tests/_fe_kiosk_siswa_shell_test.php), [UC](tests/manual/UC-fe-kiosk-siswa-shell.md)) — `public/views/siswa.php` ditulis ulang jadi shell design system (`.absensi-kiosk` > `.kiosk-card` > `.kiosk-head`/`.kiosk-flow`, judul "Absensi Siswa"); buang markup pra-pivot (form izin/sakit 404, @import font eksternal, CSS sab-* inline, submit tanpa nomor_induk). php -l OK.
- [x] **Input Nomor Induk** (wajib, ≤30, ikon `id-card`). ✅ 2026-07-07 (17/17, [tests/_fe_kiosk_siswa_input_test.php](tests/_fe_kiosk_siswa_input_test.php), [UC](tests/manual/UC-fe-kiosk-siswa-input.md)) — field `.field`/`.input-group` di `.kiosk-flow`: label "Nomor Induk (NIS/NIP)", ikon `id-card` ($icon), `required`+`maxlength=30`+`inputmode=numeric`, `x-model.trim="nomorInduk"` (state inline sementara, konsolidasi ke komponen di item GPS/Kirim). CSS `.input-group` kiosk. php -l OK.
- [x] **Ambil GPS** (`navigator.geolocation.getCurrentPosition`) → `lat`,`lng`,`accuracy`; status "mencari lokasi…"/didapat/ditolak. ✅ 2026-07-07 (27/27, [tests/_fe_kiosk_siswa_gps_test.php](tests/_fe_kiosk_siswa_gps_test.php), [UC](tests/manual/UC-fe-kiosk-siswa-gps.md)) — komponen Alpine `kioskSiswa` di public.js (state gps/gpsStatus/gpsError, `init→startGps`, `getCurrentPosition` one-shot, guard); view `.kiosk-gps` 3 state (waiting spinner "Mencari lokasi…" / ok map-pin +akurasi / error +Coba Lagi), aria-live. CSS `.kiosk-gps*`/`.spinner--sm`/`.btn--sm`. node+php -l OK.
- [x] **Indikator akurasi** — bandingkan `accuracy` vs `AbsensiConfig.akurasiMax` → warn bila lebih. ✅ 2026-07-07 (12/12, [tests/_fe_kiosk_siswa_akurasi_test.php](tests/_fe_kiosk_siswa_akurasi_test.php), [UC](tests/manual/UC-fe-kiosk-siswa-akurasi.md)) — getter `akurasiMax` (AbsensiConfig, default 100); startGps sukses set `ok`/`weak` (accuracy ≤/> max); view state `weak` kuning "Akurasi rendah · ±n m — cari sinyal lebih baik" (`.kiosk-gps--weak`). node+php -l OK.
- [x] **Kamera selfie** (`getUserMedia`) → preview → **Ambil Foto** (base64) → **Ulang Foto**. Foto opsional. ✅ 2026-07-07 (26/26, [tests/_fe_kiosk_siswa_kamera_test.php](tests/_fe_kiosk_siswa_kamera_test.php), [UC](tests/manual/UC-fe-kiosk-siswa-kamera.md)) — `kioskSiswa`: state cam(off/live/preview)+stream/photoBlob/photoUrl; `startCamera` (getUserMedia facingMode user, guard https→toast, tak blok), `capturePhoto` (canvas→toBlob jpeg), `retakePhoto`, `stopCamera`/`destroy` cleanup. View `.kiosk-cam` 3 sub-state (placeholder/video/preview) + tombol camera/rotate-ccw. node+php -l OK.
- [x] **Toggle sesi** Masuk/Pulang (opsional; kosong = auto server). ✅ 2026-07-07 (11/11, [tests/_fe_kiosk_siswa_sesi_test.php](tests/_fe_kiosk_siswa_sesi_test.php), [UC](tests/manual/UC-fe-kiosk-siswa-sesi.md)) — state `sesi:''` (default auto) di `kioskSiswa`; view `.pill-tabs` **2 tombol Masuk/Pulang** (ikut design.md §10; tap aktif lagi=lepas ke auto), `is-active`+`aria-pressed`, role=group. CSS `.pill`/`.field__hint` kiosk. node+php -l OK.
- [x] **Tombol Absen Sekarang** (Primary lg) — disable sampai GPS siap; loading "Mengirim…". ✅ 2026-07-07 (14/14, [tests/_fe_kiosk_siswa_tombol_test.php](tests/_fe_kiosk_siswa_tombol_test.php), [UC](tests/manual/UC-fe-kiosk-siswa-tombol.md)) — state `submitting` + getter `canSubmit` (nomorInduk+gps+!submitting), stub `submit` (diisi item Kirim); view tombol `.btn--primary.btn--lg.btn--block` `:disabled=!canSubmit`, loading `.is-loading`+`.btn__spin`+"Mengirim…", idle ikon send+"Absen Sekarang". node+php -l OK.
- [x] **Kirim** `POST /absen/selfie {nomor_induk,lat,lng,accuracy?,foto?,sesi?}` + tangani state:
  - 201 masuk (hadir/telat), 200 pulang, 404 `nomor_tidak_terdaftar`, 403 `diluar_radius`/`butuh_https`, 422 `nomor_kosong`/`koordinat_invalid`/`akurasi_rendah`, 409 `sudah_absen`/`sudah_absen_keluar`/`belum_absen_masuk`, 429 `terlalu_cepat`, 503 `sekolah_belum_diatur`.
  - ✅ 2026-07-07 (17/17, [tests/_fe_kiosk_siswa_kirim_test.php](tests/_fe_kiosk_siswa_kirim_test.php), [UC](tests/manual/UC-fe-kiosk-siswa-kirim.md)) — `kioskSiswa.submit` (async): body JSON `{nomor_induk,lat,lng,accuracy?,sesi?,foto?}` (foto `_blobToBase64` data-URL), `api.post('absen/selfie')`; sukses→`result.ok`(sesi/status/jarak/message/jam); error→`absensiApiError`→`result`(code/httpStatus/message, pesan server) semua status; guard `canSubmit`+`navigator.onLine`; `submitting` finally. **Kartu hasil dirender item Area Hasil.** node OK.
- [x] **Area hasil** — kartu feedback warna sesuai (hijau hadir / kuning telat / info pulang / merah error). ✅ 2026-07-07 (23/23, [tests/_fe_kiosk_siswa_hasil_test.php](tests/_fe_kiosk_siswa_hasil_test.php), [UC](tests/manual/UC-fe-kiosk-siswa-hasil.md)) — getter `resultClass`(success/warning/info/danger)/`resultIcon`/`resultTitle` + `reset()` di `kioskSiswa`; view `.kiosk-result` (x-show result, ikon+judul+message+meta jam/jarak, tombol Absen Lagi), aria-live assertive. CSS `.kiosk-result*`+`[x-cloak]`. **Simpangan:** endpoint tak balas `nama` → kartu tampil jam/jarak/message (nama di /absen/status). node+php -l OK.
- [x] **Widget Cek Status Hari Ini** — input nomor_induk → `GET /absen/status` → tampil `sudah_absen`, `nama`, `rekap{status,waktu_masuk,waktu_keluar}`. ✅ 2026-07-07 (23/23, [tests/_fe_kiosk_siswa_status_test.php](tests/_fe_kiosk_siswa_status_test.php), [UC](tests/manual/UC-fe-kiosk-siswa-status.md)) — state+method `checkStatus`/`jamHM` di `kioskSiswa` (input `statusNomor` terpisah, `api.get('absen/status')`, error via absensiApiError); view `.kiosk-status` (input+tombol Cek Status outline, nama, "belum absen"/badge status+jam masuk/keluar, alert error). CSS `.kiosk-status*`. node+php -l OK.
- [x] **Permission handling** — kamera ditolak (foto opsional, tetap bisa absen) & GPS ditolak (tombol "Aktifkan Lokasi", tak bisa absen tanpa lat/lng). ✅ 2026-07-07 (12/12, [tests/_fe_kiosk_siswa_permission_test.php](tests/_fe_kiosk_siswa_permission_test.php), [UC](tests/manual/UC-fe-kiosk-siswa-permission.md)) — `startCamera` set `camDenied` saat NotAllowedError → note inline "foto opsional, absen tetap bisa" (`canSubmit` tak butuh foto); GPS error tombol relabel **"Aktifkan Lokasi"** (@startGps), `canSubmit` butuh gps → absen terblok tanpa lokasi. CSS `.kiosk-cam__hint--warn`. node+php -l OK.
- [x] Loading/Error/Empty state (§10) + accessibility (`aria-live` hasil & GPS, target sentuh ≥44px). ✅ 2026-07-07 (16/16, [tests/_fe_kiosk_siswa_a11y_test.php](tests/_fe_kiosk_siswa_a11y_test.php), [UC](tests/manual/UC-fe-kiosk-siswa-a11y.md)) — target sentuh kiosk ≥44px (`.btn--sm` 40→44, `.pill` min-height 44); aria-live assertive (GPS+hasil); overlay ringan submit (`.kiosk-flow[aria-busy]` + `:aria-busy`); error/empty/retry sudah dari item2 sebelumnya. php -l OK.

## Public — Kiosk Absensi Guru RFID (`public/views/guru.php`)

Ref: [design.md §11](design.md) + RINCIAN A.2. **Fullscreen**, input UID tersembunyi, feedback BESAR. Endpoint: `POST /absen/rfid`. Config: `rfidDebounce`.

- [x] Markup fullscreen kiosk (jam besar real-time + area feedback besar + status idle). ✅ 2026-07-07 — `_fe_kiosk_guru_shell_test.php` 39/39; UC `tests/manual/UC-fe-kiosk-guru-shell.md`.
- [x] **Input UID auto-focus permanen** (klik di mana pun → refocus); dengar Enter → submit → clear → refocus (loop tap). ✅ 2026-07-07 — `_fe_kiosk_guru_focus_test.php` 12/12; UC `tests/manual/UC-fe-kiosk-guru-focus.md`.
- [x] **Kirim** `POST /absen/rfid {rfid_uid}` + tangani: 201 `{action:'masuk',status,siswa}`, 200 `{action:'keluar',siswa}`, 404 `uid_tidak_terdaftar`, 429 `double_tap`, 409 `sudah_absen`. ✅ 2026-07-07 — `_fe_kiosk_guru_kirim_test.php` 21/21; UC `tests/manual/UC-fe-kiosk-guru-kirim.md`.
- [x] **Feedback besar** — nama (Display), status badge besar (hadir/telat/keluar), pesan sambutan; tahan ~2–3 dtk → auto reset "Siap scan…". ✅ 2026-07-07 — `_fe_kiosk_guru_feedback_test.php` 12/12; UC `tests/manual/UC-fe-kiosk-guru-feedback.md`.
- [x] Jam besar berjalan (tabular-nums). Opsional: beep sukses/gagal. ✅ 2026-07-07 — `_fe_kiosk_guru_jambeep_test.php` 21/21; UC `tests/manual/UC-fe-kiosk-guru-jambeep.md`.
- [x] Error jaringan → feedback merah "coba tap lagi" → auto reset. Accessibility `aria-live=assertive`. ✅ 2026-07-07 — `_fe_kiosk_guru_neterr_test.php` 7/7; UC `tests/manual/UC-fe-kiosk-guru-neterr.md`.

---

## Admin — Users (`admin/views/users.php`)

Ref: [design.md §5](design.md) + RINCIAN B.1. **Konten di dalam wp-admin** (tanpa sidebar/topbar sendiri). Endpoint: `/users` CRUD, `/users/{id}/rfid`, `/users/import`.

- [x] Header halaman: judul "Users" + tombol **Tambah User** (Primary) & **Import Excel** (Outline). ✅ 2026-07-07 — `_fe_admin_users_header_test.php` 16/16; UC `tests/manual/UC-fe-admin-users-header.md`.
- [x] **Filter bar**: Search (nama/nomor_induk, debounce, param `search`) + Select Group (dari `/group`, param `group_id`). ✅ 2026-07-07 — `_fe_admin_users_filter_test.php` 16/16; UC `tests/manual/UC-fe-admin-users-filter.md`.
- [x] **Tabel** (`GET /users`): kolom User (avatar+nama+nomor_induk), Group (`nama_group`), Tipe (badge), RFID (mask), Aksi (edit/bind/hapus). Pagination. ✅ 2026-07-07 — `_fe_admin_users_tabel_test.php` 27/27; UC `tests/manual/UC-fe-admin-users-tabel.md`. ⚠️ BE `/users` array polos tanpa search/pagination server → search+paging client-side (group filter server).
- [x] **Modal Form** tambah/edit: `nomor_induk`* (≤30), `nama`* (≤150), `group_id` (dropdown), `rfid_uid` (opsional). `POST`/`PUT`. Tangani 409 duplikat (inline field), 422. ✅ 2026-07-07 — `_fe_admin_users_modalform_test.php` 24/24; UC `tests/manual/UC-fe-admin-users-modalform.md`. ⚠️ 409 BE generik → tandai nomor_induk+rfid_uid keduanya.
- [x] **Modal Bind RFID**: input UID auto-focus (tap) + opsi Ganti (`replace=true`) → `POST /users/{id}/rfid`. Tangani 409 `kartu_terpakai`/`sudah_punya_kartu`. ✅ 2026-07-07 — `_fe_admin_users_bind_test.php` 20/20; UC `tests/manual/UC-fe-admin-users-bind.md`. ⚠️ BE tak punya replace/sudah_punya_kartu → guard "Ganti" client-side; 409 kartu_terpakai tanpa nama pemilik.
- [x] **Modal Import Excel**: upload `.xlsx` (**multipart field `file`** atau base64) → `POST /users/import`; info kolom (nama/nomor_induk/group — group tak ada = auto-create, cap 2000 baris); tampil `{imported, gagal, errors[{baris,pesan}]}`; 503 vendor absen. ✅ 2026-07-07 — `_fe_admin_users_import_test.php` 22/22; UC `tests/manual/UC-fe-admin-users-import.md`. (FE kirim base64.)
- [x] **Hapus**: konfirmasi → `DELETE /users/{id}` + toast. ✅ 2026-07-07 — `_fe_admin_users_hapus_test.php` 15/15; UC `tests/manual/UC-fe-admin-users-hapus.md`.
- [x] State: skeleton tabel, empty ("Belum ada user"/filter kosong), error + Coba Lagi. Responsive: mobile → card-list. Accessibility modal (focus-trap/Esc/aria). ✅ 2026-07-08 — `_fe_admin_users_state_test.php` 19/19; UC `tests/manual/UC-fe-admin-users-state.md`.

## Admin — Group (`admin/views/group.php`)

Ref: [design.md §6](design.md) + RINCIAN B.2. Endpoint: `/group` CRUD.

- [x] Header: judul "Group" + tombol **Tambah Group**. ✅ 2026-07-08 — `_fe_admin_group_header_test.php` 12/12; UC `tests/manual/UC-fe-admin-group-header.md`.
- [x] **Tabel** (`GET /group`): Nama, Tipe (badge Kelas/Guru/Staff), Jumlah User (`jumlah_user`), Aksi. ✅ 2026-07-08 — `_fe_admin_group_tabel_test.php` 15/15; UC `tests/manual/UC-fe-admin-group-tabel.md`.
- [x] **Modal Form**: `nama`* (≤100), `tipe` (kelas default/guru/staff). `POST`/`PUT`. ✅ 2026-07-08 — `_fe_admin_group_modalform_test.php` 22/22; UC `tests/manual/UC-fe-admin-group-modalform.md`.
- [x] **Hapus** → `DELETE /group/{id}`; tangani **409 `group_ada_user`** (alert "masih ada N user"). ✅ 2026-07-08 — `_fe_admin_group_hapus_test.php` 19/19; UC `tests/manual/UC-fe-admin-group-hapus.md`. (Proaktif: disable Hapus bila jumlah_user>0.)
- [x] State empty/error/skeleton; responsive card-list mobile. ✅ 2026-07-08 — `_fe_admin_group_state_test.php` 14/14; UC `tests/manual/UC-fe-admin-group-state.md`.

## Admin — Laporan (`admin/views/laporan.php`)

Ref: [design.md §8](design.md) + RINCIAN B.4. Endpoint: `/laporan`, `/laporan/summary`, `/laporan/export`. Relabel **Group**/**Nomor Induk**.

- [x] Header: judul "Laporan" + tombol **Export** (dropdown csv/xlsx/pdf). ✅ 2026-07-08 — `_fe_admin_laporan_header_test.php` 19/19; UC `tests/manual/UC-fe-admin-laporan-header.md`.
- [x] **Summary cards (6)**: hadir/telat/izin/sakit/alpha/total (`GET /laporan/summary`), warna peta status. ✅ 2026-07-08 — `_fe_admin_laporan_summary_test.php` 17/17; UC `tests/manual/UC-fe-admin-laporan-summary.md`.
- [x] **Filter server**: Date Picker `dari`/`sampai`, Select `preset`, Select `group_id`, paging. ✅ 2026-07-08 — `_fe_admin_laporan_filter_test.php` 16/16; UC `tests/manual/UC-fe-admin-laporan-filter.md`. (paging state page/perPage; UI pagination = item Tabel.)
- [x] **Filter status pill-tabs** (client-side — backend tak punya param status; saring baris termuat). ✅ 2026-07-08 — `_fe_admin_laporan_statuspill_test.php` 11/11; UC `tests/manual/UC-fe-admin-laporan-statuspill.md`.
- [x] **Tabel** (`GET /laporan`): Tanggal, Nama, Nomor Induk, Group, Status (badge), Waktu Masuk/Keluar, Metode, Jarak, Bukti (bila ada). Pagination `{data,total,page,per_page,total_page}`. ✅ 2026-07-08 — `_fe_admin_laporan_tabel_test.php` 23/23; UC `tests/manual/UC-fe-admin-laporan-tabel.md`. (Jarak = kolom `jarak_meter`; pagination server-side.)
- [x] **Export** → `GET /laporan/export?format=…`+filter → unduh; tangani **503 `export_unavailable`** (xlsx/pdf tanpa vendor → saran CSV). ✅ 2026-07-08 — `_fe_admin_laporan_export_test.php` 13/13; UC `tests/manual/UC-fe-admin-laporan-export.md`. (fetch blob + X-WP-Nonce; disable saat kosong.)
- [ ] (Opsional) grafik ringkas distribusi status. State empty/error/skeleton; responsive.

## Admin — Dashboard (`admin/views/dashboard.php`)

Ref: [design.md §4](design.md) + RINCIAN B.5. Sumber: `/laporan/summary`, `/users`(total), `/group`, `/laporan`(terbaru).

- [x] **Quick Stats (6)**: Total User, Total Group, Hadir, Telat, Izin, Alpha. ✅ 2026-07-08 — `_fe_admin_dashboard_stats_test.php` 21/21; UC `tests/manual/UC-fe-admin-dashboard-stats.md`. (Total User/Group = panjang array /users,/group.)
- [x] **Grafik kehadiran** (tren per status; data dari summary/laporan). ✅ 2026-07-08 — `_fe_admin_dashboard_grafik_test.php` 17/17; UC `tests/manual/UC-fe-admin-dashboard-grafik.md`. (Bar distribusi status hari ini dari summary; tren time-series butuh endpoint agregat BE.)
- [x] **Quick Action**: tombol pintas (Tambah User, Tambah Group, Lihat Laporan, Export). ✅ 2026-07-08 — `_fe_admin_dashboard_quickaction_test.php` 11/11; UC `tests/manual/UC-fe-admin-dashboard-quickaction.md`.
- [x] **Absensi Terbaru**: tabel ringkas (`GET /laporan` terbaru: nama, group, status, waktu_masuk). ✅ 2026-07-08 — `_fe_admin_dashboard_recent_test.php` 14/14; UC `tests/manual/UC-fe-admin-dashboard-recent.md`.
- [x] State empty/error/skeleton; responsive. ✅ 2026-07-08 — `_fe_admin_dashboard_state_test.php` 11/11; UC `tests/manual/UC-fe-admin-dashboard-state.md`.

## Admin — Pengaturan (`admin/views/settings.php` — sudah ada, selaraskan ke design)

Ref: [design.md §9](design.md) + RINCIAN B.6. Endpoint: `GET/PUT /settings` (prefill juga `AbsensiAdmin.settings`).

- [x] Kelompokkan jadi **5 card**: Lokasi/GPS (lat,lng,radius,akurasi_max), Jam Kerja (jam_masuk,jam_keluar,telat_menit), RFID (rfid_debounce), Retensi (retensi_hari), WhatsApp (gateway; token tak prefill — **card boleh disembunyikan, luar MVP**). ✅ 2026-07-08 — `_fe_admin_settings_cards_test.php` 25/25; UC `tests/manual/UC-fe-admin-settings-cards.md`. (Komponen baru `settingsManager`; token tak di-prefill.)
- [x] (Disarankan) map picker lat/lng; hint radius maks 500. ✅ 2026-07-08 — `_fe_admin_settings_map_test.php` 13/13; UC `tests/manual/UC-fe-admin-settings-map.md`. ⚠️ Peta interaktif Leaflet butuh enqueue BE; versi FE = GPS perangkat + link OSM.
- [x] Tombol **Simpan** → `PUT /settings`; tangani 422 field. Toast sukses. ✅ 2026-07-08 — `_fe_admin_settings_simpan_test.php` 16/16; UC `tests/manual/UC-fe-admin-settings-simpan.md`.

> **Submenu "Absen RFID" (admin) DIBUANG** (BE: Menu.php + view `rfid.php` dihapus). Tak perlu bikin halaman ini. Bind kartu = popup di **Users** (`/users/{id}/rfid`); tap-absen = kiosk publik `/absensi-guru`.

---

## Pembersihan UI

- [x] Hapus markup/JS: surface ortu (`[absensi_ortu]` sudah dibuang BE), link login per-role, nav role-based, halaman ortu, form izin/sakit, konfirmasi guru. ✅ 2026-07-08 — hapus `absensiOrtu` (public.js) + `waliLinker`+`openWali` (admin.js); izin/konfirmasi/login sudah hilang via rewrite view. `node --check` OK; 51 file tes FE GREEN; UC `tests/manual/UC-fe-pembersihan-ortu-izin.md`.
- [x] Relabel seluruh UI: "Kelas"→"Group", "NIS"→"Nomor Induk (NIS/NIP)". ✅ 2026-07-08 — `_fe_relabel_test.php` 16/16; UC `tests/manual/UC-fe-relabel.md`. UI aktif sudah Group/Nomor Induk sejak awal; "Kelas" tersisa hanya = tipe group (sah). Residu di komponen/view orphan → dibuang item 126/127.
- [x] **Hapus view orphan admin** (HANDOFF §5): `admin/views/siswa.php`, `admin/views/kelas.php` — masih rujuk role/endpoint lama yang sudah 404. Kiosk publik pakai `public/views/siswa.php`/`guru.php` (beda file). ✅ 2026-07-08 — `git rm` siswa.php+kelas.php; Menu tak render mereka; view admin aktif `php -l` OK; UC `tests/manual/UC-fe-hapus-orphan-views.md`.
- [x] Rework `public/views/selfie.php`/`status.php` lama (gate-login model lama) — tak dipakai kiosk; hapus atau abaikan. ✅ 2026-07-08 — selfie/status di-rework jadi notice stub (BE include tanpa guard → tak dihapus); `ortu.php` orphan dihapus. `php -l` OK; 52 file tes FE GREEN; UC `tests/manual/UC-fe-rework-selfie-status.md`.
- [ ] `admin/views/jadwal.php` (bila dipakai): relabel `kelas_id`→`group_id` (endpoint `/jadwal` masih ada, tapi TAK ada halaman khusus di design MVP — kelola jadwal opsional/menyusul).

---

## Kontrak REST (dari BE — jangan mengarang field/endpoint)

- `POST /absen/selfie` `{nomor_induk,lat,lng,accuracy?,foto?,sesi?}` → 201/200; 404/403/422/409/429/503.
- `POST /absen/rfid` `{rfid_uid}` → 201/200; 404 `uid_tidak_terdaftar`, 429 `double_tap`, 409 `sudah_absen`.
- `GET /absen/status?nomor_induk=` → `{sudah_absen,nama,tanggal,rekap}`.
- `/users` (GET/POST), `/users/{id}` (GET/PUT/DELETE), `/users/{id}/rfid` (POST), `/users/import` (POST xlsx).
- `/group` (GET/POST), `/group/{id}` (GET/PUT/DELETE).
- `/laporan`,`/laporan/summary`,`/laporan/export` — param `group_id` (eks `kelas_id`).
- `/settings` (GET/PUT).

> **Endpoint 404 (jangan panggil):** `/siswa*`,`/kelas*`,`/wali*`,`/child*`,`/absen/izin`,`POST /absen/status`,`/absen/rfid/enroll`,`/absen/rfid/resolve`. Izin/sakit & notif WA = **luar MVP**.
> Localize `AbsensiConfig`/`AbsensiAdmin` (restUrl, nonce, rfidDebounce, akurasiMax, settings) disediakan BE — FE **baca saja**, jangan rename/tambah (minta BE bila butuh field baru). Field `anakList` **sudah dibuang** dari `AbsensiConfig` (model ortu). Endpoint admin wajib header `X-WP-Nonce`; kiosk publik tanpa auth (nonce opsional).
