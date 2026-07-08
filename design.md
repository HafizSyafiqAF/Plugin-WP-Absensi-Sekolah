# Design Documentation — Plugin Absensi Sekolah

**Versi:** 1.0 · **Basis fungsional:** [RINCIAN-HALAMAN.md](RINCIAN-HALAMAN.md) (single source of truth) · **Untuk:** Developer Frontend

> Dokumen ini acuan utama desain UI/UX frontend Plugin Absensi Sekolah. Seluruh halaman, komponen, tombol, input, state, dan flow diturunkan **persis** dari RINCIAN-HALAMAN.md — tak ada fitur ditambah, tak ada dihilangkan. Semua data yang ditampilkan berasal dari endpoint REST yang sudah ada (`/wp-json/absensi/v1/`). Style visual mengikuti pola dashboard SaaS enterprise modern: clean, minimalis, card-based, banyak white space, soft shadow, rounded corner.

---

## Daftar Isi

1. [Prinsip Desain](#1-prinsip-desain)
2. [Design System](#2-design-system)
3. [Layout Global (App Shell)](#3-layout-global-app-shell)
4. [Halaman: Dashboard](#4-halaman-dashboard)
5. [Halaman: Users](#5-halaman-users)
6. [Halaman: Group](#6-halaman-group)
7. [Halaman: Absen RFID (Admin) — DIHAPUS](#7-halaman-absen-rfid-admin--dihapus)
8. [Halaman: Laporan](#8-halaman-laporan)
9. [Halaman: Pengaturan](#9-halaman-pengaturan)
10. [Halaman Publik: Absensi Siswa (Kiosk)](#10-halaman-publik-absensi-siswa-kiosk)
11. [Halaman Publik: Absensi Guru (Kiosk RFID)](#11-halaman-publik-absensi-guru-kiosk-rfid)
12. [Design Rules & Konsistensi](#12-design-rules--konsistensi)
13. [Lampiran: Peta Endpoint & State](#13-lampiran-peta-endpoint--state)

---

## 1. Prinsip Desain

Lima prinsip yang mengikat seluruh keputusan visual:

1. **Konsistensi mutlak.** Radius, shadow, spacing, warna, dan tipografi identik di semua halaman. Satu komponen (mis. tombol primary) tampil sama persis di mana pun.
2. **Hierarki jelas.** Ukuran, berat font, dan warna dipakai untuk memandu mata: judul > sub-judul > konten > caption. White space memisahkan kelompok informasi.
3. **Dua konteks berbeda.** Ada **dua identitas layout**: (a) **Admin** — konten di dalam **wp-admin** (navigasi & sidebar = milik WordPress; FE desain area konten `.wrap` saja: card + table); (b) **Kiosk Publik** — halaman frontend **fullscreen** mandiri (fokus tunggal, terbaca jauh) untuk absen. Keduanya **tak boleh** dicampur.
4. **Feedback selalu ada.** Tiap aksi memberi respons visual: loading, sukses (toast hijau), error (toast/alert merah), empty state. Tak ada aksi "diam".
5. **Data-driven & jujur.** Semua angka/label berasal dari endpoint nyata. Tak ada elemen yang menampilkan data yang tak punya sumber backend.

---

## 2. Design System

### 2.1 Color Palette

| Token | Hex | Pemakaian |
|---|---|---|
| **Primary** | `#2563EB` | Tombol utama, link aktif, highlight, item sidebar aktif, fokus ring |
| **Secondary** | `#60A5FA` | Aksen sekunder, hover ringan primary, ikon informatif |
| **Success** | `#22C55E` | Status hadir, notifikasi sukses, badge "Paid"-equivalent |
| **Warning** | `#F59E0B` | Status telat, peringatan, konfirmasi hati-hati |
| **Danger** | `#EF4444` | Status alpha, hapus, error, badge gagal |
| **Purple Accent** | `#8B5CF6` | **KHUSUS status "Sakit"** — BUKAN warna brand/aksi (cegah tabrakan makna dengan tombol) |
| **Info** | `#06B6D4` | Status izin, info netral, tooltip informatif |
| **Sidebar Text** | `#1E293B` | **Teks & ikon sidebar** (sidebar berlatar putih — lihat §3.1). Token "Sidebar" dialih-fungsikan jadi warna teks, bukan latar. |
| **Background** | `#F8FAFC` | Latar utama area konten |
| **Card** | `#FFFFFF` | Latar kartu, tabel, modal, input |
| **Border** | `#E5E7EB` | Garis pemisah, border input/card/table |
| **Text Primary** | `#1F2937` | Judul, teks utama, angka penting |
| **Text Secondary** | `#6B7280` | Sub-teks, caption, placeholder, label |
| **Hover Sidebar** | `#334155` | Latar item sidebar saat hover |

**Turunan opacity** (untuk badge & background lembut): tiap warna status dipakai pada teks dengan latar versi 10% opacity-nya. Contoh badge Hadir: teks `#22C55E` di atas `rgba(34,197,94,0.1)`.

> **Arah warna (Hybrid — struktur ikut referensi, warna ikut sistem ini):**
> 1. **Sidebar TERANG** (putih/`#F8FAFC`), bukan gelap — lebih clean & airy (sesuai brief + referensi). Teks/ikon sidebar pakai `#1E293B`.
> 2. **Aksen utama = Primary BIRU `#2563EB`** untuk semua tombol, link, item aktif, fokus.
> 3. **Ungu `#8B5CF6` DIKUNCI untuk status "Sakit" saja** — jangan dipakai sebagai brand/tombol/aktif (kalau ungu jadi aksi, tabrakan makna dengan badge sakit).
> 4. **Filter status pakai pill-tabs** (adopsi pola referensi). Search = kontekstual di area tabel (bukan sidebar — admin pakai chrome wp-admin, lihat §3).

#### Badge Status Kehadiran (WAJIB konsisten di seluruh app)

| Status | Warna teks | Latar badge | Token |
|---|---|---|---|
| **Hadir** | `#16A34A` (hijau tua) | `rgba(34,197,94,0.12)` | Success |
| **Telat** | `#D97706` (kuning tua) | `rgba(245,158,11,0.12)` | Warning |
| **Izin** | `#0891B2` (cyan tua) | `rgba(6,182,212,0.12)` | Info |
| **Sakit** | `#7C3AED` (ungu tua) | `rgba(139,92,246,0.12)` | Purple |
| **Alpha** | `#DC2626` (merah tua) | `rgba(239,68,68,0.12)` | Danger |

> Aturan: teks pakai versi lebih gelap agar kontras (WCAG AA) di atas latar 12% opacity. Badge selalu `rounded-full`, padding `2px 10px`, font caption medium.

### 2.2 Typography

**Font utama:** `Inter` (fallback: `Plus Jakarta Sans`, `system-ui`, `sans-serif`). Font-feature `cv02, cv03, cv04` aktif untuk angka tabel yang rapi.

| Level | Ukuran / Line-height | Weight | Pemakaian |
|---|---|---|---|
| **Display** | 30px / 38px | 700 (Bold) | Angka besar kiosk, hero stat |
| **Heading (H1)** | 24px / 32px | 700 | Judul halaman |
| **Sub Heading (H2)** | 18px / 26px | 600 (Semibold) | Judul card, judul section |
| **Sub Heading (H3)** | 16px / 24px | 600 | Judul sub-section, label group |
| **Body** | 14px / 22px | 400 (Regular) | Teks konten, isi tabel, deskripsi |
| **Body Strong** | 14px / 22px | 500 (Medium) | Nama user, nilai penting inline |
| **Caption** | 12px / 18px | 400 / 500 | Label kecil, timestamp, hint, badge |
| **Button** | 14px / 20px | 600 | Teks tombol |
| **Table Header** | 12px / 18px | 600, uppercase, tracking 0.04em, warna Text Secondary | Header kolom tabel |
| **Table Cell** | 14px / 20px | 400 | Isi sel |

**Kiosk khusus** (terbaca jauh): Nama = 36–48px Bold; Status = 28–32px Semibold; Jam = 40–56px Bold (tabular-nums).

### 2.3 Iconography

**Ikon set:** Lucide Icons (fallback Heroicons — pilih satu set, konsisten). Ukuran default 20px (sidebar & tombol), 16px (inline/tabel), 18px (input adornment). Stroke 1.75px. Warna ikут warna teks konteksnya.

#### Ikon Menu Sidebar

| Menu | Lucide | Heroicons setara |
|---|---|---|
| Dashboard | `layout-dashboard` | `Squares2x2` |
| Users | `users` | `Users` |
| Group | `layers` / `folder` | `RectangleGroup` |
| Laporan | `bar-chart-3` / `file-text` | `ChartBar` |
| Pengaturan | `settings` | `Cog6Tooth` |

> Submenu "Absen RFID" dibuang — tak ada ikon menu untuk itu. Ikon `credit-card` dipakai untuk aksi **Bind RFID** di halaman Users.

#### Ikon Aksi & Utility

| Fungsi | Lucide |
|---|---|
| Absensi (brand/logo) | `clipboard-check` |
| Guru / kiosk | `id-card` |
| Tambah / New | `plus` |
| Edit | `pencil` / `square-pen` |
| Delete | `trash-2` |
| Bind RFID | `credit-card` / `radio` |
| Import (Excel) | `upload` / `file-spreadsheet` |
| Export | `download` |
| Refresh / Sync | `refresh-cw` |
| Search | `search` |
| Filter | `sliding-horizontal` / `filter` |
| Kamera | `camera` |
| Lokasi GPS | `map-pin` |
| Retake foto | `rotate-ccw` |
| Kirim / Submit | `send` / `check` |
| Sukses | `check-circle-2` |
| Error / gagal | `x-circle` / `alert-circle` |
| Warning | `alert-triangle` |
| Info | `info` |
| Jam | `clock` |
| Kalender / Date | `calendar` |
| Chevron paging | `chevron-left`, `chevron-right` |
| More action | `more-horizontal` (⋯) |
| Tutup modal | `x` |

### 2.4 Border Radius

| Token | Nilai | Pemakaian |
|---|---|---|
| `radius-sm` | 6px | Badge, tag kecil, input inline |
| `radius-md` | 8px | Tombol, input, select, dropdown |
| `radius-lg` | 12px | Card, modal, panel, table container |
| `radius-xl` | 16px | Kiosk panel, hero card |
| `radius-full` | 9999px | Avatar, badge pill, toggle |

Aturan: card selalu `radius-lg` (12px). Tombol & input selalu `radius-md` (8px). Konsisten — jangan campur.

### 2.5 Elevation & Shadow

| Token | Nilai | Pemakaian |
|---|---|---|
| `shadow-xs` | `0 1px 2px rgba(16,24,40,0.05)` | Border-assist pada card diam |
| `shadow-sm` | `0 1px 3px rgba(16,24,40,0.08), 0 1px 2px rgba(16,24,40,0.04)` | Card default, input focus ring shadow |
| `shadow-md` | `0 4px 8px rgba(16,24,40,0.08), 0 2px 4px rgba(16,24,40,0.04)` | Dropdown, hover card, popover |
| `shadow-lg` | `0 12px 24px rgba(16,24,40,0.12)` | Modal, drawer |
| `shadow-focus` | `0 0 0 3px rgba(37,99,235,0.25)` | Ring fokus input/tombol (Primary 25%) |

Prinsip: shadow **lembut & rendah** (soft shadow). Card diam pakai `shadow-sm`; naik ke `shadow-md` saat hover interaktif. Modal/drawer `shadow-lg`.

### 2.6 Grid System

- **Container konten:** max-width 1440px, padding horizontal 24px (desktop), 16px (tablet), 12px (mobile).
- **Grid Quick Stats:** 12-column grid, gap 16px. Kartu stat menempati 2 kolom (6 kartu = penuh) di desktop; 6 kolom (2 kartu/baris) di tablet; 12 kolom (1 kartu/baris) di mobile.
- **Grid form:** 2 kolom di desktop (label kiri/atas + field), 1 kolom di mobile.
- **Gap standar:** 16px antar-card, 24px antar-section, 8px antar-elemen dalam grup.

### 2.7 Spacing System

Basis **4px**. Skala: `4, 8, 12, 16, 20, 24, 32, 40, 48, 64`.

| Token | px | Pemakaian |
|---|---|---|
| `space-1` | 4 | Jarak ikon-teks |
| `space-2` | 8 | Padding badge, gap kecil |
| `space-3` | 12 | Padding input vertikal |
| `space-4` | 16 | Padding card, gap antar-card |
| `space-5` | 20 | Padding card besar |
| `space-6` | 24 | Padding konten halaman, antar-section |
| `space-8` | 32 | Section besar |
| `space-10` | 40 | Header kiosk |
| `space-12` | 48 | Blok kiosk |

Padding card standar: 20px. Padding sel tabel: 12px vertikal, 16px horizontal.

### 2.8 Responsive Breakpoints

| Breakpoint | Lebar | Perilaku utama |
|---|---|---|
| **Mobile** | < 640px | Sidebar jadi drawer (hamburger). Tabel → card list vertikal. Stat 1/baris. Form 1 kolom. |
| **Tablet** | 640–1024px | Sidebar collapsible (ikon saja / expand). Tabel scroll horizontal. Stat 2–3/baris. |
| **Desktop** | 1024–1440px | Sidebar penuh. Layout lengkap. |
| **Wide** | > 1440px | Container di-center max 1440px, margin auto. |

Kiosk (siswa/guru): responsif tersendiri — selalu fullscreen, konten di-center, skala font mengikuti viewport (lebih besar di layar besar).

### 2.9 Komponen — Spesifikasi

#### Button

| Varian | Latar | Teks | Border | Pemakaian |
|---|---|---|---|---|
| **Primary** | `#2563EB` | putih | — | Aksi utama (Simpan, Tambah, Kirim) |
| **Secondary/Outline** | putih | `#1F2937` | `#E5E7EB` | Aksi sekunder (Batal, Export, Sync) |
| **Ghost** | transparan | `#6B7280` | — | Aksi tersier, ikon-only |
| **Danger** | `#EF4444` | putih | — | Hapus permanen |
| **Danger Outline** | putih | `#EF4444` | `rgba(239,68,68,0.4)` | Hapus (konfirmasi awal) |
| **Success** | `#22C55E` | putih | — | Konfirmasi positif (opsional) |

Ukuran: **sm** (h32, px12, font 13px), **md** (h40, px16, font 14px — default), **lg** (h48, px20, font 15px — kiosk/CTA). Radius 8px. Ikon opsional kiri (gap 8px). State disabled: opacity 50%, cursor not-allowed. Loading: spinner ganti ikon + teks "Memproses…", tombol disabled.

#### Input (Text / Number)

- Tinggi 40px, padding 10px 12px, radius 8px, border `#E5E7EB`, latar putih, teks 14px.
- Label di atas (Caption 12px medium, Text Secondary). Placeholder Text Secondary.
- **Focus:** border `#2563EB` + `shadow-focus`.
- **Error:** border `#EF4444`, pesan error 12px merah di bawah, ikon `alert-circle`.
- **Disabled:** latar `#F8FAFC`, teks Text Secondary.
- Adornment kiri/kanan (ikon 18px) opsional (mis. ikon nomor induk, jam).

#### Select / Dropdown

- Sama styling dengan input, ikon `chevron-down` di kanan.
- Menu opsi: card putih, `shadow-md`, radius 8px, item hover `#F8FAFC`, item terpilih teks Primary + centang.
- Dipakai: filter Group, pilih tipe Group, pilih format export.

#### Date Picker

- Field input + ikon `calendar` kanan. Klik → popover kalender (card, shadow-md).
- Tanggal terpilih: lingkaran Primary. Hari ini: border Primary. Range (dari–sampai): highlight Secondary 12%.
- Dipakai: filter Laporan (`dari`, `sampai`).

#### Search

- Input dengan ikon `search` kiri, placeholder "Cari…". Radius 8px.
- Debounce 300ms saat mengetik. Tombol clear (`x`) muncul saat ada isi.
- Dipakai: Users (cari nama/nomor induk), tabel Laporan (opsional).
- **Posisi:** di **header/area tabel** halaman (mis. cari user di atas tabel Users). Tak ada search global di sidebar — navigasi & search wp-admin milik WordPress.

#### Filter Pill Tabs (Segmented)

- Deret pill horizontal (adopsi pola referensi "All / Unfulfilled / …"). Tiap pill: `radius-full`, padding 4px 14px, Caption/Body medium.
  - **Aktif:** latar Primary `#2563EB` (atau Primary 12% + teks Primary), teks putih.
  - **Non-aktif:** teks Text Secondary, hover latar `#F8FAFC`.
- Dipakai: **Laporan** (filter status: Semua · Hadir · Telat · Izin · Sakit · Alpha). ⚠️ Backend `/laporan` **tak punya param status** — pill status ini **filter CLIENT-SIDE** atas baris yang sudah dimuat (bukan refetch). Filter server tetap date/preset/`group_id`. Warna teks pill status boleh ikut peta badge saat aktif.
- Alternatif dari Select untuk filter yang pilihannya sedikit & sering dipakai.

#### Table (Modern)

- Container: card putih, radius 12px, `shadow-sm`, border `#E5E7EB`, overflow tersembunyi.
- Header: latar `#F8FAFC` atau putih, teks Table Header (uppercase 12px, Text Secondary), border-bottom `#E5E7EB`, tinggi baris 44px.
- Baris: tinggi 56px, border-bottom `#E5E7EB` tipis, hover latar `rgba(37,99,235,0.03)`.
- Sel: padding 12px/16px, teks 14px. Kolom aksi di kanan (ikon ⋯ atau tombol ikon).
- Avatar user: lingkaran 32px (inisial atau foto) + nama (Body Strong) & sub-teks (nomor induk, Caption).
- Zebra opsional OFF (pakai border pemisah saja, lebih clean).
- Kolom kanan-rata untuk angka; badge status di kolomnya.

#### Card

- Latar putih, radius 12px, `shadow-sm`, border `#E5E7EB`, padding 20px.
- Header card: judul H2/H3 kiri, aksi (⋯ atau tombol) kanan, border-bottom opsional.
- **Stat card:** ikon kecil di sudut (dalam kotak lembut warna status 12%), label (Caption), angka (Display/H1), delta/trend (Caption + panah, hijau naik / merah turun) — sesuai referensi Quick Stats.

#### Modal

- Overlay `rgba(15,23,42,0.5)` (blur ringan opsional). Panel: card putih, radius 12px, `shadow-lg`, max-width 480px (form) / 640px (besar), center.
- Header: judul H2 + tombol close (`x`) kanan atas. Body: konten/form. Footer: tombol rata kanan (Batal ghost/outline + Primary).
- Animasi: fade + scale-in 150ms. Klik overlay / Esc → tutup (kecuali proses berjalan).
- Dipakai: form tambah/edit User & Group, konfirmasi hapus.

#### Drawer

- Panel geser dari kanan, lebar 420px (desktop) / full (mobile), latar putih, `shadow-lg`.
- Header + body + footer seperti modal. Dipakai opsional untuk detail/edit panjang (mis. detail user). Default form pakai Modal; Drawer opsional bila konten banyak.

#### Badge

- Pill (`radius-full`), padding 2px 10px, Caption medium. Warna sesuai peta status kehadiran (§2.1) atau tipe group.
- Tipe Group: Kelas = Primary 12%, Guru = Purple 12%, Staff = Info 12% (teks versi gelap masing-masing).

#### Toast (Notifikasi sementara)

- Muncul kanan-atas, card putih border kiri tebal 4px warna status, `shadow-md`, radius 8px, auto-dismiss 4 dtk + tombol close.
- Varian: Success (hijau, ikon check-circle), Error (merah, x-circle), Warning (kuning), Info (cyan).
- Dipakai: hasil simpan/hapus/import/export/bind RFID.

#### Alert (Inline, persisten)

- Banner dalam konten/modal, latar warna status 8%, border 1px warna status 30%, ikon + teks + (opsional) aksi.
- Dipakai: peringatan konfigurasi (mis. "Koordinat sekolah belum diatur"), ringkasan error import.

#### Pagination

- Kanan bawah tabel: teks "Menampilkan 1–10 dari 80" (Caption, kiri) + kontrol angka (kanan).
- Tombol angka: kotak 32px radius 8px; aktif = latar Primary teks putih; lain = ghost hover `#F8FAFC`. Prev/Next ikon chevron. Ellipsis `…` untuk lompatan.

#### Empty State

- Ilustrasi/ikon garis (64px, Text Secondary), judul H3 ("Belum ada data"), deskripsi Body (Text Secondary), tombol aksi Primary (mis. "Tambah User") bila relevan. Center dalam card.

#### Error State

- Ikon `alert-triangle` (48px, Danger), judul "Gagal memuat data", deskripsi pesan error, tombol "Coba Lagi" (outline) untuk refetch. Center.

#### Loading State & Skeleton

- **Spinner:** lingkaran Primary berputar (20px inline / 32px area) untuk aksi & area kecil.
- **Skeleton:** blok abu (`#E5E7EB`) shimmer untuk tabel & card saat load awal. Baris tabel skeleton = balok tinggi baris; stat card skeleton = balok angka + label. Durasi shimmer 1.5s loop.
- **Progress:** import Excel & export panjang → progress/spinner + teks status.

---

## 3. Layout Halaman Admin (DI DALAM wp-admin)

> **PENTING:** ini plugin WordPress. Halaman admin render **di dalam wp-admin**, yang **sudah menyediakan** sidebar navigasi (menu WP + menu "Absensi" & 6 submenu-nya) + admin bar atas. **FE TIDAK membuat sidebar/topbar sendiri** — cukup desain **area konten** di dalam `.wrap`. Navigasi antar-halaman plugin = **submenu WordPress** yang sudah ada (Dashboard/Users/Group/Laporan/Pengaturan — 5 submenu; "Absen RFID" dibuang).

```
┌──────────────┬────────────────────────────────────────────┐
│ [WP Admin Bar sepanjang atas — punya WordPress]            │
├──────────────┼────────────────────────────────────────────┤
│ WP SIDEBAR   │   AREA KONTEN PLUGIN  (.wrap)               │
│ (punya WP)   │   ┌──────────────────────────────────────┐ │
│  Dashboard   │   │  Judul Halaman        [Tombol Aksi]   │ │  ← ini yang FE desain
│  ...         │   │  ──────────────────────────────────   │ │
│  Absensi ◀   │   │  Quick Stats / Filter / Card / Table  │ │
│   • Users ◀  │   │                                        │ │
│   • Group    │   └──────────────────────────────────────┘ │
│  Settings    │                                             │
└──────────────┴────────────────────────────────────────────┘
        ▲                          ▲
   navigasi = WP           HANYA bagian ini yang didesain FE
```

### 3.1 Area konten (yang FE desain)

Tiap halaman admin = konten di dalam `.wrap` wp-admin. Struktur baku:

1. **Header halaman** — judul (WordPress `.wrap h1`, boleh di-styling per §2.2 Heading) + (kanan) tombol aksi utama halaman (mis. "Tambah User" Primary, "Export" Outline, "Refresh" Ghost). Baris pertama konten, judul kiri + tombol kanan (pola referensi).
2. **Body** — Quick Stats / Filter bar / Card / Table / Pagination sesuai halaman.
3. Latar area konten = Background `#F8FAFC` (atau default wp-admin); kartu/tabel putih.

**TIDAK ADA:** sidebar plugin, topbar plugin, breadcrumb kustom, search global plugin. Semua itu sudah disediakan wp-admin. (Breadcrumb mengandalkan menu/judul submenu WP.)

### 3.2 Coexist dengan wp-admin (batasan teknis WAJIB)

- **Scope CSS:** bungkus seluruh markup plugin dalam satu wrapper (mis. `<div class="absensi-app">…`) dan **prefix semua style di bawahnya** agar tak bocor merusak chrome wp-admin. Tailwind **preflight sudah dimatikan** di enqueue admin (lihat `Plugin.php`) — jangan aktifkan reset global.
- **Jangan** override elemen global wp-admin (`body`, `#adminmenu`, `.wp-toolbar`, dsb).
- **Enqueue** asset admin hanya di halaman plugin (hook mengandung `absensi`) — sudah diatur backend.
- Alpine.js + Tailwind via CDN (config di `AbsensiAdmin`).

### 3.3 Sub-nav plugin (OPSIONAL)

Boleh tambah **tab horizontal** di atas konten untuk pindah cepat antar-halaman plugin (gaya WooCommerce: "Users · Group · Laporan · Pengaturan"), pakai komponen Pill Tabs / tab underline. **Bukan** pengganti navigasi — navigasi utama tetap submenu WP. Ini sekadar pelengkap; boleh dilewati.

---

## 4. Halaman: Dashboard

### Tujuan Halaman
Memberi operator gambaran cepat kondisi absensi hari ini + ringkasan populasi (jumlah user/group) + akses cepat ke aksi umum. Semua angka berasal dari endpoint nyata: kehadiran dari `GET /laporan/summary`; total user dari `GET /users` (field `total`); total group dari `GET /group`; aktivitas terkini dari `GET /laporan` (baris terbaru).

### User Flow
1. Admin login → landing di Dashboard.
2. Lihat Quick Stats (kehadiran hari ini + populasi).
3. Baca grafik tren kehadiran & daftar absensi terbaru.
4. Klik Quick Action → lompat ke halaman terkait (Tambah User, Lihat Laporan, dll).

### Layout

```
------------------------------------------------------------
Breadcrumb: Absensi › Dashboard
Judul: "Dashboard"                         [⟳ Refresh]
------------------------------------------------------------
QUICK STATS (grid 6 kartu)
[Total User][Total Group][Hadir][Telat][Izin][Alpha]
------------------------------------------------------------
┌───────────────────────────┬──────────────────────────┐
│  GRAFIK KEHADIRAN          │  QUICK ACTION            │
│  (bar/line, rentang)       │  [+ Tambah User]         │
│                            │  [+ Tambah Group]        │
│                            │  [Lihat Laporan]         │
│                            │  [Export Laporan]        │
├───────────────────────────┴──────────────────────────┤
│  ABSENSI TERBARU (tabel ringkas: nama, status, jam)   │
------------------------------------------------------------
```

### Komponen
- **Quick Stats (6 kartu):**
  - Total User (ikon `users`, angka dari `/users` total) — netral/Primary.
  - Total Group (ikon `layers`, dari `/group`) — netral/Primary.
  - Hadir Hari Ini (ikon `check-circle-2`, dari summary `hadir`) — Success.
  - Telat (ikon `clock`, `telat`) — Warning.
  - Izin (ikon `info`, `izin`) — Info/cyan.
  - Alpha (ikon `x-circle`, `alpha`) — Danger.
  - (Sakit tersedia di summary; bila butuh kartu ke-7, pakai Purple. Default 6 kartu utama sesuai permintaan.)
  - Tiap kartu: label Caption, angka Display, sub-teks Caption ("hari ini").
- **Grafik Kehadiran:** card berisi bar/line chart tren hadir/telat/izin/sakit/alpha per rentang (data dari beberapa panggilan `/laporan/summary` per tanggal, atau agregasi `/laporan`). Legend warna sesuai peta status. Judul H2 + filter rentang kecil (opsional).
- **Quick Action:** card daftar tombol pintas ke halaman lain.
- **Absensi Terbaru:** tabel ringkas (avatar+nama, nama_group, status badge, waktu_masuk) dari `GET /laporan` terbaru (limit ~5–10).

### Form
Tak ada form input di Dashboard (read-only + navigasi).

### Button
- **Refresh** (header halaman, ghost/outline, ikon `refresh-cw`) — refetch semua data.
- **Quick Action:** Tambah User (Primary), Tambah Group (Outline), Lihat Laporan (Ghost/link), Export Laporan (Outline, ikon `download`).

### Warna
- Kartu kehadiran memakai warna status masing-masing pada ikon + delta. Grafik pakai palet status. Sisanya netral (Card putih, teks Text Primary/Secondary).

### Interaction
- **Hover:** kartu stat naik ke `shadow-md`; tombol quick action hover sesuai varian.
- **Focus:** tombol/link fokus ring Primary.
- **Loading:** skeleton untuk 6 kartu + grafik + tabel saat load awal.
- **Disabled:** Refresh disabled saat sedang refetch (spinner).
- **Success/Error:** refetch gagal → error state di area grafik/tabel + toast.

### Responsive
- **Desktop:** 6 kartu satu baris; grafik (2/3) + quick action (1/3) berdampingan; tabel penuh.
- **Tablet:** kartu 3/baris (2 baris); grafik & quick action tetap 2 kolom atau tumpuk; tabel scroll-x.
- **Mobile:** kartu 1/baris; grafik full-width; quick action list vertikal; tabel jadi card-list.

### Empty State
Bila belum ada data absensi hari ini: kartu menampilkan angka 0; area "Absensi Terbaru" tampil empty state ("Belum ada absensi hari ini", ikon `clipboard-check`).

### Error State
Gagal load summary → tiap kartu tampil "–" + area utama tampil Error State dengan tombol "Coba Lagi".

### Loading State
Skeleton: 6 balok stat card, 1 balok grafik besar, 5 baris skeleton tabel.

### Accessibility
- Angka stat punya `aria-label` lengkap ("Hadir hari ini: 06").
- Grafik sertakan tabel data tersembunyi / `aria` ringkas untuk screen reader.
- Warna status tak jadi satu-satunya penanda — selalu ada teks status.

---

## 5. Halaman: Users

### Tujuan Halaman
Kelola master orang yang diabsen (siswa/guru/staff dalam satu tabel, dibedakan lewat tipe group). CRUD user, bind kartu RFID, dan import massal via Excel. Endpoint: `GET/POST /users`, `GET/PUT/DELETE /users/{id}`, `POST /users/{id}/rfid`, `POST /users/import`.

### User Flow
1. Buka Users → tabel termuat (`GET /users`, paginated).
2. (Opsional) filter Group / ketik pencarian → tabel ter-refetch.
3. Klik **Tambah User** → modal form → isi → Simpan (`POST`) → toast sukses → tabel refresh.
4. Klik **Edit** pada baris → modal terisi → ubah → Simpan (`PUT`).
5. Klik **Bind RFID** → modal → tap kartu (UID masuk) → Simpan (`POST /users/{id}/rfid`).
6. Klik **Import Excel** → pilih file → unggah (`POST /users/import`) → tampil hasil imported/gagal/errors.
7. Klik **Hapus** → konfirmasi → `DELETE`.

### Layout

```
------------------------------------------------------------
Breadcrumb: Absensi › Users
Judul: "Users"          [Import Excel] [+ Tambah User]
------------------------------------------------------------
FILTER BAR:
[Search: cari nama/nomor induk]   [Filter Group ▾]
------------------------------------------------------------
TABLE:
| User (avatar+nama+no.induk) | Group | Tipe | RFID | Aksi |
| ...                         | ...   | ...  | •••• | ⋯   |
------------------------------------------------------------
Menampilkan 1–10 dari N        [ ‹ 1 2 3 … › ]
------------------------------------------------------------
```

### Komponen
- **Filter Bar:** Search (ikon search, debounce 300ms, kirim param `search`), Select Group (opsi dari `GET /group`, kirim `group_id`), reset filter.
- **Tabel Users** (kolom):
  - **User** — avatar (inisial/foto) + nama (Body Strong) + nomor induk (Caption).
  - **Group** — nama_group.
  - **Tipe** — badge tipe group (Kelas/Guru/Staff).
  - **RFID** — UID di-mask (mis. `••••E5F6`) bila ada; "—" bila belum bind.
  - **Aksi** — menu ⋯ atau tombol ikon: Edit (`pencil`), Bind RFID (`credit-card`), Hapus (`trash-2`).
- **Pagination** bawah.
- **Modal Form User** (tambah/edit).
- **Modal Bind RFID.**
- **Modal Import Excel.**
- **Modal Konfirmasi Hapus.**

### Form

**Form Tambah/Edit User (Modal):**

| Field | Tipe | Aturan | Param |
|---|---|---|---|
| Nomor Induk | text | wajib, ≤30 | `nomor_induk` |
| Nama | text | wajib, ≤150 | `nama` |
| Group | select | opsi dari `/group` | `group_id` |
| RFID UID | text | opsional (bisa juga via Bind) | `rfid_uid` |

Validasi client: nomor induk & nama tak boleh kosong. Server bisa balas **409** (nomor_induk / rfid_uid duplikat) → tampilkan error di field terkait.

**Modal Bind RFID:**
- Judul "Bind Kartu RFID — {nama}".
- Input UID **auto-focus** (tap scanner mengisi otomatis) + hint "Tap kartu pada scanner".
- Checkbox / konfirmasi **Ganti kartu** (kirim `replace=true`) — muncul bila user sudah punya kartu.
- Tombol Simpan (`POST /users/{id}/rfid`). Tangani 409 `kartu_terpakai` (UID milik user lain — tampil nama pemilik) & `sudah_punya_kartu` (minta centang Ganti).

**Modal Import Excel:**
- Area upload (drag-drop / pilih file `.xlsx`), ikon `file-spreadsheet`.
- Info kolom wajib: **nama, nomor_induk, group** (group tak ada → auto-create). Cap **2000 baris**.
- Setelah unggah: ringkasan **imported** (sukses), **gagal**, dan tabel **errors** `[{baris, pesan}]`.
- 503 → alert "Fitur import belum tersedia (komponen server belum terpasang)".

### Button
- **Tambah User** (header halaman, Primary, ikon `plus`).
- **Import Excel** (header halaman, Outline, ikon `upload`).
- Per baris: **Edit** (ghost ikon), **Bind RFID** (ghost ikon), **Hapus** (danger ghost ikon).
- Modal: **Simpan** (Primary), **Batal** (Outline/Ghost), **Hapus** (Danger).

### Warna
- Tombol Tambah = Primary. Hapus = Danger. Badge tipe group sesuai peta. RFID mask = Text Secondary. Sukses/gagal via toast.

### Interaction
- **Hover:** baris tabel highlight; tombol aksi muncul lebih jelas.
- **Focus:** input modal fokus ring Primary; input Bind RFID auto-focus saat modal buka.
- **Loading:** skeleton tabel saat load/refetch; tombol Simpan → spinner "Menyimpan…"; Import → progress.
- **Disabled:** Simpan disabled bila field wajib kosong.
- **Success:** toast hijau "User disimpan" + tabel refresh + modal tutup.
- **Error:** field error inline (409/422); toast merah untuk error umum.

### Responsive
- **Desktop:** tabel penuh, filter satu baris.
- **Tablet:** tabel scroll-x; filter bisa wrap.
- **Mobile:** tabel → **card-list** (tiap user satu card: nama+no.induk, group/tipe, RFID, tombol aksi); filter jadi dropdown/sheet; modal full-width.

### Empty State
Tabel kosong (belum ada user / hasil filter kosong): empty state "Belum ada user" + tombol "Tambah User"; untuk hasil filter: "Tak ada user cocok" + tombol "Reset filter".

### Error State
Gagal `GET /users` → error state + "Coba Lagi". Gagal aksi (create/update/delete) → toast + pertahankan modal agar user bisa perbaiki.

### Loading State
Skeleton 10 baris (avatar bulat + 4 balok teks). Modal saat submit: tombol spinner. Import: progress bar/spinner + teks "Mengunggah… memproses baris".

### Accessibility
- Modal: focus-trap, Esc menutup, `aria-labelledby` judul.
- Tombol ikon punya `aria-label` ("Edit user", "Hapus user", "Bind RFID").
- Input Bind RFID: `aria-describedby` hint tap kartu.
- RFID mask tetap sertakan `aria` status ("Kartu terpasang" / "Belum ada kartu").

---

## 6. Halaman: Group

### Tujuan Halaman
Kelola kelompok absen (kelas/guru/staff). CRUD group + lihat jumlah user per group. Endpoint: `GET/POST /group`, `GET/PUT/DELETE /group/{id}`.

### User Flow
1. Buka Group → tabel termuat (`GET /group`, tiap baris bawa `jumlah_user`).
2. **Tambah Group** → modal → nama + tipe → Simpan (`POST`).
3. **Edit** → modal terisi → Simpan (`PUT`).
4. **Hapus** → konfirmasi → `DELETE`. Bila `jumlah_user > 0` → server balas **409 `group_ada_user`** → tampilkan pesan "Pindahkan/hapus user dulu".

### Layout

```
------------------------------------------------------------
Breadcrumb: Absensi › Group
Judul: "Group"                          [+ Tambah Group]
------------------------------------------------------------
TABLE:
| Nama Group | Tipe | Jumlah User | Aksi |
| Kelas 7A   |Kelas | 32          | ⋯   |
------------------------------------------------------------
(Pagination bila banyak)
------------------------------------------------------------
```

### Komponen
- **Tabel Group:** kolom Nama (Body Strong), Tipe (badge Kelas/Guru/Staff), Jumlah User (angka + ikon `users` kecil), Aksi (Edit/Hapus).
- **Modal Form Group.**
- **Modal Konfirmasi Hapus.**

### Form

**Form Tambah/Edit Group (Modal):**

| Field | Tipe | Aturan | Param |
|---|---|---|---|
| Nama Group | text | wajib, ≤100 | `nama` |
| Tipe | select | Kelas (default) / Guru / Staff | `tipe` |

### Button
- **Tambah Group** (Primary, ikon `plus`).
- Per baris: **Edit** (ghost), **Hapus** (danger ghost).
- Modal: **Simpan** (Primary), **Batal** (Outline).

### Warna
- Badge tipe: Kelas = Primary 12%, Guru = Purple 12%, Staff = Info 12%. Hapus = Danger.

### Interaction
- **Hover:** baris highlight.
- **Loading:** skeleton tabel; Simpan spinner.
- **Success:** toast "Group disimpan" + refresh.
- **Error:** **409 group_ada_user** → alert dalam modal konfirmasi hapus ("Group masih memiliki {n} user"). 422 nama kosong → error field.

### Responsive
- Desktop: tabel penuh. Tablet: scroll-x. Mobile: card-list (nama, tipe badge, jumlah user, aksi).

### Empty State
"Belum ada group" + tombol "Tambah Group".

### Error State
Gagal load → error state + Coba Lagi.

### Loading State
Skeleton baris tabel; modal submit spinner.

### Accessibility
- Modal focus-trap + Esc. Tombol ikon `aria-label`. Konfirmasi hapus jelaskan konsekuensi 409 sebelum kirim bila `jumlah_user` diketahui > 0 (disable tombol hapus + tooltip).

---

## 7. Halaman: Absen RFID (Admin) — DIHAPUS

> **Submenu & halaman admin "Absen RFID" DIBUANG** (BE: hapus dari `Menu.php` + view `rfid.php`). Tak perlu didesain.
> - **Bind kartu RFID** → di halaman **Users** (§5), popup Bind RFID → `POST /users/{id}/rfid`.
> - **Tap-absen RFID** → di **kiosk publik** Absensi Guru (§11) → `POST /absen/rfid`.
> - Endpoint lama `/absen/rfid/enroll` & `/absen/rfid/resolve` sudah 404.
>
> (Nomor bagian §7 dipertahankan agar tautan §8–§11 tak bergeser.)

---

## 8. Halaman: Laporan

### Tujuan Halaman
Rekap kehadiran per rentang + ringkasan + export (CSV/XLSX/PDF). Endpoint: `GET /laporan`, `GET /laporan/summary`, `GET /laporan/export`. Label pakai **"Group"** & **"Nomor Induk"**.

### User Flow
1. Buka Laporan → default rentang (mis. bulan ini) → summary + tabel termuat.
2. Ubah filter (dari/sampai atau preset, group_id) → refetch summary + tabel.
3. (Opsional) cari dalam hasil.
4. Klik **Export** → pilih format → unduh.

### Layout

```
------------------------------------------------------------
Breadcrumb: Absensi › Laporan
Judul: "Laporan"                        [Export ▾]
------------------------------------------------------------
SUMMARY CARDS: [Hadir][Telat][Izin][Sakit][Alpha][Total]
------------------------------------------------------------
FILTER (server): [Dari 📅][Sampai 📅][Preset ▾][Group ▾]
STATUS (client pill-tabs): (Semua)(Hadir)(Telat)(Izin)(Sakit)(Alpha)
------------------------------------------------------------
(Opsional) GRAFIK ringkas per status
------------------------------------------------------------
TABLE:
| Tgl | Nama | No.Induk | Group | Status | Masuk | Keluar |
|     |      |          |       | badge  |  jam  |  jam   |  (Metode, Jarak, Bukti)
------------------------------------------------------------
Menampilkan 1–10 dari N        [ ‹ 1 2 3 … › ]
------------------------------------------------------------
```

### Komponen
- **Summary Cards (6):** Hadir (Success), Telat (Warning), Izin (Info), Sakit (Purple), Alpha (Danger), Total (Primary/netral) — dari `GET /laporan/summary`.
- **Filter server:** Date Picker `dari` & `sampai`, Select `preset` (harian/mingguan/bulanan), Select `group_id`, paging (`per_page`, `page`). (Search opsional pada hasil.)
- **Filter status (pill-tabs, client-side):** Semua/Hadir/Telat/Izin/Sakit/Alpha — saring baris yang sudah dimuat (backend tak punya param status).
- **Grafik ringkas (opsional):** distribusi status pada rentang (donut/bar) — dari summary.
- **Tabel Rekap:** kolom Tanggal, Nama, Nomor Induk, Group (nama_group), Status (badge), Waktu Masuk, Waktu Keluar, Metode Masuk/Keluar, Jarak (m), Bukti (`bukti_url` bila ada — link, saat ini dormant). Respons `{data[], total, page, per_page, total_page}`.
- **Pagination.**
- **Menu Export** (dropdown format).

### Form
Tak ada form input data; hanya filter (date, select).

### Button
- **Export** (Outline, ikon `download`) → dropdown: CSV, XLSX, PDF → `GET /laporan/export?format=…` + filter aktif → unduh.
- Filter: tombol **Terapkan** (Primary) & **Reset** (Ghost) bila filter tak auto-apply.

### Warna
- Summary cards & badge status pakai peta warna kehadiran. Export netral (Outline).

### Interaction
- **Hover:** baris tabel highlight; kartu summary `shadow-md`.
- **Loading:** skeleton summary + tabel saat refetch filter.
- **Disabled:** Export disabled saat data kosong.
- **Success:** export → toast "File diunduh" (atau trigger download).
- **Error:** **503 `export_unavailable`** untuk XLSX/PDF (vendor absen) → toast "Export {format} belum tersedia, gunakan CSV". Gagal load → error state.

### Responsive
- **Desktop:** summary 6/baris; filter satu baris; tabel penuh.
- **Tablet:** summary 3/baris; filter wrap; tabel scroll-x.
- **Mobile:** summary 2/baris; filter dalam sheet/accordion; tabel → card-list (tanggal, nama, status badge, masuk/keluar).

### Empty State
Rentang tanpa data: summary semua 0 + tabel empty state ("Tak ada data pada rentang ini", saran ubah filter).

### Error State
Gagal load summary/tabel → error state + Coba Lagi.

### Loading State
Skeleton 6 summary card + baris tabel. Export → spinner pada tombol.

### Accessibility
- Date picker keyboard-navigable. Summary `aria-label`. Tabel header `scope="col"`. Tombol export dropdown ARIA menu.

---

## 9. Halaman: Pengaturan

### Tujuan Halaman
Konfigurasi sistem: lokasi/GPS, jam kerja, RFID, retensi, WhatsApp. Endpoint: `GET /settings` (prefill), `PUT /settings` (simpan). Prefill juga dari `AbsensiAdmin.settings`. Dikelompokkan jadi beberapa **card**.

### User Flow
1. Buka Pengaturan → form terisi nilai saat ini (`GET /settings` / prefill).
2. Ubah field per card.
3. **Simpan** → `PUT /settings` → toast sukses.

### Layout

```
------------------------------------------------------------
Breadcrumb: Absensi › Pengaturan
Judul: "Pengaturan"                        [Simpan]
------------------------------------------------------------
┌──────────────── CARD: Lokasi & GPS ─────────────────┐
│ Latitude | Longitude | (map picker) | Radius (m)     │
│ Akurasi GPS Maks (m)                                 │
└──────────────────────────────────────────────────────┘
┌──────────────── CARD: Jam Kerja ────────────────────┐
│ Jam Masuk | Jam Keluar | Toleransi Telat (menit)     │
└──────────────────────────────────────────────────────┘
┌──────────────── CARD: RFID ─────────────────────────┐
│ Debounce Anti Double-Tap (detik)                     │
└──────────────────────────────────────────────────────┘
┌──────────────── CARD: Retensi ──────────────────────┐
│ Retensi Foto Selfie (hari)                           │
└──────────────────────────────────────────────────────┘
┌──────────────── CARD: WhatsApp (opsional) ──────────┐
│ Gateway URL | Token (tak di-prefill)   [luar MVP]    │
└──────────────────────────────────────────────────────┘
------------------------------------------------------------
```

### Komponen
Lima card kelompok pengaturan (Lokasi/GPS, Jam Kerja, RFID, Retensi, WhatsApp). Setiap card: judul H3 + deskripsi singkat + field. Tombol **Simpan** global (header halaman/bawah) atau per-card (pilih satu pola — konsisten; disarankan Simpan global).

### Form

| Card | Field | Tipe | Default | Param |
|---|---|---|---|---|
| Lokasi & GPS | Latitude | number | — | `absensi_lat` |
| | Longitude | number | — | `absensi_lng` |
| | Radius (m) | number | 100 | `absensi_radius` (server clamp maks 500) |
| | Akurasi GPS Maks (m) | number | 100 | `absensi_akurasi_max` |
| Jam Kerja | Jam Masuk | time | 07:00 | `absensi_jam_masuk` |
| | Jam Keluar | time | 15:00 | `absensi_jam_keluar` |
| | Toleransi Telat (menit) | number | 15 | `absensi_telat_menit` |
| RFID | Debounce (detik) | number | 3 | `absensi_rfid_debounce` |
| Retensi | Retensi Foto (hari) | number | 90 | `absensi_retensi_hari` |
| WhatsApp | Gateway URL | text | — | `absensi_wa_gateway` |
| | Token | password | (tak di-prefill) | `absensi_wa_token` |

> **Map picker** untuk Latitude/Longitude sangat disarankan (pilih titik sekolah di peta). Field lat/lng terisi otomatis dari titik.
> **Radius:** server clamp maksimum 500 — beri hint & validasi client.
> **WhatsApp:** notifikasi WA = **luar MVP**. Card boleh **disembunyikan** atau ditandai "Belum aktif". `wa_token` **tak** di-prefill (sensitif) — placeholder "••••" bila sudah terisi.

### Button
- **Simpan** (Primary) — `PUT /settings`.
- (Opsional) **Reset ke default** (Ghost) per card.

### Warna
- Card netral (putih). Simpan Primary. Hint validasi Warning/Danger.

### Interaction
- **Focus:** input fokus ring Primary.
- **Loading:** Simpan → spinner "Menyimpan…".
- **Disabled:** Simpan disabled bila tak ada perubahan (dirty-check) — opsional.
- **Success:** toast "Pengaturan disimpan".
- **Error:** 422 (mis. lat/lng di luar rentang) → error field terkait; toast untuk error umum.

### Responsive
- Desktop: card 2 kolom (mis. Lokasi & Jam Kerja berdampingan) atau 1 kolom lebar. Mobile: 1 kolom, field full-width.

### Empty State
Tak berlaku (form selalu punya nilai default).

### Error State
Gagal `GET /settings` → banner error di atas form + tombol Coba Lagi (fallback ke `AbsensiAdmin.settings`).

### Loading State
Skeleton field saat load awal; Simpan spinner.

### Accessibility
- Label terkait tiap input (`for`/`id`). Grup card pakai `fieldset`/`legend` semantik. Field number punya `min`/`max` (radius ≤500). Token field `type=password` + toggle lihat.

---

## 10. Halaman Publik: Absensi Siswa (Kiosk)

> **JANGAN pakai layout dashboard.** Ini **kiosk publik fullscreen**, tanpa sidebar/topbar. Fokus tunggal: satu alur absen di tengah layar, tombol besar, mudah dipakai di HP/tablet. Tanpa login. **HTTPS wajib** (kamera+GPS). Endpoint: `POST /absen/selfie`. Config: `AbsensiConfig` (`restUrl, nonce, akurasiMax`).

### Tujuan Halaman
Siswa (atau siapa pun terdaftar) absen mandiri: ketik nomor induk → ambil lokasi GPS → ambil selfie → kirim → lihat hasil.

### User Flow
1. Buka halaman `/absensi-siswa`.
2. Izinkan lokasi (GPS) & kamera saat diminta browser.
3. Ketik **Nomor Induk**.
4. Sistem ambil GPS (lat/lng/accuracy) → tampil status akurasi.
5. Ambil **selfie** (opsional) → preview → bisa retake.
6. (Opsional) pilih sesi Masuk/Pulang.
7. Klik **Absen Sekarang** → kirim → tampil hasil (sukses hadir/telat/pulang, atau error).
8. (Opsional) **Cek Status Hari Ini** via nomor induk.

### Layout

```
        ┌───────────────────────────────────────┐
        │          ABSENSI SISWA (judul)         │
        │      Selamat datang, silakan absen     │
        ├───────────────────────────────────────┤
        │  [ Nomor Induk : ____________ ]        │
        │                                         │
        │  📍 Status GPS: mencari lokasi…         │
        │     Akurasi: 25 m (OK)                  │
        │                                         │
        │  ┌─────── CAMERA PREVIEW ───────┐       │
        │  │                              │       │
        │  └──────────────────────────────┘       │
        │  [ Ambil Foto ]  [ Ulang Foto ]         │
        │                                         │
        │  Sesi:  ( Masuk )  ( Pulang )           │
        │                                         │
        │  [        ABSEN SEKARANG         ]      │
        │                                         │
        │  ── Area Hasil / Feedback ──            │
        │  [ Cek Status Hari Ini ]                │
        └───────────────────────────────────────┘
```

### Komponen
- **Header kiosk:** judul "Absensi Siswa" + sub-instruksi.
- **Input Nomor Induk:** besar, wajib, ≤30, ikon `id-card`.
- **Status GPS:** ikon `map-pin` + teks state:
  - "Mencari lokasi…" (loading, spinner).
  - "Lokasi didapat · Akurasi {n} m" + indikator OK/kurang (bandingkan `akurasiMax`, default 100 — bila `accuracy > akurasiMax` → warning kuning "Akurasi rendah, cari sinyal lebih baik").
  - "Izin lokasi ditolak" (error, tombol coba lagi).
- **Camera Preview:** area video (`getUserMedia`), tombol **Ambil Foto** (`camera`) → capture frame → base64; tombol **Ulang Foto** (`rotate-ccw`) muncul setelah capture. Foto **opsional**.
- **Toggle Sesi:** Masuk / Pulang (opsional; kosong = auto server).
- **Tombol Absen Sekarang:** besar Primary, disabled sampai GPS siap.
- **Area Hasil:** kartu feedback (lihat state).
- **Cek Status Hari Ini:** input nomor induk + tombol → `GET /absen/status` → tampil `sudah_absen`, `nama`, `rekap{status, waktu_masuk, waktu_keluar}`.

### Form

| Field | Tipe | Aturan | Param |
|---|---|---|---|
| Nomor Induk | text | wajib, ≤30 | `nomor_induk` |
| (GPS) Latitude/Longitude | auto (Geolocation) | wajib | `lat`, `lng` |
| (GPS) Accuracy | auto | opsional | `accuracy` |
| Foto selfie | capture kamera | opsional (base64) | `foto` |
| Sesi | toggle | opsional (masuk/pulang) | `sesi` |

### Button
- **Ambil Foto** (Primary/Secondary besar, ikon `camera`).
- **Ulang Foto** (Outline, ikon `rotate-ccw`).
- **Absen Sekarang** (Primary lg, ikon `send`/`check`) — submit.
- **Cek Status Hari Ini** (Outline/Ghost).

### Warna
- CTA "Absen Sekarang" = Primary. Status GPS OK = Success; akurasi rendah = Warning; izin ditolak = Danger. Hasil sukses hadir = Success; telat = Warning; pulang = Info/Primary. Error = Danger.

### Interaction
- **Hover/Focus:** tombol besar hover jelas; input fokus ring Primary.
- **Loading GPS:** spinner + teks "mencari lokasi"; tombol Absen disabled.
- **Loading submit:** tombol → spinner "Mengirim…".
- **Disabled:** Absen disabled bila nomor kosong / GPS belum siap.
- **Permission Camera:** bila ditolak → pesan "Izinkan kamera untuk selfie" (foto jadi opsional, tetap bisa absen tanpa foto). 
- **Permission GPS:** bila ditolak → pesan + tombol "Aktifkan Lokasi"; tak bisa absen tanpa GPS (lat/lng wajib).
- **Success:** kartu hijau besar "Absen berhasil!" (hadir) / kuning "Diterima, Anda terlambat" (telat) / info "Absen pulang berhasil". Tampilkan nama & jam.
- **Error:** kartu merah dengan pesan spesifik per code.

#### State Respons (WAJIB ditangani)

| HTTP | code | Tampilan kiosk |
|---|---|---|
| 201 | — masuk | Hijau "Absen berhasil!" (hadir) / Kuning "Anda terlambat" (telat). `{status, jarak, message}`. |
| 200 | — pulang | Info "Absen pulang berhasil!" `{jarak, message}`. |
| 404 | `nomor_tidak_terdaftar` | Merah "Nomor induk tidak terdaftar". |
| 403 | `diluar_radius` | Merah + pesan jarak/batas dari server. |
| 403 | `butuh_https` | Merah "Butuh koneksi aman (HTTPS)". |
| 422 | `nomor_kosong`/`koordinat_invalid`/`akurasi_rendah` | Kuning/merah + pesan detail. |
| 409 | `sudah_absen`/`sudah_absen_keluar`/`belum_absen_masuk` | Info/kuning + pesan. |
| 429 | `terlalu_cepat` | Kuning "Terlalu cepat, tunggu sebentar". |
| 503 | `sekolah_belum_diatur` | Merah "Koordinat sekolah belum diatur, hubungi admin". |

### Responsive
- Konten center, max-width ~480px, padding besar. Font & tombol lebih besar di layar besar. Di HP: full-width, tombol full, kamera fit lebar.

### Empty State
Sebelum input: form bersih + instruksi. GPS belum diambil: status "menunggu lokasi".

### Error State
GPS gagal / kamera gagal / jaringan gagal → pesan jelas + tombol coba lagi. Tak menghalangi retry.

### Loading State
- GPS: spinner status lokasi.
- Submit: tombol spinner + overlay ringan.

### Accessibility
- Input nomor induk `type="text" inputmode="numeric"` bila NIS numerik; label jelas.
- Status GPS & hasil pakai `aria-live="assertive"` (diumumkan).
- Kontras tombol/teks tinggi. Target sentuh ≥44px.
- Pesan izin kamera/GPS jelas + tautan cara mengaktifkan.

---

## 11. Halaman Publik: Absensi Guru (Kiosk RFID)

> **JANGAN pakai layout dashboard.** Kiosk **fullscreen**, input RFID **tersembunyi/tak menonjol**, feedback **BESAR** terbaca dari jauh. Perangkat di meja + scanner RFID (HID keyboard). Endpoint: `POST /absen/rfid`. Config: `AbsensiConfig.rfidDebounce`.

### Tujuan Halaman
Kiosk tap kartu: tap → scanner "ketik" UID + Enter → kirim → tampil nama+status besar → auto siap tap berikut.

### User Flow
1. Buka `/absensi-guru` di perangkat kiosk (fullscreen).
2. Sistem auto-fokus ke input UID tersembunyi ("Siap scan").
3. Orang tap kartu → UID terisi + Enter → submit otomatis.
4. Layar tampil feedback besar (nama + status masuk/keluar).
5. Setelah jeda singkat → reset → "Siap scan" lagi.

### Layout

```
┌───────────────────────────────────────────────────────┐
│                                          🕐 07:14:32    │  (jam besar, pojok)
│                                                         │
│                                                         │
│                  ┌───────────────┐                      │
│                  │   (ikon/avatar)│                      │
│                  └───────────────┘                      │
│                                                         │
│                B U D I   S A N T O S O                  │  (nama BESAR)
│                                                         │
│                  ✓  MASUK — HADIR                       │  (status BESAR badge)
│              "Selamat datang, Budi!"                    │
│                                                         │
│              (input UID tersembunyi, auto-focus)        │
│                                                         │
│                    Siap scan kartu…                     │  (status idle saat reset)
└───────────────────────────────────────────────────────┘
```

### Komponen
- **Jam besar** (real-time, tabular-nums, pojok atau tengah-atas) — ikon `clock`.
- **Input UID tersembunyi:** auto-focus permanen; setelah submit → clear → refocus. Tak menonjol (kiosk otomatis).
- **Area Feedback besar:**
  - Ikon/avatar (opsional).
  - **Nama** (Display 36–48px Bold).
  - **Status** badge besar (masuk/keluar + hadir/telat) — warna peta status.
  - Pesan sambutan ("Selamat datang, {nama}!" / "Selamat siang, {nama}! Waktu keluar dicatat.").
- **Status idle:** "Siap scan kartu…" saat menunggu.

### Form
- Hanya input UID (`rfid_uid`) tersembunyi, auto-submit saat Enter.

### Button
- Tak ada tombol utama (otomatis). Opsional: tombol kecil "Keluar Mode Kiosk" (pojok, ghost) untuk operator.

### Warna
- Latar kiosk netral/gelap-lembut agar feedback menonjol (boleh latar `#F8FAFC` atau gelap `#1E293B` — pilih satu, konsisten). Status: Hadir hijau, Telat kuning, Keluar info/cyan. Error merah. Idle netral.

### Interaction
- **Focus:** input UID selalu fokus (klik di mana pun → refocus).
- **Loading:** singkat (spinner kecil) saat kirim — biasanya cepat.
- **Success:** feedback besar muncul (animasi fade/scale) → tahan ~2–3 dtk → reset ke idle.
- **Auto Ready Scan:** setelah feedback, otomatis kembali "Siap scan".
- **Error:** feedback besar merah.

#### State Respons (WAJIB ditangani)

| HTTP | field/code | Tampilan besar |
|---|---|---|
| 201 | `{action:'masuk', status, siswa}` | Hijau/kuning "MASUK — HADIR/TELAT" + "Selamat datang, {siswa}!" |
| 200 | `{action:'keluar', siswa}` | Info "KELUAR" + "Selamat siang, {siswa}! Waktu keluar dicatat." |
| 404 | `uid_tidak_terdaftar` | Merah "Kartu tidak terdaftar" |
| 429 | `double_tap` | Kuning "Kartu baru saja di-tap, tunggu sebentar" |
| 409 | `sudah_absen` | Info "{siswa} sudah absen masuk & keluar hari ini" |

### Responsive
- Selalu fullscreen. Di layar besar (kiosk TV/tablet): font sangat besar. Di HP (fallback): tetap besar, center.

### Empty State
Idle: "Siap scan kartu…" + jam berjalan.

### Error State
Gagal jaringan → feedback merah "Gagal, coba tap lagi" → auto reset.

### Loading State
Spinner singkat saat request; jangan menghalangi tap berikutnya lama-lama.

### Accessibility
- Feedback `aria-live="assertive"` (diumumkan untuk audio kiosk).
- Kontras tinggi, font besar. Input tersembunyi tetap punya `aria-label="Input kartu RFID"`.
- Suara opsional (beep sukses/gagal) membantu operator tanpa lihat layar.

---

## 12. Design Rules & Konsistensi

Aturan mengikat agar seluruh app satu identitas:

1. **Radius konsisten:** Card 12px, tombol/input 8px, badge full. Tak ada radius acak.
2. **Shadow konsisten:** Card diam `shadow-sm`, hover `shadow-md`, modal/drawer `shadow-lg`. Tak ada shadow keras.
3. **Grid & spacing konsisten:** gap 16px antar-card, 24px antar-section, padding card 20px, sel tabel 12/16px. Basis 4px.
4. **Warna konsisten:** Primary **biru** hanya untuk aksi utama & item aktif. **Ungu `#8B5CF6` DIKUNCI untuk status "Sakit"** — jangan jadi brand/tombol. Status kehadiran **selalu** pakai peta warna (§2.1) di badge, kartu, grafik. Danger hanya destruktif/error.
5. **Tipografi konsisten:** skala §2.2 dipakai seragam. Judul halaman H1, judul card H2/H3, isi Body.
6. **Ikon konsisten:** satu set (Lucide), ukuran & stroke seragam, makna tetap (mis. `trash-2` selalu hapus).
7. **Dua konteks tak dicampur:** Admin = **konten di dalam wp-admin** (navigasi/sidebar milik WordPress; FE desain `.wrap` saja, tanpa sidebar/topbar plugin). Kiosk = frontend fullscreen fokus tunggal.
8. **Feedback seragam:** semua aksi CRUD → toast (sukses hijau / error merah). Semua tabel punya empty/error/loading state. Semua modal punya focus-trap + Esc.
9. **Badge status = kontrak visual:** hadir hijau, telat kuning, izin cyan, sakit ungu, alpha merah — tak boleh beda di halaman mana pun.
10. **Label domain seragam:** "Group" (bukan Kelas), "Nomor Induk" (bukan NIS) di seluruh UI + export.

---

## 13. Lampiran: Peta Endpoint & State

### Peta Halaman → Endpoint

| Halaman | Endpoint dipakai | Permission |
|---|---|---|
| Dashboard | `GET /laporan/summary`, `GET /users`, `GET /group`, `GET /laporan` | admin (`manage_options`) |
| Users | `GET/POST /users`, `GET/PUT/DELETE /users/{id}`, `POST /users/{id}/rfid`, `POST /users/import` | admin |
| Group | `GET/POST /group`, `GET/PUT/DELETE /group/{id}` | admin |
| Laporan | `GET /laporan`, `GET /laporan/summary`, `GET /laporan/export` | admin |
| Pengaturan | `GET/PUT /settings` | admin |
| Absensi Siswa (kiosk) | `POST /absen/selfie`, `GET /absen/status` | publik |
| Absensi Guru (kiosk) | `POST /absen/rfid` | publik |

### Peta Warna State Global

| State | Warna | Komponen |
|---|---|---|
| Sukses / Hadir | Success `#22C55E` | Toast, badge, kartu, feedback kiosk |
| Peringatan / Telat / rate-limit | Warning `#F59E0B` | Toast, badge, alert |
| Error / Alpha / gagal | Danger `#EF4444` | Toast, badge, error state, feedback kiosk |
| Izin / info | Info `#06B6D4` | Badge, info card |
| Sakit / aksen | Purple `#8B5CF6` | Badge |
| Aksi utama / aktif | Primary `#2563EB` | Tombol, sidebar aktif, fokus |

### Ringkasan Elemen per Halaman (checklist dari RINCIAN-HALAMAN.md)

- **Absensi Siswa:** ✅ nama page · input nomor induk · status GPS · indikator akurasi · kamera+capture+retake · toggle sesi · tombol absen · area hasil · cek status · 8 state respons · permission camera/GPS · loading/error/success.
- **Absensi Guru:** ✅ input UID tersembunyi auto-focus · feedback besar (nama+status) · jam besar · loop auto-ready · 5 state respons.
- **Users:** ✅ search · filter group · tabel · tambah/edit/hapus · bind RFID · import Excel · pagination · modal · validasi.
- **Group:** ✅ tambah/edit/hapus · jumlah user · tabel · modal · 409 group_ada_user.
- **Absen RFID (admin):** ❌ DIHAPUS — bind via Users (`/users/{id}/rfid`), tap via kiosk guru (`/absen/rfid`); endpoint enroll/resolve lama dihapus.
- **Laporan:** ✅ summary card · filter (dari/sampai/preset/group) · search · export csv/xlsx/pdf (503 guard) · grafik · tabel · pagination.
- **Pengaturan:** ✅ card Lokasi/GPS · Jam Kerja · RFID · Retensi · WhatsApp (luar MVP, token tak prefill).
- **Dashboard:** ✅ quick stats (user/group/hadir/telat/izin/alpha) · grafik kehadiran · absensi terbaru · quick action.

---

*Dokumen ini turunan langsung dari RINCIAN-HALAMAN.md. Bila ada konflik, RINCIAN-HALAMAN.md + kode backend adalah acuan final. Kontrak REST per-endpoint: `tests/manual/UC-pivot-*.md`.*
