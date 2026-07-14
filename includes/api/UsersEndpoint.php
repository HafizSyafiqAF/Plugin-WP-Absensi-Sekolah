<?php
namespace Absensi\api;

defined( 'ABSPATH' ) || exit;

use Absensi\helpers\SanitizeHelper;

/**
 * REST Endpoint: /wp-json/absensi/v1/users
 * CRUD master data user (siswa/guru/staff, skema v2) & bind RFID UID.
 * Admin-only (cap manage_options). Eks-SiswaEndpoint.
 */
class UsersEndpoint {

    const NAMESPACE = 'absensi/v1';

    public function register_routes(): void {
        // GET /users  – list; POST /users – tambah
        register_rest_route( self::NAMESPACE, '/users', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ $this, 'list_users' ],
                'permission_callback' => [ $this, 'can_manage' ],
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'create_user' ],
                'permission_callback' => [ $this, 'can_manage' ],
                'args'                => $this->user_args( true ),
            ],
        ] );

        // GET/PUT/DELETE /users/{id}
        register_rest_route( self::NAMESPACE, '/users/(?P<id>\d+)', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_user' ],
                'permission_callback' => [ $this, 'can_manage' ],
            ],
            [
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => [ $this, 'update_user' ],
                'permission_callback' => [ $this, 'can_manage' ],
                'args'                => $this->user_args( false ),
            ],
            [
                'methods'             => \WP_REST_Server::DELETABLE,
                'callback'            => [ $this, 'delete_user' ],
                'permission_callback' => [ $this, 'can_manage' ],
            ],
        ] );

        // POST /users/bulk-delete – hapus banyak user sekaligus (checklist di tabel).
        // POST (bukan DELETE) karena body pada DELETE tak dijamin lolos proxy/klien.
        register_rest_route( self::NAMESPACE, '/users/bulk-delete', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'bulk_delete_users' ],
            'permission_callback' => [ $this, 'can_manage' ],
            'args'                => [
                'ids' => [
                    'required' => true,
                    'type'     => 'array',
                    'items'    => [ 'type' => 'integer' ],
                ],
            ],
        ] );

        // POST /users/{id}/rfid – bind/ganti UID RFID
        register_rest_route( self::NAMESPACE, '/users/(?P<id>\d+)/rfid', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'set_rfid' ],
            'permission_callback' => [ $this, 'can_manage' ],
            'args'                => [
                'rfid_uid' => [ 'required' => true, 'type' => 'string', 'maxLength' => 50 ],
            ],
        ] );

        // POST /users/import – impor massal dari Excel (.xlsx)
        register_rest_route( self::NAMESPACE, '/users/import', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'import_users' ],
            'permission_callback' => [ $this, 'can_manage' ],
        ] );

        // POST /guru/import – impor massal AKUN GURU (WP user role `guru`) dari Excel (.xlsx)
        register_rest_route( self::NAMESPACE, '/guru/import', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'import_guru' ],
            'permission_callback' => [ $this, 'can_manage' ],
        ] );
    }

    public function list_users( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;
        $group_id = absint( $req->get_param( 'group_id' ) );
        $where    = $group_id ? $wpdb->prepare( 'WHERE u.group_id = %d', $group_id ) : '';
        $rows     = $wpdb->get_results(
            "SELECT u.*, g.nama AS nama_group, g.tipe AS tipe_group
               FROM {$wpdb->prefix}absensi_users u
               LEFT JOIN {$wpdb->prefix}absensi_group g ON g.id = u.group_id
               $where
               ORDER BY u.nama ASC"
        );
        return new \WP_REST_Response( $rows );
    }

    public function get_user( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;
        $user = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}absensi_users WHERE id = %d",
            (int) $req->get_param( 'id' )
        ) );
        return $user
            ? new \WP_REST_Response( $user )
            : $this->error( 'user_tidak_ditemukan', 'Tidak ditemukan.', 404 );
    }

    public function create_user( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;
        $data = SanitizeHelper::users( $req->get_params() );
        if ( empty( $data['nomor_induk'] ) || empty( $data['nama'] ) ) {
            return $this->error( 'field_wajib', 'nomor_induk dan nama wajib diisi.', 422 );
        }
        $ok = $wpdb->insert( $wpdb->prefix . 'absensi_users', $data );
        if ( ! $ok ) {
            // Kemungkinan besar nomor_induk / rfid_uid duplikat (UNIQUE).
            return $this->error( 'gagal_simpan', 'Gagal menyimpan (nomor induk atau UID mungkin sudah dipakai).', 409 );
        }
        return new \WP_REST_Response( [ 'id' => $wpdb->insert_id ], 201 );
    }

    public function update_user( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;
        $data = SanitizeHelper::users( $req->get_params() );
        if ( empty( $data ) ) {
            return $this->error( 'tak_ada_perubahan', 'Tak ada field yang bisa diperbarui.', 422 );
        }
        $ok = $wpdb->update(
            $wpdb->prefix . 'absensi_users',
            $data,
            [ 'id' => (int) $req->get_param( 'id' ) ]
        );
        if ( false === $ok ) {
            return $this->error( 'gagal_simpan', 'Gagal memperbarui (nomor induk atau UID mungkin sudah dipakai).', 409 );
        }
        return new \WP_REST_Response( [ 'updated' => true ] );
    }

    /**
     * Hapus user + SELURUH rekapnya (cascade manual — tak ada FK di skema).
     *
     * Rekap yatim bukan sekadar kotor: `/laporan/summary` menghitung COUNT(*) langsung dari
     * absensi_rekap TANPA join users, jadi baris milik user yang sudah dihapus tetap menaikkan
     * angka Hadir/Telat. Di list & export (LEFT JOIN) ia muncul sebagai baris tanpa nama. Dan
     * karena `rekap.user_id` cuma angka, id yang dipakai ulang (MariaDB/MySQL 5.7 me-reset
     * AUTO_INCREMENT ke MAX(id)+1 tiap restart) membuat riwayat orang lama nempel ke user baru.
     */
    public function delete_user( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;
        $id = (int) $req->get_param( 'id' );

        $rekap = (int) $wpdb->delete( $wpdb->prefix . 'absensi_rekap', [ 'user_id' => $id ], [ '%d' ] );
        $wpdb->delete( $wpdb->prefix . 'absensi_users', [ 'id' => $id ], [ '%d' ] );

        return new \WP_REST_Response( [ 'deleted' => true, 'rekap_dihapus' => $rekap ] );
    }

    /**
     * Hapus banyak user sekaligus (dari checklist tabel Users).
     * Satu query DELETE ... IN (...) — bukan N request DELETE /users/{id}.
     * `ids` di-absint + dedup; id tak ada di DB diabaikan (idempotent).
     * Cap 500 id per request. Balas jumlah baris yang benar-benar terhapus.
     * Rekap milik user-user itu ikut dihapus — alasan sama seperti di delete_user().
     */
    public function bulk_delete_users( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;

        $ids = array_values( array_unique( array_filter(
            array_map( 'absint', (array) $req->get_param( 'ids' ) )
        ) ) );

        if ( empty( $ids ) ) {
            return $this->error( 'ids_kosong', 'Tidak ada user yang dipilih.', 422 );
        }
        if ( count( $ids ) > 500 ) {
            return $this->error( 'terlalu_banyak', 'Maksimal 500 user per penghapusan.', 422 );
        }

        $ph = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

        $rekap = (int) $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}absensi_rekap WHERE user_id IN ( {$ph} )",
            $ids
        ) );
        $deleted = (int) $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}absensi_users WHERE id IN ( {$ph} )",
            $ids
        ) );

        return new \WP_REST_Response( [ 'deleted' => $deleted, 'rekap_dihapus' => $rekap ], 200 );
    }

    /** Bind/ganti UID RFID ke user (cek duplikasi UID lintas user). */
    public function set_rfid( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;
        $id  = (int) $req->get_param( 'id' );
        $uid = SanitizeHelper::rfid_uid( $req->get_param( 'rfid_uid' ) );
        if ( '' === $uid ) {
            return $this->error( 'uid_kosong', 'UID RFID tidak valid.', 422 );
        }

        $conflict = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}absensi_users WHERE rfid_uid = %s AND id != %d",
            $uid, $id
        ) );
        if ( $conflict ) {
            return $this->error( 'kartu_terpakai', 'UID sudah dipakai user lain.', 409 );
        }

        $wpdb->update(
            $wpdb->prefix . 'absensi_users',
            [ 'rfid_uid' => $uid ],
            [ 'id' => $id ],
            [ '%s' ],
            [ '%d' ]
        );
        return new \WP_REST_Response( [ 'rfid_uid' => $uid ] );
    }

    /**
     * Impor massal user dari Excel (.xlsx). Kolom header: nama, nomor_induk, group (opsional),
     * tipe (opsional — tipe group).
     * group: resolve by (nama + tipe) → pakai id yang ada, atau auto-create dengan tipe itu.
     * Kalau kolom tipe kosong dan group-nya belum ada → baris ditolak (tipe group wajib, tak ada
     * preset default). Buat group dulu di menu Group, atau isi kolom tipe.
     * Validasi + sanitasi per baris; cap 2000 baris; lapor error per baris.
     * File via multipart ($_FILES['file']) ATAU base64 param 'file'.
     */
    public function import_users( \WP_REST_Request $req ): \WP_REST_Response {
        if ( ! class_exists( '\\PhpOffice\\PhpSpreadsheet\\IOFactory' ) ) {
            return $this->error( 'spreadsheet_absen', 'Import Excel butuh PhpSpreadsheet (jalankan composer install).', 503 );
        }

        $path = $this->resolve_upload_path( $req );
        if ( is_wp_error( $path ) ) {
            return $this->error( $path->get_error_code(), $path->get_error_message(), 422 );
        }

        try {
            $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load( $path )->getActiveSheet();
            $rows  = $sheet->toArray( null, true, true, false ); // kolom 0-indexed
        } catch ( \Throwable $e ) {
            $this->cleanup_temp( $path );
            return $this->error( 'file_tidak_terbaca', 'File Excel tidak bisa dibaca.', 422 );
        }
        $this->cleanup_temp( $path );

        if ( empty( $rows ) ) {
            return $this->error( 'file_kosong', 'File kosong.', 422 );
        }

        // Header → indeks kolom (case-insensitive, beberapa alias).
        $header    = array_map( static fn( $c ) => strtolower( trim( (string) $c ) ), (array) array_shift( $rows ) );
        $col_nama  = $this->find_col( $header, [ 'nama' ] );
        $col_nomor = $this->find_col( $header, [ 'nomor_induk', 'nomor induk', 'nis', 'nip' ] );
        $col_group = $this->find_col( $header, [ 'group', 'grup', 'kelas' ] );
        $col_tipe  = $this->find_col( $header, [ 'tipe', 'tipe_group', 'tipe group', 'jenis' ] );
        if ( null === $col_nama || null === $col_nomor ) {
            return $this->error( 'header_invalid', 'Header wajib memuat kolom: nama, nomor_induk (opsional: group, tipe).', 422 );
        }

        if ( count( $rows ) > 2000 ) {
            return $this->error( 'terlalu_banyak_baris', 'Maksimal 2000 baris data per impor.', 422 );
        }

        global $wpdb;
        $group_cache = [];
        $seen_nomor  = [];
        $imported    = 0;
        $errors      = [];

        foreach ( $rows as $i => $row ) {
            $baris = $i + 2; // +1 header, +1 ke 1-indexed
            $nama  = trim( (string) ( $row[ $col_nama ] ?? '' ) );
            $nomor = trim( (string) ( $row[ $col_nomor ] ?? '' ) );

            if ( '' === $nama && '' === $nomor ) {
                continue; // baris kosong → lewati diam
            }
            if ( '' === $nama || '' === $nomor ) {
                $errors[] = [ 'baris' => $baris, 'pesan' => 'nama dan nomor_induk wajib diisi.' ];
                continue;
            }
            $nomor_key = strtolower( $nomor );
            if ( isset( $seen_nomor[ $nomor_key ] ) ) {
                $errors[] = [ 'baris' => $baris, 'pesan' => "nomor_induk '{$nomor}' duplikat dalam file (lihat baris {$seen_nomor[ $nomor_key ]})." ];
                continue;
            }

            $group_id = 0;
            if ( null !== $col_group ) {
                $gnama = trim( (string) ( $row[ $col_group ] ?? '' ) );
                $gtipe = null !== $col_tipe ? trim( (string) ( $row[ $col_tipe ] ?? '' ) ) : '';
                if ( '' !== $gnama ) {
                    $group_id = $this->resolve_group( $gnama, $gtipe, $group_cache );
                    if ( ! $group_id ) {
                        $errors[] = [
                            'baris' => $baris,
                            'pesan' => "Group '{$gnama}' belum ada. Isi kolom 'tipe' agar group dibuat otomatis, atau buat group-nya dulu di menu Group.",
                        ];
                        continue;
                    }
                }
            }

            $data = SanitizeHelper::users( [ 'nomor_induk' => $nomor, 'nama' => $nama, 'group_id' => $group_id ] );
            if ( ! $wpdb->insert( $wpdb->prefix . 'absensi_users', $data ) ) {
                $errors[] = [ 'baris' => $baris, 'pesan' => "Gagal simpan (nomor_induk '{$nomor}' mungkin sudah terdaftar)." ];
                continue;
            }
            $seen_nomor[ $nomor_key ] = $baris;
            $imported++;
        }

        return new \WP_REST_Response( [
            'imported' => $imported,
            'gagal'    => count( $errors ),
            'errors'   => $errors,
        ], 200 );
    }

    /**
     * POST /guru/import — impor massal AKUN GURU (WP user role `guru`) dari Excel (.xlsx).
     *
     * Scope baris 73-74 (TODO-BE-AKUN-GURU item 5): buat endpoint + terima file + guard vendor (503)
     * + parse header/baris (reuse pola /users/import). Kolom: `username` (wajib), `nama`/`password`
     * (opsional). Pembuatan WP user per baris (`wp_insert_user` role guru) + laporan error per baris =
     * baris 75 (BELUM digarap) — lihat penanda TODO(item-5) di loop.
     */
    public function import_guru( \WP_REST_Request $req ): \WP_REST_Response {
        if ( ! class_exists( '\\PhpOffice\\PhpSpreadsheet\\IOFactory' ) ) {
            return $this->error( 'spreadsheet_absen', 'Import Excel butuh PhpSpreadsheet (jalankan composer install).', 503 );
        }

        $path = $this->resolve_upload_path( $req );
        if ( is_wp_error( $path ) ) {
            return $this->error( $path->get_error_code(), $path->get_error_message(), 422 );
        }

        try {
            $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load( $path )->getActiveSheet();
            $rows  = $sheet->toArray( null, true, true, false ); // kolom 0-indexed
        } catch ( \Throwable $e ) {
            $this->cleanup_temp( $path );
            return $this->error( 'file_tidak_terbaca', 'File Excel tidak bisa dibaca.', 422 );
        }
        $this->cleanup_temp( $path );

        if ( empty( $rows ) ) {
            return $this->error( 'file_kosong', 'File kosong.', 422 );
        }

        // Header → indeks kolom (case-insensitive, beberapa alias).
        $header       = array_map( static fn( $c ) => strtolower( trim( (string) $c ) ), (array) array_shift( $rows ) );
        $col_username = $this->find_col( $header, [ 'username', 'user', 'user_login' ] );
        $col_nama     = $this->find_col( $header, [ 'nama', 'name', 'display_name' ] );
        $col_password = $this->find_col( $header, [ 'password', 'pass', 'kata_sandi' ] );
        $col_email    = $this->find_col( $header, [ 'email', 'e-mail', 'surel' ] );
        if ( null === $col_username ) {
            return $this->error( 'header_invalid', 'Header wajib memuat kolom: username (opsional: nama, password, email).', 422 );
        }

        if ( count( $rows ) > 2000 ) {
            return $this->error( 'terlalu_banyak_baris', 'Maksimal 2000 baris data per impor.', 422 );
        }

        // Per baris: validasi (username valid+unik, password ≥ min bila diisi, email valid+unik bila diisi)
        // → wp_insert_user role `guru`. Password kosong → auto-generate. Error per baris dikumpulkan.
        $min_pass   = 6;
        $seen_user  = [];
        $imported   = 0;
        $errors     = [];

        foreach ( $rows as $i => $row ) {
            $baris    = $i + 2; // +1 header, +1 ke 1-indexed
            $username = trim( (string) ( $row[ $col_username ] ?? '' ) );
            $nama     = null !== $col_nama     ? trim( (string) ( $row[ $col_nama ] ?? '' ) )     : '';
            $password = null !== $col_password ? trim( (string) ( $row[ $col_password ] ?? '' ) ) : '';
            $email    = null !== $col_email    ? trim( (string) ( $row[ $col_email ] ?? '' ) )    : '';

            // Baris benar-benar kosong → lewati diam.
            if ( '' === $username && '' === $nama && '' === $password && '' === $email ) {
                continue;
            }
            if ( '' === $username ) {
                $errors[] = [ 'baris' => $baris, 'pesan' => 'username wajib diisi.' ];
                continue;
            }
            $login = sanitize_user( $username, true );
            if ( '' === $login || ! validate_username( $login ) ) {
                $errors[] = [ 'baris' => $baris, 'pesan' => "username '{$username}' tidak valid." ];
                continue;
            }
            $ukey = strtolower( $login );
            if ( isset( $seen_user[ $ukey ] ) ) {
                $errors[] = [ 'baris' => $baris, 'pesan' => "username '{$login}' duplikat dalam file (lihat baris {$seen_user[ $ukey ]})." ];
                continue;
            }
            if ( username_exists( $login ) ) {
                $errors[] = [ 'baris' => $baris, 'pesan' => "username '{$login}' sudah terdaftar." ];
                continue;
            }
            if ( '' !== $email ) {
                if ( ! is_email( $email ) ) {
                    $errors[] = [ 'baris' => $baris, 'pesan' => "email '{$email}' tidak valid." ];
                    continue;
                }
                if ( email_exists( $email ) ) {
                    $errors[] = [ 'baris' => $baris, 'pesan' => "email '{$email}' sudah terdaftar." ];
                    continue;
                }
            }
            if ( '' === $password ) {
                $password = wp_generate_password( 12, true ); // kosong → auto-generate
            } elseif ( strlen( $password ) < $min_pass ) {
                $errors[] = [ 'baris' => $baris, 'pesan' => "password minimal {$min_pass} karakter." ];
                continue;
            }

            $userdata = [
                'user_login'   => $login,
                'user_pass'    => $password,
                'display_name' => '' !== $nama ? $nama : $login,
                'role'         => 'guru',
            ];
            if ( '' !== $email ) {
                $userdata['user_email'] = $email;
            }
            $uid = wp_insert_user( $userdata );
            if ( is_wp_error( $uid ) ) {
                $errors[] = [ 'baris' => $baris, 'pesan' => $uid->get_error_message() ];
                continue;
            }
            $seen_user[ $ukey ] = $baris;
            $imported++;
        }

        return new \WP_REST_Response( [
            'imported' => $imported,
            'gagal'    => count( $errors ),
            'errors'   => $errors,
        ], 200 );
    }

    /** Ambil path file impor: multipart ($_FILES['file']) atau base64 param 'file'. */
    private function resolve_upload_path( \WP_REST_Request $req ) {
        $files = $req->get_file_params();
        if ( ! empty( $files['file']['tmp_name'] ) && is_uploaded_file( $files['file']['tmp_name'] ) ) {
            return $files['file']['tmp_name']; // multipart; PHP bersihkan sendiri
        }

        $b64 = (string) $req->get_param( 'file' );
        if ( '' === $b64 ) {
            return new \WP_Error( 'file_kosong', 'File impor tidak ditemukan.' );
        }
        if ( str_contains( $b64, ',' ) ) {
            [ , $b64 ] = explode( ',', $b64, 2 );
        }
        $bin = base64_decode( $b64, true );
        if ( false === $bin ) {
            return new \WP_Error( 'file_invalid', 'File base64 tidak valid.' );
        }
        if ( strlen( $bin ) > 5 * 1024 * 1024 ) {
            return new \WP_Error( 'file_terlalu_besar', 'File maksimal 5 MB.' );
        }
        // tempnam + get_temp_dir: tersedia di konteks REST (wp_tempnam ada di wp-admin/includes).
        $tmp = tempnam( get_temp_dir(), 'absensi-import' );
        if ( false === $tmp ) {
            return new \WP_Error( 'temp_gagal', 'Gagal membuat file sementara.' );
        }
        file_put_contents( $tmp, $bin );
        return $tmp;
    }

    /** Hapus file temp yang kita buat (base64). Multipart dibiarkan (PHP yang bersihkan). */
    private function cleanup_temp( string $path ): void {
        if ( $path && ! is_uploaded_file( $path ) && file_exists( $path ) ) {
            @unlink( $path );
        }
    }

    /** Cari indeks kolom pertama yang cocok salah satu alias (lowercase). */
    private function find_col( array $header, array $aliases ): ?int {
        foreach ( $header as $idx => $name ) {
            if ( in_array( $name, $aliases, true ) ) {
                return (int) $idx;
            }
        }
        return null;
    }

    /**
     * Resolve group dari baris impor → id.
     *
     * Nama group boleh kembar beda tipe (mis. "5B" Siswa vs "5B" Guru), jadi:
     * - `$tipe` diisi → cari by (nama, tipe); tak ada → auto-create dengan tipe itu.
     * - `$tipe` kosong → cari by nama saja; tak ada → 0 (BUKAN auto-create: tipe wajib,
     *   tak boleh ada preset diam-diam. Pemanggil melaporkan error per baris).
     * Di-cache per impor.
     */
    private function resolve_group( string $nama, string $tipe, array &$cache ): int {
        global $wpdb;
        $key = strtolower( $nama ) . '|' . strtolower( $tipe );
        if ( isset( $cache[ $key ] ) ) {
            return $cache[ $key ];
        }
        $sql = '' !== $tipe
            ? $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}absensi_group WHERE nama = %s AND tipe = %s LIMIT 1", $nama, $tipe )
            : $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}absensi_group WHERE nama = %s LIMIT 1", $nama );
        $id  = (int) $wpdb->get_var( $sql );

        if ( ! $id && '' !== $tipe ) {
            $wpdb->insert( $wpdb->prefix . 'absensi_group', SanitizeHelper::group( [ 'nama' => $nama, 'tipe' => $tipe ] ) );
            $id = (int) $wpdb->insert_id;
        }
        return $cache[ $key ] = $id;
    }

    /** Admin-only. */
    public function can_manage(): bool {
        return current_user_can( 'manage_options' );
    }

    private function user_args( bool $required ): array {
        return [
            'nomor_induk' => [ 'required' => $required, 'type' => 'string',  'maxLength' => 30 ],
            'nama'        => [ 'required' => $required, 'type' => 'string',  'maxLength' => 150 ],
            'group_id'    => [ 'required' => false,     'type' => 'integer' ],
            'rfid_uid'    => [ 'required' => false,     'type' => 'string',  'maxLength' => 50 ],
        ];
    }

    private function error( string $code, string $message, int $status = 400 ): \WP_REST_Response {
        return new \WP_REST_Response( [ 'code' => $code, 'message' => $message, 'data' => [ 'status' => $status ] ], $status );
    }
}
