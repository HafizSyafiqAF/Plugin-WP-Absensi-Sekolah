<?php
require dirname( __DIR__, 4 ) . '/wp-load.php';
foreach ( [ 'qa_absensi_siswa', 'qa_guru', 'qa_orang_tua' ] as $login ) {
	$u = get_user_by( 'login', $login );
	if ( $u ) { wp_set_password( 'QaTest#2026', $u->ID ); echo $login, " ok\n"; }
}
