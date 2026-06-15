<?php
/**
 * Verifikasi FE — integrasi auto-page + nav visibility + render surface.
 * Jalankan: php tests/_verifikasi_fe_integrasi.php
 */
error_reporting( E_ALL & ~E_DEPRECATED );
require dirname( __DIR__, 4 ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

$plugin = 'plugin-wp-absensi-sekolah/absensi-sekolah.php';
$pass = 0; $fail = 0;
function ok( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "  PASS  $label\n"; }
	else         { $fail++; echo "  FAIL  $label\n"; }
}

echo "== 1. Re-aktivasi plugin (trigger seed_pages) ==\n";
deactivate_plugins( $plugin );
$res = activate_plugin( $plugin );
ok( null === $res, 'activate_plugin tanpa error' . ( is_wp_error( $res ) ? ' — ' . $res->get_error_message() : '' ) );

echo "\n== 2. Auto-page surface ==\n";
$pages = get_option( 'absensi_pages' );
ok( is_array( $pages ), 'option absensi_pages ada (array)' );
$expect = [
	'siswa' => [ 'absensi-siswa', '[absensi_siswa]' ],
	'guru'  => [ 'absensi-guru',  '[absensi_guru]'  ],
	'ortu'  => [ 'absensi-ortu',  '[absensi_ortu]'  ],
];
foreach ( $expect as $key => [$slug, $sc] ) {
	$id = $pages[ $key ] ?? 0;
	$p  = $id ? get_post( $id ) : null;
	ok( $p && 'page' === $p->post_type, "page '$key' ada (ID $id)" );
	ok( $p && 'publish' === $p->post_status, "page '$key' status publish" );
	ok( $p && $slug === $p->post_name, "page '$key' slug /$slug" );
	ok( $p && str_contains( $p->post_content, $sc ), "page '$key' berisi shortcode $sc" );
}

echo "\n== 3. Roles ter-seed ==\n";
foreach ( [ 'absensi_siswa', 'guru', 'orang_tua', 'absensi_admin' ] as $r ) {
	ok( null !== get_role( $r ), "role '$r' ada" );
}

echo "\n== 4. User uji per role ==\n";
$users = [];
foreach ( [ 'absensi_siswa', 'guru', 'orang_tua', 'administrator' ] as $role ) {
	$login = 'qa_' . $role;
	$u = get_user_by( 'login', $login );
	if ( ! $u ) {
		$uid = wp_create_user( $login, wp_generate_password(), $login . '@example.test' );
		$u = get_user_by( 'id', $uid );
		$u->set_role( $role );
	}
	$users[ $role ] = $u->ID;
	ok( true, "user $login (ID {$u->ID})" );
}

echo "\n== 5. Nav visibility per role (get_pages — jalur block theme) ==\n";
$page_ids = array_values( array_map( 'intval', $pages ) );
$visible = function () use ( $page_ids ) {
	$got = wp_list_pluck( get_pages(), 'ID' );
	$v = [];
	foreach ( [ 'siswa', 'guru', 'ortu' ] as $i => $k ) {
		if ( in_array( $page_ids[ array_search( $k, [ 'siswa', 'guru', 'ortu' ] ) ], $got, true ) ) $v[] = $k;
	}
	global $absensi_pages_map;
	return $v;
};
$lihat = function ( $uid ) use ( $pages ) {
	wp_set_current_user( $uid );
	$got = wp_list_pluck( get_pages(), 'ID' );
	$v = [];
	foreach ( $pages as $k => $id ) { if ( in_array( (int) $id, array_map( 'intval', $got ), true ) ) $v[] = $k; }
	sort( $v );
	return $v;
};
ok( $lihat( $users['absensi_siswa'] ) === [ 'siswa' ], 'role absensi_siswa → hanya [siswa]' );
ok( $lihat( $users['guru'] )          === [ 'guru' ],  'role guru → hanya [guru]' );
ok( $lihat( $users['orang_tua'] )     === [ 'ortu' ],  'role orang_tua → hanya [ortu]' );
ok( $lihat( $users['administrator'] ) === [ 'guru', 'ortu', 'siswa' ], 'administrator → ketiganya' );
wp_set_current_user( 0 );
ok( $lihat( 0 ) === [], 'guest → tak ada link surface' );

echo "\n== 6. Render shortcode per role (gate + view FE) ==\n";
wp_set_current_user( 0 );
$out = do_shortcode( '[absensi_siswa]' );
ok( str_contains( $out, 'login' ), 'guest → [absensi_siswa] minta login' );

wp_set_current_user( $users['absensi_siswa'] );
$out = do_shortcode( '[absensi_siswa]' );
ok( str_contains( $out, 'absensiSiswa' ), 'siswa → [absensi_siswa] render view FE (x-data absensiSiswa)' );
$out = do_shortcode( '[absensi_guru]' );
ok( str_contains( $out, 'tidak memiliki akses' ), 'siswa → [absensi_guru] ditolak (cap)' );

wp_set_current_user( $users['guru'] );
$out = do_shortcode( '[absensi_guru]' );
ok( str_contains( $out, 'absensiGuru' ), 'guru → [absensi_guru] render view FE (x-data absensiGuru)' );

wp_set_current_user( $users['orang_tua'] );
$out = do_shortcode( '[absensi_ortu]' );
ok( str_contains( $out, 'absensiOrtu' ), 'ortu → [absensi_ortu] render view FE (x-data absensiOrtu)' );
$out = do_shortcode( '[absensi_guru]' );
ok( str_contains( $out, 'tidak memiliki akses' ), 'ortu → [absensi_guru] ditolak (cap)' );

echo "\n== 7. Aset publik ==\n";
ok( file_exists( ABSENSI_PLUGIN_DIR . 'public/js/alpine.min.js' ), 'public/js/alpine.min.js ada' );
$js = file_get_contents( ABSENSI_PLUGIN_DIR . 'public/js/public.js' );
ok( str_contains( $js, 'alpine.min.js' ), 'public.js memuat bootstrap Alpine' );

echo "\n=================================\n";
echo "HASIL: $pass pass, $fail fail\n";
exit( $fail ? 1 : 0 );
