<?php
namespace Absensi;

defined( 'ABSPATH' ) || exit;

/**
 * Mengelola instalasi, aktivasi, dan penghapusan plugin.
 * Dipanggil oleh register_activation_hook di file utama.
 */
class Installer {

    /** Versi skema DB – naikkan setiap ada perubahan tabel. */
    const DB_VERSION = '2.5.0';

    /**
     * Capability gerbang akses kiosk RFID (page /absensi/guru + endpoint /absen/rfid).
     * Dimiliki role `guru` DAN administrator. Login = akses saja; identitas absen tetap dari rfid_uid.
     */
    const CAP_RFID = 'absensi_rfid';

    public static function activate(): void {
        self::create_tables();
        self::seed_default_options();
        self::seed_roles();
        self::seed_pages();
        Retensi::schedule();
        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        self::remove_pages();
        Retensi::unschedule();
        flush_rewrite_rules();
    }

    /**
     * Migration runner — dipanggil di plugins_loaded (Plugin::boot()).
     * Murah & idempotent: hanya jalankan dbDelta saat versi skema tersimpan < DB_VERSION,
     * jadi tak perlu deactivate+activate ulang setelah update skema. dbDelta aman dijalankan
     * berulang (CREATE/ALTER hanya untuk selisih). Return true bila upgrade dijalankan.
     */
    public static function maybe_upgrade(): bool {
        $installed = (string) get_option( 'absensi_db_version', '0' );
        if ( version_compare( $installed, self::DB_VERSION, '>=' ) ) {
            return false; // skema sudah terkini
        }
        // Migrasi breaking v2 (rename tabel/kolom) HARUS sebelum create_tables:
        // dbDelta hanya CREATE/ALTER-tambah, tak bisa RENAME. Idempotent → aman diulang.
        if ( version_compare( $installed, '2.0.0', '<' ) ) {
            self::migrate_to_v2();
        }
        // v2.1.0: tipe group ENUM → VARCHAR (tipe kustom). dbDelta tak bisa ubah ENUM→VARCHAR.
        if ( version_compare( $installed, '2.1.0', '<' ) ) {
            self::migrate_to_v2_1();
        }
        self::create_tables(); // dbDelta + update_option( 'absensi_db_version', DB_VERSION )
        // Re-seed option default (idempotent) supaya upgrade lewat maybe_upgrade tetap
        // sinkron tanpa harus deactivate+activate ulang.
        self::seed_default_options();
        // v2.3.0: sapu baris yatim warisan (butuh tabel sudah ada → setelah create_tables).
        if ( version_compare( $installed, '2.3.0', '<' ) ) {
            self::bersihkan_yatim();
        }
        // v2.4.0: buang role & cap pra-pivot yang menempel di DB.
        if ( version_compare( $installed, '2.4.0', '<' ) ) {
            self::bersihkan_role_warisan();
        }
        // Langkah migrasi per-versi berikutnya (backfill data) ditambah di sini.
        return true;
    }

    /**
     * v2.4.0 — buang role & capability pra-pivot yang menempel di DB.
     *
     * Sebelum pivot ada role `absensi_admin`/`absensi_siswa`/`orang_tua` + cap absen di
     * administrator. Tak ada gate v2 yang membacanya (gate pakai `manage_options` &
     * `absensi_rfid`), jadi inert — tapi mengotori dropdown role dan MENETAP selamanya di
     * situs produksi yang upgrade dari pra-pivot (uninstall.php cuma jalan saat plugin DIHAPUS).
     * Dibersihkan di sini supaya ikut rapi tiap upgrade. Idempotent.
     *
     * `guru` + `administrator` + cap `absensi_rfid` (v2) DIPERTAHANKAN. Daftar cap/role di sini
     * WAJIB sinkron dengan uninstall.php.
     */
    private static function bersihkan_role_warisan(): void {
        // Cap pra-pivot yang mungkin masih menempel di administrator (absensi_rfid v2 DIPERTAHANKAN).
        $caps_warisan = [ 'absensi_submit_self', 'absensi_submit_rfid', 'absensi_enroll_rfid', 'absensi_view_reports', 'absensi_view_child' ];
        $admin = get_role( 'administrator' );
        if ( $admin ) {
            foreach ( $caps_warisan as $cap ) {
                $admin->remove_cap( $cap );
            }
        }

        // Role pra-pivot. User yang MASIH memegangnya dipindah ke `subscriber` dulu bila itu
        // satu-satunya role-nya — supaya akun tak jadi role-less (terkunci / hilang dari daftar).
        $roles_warisan = [ 'absensi_admin', 'absensi_siswa', 'orang_tua' ];
        foreach ( $roles_warisan as $slug ) {
            if ( ! get_role( $slug ) ) {
                continue;
            }
            foreach ( get_users( [ 'role' => $slug, 'fields' => [ 'ID' ] ] ) as $u ) {
                $wu = new \WP_User( (int) $u->ID );
                $wu->remove_role( $slug );
                if ( empty( $wu->roles ) ) {
                    $wu->add_role( 'subscriber' );
                }
            }
            remove_role( $slug );
        }
    }

    /**
     * v2.3.0 — buang rekap milik user yang sudah dihapus & jadwal milik group yang sudah dihapus.
     *
     * Sebelum versi ini, DELETE user/group tak menyentuh tabel anaknya (skema tanpa FK), jadi
     * barisnya menumpuk: rekap yatim tetap dihitung `/laporan/summary` (COUNT tanpa JOIN users)
     * sehingga angka Hadir/Telat lebih besar dari kenyataan, muncul tanpa nama di Laporan/Export,
     * dan bisa "diwarisi" user/group baru saat id dipakai ulang. Endpoint DELETE sekarang cascade;
     * ini membersihkan sisa lama. Idempotent (jalan sekali, dan aman diulang).
     */
    private static function bersihkan_yatim(): void {
        global $wpdb;
        $p = $wpdb->prefix;

        $wpdb->query( "DELETE r FROM {$p}absensi_rekap r
            LEFT JOIN {$p}absensi_users u ON u.id = r.user_id
            WHERE u.id IS NULL" );

        $wpdb->query( "DELETE j FROM {$p}absensi_jadwal j
            LEFT JOIN {$p}absensi_group g ON g.id = j.group_id
            WHERE g.id IS NULL" );
    }

    // ─── Buat Tabel Custom ───────────────────────────────────────────────────

    private static function create_tables(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Tabel users (eks-siswa) — orang yang diabsen: siswa/guru/staff. Tanpa akun WP.
        dbDelta( "CREATE TABLE {$wpdb->prefix}absensi_users (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            nomor_induk VARCHAR(30)  NOT NULL COMMENT 'NIS/NIP — nomor induk unik',
            nama        VARCHAR(150) NOT NULL,
            group_id    BIGINT UNSIGNED NOT NULL DEFAULT 0,
            rfid_uid    VARCHAR(50)  DEFAULT NULL COMMENT 'UID kartu RFID',
            foto_path   VARCHAR(255) DEFAULT NULL,
            created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY nomor_induk (nomor_induk),
            UNIQUE KEY rfid_uid (rfid_uid)
        ) $charset;" );

        // Tabel group (eks-kelas) — kelompok absen + tipe.
        dbDelta( "CREATE TABLE {$wpdb->prefix}absensi_group (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            nama       VARCHAR(100) NOT NULL,
            tipe       VARCHAR(50) NOT NULL DEFAULT 'kelas' COMMENT 'Tipe kustom bebas (mis. kelas/guru/staff/ekskul)',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset;" );

        // Tabel jadwal (hari & jam masuk/keluar per group)
        dbDelta( "CREATE TABLE {$wpdb->prefix}absensi_jadwal (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            group_id    BIGINT UNSIGNED NOT NULL,
            hari        TINYINT UNSIGNED NOT NULL COMMENT '1=Senin ... 7=Minggu',
            jam_masuk   TIME NOT NULL,
            jam_keluar  TIME NOT NULL,
            PRIMARY KEY (id),
            KEY group_id (group_id)
        ) $charset;" );

        // Tabel rekap absensi (1 baris per user per tanggal)
        //
        // ⚠ JANGAN sejajarkan nama kolom dengan spasi berganda di sini. dbDelta membaca tipe
        // kolom lewat pola "<nama><SATU spasi><tipe>"; dengan spasi berganda ia menganggap tipe
        // kolom kosong, lalu menerbitkan `ALTER … CHANGE COLUMN x x ` tanpa tipe untuk SETIAP
        // kolom. MySQL menolak semuanya, dan — ini bagian yang menipu — kolom BARU pada
        // statement yang sama ikut gagal ditambahkan tanpa pesan apa pun ke pemanggil.
        // Gejalanya: DB_VERSION naik, `create_tables()` "sukses", tapi kolomnya tak pernah ada.
        // Satu spasi, satu ruang. Hindari juga tanda kurung di dalam COMMENT.
        dbDelta( "CREATE TABLE {$wpdb->prefix}absensi_rekap (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            group_id BIGINT UNSIGNED NOT NULL,
            tanggal DATE NOT NULL,
            waktu_masuk DATETIME DEFAULT NULL,
            waktu_keluar DATETIME DEFAULT NULL,
            status ENUM('hadir','telat','izin','sakit','alpha') NOT NULL DEFAULT 'hadir',
            mode ENUM('selfie','rfid','manual') NOT NULL DEFAULT 'selfie',
            metode_masuk ENUM('selfie','rfid','manual') DEFAULT NULL,
            metode_keluar ENUM('selfie','rfid','manual') DEFAULT NULL,
            lat DECIMAL(10,7) DEFAULT NULL,
            lng DECIMAL(10,7) DEFAULT NULL,
            jarak_meter INT UNSIGNED DEFAULT NULL,
            akurasi DECIMAL(7,2) DEFAULT NULL,
            flag_lokasi VARCHAR(30) DEFAULT NULL,
            foto_path VARCHAR(255) DEFAULT NULL,
            catatan TEXT DEFAULT NULL,
            izin_tipe ENUM('izin','sakit') DEFAULT NULL,
            bukti_status ENUM('menunggu','setuju','tolak') DEFAULT NULL,
            bukti_path VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unik_user_tanggal (user_id, tanggal),
            KEY tanggal (tanggal),
            KEY group_id (group_id)
        ) $charset;" );

        // Tabel hari libur (v2.2.0) — tanggal yang TIDAK dihitung kehadiran (jadi bukan alpha).
        // Disimpan sebagai RENTANG: libur sehari → tanggal_mulai = tanggal_selesai. Rentang boleh
        // tumpang tindih (libur tetap libur), jadi tak ada constraint anti-overlap.
        dbDelta( "CREATE TABLE {$wpdb->prefix}absensi_libur (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tanggal_mulai   DATE NOT NULL,
            tanggal_selesai DATE NOT NULL,
            keterangan      VARCHAR(150) NOT NULL DEFAULT '' COMMENT 'mis. Idul Fitri, Libur Semester',
            created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY tanggal_mulai (tanggal_mulai),
            KEY tanggal_selesai (tanggal_selesai)
        ) $charset;" );

        update_option( 'absensi_db_version', self::DB_VERSION );
    }

    // ─── Migrasi v2 (breaking: rename tabel/kolom — dbDelta tak bisa) ─────────

    /**
     * Pivot v2 (model kiosk tanpa login): rename tabel & kolom ke skema generik.
     *   absensi_siswa → absensi_users (nis→nomor_induk, kelas_id→group_id, DROP user_id)
     *   absensi_kelas → absensi_group (nama_kelas→nama, ADD tipe, DROP tingkat/guru_id)
     *   absensi_rekap : siswa_id→user_id, kelas_id→group_id, DROP guru_id
     *   absensi_jadwal: kelas_id→group_id
     *   absensi_wali  : DROP TABLE (tak ada ortu)
     *
     * Idempotent: tiap langkah dijaga cek eksistensi tabel/kolom/index, jadi aman
     * dipanggil berulang. Index ber-nama lama (nis/unik_siswa_tanggal/kelas_id) dibuang
     * di sini; create_tables() (dipanggil setelah ini di maybe_upgrade) menata index baru.
     */
    private static function migrate_to_v2(): void {
        global $wpdb;
        $p = $wpdb->prefix;

        // 1. Rename tabel (hanya bila lama ada & baru belum).
        if ( self::table_exists( "{$p}absensi_siswa" ) && ! self::table_exists( "{$p}absensi_users" ) ) {
            $wpdb->query( "RENAME TABLE {$p}absensi_siswa TO {$p}absensi_users" );
        }
        if ( self::table_exists( "{$p}absensi_kelas" ) && ! self::table_exists( "{$p}absensi_group" ) ) {
            $wpdb->query( "RENAME TABLE {$p}absensi_kelas TO {$p}absensi_group" );
        }

        // 2. users: rename kolom, buang user_id + index unik lama.
        $users = "{$p}absensi_users";
        if ( self::table_exists( $users ) ) {
            if ( self::column_exists( $users, 'nis' ) ) {
                $wpdb->query( "ALTER TABLE {$users} CHANGE nis nomor_induk VARCHAR(30) NOT NULL" );
            }
            if ( self::column_exists( $users, 'kelas_id' ) ) {
                $wpdb->query( "ALTER TABLE {$users} CHANGE kelas_id group_id BIGINT UNSIGNED NOT NULL DEFAULT 0" );
            }
            if ( self::column_exists( $users, 'user_id' ) ) {
                $wpdb->query( "ALTER TABLE {$users} DROP COLUMN user_id" );
            }
            if ( self::index_exists( $users, 'nis' ) ) {
                $wpdb->query( "ALTER TABLE {$users} DROP INDEX nis" );
            }
        }

        // 3. group: rename kolom, tambah tipe, buang tingkat/guru_id.
        $group = "{$p}absensi_group";
        if ( self::table_exists( $group ) ) {
            if ( self::column_exists( $group, 'nama_kelas' ) ) {
                $wpdb->query( "ALTER TABLE {$group} CHANGE nama_kelas nama VARCHAR(100) NOT NULL" );
            }
            if ( ! self::column_exists( $group, 'tipe' ) ) {
                $wpdb->query( "ALTER TABLE {$group} ADD COLUMN tipe ENUM('kelas','guru','staff') NOT NULL DEFAULT 'kelas'" );
            }
            if ( self::column_exists( $group, 'tingkat' ) ) {
                $wpdb->query( "ALTER TABLE {$group} DROP COLUMN tingkat" );
            }
            if ( self::column_exists( $group, 'guru_id' ) ) {
                $wpdb->query( "ALTER TABLE {$group} DROP COLUMN guru_id" );
            }
        }

        // 4. rekap: rename kolom, buang guru_id + index lama.
        $rekap = "{$p}absensi_rekap";
        if ( self::table_exists( $rekap ) ) {
            if ( self::column_exists( $rekap, 'siswa_id' ) ) {
                $wpdb->query( "ALTER TABLE {$rekap} CHANGE siswa_id user_id BIGINT UNSIGNED NOT NULL" );
            }
            if ( self::column_exists( $rekap, 'kelas_id' ) ) {
                $wpdb->query( "ALTER TABLE {$rekap} CHANGE kelas_id group_id BIGINT UNSIGNED NOT NULL" );
            }
            if ( self::column_exists( $rekap, 'guru_id' ) ) {
                $wpdb->query( "ALTER TABLE {$rekap} DROP COLUMN guru_id" );
            }
            if ( self::index_exists( $rekap, 'unik_siswa_tanggal' ) ) {
                $wpdb->query( "ALTER TABLE {$rekap} DROP INDEX unik_siswa_tanggal" );
            }
            if ( self::index_exists( $rekap, 'kelas_id' ) ) {
                $wpdb->query( "ALTER TABLE {$rekap} DROP INDEX kelas_id" );
            }
        }

        // 5. jadwal: rename kolom + index lama.
        $jadwal = "{$p}absensi_jadwal";
        if ( self::table_exists( $jadwal ) ) {
            if ( self::column_exists( $jadwal, 'kelas_id' ) ) {
                $wpdb->query( "ALTER TABLE {$jadwal} CHANGE kelas_id group_id BIGINT UNSIGNED NOT NULL" );
            }
            if ( self::index_exists( $jadwal, 'kelas_id' ) ) {
                $wpdb->query( "ALTER TABLE {$jadwal} DROP INDEX kelas_id" );
            }
        }

        // 6. Buang tabel wali (tak ada ortu di model baru).
        $wpdb->query( "DROP TABLE IF EXISTS {$p}absensi_wali" );
    }

    /**
     * Migrasi v2.1.0: kolom `tipe` group ENUM('kelas','guru','staff') → VARCHAR(50)
     * agar tipe bisa diisi bebas oleh admin (mis. "Ekskul", "Panitia"). Data lama tetap.
     * Idempotent: hanya MODIFY bila kolom masih ENUM.
     */
    private static function migrate_to_v2_1(): void {
        global $wpdb;
        $group = "{$wpdb->prefix}absensi_group";
        if ( self::table_exists( $group ) && self::column_is_enum( $group, 'tipe' ) ) {
            $wpdb->query( "ALTER TABLE `{$group}` MODIFY tipe VARCHAR(50) NOT NULL DEFAULT 'kelas'" );
        }
    }

    /** True bila tipe kolom masih ENUM (dipakai migrasi ke VARCHAR, idempotent). */
    private static function column_is_enum( string $table, string $column ): bool {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare( "SHOW COLUMNS FROM `{$table}` WHERE Field = %s", $column ) );
        return $row && 0 === stripos( (string) $row->Type, 'enum' );
    }

    /** True bila tabel ada. */
    private static function table_exists( string $table ): bool {
        global $wpdb;
        return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
    }

    /**
     * True bila kolom ada di tabel. Nama tabel = literal internal ($wpdb->prefix +
     * konstanta), aman diinterpolasi; nilai kolom tetap lewat prepare().
     */
    private static function column_exists( string $table, string $column ): bool {
        global $wpdb;
        return (bool) $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM `{$table}` WHERE Field = %s", $column ) );
    }

    /** True bila index/key bernama $index ada di tabel. */
    private static function index_exists( string $table, string $index ): bool {
        global $wpdb;
        return (bool) $wpdb->get_results( $wpdb->prepare( "SHOW INDEX FROM `{$table}` WHERE Key_name = %s", $index ) );
    }

    // ─── Role & Capability ─────────────────────────────────────────────────────

    /**
     * Daftarkan role `guru` + cap gerbang kiosk RFID. Idempotent (aman diulang).
     *
     * Guru = operator kiosk RFID (tap kartu SISWA di device sendiri), login-only.
     * Caps minimal: `read` (login/profil) + CAP_RFID. TANPA cap admin lain (dashboard
     * penuh diblok terpisah di Plugin::admin_init). CAP_RFID juga diberikan ke
     * administrator agar admin ikut lolos gate page/endpoint.
     *
     * Penghapusan role hanya di uninstall (hardcoded di uninstall.php) — deactivate
     * TIDAK menghapus role agar akun guru & assignment-nya tak rusak saat re-activate.
     */
    private static function seed_roles(): void {
        $guru = get_role( 'guru' );
        if ( ! $guru ) {
            add_role( 'guru', __( 'Guru', 'absensi-sekolah' ), [ 'read' => true, self::CAP_RFID => true ] );
        } else {
            // Role sudah ada (mis. residu pra-pivot) → sinkron cap.
            $guru->add_cap( 'read' );
            $guru->add_cap( self::CAP_RFID );
        }
        $admin = get_role( 'administrator' );
        if ( $admin ) {
            $admin->add_cap( self::CAP_RFID );
        }
    }

    // ─── Default Options ──────────────────────────────────────────────────────

    private static function seed_default_options(): void {
        $defaults = [
            'absensi_lat'          => '',       // Latitude koordinat sekolah
            'absensi_lng'          => '',       // Longitude koordinat sekolah
            'absensi_radius'       => 100,      // Radius valid dalam meter
            'absensi_jam_masuk'    => '07:00',
            'absensi_jam_keluar'   => '15:00',
            'absensi_telat_menit'  => 15,       // Menit toleransi keterlambatan
            'absensi_akurasi_max'  => 100,      // Akurasi GPS maksimum diterima (meter)
            'absensi_rfid_debounce' => 3,       // Window anti double-tap RFID (detik)
            'absensi_retensi_hari' => 90,       // Retensi foto selfie (hari)
            'absensi_wa_gateway'   => '',       // URL gateway WA (opsional)
            'absensi_wa_token'     => '',
        ];
        foreach ( $defaults as $key => $value ) {
            if ( false === get_option( $key ) ) {
                add_option( $key, $value );
            }
        }
    }

    // ─── Auto-buat Page Publik (surface FE) ────────────────────────────────────

    /**
     * Buat halaman WP berisi shortcode surface saat aktivasi (siswa/guru — kiosk publik),
     * agar langsung muncul di publik tanpa user membuat manual.
     *
     * Idempotent & hormati konten user:
     * - ID tersimpan di option `absensi_pages` ({siswa,guru}). Sudah ada & valid → skip.
     * - Page ber-slug sama yang sudah dibuat user → pakai ID-nya (tak buat dobel).
     *   Page "adopsi" ini TIDAK dicatat sebagai buatan plugin → tak ikut terhapus
     *   saat deactivate (lihat remove_pages()).
     * - ID yang benar-benar dibuat plugin dicatat di option `absensi_pages_created`.
     * - HANYA dipanggil di activate() (BUKAN maybe_upgrade) supaya page yang sengaja
     *   dihapus user tak otomatis muncul lagi.
     */
    private static function seed_pages(): void {
        // Urutan penting: siswa dulu (jadi parent), lalu guru (child → URL /absensi/guru).
        $defs = [
            'siswa' => [ 'title' => 'Absensi',       'slug' => 'absensi', 'shortcode' => '[absensi_siswa]' ],
            'guru'  => [ 'title' => 'Absensi Guru',  'slug' => 'guru',    'shortcode' => '[absensi_guru]' ],
        ];

        $pages   = (array) get_option( 'absensi_pages', [] );
        $created = (array) get_option( 'absensi_pages_created', [] );

        foreach ( $defs as $key => $def ) {
            // Sudah tercatat & page masih ada (bukan trash) → skip.
            if ( ! empty( $pages[ $key ] ) ) {
                $existing = get_post( (int) $pages[ $key ] );
                if ( $existing && 'trash' !== $existing->post_status ) {
                    continue;
                }
            }

            // User mungkin sudah punya page ber-slug sama → pakai itu, jangan dobel.
            $by_slug = get_page_by_path( $def['slug'] );
            if ( $by_slug ) {
                $pages[ $key ] = (int) $by_slug->ID;
                continue;
            }

            // Guru = child dari page siswa (sudah diproses lebih dulu) → URL /absensi/guru.
            $parent = ( 'guru' === $key ) ? (int) ( $pages['siswa'] ?? 0 ) : 0;

            $id = wp_insert_post( [
                'post_title'   => $def['title'],
                'post_name'    => $def['slug'],
                'post_content' => $def['shortcode'],
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_parent'  => $parent,
            ] );
            if ( $id && ! is_wp_error( $id ) ) {
                $pages[ $key ]   = (int) $id;
                $created[ $key ] = (int) $id;
            }
        }

        update_option( 'absensi_pages', $pages );
        update_option( 'absensi_pages_created', $created );
    }

    /**
     * Hapus page surface saat deactivate — HANYA yang dibuat plugin sendiri
     * (tercatat di `absensi_pages_created`). Page user yang "diadopsi" (slug sama,
     * dibuat manual) dibiarkan. Safety ganda: konten harus masih memuat shortcode
     * surface-nya (page yang sudah di-repurpose user tak ikut terhapus).
     * Kedua option page dihapus → aktivasi berikutnya buat ulang dari bersih.
     */
    private static function remove_pages(): void {
        $shortcodes = [
            'siswa' => '[absensi_siswa]',
            'guru'  => '[absensi_guru]',
        ];

        $created = (array) get_option( 'absensi_pages_created', [] );

        foreach ( $created as $key => $id ) {
            $page = get_post( (int) $id );
            if ( ! $page || 'page' !== $page->post_type ) {
                continue;
            }
            $sc = $shortcodes[ $key ] ?? null;
            if ( $sc && ! str_contains( (string) $page->post_content, $sc ) ) {
                continue; // sudah di-repurpose user → biarkan
            }
            wp_delete_post( (int) $id, true );
        }

        delete_option( 'absensi_pages' );
        delete_option( 'absensi_pages_created' );
    }
}
