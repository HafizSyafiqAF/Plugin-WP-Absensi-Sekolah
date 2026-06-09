<?php
namespace Absensi;

defined( 'ABSPATH' ) || exit;

/**
 * Retensi foto selfie — hapus file lebih tua dari `absensi_retensi_hari` (default 90).
 * Dijadwalkan harian via WP-Cron. `absensi_retensi_hari = 0` → nonaktif (simpan selamanya).
 */
class Retensi {

    const HOOK = 'absensi_purge_selfie';

    /** Daftarkan handler cron + jadwalkan event harian (idempotent). */
    public static function init(): void {
        add_action( self::HOOK, [ __CLASS__, 'purge' ] );
        self::schedule();
    }

    /** Jadwalkan event harian bila belum ada. */
    public static function schedule(): void {
        if ( ! wp_next_scheduled( self::HOOK ) ) {
            wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', self::HOOK );
        }
    }

    /** Batalkan jadwal (dipanggil saat deactivate). */
    public static function unschedule(): void {
        wp_clear_scheduled_hook( self::HOOK );
    }

    /**
     * Hapus foto selfie lebih tua dari batas retensi.
     * @param int|null $days override hari (untuk tes); null = baca option.
     * @return int jumlah file dihapus.
     */
    public static function purge( ?int $days = null ): int {
        $days = null === $days ? (int) get_option( 'absensi_retensi_hari', 90 ) : $days;
        if ( $days <= 0 ) {
            return 0; // retensi nonaktif → jangan hapus apa pun
        }

        $base = wp_upload_dir()['basedir'] . '/absensi-selfie';
        if ( ! is_dir( $base ) ) {
            return 0;
        }

        $cutoff  = time() - ( $days * DAY_IN_SECONDS );
        $deleted = 0;

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator( $base, \FilesystemIterator::SKIP_DOTS )
        );
        foreach ( $it as $file ) {
            if ( ! $file->isFile() ) {
                continue;
            }
            // Hanya foto selfie (jaga index.php/.htaccess & file lain).
            if ( ! str_starts_with( $file->getFilename(), 'selfie-' ) ) {
                continue;
            }
            if ( $file->getMTime() < $cutoff ) {
                if ( @unlink( $file->getPathname() ) ) {
                    $deleted++;
                }
            }
        }
        return $deleted;
    }
}
