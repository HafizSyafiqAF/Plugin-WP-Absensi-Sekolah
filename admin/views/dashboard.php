<?php
defined( 'ABSPATH' ) || exit;
global $wpdb;
$prefix  = $wpdb->prefix;
$today   = current_time( 'Y-m-d' );

$total_siswa = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}absensi_siswa" );

$rekap_hari_ini = $wpdb->get_row( $wpdb->prepare(
    "SELECT
        SUM(status='hadir')             AS hadir,
        SUM(status='telat')             AS telat,
        SUM(status IN('izin','sakit'))  AS izin_sakit,
        SUM(status='alpha')             AS alpha,
        COUNT(*)                        AS total
     FROM {$prefix}absensi_rekap
     WHERE tanggal = %s",
    $today
), ARRAY_A );

$hadir      = (int) ( $rekap_hari_ini['hadir']     ?? 0 );
$telat      = (int) ( $rekap_hari_ini['telat']     ?? 0 );
$izin_sakit = (int) ( $rekap_hari_ini['izin_sakit']?? 0 );
$alpha      = (int) ( $rekap_hari_ini['alpha']     ?? 0 );

$absen_terbaru = $wpdb->get_results( $wpdb->prepare(
    "SELECT r.*, s.nama, s.nis, k.nama_kelas
       FROM {$prefix}absensi_rekap   r
       JOIN {$prefix}absensi_siswa   s ON s.id = r.siswa_id
       LEFT JOIN {$prefix}absensi_kelas k ON k.id = r.kelas_id
      WHERE r.tanggal = %s
      ORDER BY COALESCE(r.waktu_keluar, r.waktu_masuk) DESC
      LIMIT 10",
    $today
) );

/* Helpers */
$pct = fn(int $val) => $total_siswa > 0 ? round( $val / $total_siswa * 100 ) : 0;
$hadir_pct     = $pct( $hadir );
$telat_pct     = $pct( $telat );
$izin_pct      = $pct( $izin_sakit );
$alpha_pct     = $pct( $alpha );
$total_absen   = $hadir + $telat + $izin_sakit + $alpha;
$kehadiran_pct = $total_siswa > 0 ? round( ( $hadir + $telat ) / $total_siswa * 100 ) : 0;

/* SVG sparkline paths — line + area (closed at bottom) */
$line = [
    'primary' => 'M0,22 C8,18 16,26 24,20 C32,14 40,24 48,16 C56,8 64,20 72,14 C80,8 88,18 96,12',
    'success' => 'M0,24 C8,20 16,14 24,18 C32,22 40,10 48,14 C56,18 64,8 72,12 C80,16 88,6 96,10',
    'warning' => 'M0,16 C8,22 16,18 24,24 C32,20 40,26 48,18 C56,10 64,22 72,16 C80,20 88,12 96,18',
    'info'    => 'M0,20 C8,14 16,22 24,16 C32,10 40,18 48,12 C56,16 64,8 72,14 C80,20 88,10 96,16',
    'danger'  => 'M0,12 C8,18 16,14 24,20 C32,26 40,18 48,24 C56,16 64,22 72,14 C80,10 88,18 96,12',
];
$area = array_map( fn($p) => $p . ' L96,32 L0,32 Z', $line );

/* SVG donut */
$r    = 52;
$circ = round( 2 * M_PI * $r, 2 );
$dash_hadir = round( $circ * $hadir_pct  / 100, 2 );
$dash_telat = round( $circ * $telat_pct  / 100, 2 );
$dash_izin  = round( $circ * $izin_pct   / 100, 2 );
$dash_alpha = round( $circ * $alpha_pct  / 100, 2 );
$off_hadir  = 0;
$off_telat  = $circ - $dash_hadir;
$off_izin   = $circ - $dash_hadir - $dash_telat;
$off_alpha  = $circ - $dash_hadir - $dash_telat - $dash_izin;
?>

<div class="wrap db-wrap">
  <!-- Blob background — fixed, di belakang semua konten -->
  <div class="db-bg" aria-hidden="true">
    <span class="db-blob db-blob--1"></span>
    <span class="db-blob db-blob--2"></span>
    <span class="db-blob db-blob--3"></span>
  </div>
  <hr class="wp-header-end" style="margin:0">


  <!-- ══ HERO ══ -->
  <div class="db-hero">
    <div class="db-hero__left">
      <p class="db-hero__eyebrow">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
        </svg>
        <?php echo esc_html( wp_date( 'l, d F Y', strtotime( $today ) ) ); ?>
      </p>
      <h1 class="db-hero__title">
        <?php esc_html_e( 'Selamat datang,', 'absensi-sekolah' ); ?>
        <span class="db-hero__name"><?php echo esc_html( wp_get_current_user()->display_name ); ?></span>
      </h1>
      <p class="db-hero__sub"><?php esc_html_e( 'Rekap kehadiran hari ini untuk semua kelas.', 'absensi-sekolah' ); ?></p>
      <div class="db-hero__chips">
        <span class="db-chip db-chip--glass">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="8" r="4"/><path d="M20 21a8 8 0 10-16 0"/></svg>
          <?php echo esc_html( $total_siswa ); ?> Siswa
        </span>
        <span class="db-chip db-chip--green">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
          <?php echo esc_html( $kehadiran_pct ); ?>% Hadir Hari Ini
        </span>
        <?php if ( $alpha > 0 ) : ?>
        <span class="db-chip db-chip--red">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
          <?php echo esc_html( $alpha ); ?> Alpha
        </span>
        <?php endif; ?>
      </div>
    </div>

    <div class="db-hero__ring-wrap">
      <svg class="db-ring" viewBox="0 0 120 120" width="126" height="126">
        <circle cx="60" cy="60" r="<?php echo $r; ?>" fill="none" stroke="rgba(0,0,0,.06)" stroke-width="11"/>
        <?php if ($dash_hadir > 0) : ?>
        <circle cx="60" cy="60" r="<?php echo $r; ?>" fill="none" stroke="#4ade80" stroke-width="11"
          stroke-dasharray="<?php echo $dash_hadir.' '.($circ-$dash_hadir); ?>"
          stroke-dashoffset="<?php echo $off_hadir; ?>"
          transform="rotate(-90 60 60)" stroke-linecap="round"/>
        <?php endif; ?>
        <?php if ($dash_telat > 0) : ?>
        <circle cx="60" cy="60" r="<?php echo $r; ?>" fill="none" stroke="#fb923c" stroke-width="11"
          stroke-dasharray="<?php echo $dash_telat.' '.($circ-$dash_telat); ?>"
          stroke-dashoffset="<?php echo $off_telat; ?>"
          transform="rotate(-90 60 60)" stroke-linecap="round"/>
        <?php endif; ?>
        <?php if ($dash_izin > 0) : ?>
        <circle cx="60" cy="60" r="<?php echo $r; ?>" fill="none" stroke="#38bdf8" stroke-width="11"
          stroke-dasharray="<?php echo $dash_izin.' '.($circ-$dash_izin); ?>"
          stroke-dashoffset="<?php echo $off_izin; ?>"
          transform="rotate(-90 60 60)" stroke-linecap="round"/>
        <?php endif; ?>
        <?php if ($dash_alpha > 0) : ?>
        <circle cx="60" cy="60" r="<?php echo $r; ?>" fill="none" stroke="#f87171" stroke-width="11"
          stroke-dasharray="<?php echo $dash_alpha.' '.($circ-$dash_alpha); ?>"
          stroke-dashoffset="<?php echo $off_alpha; ?>"
          transform="rotate(-90 60 60)" stroke-linecap="round"/>
        <?php endif; ?>
        <text x="60" y="55" text-anchor="middle" font-family="'Plus Jakarta Sans', sans-serif" font-size="16" font-weight="800" fill="#1E293B"><?php echo $kehadiran_pct; ?>%</text>
        <text x="60" y="69" text-anchor="middle" font-family="'Plus Jakarta Sans', sans-serif" font-size="8" font-weight="700" fill="#64748B" letter-spacing="1">HADIR</text>
      </svg>
      <div class="db-ring__legend">
        <span class="db-ring__dot" style="--c:#4ade80">Hadir</span>
        <span class="db-ring__dot" style="--c:#fb923c">Telat</span>
        <span class="db-ring__dot" style="--c:#38bdf8">Izin/Sakit</span>
        <span class="db-ring__dot" style="--c:#f87171">Alpha</span>
      </div>
    </div>
  </div>

  <!-- ══ STAT CARDS ══ -->
  <div class="db-cards">

    <?php
    $cards = [
      [
        'key'   => 'primary',
        'label' => 'Total Siswa',
        'val'   => $total_siswa,
        'pct'   => 100,
        'sub'   => $total_siswa . ' terdaftar',
        'trend' => 'up',
        'color' => '#006666',
        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>',
      ],
      [
        'key'   => 'success',
        'label' => 'Hadir',
        'val'   => $hadir,
        'pct'   => $hadir_pct,
        'sub'   => $hadir_pct . '% dari total',
        'trend' => 'up',
        'color' => '#00A63D',
        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
      ],
      [
        'key'   => 'warning',
        'label' => 'Telat',
        'val'   => $telat,
        'pct'   => $telat_pct,
        'sub'   => $telat > 0 ? $telat_pct.'% dari total' : 'Tidak ada keterlambatan',
        'trend' => $telat > 0 ? 'down' : null,
        'color' => '#FE9900',
        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>',
      ],
      [
        'key'   => 'info',
        'label' => 'Izin / Sakit',
        'val'   => $izin_sakit,
        'pct'   => $izin_pct,
        'sub'   => 'Izin &amp; sakit hari ini',
        'trend' => null,
        'color' => '#0891B2',
        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>',
      ],
      [
        'key'   => 'danger',
        'label' => 'Alpha',
        'val'   => $alpha,
        'pct'   => $alpha_pct,
        'sub'   => $alpha > 0 ? $alpha_pct.'% tanpa keterangan' : 'Semua hadir',
        'trend' => $alpha > 0 ? 'down' : null,
        'color' => '#FF2157',
        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
      ],
    ];
    foreach ( $cards as $c ) :
      $c_hex = $c['color'];
    ?>
    <div class="db-card">
      <!-- SVG gradient defs per card -->
      <svg width="0" height="0" style="position:absolute">
        <defs>
          <linearGradient id="grad-<?php echo esc_attr($c['key']); ?>" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="<?php echo esc_attr($c_hex); ?>" stop-opacity="0.18"/>
            <stop offset="100%" stop-color="<?php echo esc_attr($c_hex); ?>" stop-opacity="0"/>
          </linearGradient>
        </defs>
      </svg>

      <div class="db-card__top">
        <div class="db-card__id">
          <div class="db-card__icon" style="background:<?php echo esc_attr($c_hex); ?>18;color:<?php echo esc_attr($c_hex); ?>">
            <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
              <?php echo $c['icon']; ?>
            </svg>
          </div>
          <span class="db-card__label"><?php echo esc_html( $c['label'] ); ?></span>
        </div>
        <!-- Sparkline with area fill -->
        <svg class="db-spark" viewBox="0 0 96 32" fill="none" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
          <path d="<?php echo esc_attr( $area[ $c['key'] ] ); ?>" fill="url(#grad-<?php echo esc_attr($c['key']); ?>)"/>
          <path d="<?php echo esc_attr( $line[ $c['key'] ] ); ?>" stroke="<?php echo esc_attr($c_hex); ?>" stroke-width="2.2" stroke-linecap="round" fill="none"/>
        </svg>
      </div>

      <p class="db-card__value" style="color:<?php echo esc_attr($c_hex); ?>"><?php echo esc_html( $c['val'] ); ?></p>

      <div class="db-card__footer">
        <?php if ( $c['trend'] === 'up' ) : ?>
          <span class="db-trend db-trend--up">▲</span>
        <?php elseif ( $c['trend'] === 'down' ) : ?>
          <span class="db-trend db-trend--down">▼</span>
        <?php endif; ?>
        <span class="db-card__sub"><?php echo $c['sub']; ?></span>
      </div>
    </div>
    <?php endforeach; ?>

  </div>

  <!-- ══ BAWAH: TABEL + RINGKASAN ══ -->
  <div class="db-bottom">

    <!-- Tabel -->
    <div class="db-panel" x-data="dashboardTable">
      <div class="db-panel__head">
        <div class="db-panel__head-left">
          <div class="db-panel__head-icon">
            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
          </div>
          <div>
            <h2 class="db-panel__title"><?php esc_html_e( 'Aktivitas Terbaru', 'absensi-sekolah' ); ?></h2>
            <p class="db-panel__sub"><?php esc_html_e( '10 absensi terakhir hari ini', 'absensi-sekolah' ); ?></p>
          </div>
        </div>
        <a href="<?php echo esc_url( admin_url('admin.php?page=absensi-laporan') ); ?>" class="db-btn-outline">
          <?php esc_html_e( 'Semua Laporan', 'absensi-sekolah' ); ?>
          <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
          </svg>
        </a>
      </div>

      <?php if ( empty( $absen_terbaru ) ) : ?>
        <div class="db-empty">
          <div class="db-empty__ico">
            <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.4">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
          </div>
          <p class="db-empty__title"><?php esc_html_e( 'Belum ada absensi hari ini', 'absensi-sekolah' ); ?></p>
          <p class="db-empty__sub"><?php esc_html_e( 'Data akan muncul setelah siswa melakukan absensi.', 'absensi-sekolah' ); ?></p>
        </div>
      <?php else : ?>
        <div class="db-tbl-scroll">
          <table class="db-tbl">
            <thead>
              <tr>
                <th class="db-th--num">#</th>
                <th><?php esc_html_e( 'Siswa', 'absensi-sekolah' ); ?></th>
                <th><?php esc_html_e( 'Kelas', 'absensi-sekolah' ); ?></th>
                <th><?php esc_html_e( 'Masuk', 'absensi-sekolah' ); ?></th>
                <th><?php esc_html_e( 'Pulang', 'absensi-sekolah' ); ?></th>
                <th><?php esc_html_e( 'Status', 'absensi-sekolah' ); ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ( $absen_terbaru as $i => $row ) :
                $sc = [
                  'hadir' => ['cls'=>'success','dot'=>'#00A63D','bg'=>'#DCFCE7','txt'=>'#007A2E'],
                  'telat' => ['cls'=>'warning','dot'=>'#FE9900','bg'=>'#FEF3C7','txt'=>'#9A5700'],
                  'alpha' => ['cls'=>'danger', 'dot'=>'#FF2157','bg'=>'#FFE4EC','txt'=>'#B0102F'],
                  'izin'  => ['cls'=>'info',   'dot'=>'#0891B2','bg'=>'#E0F7FA','txt'=>'#076484'],
                  'sakit' => ['cls'=>'info',   'dot'=>'#0891B2','bg'=>'#E0F7FA','txt'=>'#076484'],
                ][ $row->status ] ?? ['cls'=>'muted','dot'=>'#AAA','bg'=>'#F0F1F5','txt'=>'#666'];
                $initials    = implode('', array_slice( array_map( fn($w) => strtoupper($w[0] ?? ''), explode(' ', $row->nama) ), 0, 2 ));
                $mode_masuk  = $row->metode_masuk  ?? ( $row->mode ?? null );
                $mode_keluar = $row->metode_keluar ?? null;
              ?>
              <tr>
                <td class="db-td--num"><?php echo $i + 1; ?></td>
                <td>
                  <div class="db-siswa">
                    <div class="db-avatar" style="background:<?php echo esc_attr($sc['bg']); ?>;color:<?php echo esc_attr($sc['txt']); ?>"><?php echo esc_html($initials); ?></div>
                    <div>
                      <p class="db-siswa__name"><?php echo esc_html($row->nama); ?></p>
                      <p class="db-siswa__nis"><?php echo esc_html($row->nis); ?></p>
                    </div>
                  </div>
                </td>
                <td><span class="db-kelas"><?php echo esc_html($row->nama_kelas ?? '—'); ?></span></td>
                <td>
                  <?php if ($row->waktu_masuk) : ?>
                    <p class="db-time" x-text="fmtTime('<?php echo esc_attr($row->waktu_masuk); ?>')"><?php echo esc_html(substr($row->waktu_masuk,11,5)); ?></p>
                    <?php if ($mode_masuk) : ?><span class="db-mode db-mode--in"><?php echo esc_html($mode_masuk); ?></span><?php endif; ?>
                  <?php else : ?><span class="db-dash">—</span><?php endif; ?>
                </td>
                <td>
                  <?php if ($row->waktu_keluar) : ?>
                    <p class="db-time db-time--out" x-text="fmtTime('<?php echo esc_attr($row->waktu_keluar); ?>')"><?php echo esc_html(substr($row->waktu_keluar,11,5)); ?></p>
                    <?php if ($mode_keluar) : ?><span class="db-mode db-mode--out"><?php echo esc_html($mode_keluar); ?></span><?php endif; ?>
                  <?php else : ?><span class="db-dash">—</span><?php endif; ?>
                </td>
                <td>
                  <span class="db-badge" style="background:<?php echo esc_attr($sc['bg']); ?>;color:<?php echo esc_attr($sc['txt']); ?>">
                    <span class="db-badge__dot" style="background:<?php echo esc_attr($sc['dot']); ?>"></span>
                    <?php echo esc_html(ucfirst($row->status)); ?>
                  </span>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="db-tbl-foot">
          <span><?php printf( esc_html__('Menampilkan %d data terbaru','absensi-sekolah'), count($absen_terbaru) ); ?></span>
          <a href="<?php echo esc_url(admin_url('admin.php?page=absensi-laporan')); ?>" class="db-tbl-foot__lnk">Lihat semua →</a>
        </div>
      <?php endif; ?>
    </div><!-- /tabel -->

    <!-- Ringkasan -->
    <div class="db-panel db-summary-panel">
      <div class="db-panel__head">
        <div class="db-panel__head-left">
          <div class="db-panel__head-icon">
            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/><path stroke-linecap="round" stroke-linejoin="round" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/>
            </svg>
          </div>
          <div>
            <h2 class="db-panel__title"><?php esc_html_e('Ringkasan Hari Ini','absensi-sekolah'); ?></h2>
          </div>
        </div>
      </div>
      <div class="db-summary">
        <?php
        $rows = [
          ['label'=>'Hadir',      'val'=>$hadir,      'pct'=>$hadir_pct,  'c'=>'#00A63D','bg'=>'#DCFCE7','tc'=>'#007A2E'],
          ['label'=>'Telat',      'val'=>$telat,      'pct'=>$telat_pct,  'c'=>'#FE9900','bg'=>'#FEF3C7','tc'=>'#9A5700'],
          ['label'=>'Izin/Sakit', 'val'=>$izin_sakit, 'pct'=>$izin_pct,   'c'=>'#0891B2','bg'=>'#E0F7FA','tc'=>'#076484'],
          ['label'=>'Alpha',      'val'=>$alpha,      'pct'=>$alpha_pct,  'c'=>'#FF2157','bg'=>'#FFE4EC','tc'=>'#B0102F'],
        ];
        foreach ($rows as $r) : ?>
        <div class="db-sum-row">
          <div class="db-sum-row__left">
            <span class="db-sum-row__dot" style="background:<?php echo esc_attr($r['c']); ?>"></span>
            <span class="db-sum-row__lbl"><?php echo esc_html($r['label']); ?></span>
          </div>
          <div class="db-sum-row__right">
            <span class="db-sum-row__val" style="color:<?php echo esc_attr($r['c']); ?>"><?php echo esc_html($r['val']); ?></span>
            <div class="db-sum-bar"><div class="db-sum-bar__fill" style="width:<?php echo $r['pct']; ?>%;background:<?php echo esc_attr($r['c']); ?>"></div></div>
            <span class="db-sum-row__pct"><?php echo $r['pct']; ?>%</span>
          </div>
        </div>
        <?php endforeach; ?>
        <div class="db-sum-total">
          <span><?php esc_html_e('Total Absensi','absensi-sekolah'); ?></span>
          <strong><?php echo esc_html($total_absen); ?> / <?php echo esc_html($total_siswa); ?></strong>
        </div>
      </div>
    </div><!-- /ringkasan -->

  </div><!-- /db-bottom -->
</div><!-- /db-wrap -->

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap');

/* ══ BASE ══ */
.db-wrap *, .db-wrap *::before, .db-wrap *::after { box-sizing: border-box; }
.db-wrap {
  font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif !important;
  min-height: 100vh;
  padding-bottom: 48px;
  position: relative;
  z-index: 0;
}

/* ══ BACKGROUND — Clean, light glassmorphism/neumorphism canvas ══ */
body.wp-admin {
  background: #EAF0F6 !important;
}
#wpcontent, #wpbody-content, #wpbody {
  background: linear-gradient(135deg, #F5F7FB 0%, #E2E8F0 100%) fixed !important;
}

/* ══ BLOB — vibrant light accent blobs for glassmorphism ══ */
.db-bg {
  position: fixed;
  inset: 0;
  pointer-events: none;
  z-index: 0;
  overflow: hidden;
}
.db-blob {
  position: absolute;
  border-radius: 50%;
  filter: blur(140px);
  opacity: 0.85;
}
.db-blob--1 {
  width: 750px; height: 750px;
  top: -180px; left: -120px;
  background: radial-gradient(circle, rgba(129, 140, 248, 0.45) 0%, rgba(99, 102, 241, 0.15) 65%, transparent 100%);
}
.db-blob--2 {
  width: 700px; height: 700px;
  bottom: -150px; right: -80px;
  background: radial-gradient(circle, rgba(244, 114, 182, 0.40) 0%, rgba(219, 39, 119, 0.12) 65%, transparent 100%);
}
.db-blob--3 {
  width: 600px; height: 600px;
  top: 25%; right: 10%;
  background: radial-gradient(circle, rgba(103, 232, 249, 0.42) 0%, rgba(6, 182, 212, 0.12) 65%, transparent 100%);
}

/* Seluruh konten harus di atas blob */
.db-hero, .db-cards, .db-bottom { position: relative; z-index: 1; }

/* ══ HERO — Soft light glassmorphism card ══ */
.db-hero {
  background: rgba(255, 255, 255, 0.40);
  backdrop-filter: blur(24px) saturate(150%);
  -webkit-backdrop-filter: blur(24px) saturate(150%);
  border: 1px solid rgba(255, 255, 255, 0.65);
  border-radius: 24px;
  padding: 26px 30px;
  margin: 14px 0 18px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  overflow: hidden;
  position: relative;
  z-index: 1;
  box-shadow: 6px 6px 20px rgba(163, 177, 198, 0.25), 
              -6px -6px 20px rgba(255, 255, 255, 0.8),
              inset 0 1px 1px rgba(255, 255, 255, 0.7);
}
.db-hero::after {
  content: '';
  position: absolute; inset: 0;
  background-image: radial-gradient(circle, rgba(79,70,229,.015) 1px, transparent 1px);
  background-size: 22px 22px;
  pointer-events: none;
}
.db-hero__left { flex: 1; min-width: 0; position: relative; z-index: 1; }
.db-hero__eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 10.5px;
  font-weight: 700;
  color: var(--c-primary);
  background: var(--c-primary-soft);
  padding: 5px 11px;
  border-radius: 8px;
  letter-spacing: .02em;
  text-transform: uppercase;
  margin: 0 0 12px;
  border: 1px solid rgba(79,70,229,0.1);
  box-shadow: 0 1px 2px rgba(0,0,0,0.01);
}
.db-hero__title { font-size: 22px; font-weight: 800; color: #1E293B; margin: 0 0 6px; line-height: 1.25; }
.db-hero__name {
  background: linear-gradient(135deg, var(--c-primary) 0%, #7C3AED 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  font-weight: 800;
}
.db-hero__sub  { font-size: 13.5px; color: #64748B; margin: 0 0 16px; }
.db-hero__chips { display: flex; flex-wrap: wrap; gap: 8px; }
.db-chip {
  display: inline-flex; align-items: center; gap: 5px;
  font-size: 11.5px; font-weight: 600; padding: 5px 13px; border-radius: 20px;
}
.db-chip--glass { background: rgba(255,255,255,.6); color: #334155; border: 1px solid rgba(255,255,255,.8); }
.db-chip--green { background: rgba(16,185,129,.1); color: #059669; border: 1px solid rgba(16,185,129,.15); }
.db-chip--red   { background: rgba(239,68,68,.1); color: #dc2626; border: 1px solid rgba(239,68,68,.15); }
.db-hero__ring-wrap {
  display: flex; flex-direction: column; align-items: center; gap: 10px;
  flex-shrink: 0; position: relative; z-index: 1;
}
.db-ring { filter: drop-shadow(0 4px 10px rgba(163,177,198,.3)); }
.db-ring__legend { display: flex; flex-direction: column; gap: 4px; }
.db-ring__dot {
  font-size: 10.5px; font-weight: 600; color: #475569;
  display: flex; align-items: center; gap: 6px;
}
.db-ring__dot::before { content: ''; width: 7px; height: 7px; border-radius: 50%; background: var(--c); flex-shrink: 0; }

/* ══ STAT CARDS grid ══ */
.db-cards {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 14px;
  margin-bottom: 18px;
}
@media (max-width: 1200px) { .db-cards { grid-template-columns: repeat(3,1fr); } }
@media (max-width: 680px)  { .db-cards { grid-template-columns: repeat(2,1fr); } }

/* ══ STAT CARDS — Light neumorphic glass ══ */
.db-card {
  background: rgba(255, 255, 255, 0.40);
  backdrop-filter: blur(24px) saturate(150%);
  -webkit-backdrop-filter: blur(24px) saturate(150%);
  border-radius: 24px;
  padding: 18px 18px 15px;
  border: 1px solid rgba(255, 255, 255, 0.65);
  box-shadow: 6px 6px 20px rgba(163, 177, 198, 0.25), 
              -6px -6px 20px rgba(255, 255, 255, 0.8),
              inset 0 1px 1px rgba(255, 255, 255, 0.7);
  transition: transform .2s, box-shadow .2s, background .2s;
  position: relative;
  overflow: hidden;
}
.db-card:hover {
  transform: translateY(-3px);
  background: rgba(255, 255, 255, 0.60);
  box-shadow: 10px 10px 30px rgba(163, 177, 198, 0.35), 
              -10px -10px 30px rgba(255, 255, 255, 0.9),
              inset 0 1px 1px rgba(255, 255, 255, 0.8);
  border-color: rgba(255, 255, 255, 0.75);
}
.db-card__top { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 12px; }
.db-card__id { display: flex; align-items: center; gap: 9px; }
.db-card__icon {
  width: 38px;
  height: 38px;
  border-radius: 11px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  box-shadow: inset 0 1px 1px rgba(255,255,255,0.35), 0 2px 4px rgba(0,0,0,0.03);
  border: 1px solid rgba(0,0,0,0.02);
}
.db-card__label { font-size: 12px; font-weight: 600; color: #475569; }
.db-spark { width: 80px; height: 30px; flex-shrink: 0; overflow: visible; }
.db-card__value {
  font-size: 36px; font-weight: 800; line-height: 1; margin: 0 0 10px;
  letter-spacing: -.02em; font-variant-numeric: tabular-nums;
}
.db-card__footer { display: flex; align-items: center; gap: 5px; }
.db-card__sub  { font-size: 11.5px; color: #64748B; }
.db-trend      { font-size: 11px; font-weight: 700; }
.db-trend--up  { color: #059669; }
.db-trend--down{ color: #dc2626; }

/* ══ BOTTOM LAYOUT ══ */
.db-bottom { display: grid; grid-template-columns: 1fr 290px; gap: 16px; align-items: start; }
@media (max-width: 960px) { .db-bottom { grid-template-columns: 1fr; } }

/* ══ PANEL ══ */
.db-panel {
  background: rgba(255, 255, 255, 0.40);
  backdrop-filter: blur(24px) saturate(150%);
  -webkit-backdrop-filter: blur(24px) saturate(150%);
  border-radius: 24px;
  border: 1px solid rgba(255, 255, 255, 0.65);
  box-shadow: 6px 6px 20px rgba(163, 177, 198, 0.25), 
              -6px -6px 20px rgba(255, 255, 255, 0.8),
              inset 0 1px 1px rgba(255, 255, 255, 0.7);
  overflow: hidden;
}
.db-panel__head {
  display: flex; align-items: center; justify-content: space-between;
  padding: 14px 18px; border-bottom: 1px solid rgba(0,0,0,.05);
  gap: 10px; flex-wrap: wrap;
  background: rgba(255,255,255,.3);
}
.db-panel__head-left { display: flex; align-items: center; gap: 10px; }
.db-panel__head-icon {
  width: 30px; height: 30px; background: var(--c-primary-soft); color: var(--c-primary);
  border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.db-panel__title { font-size: 13.5px; font-weight: 700; color: #1E293B; margin: 0; }
.db-panel__sub   { font-size: 11.5px; color: #64748B; margin: 0; }

.db-btn-outline {
  display: inline-flex; align-items: center; gap: 5px;
  font-size: 12px; font-weight: 600; color: var(--c-primary);
  text-decoration: none; padding: 6px 13px;
  border: 1.5px solid var(--c-primary-mid); border-radius: 8px;
  transition: background .15s, color .15s, border-color .15s;
  white-space: nowrap; font-family: 'Plus Jakarta Sans', sans-serif;
}
.db-btn-outline:hover { background: var(--c-primary-soft); border-color: var(--c-primary); color: var(--c-primary-hover); }

/* ══ TABLE ══ */
.db-tbl-scroll { 
  overflow-x: auto; 
  scrollbar-width: thin;
  scrollbar-color: rgba(0, 0, 0, 0.08) transparent;
}
.db-tbl-scroll::-webkit-scrollbar { width: 5px; height: 5px; }
.db-tbl-scroll::-webkit-scrollbar-thumb { background: rgba(0, 0, 0, 0.08); border-radius: 99px; }

.db-tbl { width: 100%; border-collapse: collapse; font-size: 13.5px; font-family: 'Plus Jakarta Sans', sans-serif; }
.db-tbl thead tr { background: rgba(0,0,0,.008); }
.db-tbl th {
  text-align: left; 
  padding: 12px 16px; 
  font-size: 10.5px; 
  font-weight: 700;
  text-transform: uppercase; 
  letter-spacing: .08em;
  color: #475569; 
  border-bottom: 1.5px solid rgba(0,0,0,.04); 
  white-space: nowrap;
}
.db-th--num { width: 42px; }
.db-tbl td {
  padding: 13px 16px; 
  border-bottom: 1.1px solid rgba(0,0,0,.03);
  color: #334155; 
  vertical-align: middle;
  transition: background 0.15s ease;
}
.db-tbl tbody tr {
  transition: transform 0.15s ease, background 0.15s ease;
}
.db-tbl tbody tr:last-child td { border-bottom: none; }
.db-tbl tbody tr:hover {
  transform: translateY(-0.5px);
  background: rgba(79, 70, 229, 0.025);
}
.db-tbl tbody tr:hover td {
  color: #0F172A;
}
.db-td--num { color: #94A3B8; font-size: 11px; font-family: 'JetBrains Mono', monospace; font-weight: 700; }

/* Avatar - Squircle look */
.db-siswa { display: flex; align-items: center; gap: 11px; }
.db-avatar { 
  width: 36px; 
  height: 36px; 
  border-radius: 10px; 
  font-size: 12px; 
  font-weight: 700; 
  display: flex; 
  align-items: center; 
  justify-content: center; 
  flex-shrink: 0; 
  box-shadow: inset 0 1px 1px rgba(255,255,255,0.4), 0 2px 5px rgba(0,0,0,0.03);
  border: 1px solid rgba(0,0,0,0.02);
}
.db-siswa__name { margin: 0; font-weight: 600; font-size: 13px; color: #1E293B; }
.db-siswa__nis  { margin: 0; font-size: 10.5px; color: #64748B; font-family: 'JetBrains Mono', monospace; }

/* Kelas pill */
.db-kelas {
  display: inline-block; 
  background: rgba(255,255,255,.6); 
  color: #475569;
  font-size: 11px; 
  font-weight: 600; 
  padding: 3px 8px; 
  border-radius: 6px;
  border: 1px solid rgba(0,0,0,.05);
  box-shadow: 0 1px 2px rgba(0,0,0,0.02);
}

/* Time */
.db-time { display: block; font-family: 'JetBrains Mono', monospace; font-size: 13px; font-weight: 700; color: #334155; margin: 0; }
.db-time--out { color: #828FA3; font-weight: 500; }
.db-mode {
  display: inline-block; 
  font-size: 9.5px; 
  font-weight: 600;
  padding: 1px 6px; 
  border-radius: 4px; 
  text-transform: capitalize; 
  margin-top: 2.5px;
  font-family: 'JetBrains Mono', monospace;
  box-shadow: 0 1px 1px rgba(0,0,0,0.01);
}
.db-mode--in  { background: var(--c-primary-soft); color: var(--c-primary); border: 1px solid rgba(79,70,229,0.08); }
.db-mode--out { background: var(--c-info-soft); color: var(--c-info); border: 1px solid rgba(2,132,199,0.08); }
.db-dash { color: #CBD5E1; }

/* Badge - Glass Pill */
.db-badge {
  display: inline-flex; 
  align-items: center; 
  gap: 5.5px;
  padding: 4px 10px; 
  border-radius: 8px;
  font-size: 11px; 
  font-weight: 600; 
  text-transform: capitalize; 
  white-space: nowrap;
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4), 0 1px 2px rgba(0,0,0,0.02);
  border: 1px solid rgba(0,0,0,0.03);
}
.db-badge__dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }

/* Table footer */
.db-tbl-foot {
  display: flex; 
  align-items: center; 
  justify-content: space-between;
  padding: 11px 18px; 
  border-top: 1px solid rgba(0,0,0,.04);
  font-size: 11.5px; 
  color: #64748B;
  background: rgba(255,255,255,.15);
}
.db-tbl-foot__lnk { color: var(--c-primary); font-weight: 600; text-decoration: none; transition: color 0.15s; }
.db-tbl-foot__lnk:hover { color: var(--c-primary-hover); text-decoration: none; }

/* Empty */
.db-empty { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 48px 24px; text-align: center; }
.db-empty__ico {
  width: 56px; height: 56px; background: rgba(0,0,0,.02);
  border-radius: 50%; display: flex; align-items: center; justify-content: center;
  color: #94A3B8; margin-bottom: 4px;
  border: 1.5px dashed rgba(0,0,0,0.04);
}
.db-empty__title { margin: 0; font-size: 13px; font-weight: 700; color: #475569; }
.db-empty__sub   { margin: 0; font-size: 12px; color: #64748B; }

/* ══ RINGKASAN SIDE PANEL ══ */
.db-summary { padding: 10px 16px 14px; }
.db-sum-row {
  display: flex; align-items: center; justify-content: space-between;
  padding: 10px 0; border-bottom: 1px solid rgba(0,0,0,.03); gap: 8px;
}
.db-sum-row:last-of-type { border-bottom: none; }
.db-sum-row__left  { display: flex; align-items: center; gap: 8.5px; min-width: 78px; }
.db-sum-row__dot   { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.db-sum-row__lbl   { font-size: 12.5px; font-weight: 600; color: #475569; }
.db-sum-row__right { display: flex; align-items: center; gap: 7px; flex: 1; justify-content: flex-end; }
.db-sum-row__val   { font-size: 15px; font-weight: 700; min-width: 18px; text-align: right; font-variant-numeric: tabular-nums; font-family: 'JetBrains Mono', monospace; }
.db-sum-bar        { width: 56px; height: 5px; background: rgba(0,0,0,.04); border-radius: 99px; overflow: hidden; flex-shrink: 0; }
.db-sum-bar__fill  { height: 100%; border-radius: 99px; transition: width .6s ease; }
.db-sum-row__pct   { font-size: 11px; font-weight: 600; color: #64748B; min-width: 28px; text-align: right; font-family: 'JetBrains Mono', monospace; }
.db-sum-total {
  display: flex; align-items: center; justify-content: space-between;
  margin-top: 10px; padding-top: 12px;
  border-top: 1px solid rgba(0,0,0,.05);
  font-size: 12.5px; color: #64748B;
}
.db-sum-total strong { font-size: 14.5px; font-weight: 700; color: #1E293B; font-family: 'JetBrains Mono', monospace; }

[x-cloak] { display: none !important; }
</style>
