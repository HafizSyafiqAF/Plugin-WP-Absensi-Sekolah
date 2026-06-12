<?php
namespace Absensi;

defined( 'ABSPATH' ) || exit;

/**
 * Mengelola instalasi, aktivasi, dan penghapusan plugin.
 * Dipanggil oleh register_activation_hook di file utama.
 */
class Installer {

    /** Versi skema DB – naikkan setiap ada perubahan tabel. */
    const DB_VERSION = '1.3.1';

    /**
     * Capability custom plugin. Dipakai sebagai permission_callback di REST.
     * Administrator WP otomatis diberi semua cap ini.
     */
    const CAPS = [
        'absensi_submit_self',   // Siswa absen selfie + GPS
        'absensi_submit_rfid',   // Guru absen via tap RFID
        'absensi_enroll_rfid',   // Daftar/bind kartu RFID ke siswa
        'absensi_view_reports',  // Lihat & export laporan
        'absensi_view_child',    // Orang tua lihat absensi anak
    ];

    public static function activate(): void {
        self::create_tables();
        self::seed_default_options();
        self::seed_roles();
        self::seed_pages();
        Retensi::schedule();
        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        self::remove_roles();
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
        self::create_tables(); // dbDelta + update_option( 'absensi_db_version', DB_VERSION )
        // Re-seed role/cap & option default (idempotent) supaya upgrade lewat maybe_upgrade
        // tetap sinkron tanpa harus deactivate+activate ulang. Tanpa ini, role custom
        // (absensi_admin/guru/orang_tua) bisa hilang/stale di situs yang hanya upgrade skema.
        self::seed_roles();
        self::seed_default_options();
        // Langkah migrasi per-versi berikutnya (backfill data) ditambah di sini.
        return true;
    }

    // ─── Role & Capability ───────────────────────────────────────────────────

    /**
     * Definisi role plugin → daftar capability.
     * Slug role dipertahankan sesuai yang dirujuk permission_callback existing
     * (guru, absensi_admin, orang_tua) + absensi_siswa untuk pemegang cap submit_self.
     */
    private static function role_definitions(): array {
        return [
            'absensi_admin' => [
                'name' => 'Admin Absensi',
                'caps' => [ 'read', ...self::CAPS ], // admin sekolah: semua cap absensi
            ],
            'guru' => [
                'name' => 'Guru',
                'caps' => [ 'read', 'absensi_submit_rfid', 'absensi_enroll_rfid', 'absensi_view_reports' ],
            ],
            'absensi_siswa' => [
                'name' => 'Siswa',
                'caps' => [ 'read', 'absensi_submit_self' ],
            ],
            'orang_tua' => [
                'name' => 'Orang Tua',
                'caps' => [ 'read', 'absensi_view_child' ],
            ],
        ];
    }

    /** Seed role custom + berikan semua cap absensi ke administrator. Idempotent. */
    private static function seed_roles(): void {
        foreach ( self::role_definitions() as $slug => $def ) {
            $role = get_role( $slug );
            if ( null === $role ) {
                add_role( $slug, $def['name'], array_fill_keys( $def['caps'], true ) );
            } else {
                // Role sudah ada – sinkronkan cap (mis. setelah update versi).
                foreach ( $def['caps'] as $cap ) {
                    $role->add_cap( $cap );
                }
            }
        }

        $admin = get_role( 'administrator' );
        if ( $admin ) {
            foreach ( self::CAPS as $cap ) {
                $admin->add_cap( $cap );
            }
        }
    }

    /** Cabut cap dari administrator & hapus role custom. Dipanggil saat deactivate/uninstall. */
    private static function remove_roles(): void {
        $admin = get_role( 'administrator' );
        if ( $admin ) {
            foreach ( self::CAPS as $cap ) {
                $admin->remove_cap( $cap );
            }
        }

        foreach ( array_keys( self::role_definitions() ) as $slug ) {
            if ( get_role( $slug ) ) {
                remove_role( $slug );
            }
        }
    }

    // ─── Buat Tabel Custom ───────────────────────────────────────────────────

    private static function create_tables(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Tabel siswa
        dbDelta( "CREATE TABLE {$wpdb->prefix}absensi_siswa (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id     BIGINT UNSIGNED DEFAULT NULL COMMENT 'WP user jika ada',
            nis         VARCHAR(20)  NOT NULL,
            nama        VARCHAR(150) NOT NULL,
            kelas_id    BIGINT UNSIGNED NOT NULL DEFAULT 0,
            rfid_uid    VARCHAR(50)  DEFAULT NULL COMMENT 'UID kartu RFID siswa',
            foto_path   VARCHAR(255) DEFAULT NULL,
            created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY nis (nis),
            UNIQUE KEY rfid_uid (rfid_uid)
        ) $charset;" );

        // Tabel kelas
        dbDelta( "CREATE TABLE {$wpdb->prefix}absensi_kelas (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            nama_kelas VARCHAR(100) NOT NULL,
            tingkat    TINYINT UNSIGNED NOT NULL DEFAULT 1,
            guru_id    BIGINT UNSIGNED DEFAULT NULL COMMENT 'WP user ID guru wali',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset;" );

        // Tabel jadwal (hari & jam masuk/keluar per kelas)
        dbDelta( "CREATE TABLE {$wpdb->prefix}absensi_jadwal (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            kelas_id    BIGINT UNSIGNED NOT NULL,
            hari        TINYINT UNSIGNED NOT NULL COMMENT '1=Senin ... 7=Minggu',
            jam_masuk   TIME NOT NULL,
            jam_keluar  TIME NOT NULL,
            PRIMARY KEY (id),
            KEY kelas_id (kelas_id)
        ) $charset;" );

        // Tabel rekap absensi
        dbDelta( "CREATE TABLE {$wpdb->prefix}absensi_rekap (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            siswa_id     BIGINT UNSIGNED NOT NULL,
            kelas_id     BIGINT UNSIGNED NOT NULL,
            tanggal      DATE NOT NULL,
            waktu_masuk  DATETIME DEFAULT NULL,
            waktu_keluar DATETIME DEFAULT NULL,
            status        ENUM('hadir','telat','izin','sakit','alpha') NOT NULL DEFAULT 'hadir',
            mode          ENUM('selfie','rfid','manual') NOT NULL DEFAULT 'selfie',
            metode_masuk  ENUM('selfie','rfid','manual') DEFAULT NULL,
            metode_keluar ENUM('selfie','rfid','manual') DEFAULT NULL,
            lat          DECIMAL(10,7) DEFAULT NULL,
            lng          DECIMAL(10,7) DEFAULT NULL,
            jarak_meter  INT UNSIGNED DEFAULT NULL COMMENT 'Jarak haversine saat absen (audit)',
            foto_path    VARCHAR(255) DEFAULT NULL,
            catatan      TEXT DEFAULT NULL,
            guru_id      BIGINT UNSIGNED DEFAULT NULL COMMENT 'Guru yang validasi (RFID)',
            created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unik_siswa_tanggal (siswa_id, tanggal),
            KEY tanggal (tanggal),
            KEY kelas_id (kelas_id)
        ) $charset;" );

        // Tabel relasi wali (orang tua) → siswa (1 ortu : N anak)
        dbDelta( "CREATE TABLE {$wpdb->prefix}absensi_wali (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            wali_user_id BIGINT UNSIGNED NOT NULL,
            siswa_id     BIGINT UNSIGNED NOT NULL,
            created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unik_wali_siswa (wali_user_id, siswa_id),
            KEY wali_user_id (wali_user_id),
            KEY siswa_id (siswa_id)
        ) $charset;" );

        update_option( 'absensi_db_version', self::DB_VERSION );
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
     * Buat halaman WP berisi shortcode surface saat aktivasi (siswa/guru/ortu),
     * agar langsung muncul di publik tanpa user membuat manual.
     *
     * Idempotent & hormati konten user:
     * - ID tersimpan di option `absensi_pages` ({siswa,guru,ortu}). Sudah ada & valid → skip.
     * - Page ber-slug sama yang sudah dibuat user → pakai ID-nya (tak buat dobel).
     *   Page "adopsi" ini TIDAK dicatat sebagai buatan plugin → tak ikut terhapus
     *   saat deactivate (lihat remove_pages()).
     * - ID yang benar-benar dibuat plugin dicatat di option `absensi_pages_created`.
     * - HANYA dipanggil di activate() (BUKAN maybe_upgrade) supaya page yang sengaja
     *   dihapus user tak otomatis muncul lagi.
     */
    private static function seed_pages(): void {
        $defs = [
            'siswa' => [ 'title' => 'Absensi Siswa',     'slug' => 'absensi-siswa', 'shortcode' => '[absensi_siswa]' ],
            'guru'  => [ 'title' => 'Absensi Guru',      'slug' => 'absensi-guru',  'shortcode' => '[absensi_guru]' ],
            'ortu'  => [ 'title' => 'Absensi Orang Tua', 'slug' => 'absensi-ortu',  'shortcode' => '[absensi_ortu]' ],
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

            $id = wp_insert_post( [
                'post_title'   => $def['title'],
                'post_name'    => $def['slug'],
                'post_content' => $def['shortcode'],
                'post_status'  => 'publish',
                'post_type'    => 'page',
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
            'ortu'  => '[absensi_ortu]',
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
