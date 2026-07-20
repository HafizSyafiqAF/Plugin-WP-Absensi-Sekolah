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

        $cutoff = time() - ( $days * DAY_IN_SECONDS );

        // NULL-kan referensi foto di rekap untuk rentang yang sama. Tanpa ini, file fisik
        // terhapus tapi `rekap.foto_path` masih menyimpan path lama → Laporan Detail nampilkan
        // <img> yang menunjuk file tak ada (gambar rusak). Dilakukan lebih dulu, dan tetap jalan
        // walau folder selfie sudah tak ada (path basi bisa tertinggal di DB).
        self::null_foto_lama( $cutoff );

        $base = wp_upload_dir()['basedir'] . '/absensi-selfie';
        if ( ! is_dir( $base ) ) {
            return 0;
        }

        $deleted = 0;

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator( $base, \FilesystemIterator::SKIP_DOTS )
        );
        foreach ( $it as $file ) {
            if ( ! $file->isFile() ) {
                continue;
            }
            // Hanya foto selfie (nama diawali 'selfie'): format lama 'selfie-{id}-{hex}'
            // & format baru 'selfie_{NIS}_{tgl}-{sesi}_{hex}'. Jaga index.php/.htaccess & file lain.
            if ( ! str_starts_with( $file->getFilename(), 'selfie' ) ) {
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

    /**
     * Kosongkan `foto_path` untuk rekap yang tanggalnya lebih tua dari cutoff — menyamai
     * jendela hapus file. Retensi berbasis umur, jadi cukup dibandingkan `tanggal`.
     * @return int jumlah baris yang di-null-kan.
     */
    private static function null_foto_lama( int $cutoff ): int {
        global $wpdb;
        $batas = wp_date( 'Y-m-d', $cutoff );
        return (int) $wpdb->query( $wpdb->prepare(
            "UPDATE {$wpdb->prefix}absensi_rekap SET foto_path = NULL
              WHERE foto_path IS NOT NULL AND tanggal < %s",
            $batas
        ) );
    }
}
