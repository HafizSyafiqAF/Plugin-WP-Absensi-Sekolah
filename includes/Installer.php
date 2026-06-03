<?php
namespace Absensi;

defined( 'ABSPATH' ) || exit;

/**
 * Mengelola instalasi, aktivasi, dan penghapusan plugin.
 * Dipanggil oleh register_activation_hook di file utama.
 */
class Installer {

    /** Versi skema DB – naikkan setiap ada perubahan tabel. */
    const DB_VERSION = '1.0.0';

    public static function activate(): void {
        self::create_tables();
        self::seed_default_options();
        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        flush_rewrite_rules();
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
            status       ENUM('hadir','telat','izin','sakit','alpha') NOT NULL DEFAULT 'hadir',
            mode         ENUM('selfie','rfid','manual') NOT NULL DEFAULT 'selfie',
            lat          DECIMAL(10,7) DEFAULT NULL,
            lng          DECIMAL(10,7) DEFAULT NULL,
            foto_path    VARCHAR(255) DEFAULT NULL,
            catatan      TEXT DEFAULT NULL,
            guru_id      BIGINT UNSIGNED DEFAULT NULL COMMENT 'Guru yang validasi (RFID)',
            created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unik_siswa_tanggal (siswa_id, tanggal),
            KEY tanggal (tanggal),
            KEY kelas_id (kelas_id)
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
            'absensi_wa_gateway'   => '',       // URL gateway WA (opsional)
            'absensi_wa_token'     => '',
        ];
        foreach ( $defaults as $key => $value ) {
            if ( false === get_option( $key ) ) {
                add_option( $key, $value );
            }
        }
    }
}
