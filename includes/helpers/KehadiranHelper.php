<?php
namespace Absensi\helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Mesin ALPHA (v2.2.0) — siapa yang TIDAK hadir.
 *
 * Rekap hanya lahir saat orang tap/selfie, jadi yang bolos tak punya baris sama sekali dan
 * "Alpha" di laporan selalu 0. Helper ini menghitung alpha **saat laporan dibuka** (tanpa cron,
 * tanpa menulis baris sampah): untuk tiap (user × tanggal-aktif) yang tak punya rekap → alpha.
 *
 * Aturan (keputusan produk, dikunci bersama user):
 *  1. HARI AKTIF ikut `absensi_jadwal` per group (hari 1=Senin..7=Minggu). Group tanpa jadwal →
 *     jatuh ke **Jadwal Default** (Pengaturan): Senin–Jumat, jam keluar dari option
 *     `absensi_jam_keluar`. Default = cadangan untuk group yang belum diatur, BUKAN aturan yang
 *     menimpa semua group.
 *  2. Tanggal yang masuk `absensi_libur` → BUKAN hari aktif (tak ada alpha).
 *  3. User baru dihitung sejak tanggal ia didaftarkan (`absensi_users.created_at`) — siswa yang
 *     masuk hari ini tak dituduh bolos bulan lalu.
 *  4. Alpha baru berlaku SETELAH jam pulang hari itu lewat. Sebelum itu (hari berjalan) orangnya
 *     "belum absen", bukan alpha. Tanggal masa depan tak pernah alpha.
 *
 * Baris alpha bersifat VIRTUAL (`id = null`, `virtual = true`). Baris nyata baru ditulis kalau
 * admin mengubah statusnya (mis. jadi izin/sakit) — lihat Fase 3.
 */
class KehadiranHelper {

    /** Batas rentang yang diproses (hari). Rentang lebih panjang dipangkas — jaga memori. */
    const MAX_HARI = 366;

    /** Hari aktif default bila group belum punya jadwal: Senin(1)–Jumat(5). */
    const HARI_DEFAULT = [ 1, 2, 3, 4, 5 ];

    /**
     * Baris alpha virtual untuk rentang + filter yang sama dengan /laporan.
     * Bentuk baris menyerupai baris rekap (agar bisa digabung & diekspor apa adanya).
     *
     * @return array<int, object>
     */
    public static function alpha_rows( string $dari, string $sampai, int $group_id = 0, string $tipe = '' ): array {
        global $wpdb;

        $dari   = SanitizeHelper::normalize_date( $dari );
        $sampai = SanitizeHelper::normalize_date( $sampai );
        if ( '' === $dari || '' === $sampai || $sampai < $dari ) {
            return [];
        }

        $users = self::users( $group_id, $tipe );
        if ( ! $users ) {
            return [];
        }

        $jadwal = self::jadwal_map();                       // [group_id][hari] = jam_keluar
        $libur  = self::libur_set( $dari, $sampai );        // set 'Y-m-d' => true
        $rekap  = self::rekap_set( $dari, $sampai );        // set 'user_id|Y-m-d' => true

        $jam_keluar_global = SanitizeHelper::normalize_time( (string) get_option( 'absensi_jam_keluar', '15:00' ) ) ?: '15:00:00';
        $hari_ini          = current_time( 'Y-m-d' );
        $sekarang          = current_time( 'mysql' );       // 'Y-m-d H:i:s' zona WP

        $out = [];
        foreach ( self::tanggal_range( $dari, $sampai ) as $tgl ) {
            if ( $tgl > $hari_ini || isset( $libur[ $tgl ] ) ) {
                continue; // masa depan / hari libur → tak pernah alpha
            }
            $hari = (int) ( new \DateTimeImmutable( $tgl ) )->format( 'N' ); // 1=Senin..7=Minggu

            foreach ( $users as $u ) {
                if ( $tgl < $u->sejak ) {
                    continue; // user belum terdaftar pada tanggal itu
                }

                $gid = (int) $u->group_id;
                if ( isset( $jadwal[ $gid ] ) ) {
                    // Group punya jadwal sendiri → hari yang tak dijadwalkan = libur bagi group itu.
                    if ( ! isset( $jadwal[ $gid ][ $hari ] ) ) {
                        continue;
                    }
                    $jam_keluar = $jadwal[ $gid ][ $hari ];
                } else {
                    if ( ! in_array( $hari, self::HARI_DEFAULT, true ) ) {
                        continue;
                    }
                    $jam_keluar = $jam_keluar_global;
                }

                // Hari berjalan: alpha baru sah setelah jam pulang lewat.
                if ( $tgl === $hari_ini && $sekarang < ( $tgl . ' ' . $jam_keluar ) ) {
                    continue;
                }

                if ( isset( $rekap[ $u->id . '|' . $tgl ] ) ) {
                    continue; // sudah punya rekap (hadir/telat/izin/sakit/alpha tersimpan)
                }

                $out[] = self::baris_alpha( $u, $tgl );
            }
        }
        return $out;
    }

    /** Jumlah alpha virtual (tanpa membangun daftar penuh dua kali). */
    public static function hitung_alpha( string $dari, string $sampai, int $group_id = 0, string $tipe = '' ): int {
        return count( self::alpha_rows( $dari, $sampai, $group_id, $tipe ) );
    }

    // ─── Internal ────────────────────────────────────────────────────────────

    /** User yang diabsen + tanggal mulai dihitung (created_at). Filter group/tipe = sama dgn /laporan. */
    private static function users( int $group_id, string $tipe ): array {
        global $wpdb;

        $where = [ '1=1' ];
        if ( $group_id ) {
            $where[] = $wpdb->prepare( 'u.group_id = %d', $group_id );
        }
        if ( '' !== $tipe ) {
            $where[] = $wpdb->prepare( 'g.tipe = %s', $tipe );
        }
        $sql = "SELECT u.id, u.nama, u.nomor_induk, u.group_id,
                       DATE(u.created_at) AS sejak,
                       g.nama AS nama_group
                  FROM {$wpdb->prefix}absensi_users u
                  LEFT JOIN {$wpdb->prefix}absensi_group g ON g.id = u.group_id
                 WHERE " . implode( ' AND ', $where );

        return (array) $wpdb->get_results( $sql );
    }

    /** [group_id][hari] = jam_keluar (H:i:s). Group tanpa baris jadwal → tak muncul di map. */
    private static function jadwal_map(): array {
        global $wpdb;
        $map = [];
        $rows = (array) $wpdb->get_results( "SELECT group_id, hari, jam_keluar FROM {$wpdb->prefix}absensi_jadwal" );
        foreach ( $rows as $j ) {
            $map[ (int) $j->group_id ][ (int) $j->hari ] = (string) $j->jam_keluar;
        }
        return $map;
    }

    /** Set tanggal libur dalam rentang (rentang libur di-expand jadi tanggal satuan). */
    private static function libur_set( string $dari, string $sampai ): array {
        global $wpdb;

        $rows = (array) $wpdb->get_results( $wpdb->prepare(
            "SELECT tanggal_mulai, tanggal_selesai
               FROM {$wpdb->prefix}absensi_libur
              WHERE tanggal_mulai <= %s AND tanggal_selesai >= %s",
            $sampai, $dari
        ) );

        $set = [];
        foreach ( $rows as $l ) {
            // Potong ke rentang yang diminta — libur semester panjang tak perlu di-expand seluruhnya.
            $mulai   = max( (string) $l->tanggal_mulai, $dari );
            $selesai = min( (string) $l->tanggal_selesai, $sampai );
            foreach ( self::tanggal_range( $mulai, $selesai ) as $t ) {
                $set[ $t ] = true;
            }
        }
        return $set;
    }

    /** Set 'user_id|tanggal' yang SUDAH punya baris rekap (apa pun statusnya). */
    private static function rekap_set( string $dari, string $sampai ): array {
        global $wpdb;
        $rows = (array) $wpdb->get_results( $wpdb->prepare(
            "SELECT user_id, tanggal FROM {$wpdb->prefix}absensi_rekap WHERE tanggal BETWEEN %s AND %s",
            $dari, $sampai
        ) );
        $set = [];
        foreach ( $rows as $r ) {
            $set[ (int) $r->user_id . '|' . $r->tanggal ] = true;
        }
        return $set;
    }

    /** Daftar tanggal 'Y-m-d' dari..sampai (inklusif), dipangkas MAX_HARI. */
    private static function tanggal_range( string $dari, string $sampai ): array {
        $out = [];
        $cur = new \DateTimeImmutable( $dari );
        $end = new \DateTimeImmutable( $sampai );
        $n   = 0;
        while ( $cur <= $end && $n < self::MAX_HARI ) {
            $out[] = $cur->format( 'Y-m-d' );
            $cur   = $cur->modify( '+1 day' );
            $n++;
        }
        return $out;
    }

    /** Baris alpha virtual — kolomnya disamakan dengan baris rekap agar bisa digabung/diekspor. */
    private static function baris_alpha( object $u, string $tgl ): object {
        return (object) [
            'id'            => null,     // belum ada baris DB
            'virtual'       => true,     // penanda utk FE: ubah status = INSERT, bukan UPDATE
            'user_id'       => (string) $u->id,
            'group_id'      => (string) $u->group_id,
            'tanggal'       => $tgl,
            'waktu_masuk'   => null,
            'waktu_keluar'  => null,
            'status'        => 'alpha',
            'mode'          => null,
            'metode_masuk'  => null,
            'metode_keluar' => null,
            'lat'           => null,
            'lng'           => null,
            'jarak_meter'   => null,
            'foto_path'     => null,
            'catatan'       => null,
            'izin_tipe'     => null,
            'bukti_status'  => null,
            'bukti_path'    => null,
            'created_at'    => null,
            'nama'          => $u->nama,
            'nomor_induk'   => $u->nomor_induk,
            'nama_group'    => $u->nama_group,
            'bukti_url'     => null,
        ];
    }
}
