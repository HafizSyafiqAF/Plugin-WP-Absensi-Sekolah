<?php
/** Seed data uji untuk surface ortu: kelas, 2 siswa, link wali ke qa_orang_tua, rekap bulan ini. */
require dirname( __DIR__, 4 ) . '/wp-load.php';
global $wpdb;
$p = $wpdb->prefix;

$ortu = get_user_by( 'login', 'qa_orang_tua' );
if ( ! $ortu ) { exit( "user qa_orang_tua tidak ada\n" ); }

// Kelas
$kelas_id = $wpdb->get_var( "SELECT id FROM {$p}absensi_kelas WHERE nama_kelas = 'QA 7A'" );
if ( ! $kelas_id ) {
	$wpdb->insert( "{$p}absensi_kelas", [ 'nama_kelas' => 'QA 7A' ] );
	$kelas_id = $wpdb->insert_id;
}
echo "kelas QA 7A id=$kelas_id\n";

// 2 siswa (anak)
$anak_ids = [];
foreach ( [ [ 'QA-001', 'Budi Santoso QA' ], [ 'QA-002', 'Siti Aminah QA' ] ] as [ $nis, $nama ] ) {
	$id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$p}absensi_siswa WHERE nis = %s", $nis ) );
	if ( ! $id ) {
		$wpdb->insert( "{$p}absensi_siswa", [ 'nis' => $nis, 'nama' => $nama, 'kelas_id' => $kelas_id ] );
		$id = $wpdb->insert_id;
	}
	$anak_ids[] = (int) $id;
	echo "siswa $nama id=$id\n";
}

// Link wali
foreach ( $anak_ids as $sid ) {
	$ada = $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$p}absensi_wali WHERE wali_user_id = %d AND siswa_id = %d", $ortu->ID, $sid ) );
	if ( ! $ada ) {
		$wpdb->insert( "{$p}absensi_wali", [ 'wali_user_id' => $ortu->ID, 'siswa_id' => $sid ] );
	}
}
echo "wali link ok (user {$ortu->ID})\n";

// Rekap beberapa hari bulan ini (anak pertama)
$now = new DateTimeImmutable( 'now', wp_timezone() );
$rows = [
	[ $now->modify( '-4 days' ), '07:02:11', '15:05:40', 'hadir' ],
	[ $now->modify( '-3 days' ), '07:25:03', '15:01:12', 'telat' ],
	[ $now->modify( '-2 days' ), null,        null,       'sakit' ],
	[ $now->modify( '-1 days' ), '06:58:45', null,        'hadir' ],
];
foreach ( $rows as [ $hari, $masuk, $keluar, $status ] ) {
	$tgl = $hari->format( 'Y-m-d' );
	$ada = $wpdb->get_var( $wpdb->prepare(
		"SELECT id FROM {$p}absensi_rekap WHERE siswa_id = %d AND tanggal = %s", $anak_ids[0], $tgl ) );
	if ( $ada ) continue;
	$wpdb->insert( "{$p}absensi_rekap", [
		'siswa_id'     => $anak_ids[0],
		'kelas_id'     => $kelas_id,
		'tanggal'      => $tgl,
		'waktu_masuk'  => $masuk ? "$tgl $masuk" : null,
		'waktu_keluar' => $keluar ? "$tgl $keluar" : null,
		'status'       => $status,
		'mode'         => 'manual',
	] );
	echo "rekap $tgl $status" . ( $wpdb->last_error ? " ERR: {$wpdb->last_error}" : ' ok' ) . "\n";
}
echo "selesai\n";
