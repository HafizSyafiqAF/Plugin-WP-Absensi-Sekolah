Created At: 2026-06-09T05:10:24Z
Completed At: 2026-06-09T05:10:25Z
File Path: `file:///c:/laragon/www/absensi-sekolah/wp-content/plugins/plugin-wp-absensi-sekolah/admin/views/laporan.php`
Total Lines: 521
Total Bytes: 42747
Showing lines 1 to 521
The following code has been modified to include a line number before every line, in the format: <line_number>: <original_line>. Please note that any changes targeting the original code should remove the line number, colon, and leading space.
1: <?php
2: defined( 'ABSPATH' ) || exit;
3: if ( ! defined( 'ABSENSI_ADMIN_ASSETS' ) ) :
4:     define( 'ABSENSI_ADMIN_ASSETS', true ); ?>
5: <link rel="stylesheet" href="<?php echo esc_url( ABSENSI_PLUGIN_URL . 'assets/dist/app.css' ); ?>">
6: <script type="module" src="<?php echo esc_url( ABSENSI_PLUGIN_URL . 'assets/dist/admin.js' ); ?>"></script>
7: <?php endif; ?>
8: <script>
9: if (typeof AbsensiAdmin === 'undefined') {
10:     window.AbsensiAdmin = {
11:         restUrl: <?php echo wp_json_encode( rest_url( 'absensi/v1/' ) ); ?>,
12:         nonce:   <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>
13:     };
14: }
15: </script>
16: <?php
17: global $wpdb;
18: $kelas_list = $wpdb->get_results( "SELECT id, nama_kelas FROM {$wpdb->prefix}absensi_kelas ORDER BY nama_kelas" );
19: ?>
20: 
21: <div class="wrap lp-wrap" id="absensi-laporan-app">
22: 
23:   <div class="lp-bg" aria-hidden="true">
24:     <div class="lp-blob lp-blob--1"></div>
25:     <div class="lp-blob lp-blob--2"></div>
26:     <div class="lp-blob lp-blob--3"></div>
27:   </div>
28: 
29:   <hr class="wp-header-end" style="margin:0;">
30: 
31:   <!-- ══ HERO CARD ══ -->
32:   <div class="lp-hero-card">
33:     <div class="lp-hero-dot-grid" aria-hidden="true"></div>
34:     <div class="lp-hero-body">
35:       <div class="lp-hero-left">
36:         <div class="lp-eyebrow">
37:           <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.7
<truncated 41721 bytes>
e-btn:disabled:not(.lp-page-btn--active){opacity:.32;cursor:not-allowed;box-shadow:none;}
498: .lp-page-btn--active{background:linear-gradient(145deg,#6366F1,#4F46E5);color:#fff;border-color:transparent;box-shadow:4px 4px 12px rgba(79,70,229,.35),-1px -1px 5px rgba(255,255,255,.4),inset 0 1px 1px rgba(255,255,255,.25);cursor:default;}
499: .lp-page-btn--dots{border-color:transparent;background:transparent;box-shadow:none;color:#94A3B8;cursor:default;font-weight:700;}
500: 
501: /* ── Print ── */
502: @media print{
503: #adminmenuwrap,#adminmenuback,#adminmenuwrap,#wpadminbar,#wpfooter,
504: #wpbody-content .notice,.notice,.update-nag,
505: [x-data="filterBar"],.no-print{display:none!important;}
506: #wpcontent,#wpbody{margin:0!important;padding:0!important;float:none!important;}
507: body,.wrap{margin:0!important;font-size:12px;}
508: .lp-bg{display:none!important;}
509: .lp-hero-card{border:1px solid #ccc!important;background:white!important;box-shadow:none!important;backdrop-filter:none!important;-webkit-backdrop-filter:none!important;border-radius:4px!important;margin-bottom:12px!important;}
510: .lp-hero-title{font-size:16px!important;}
511: .lp-hero-sub{font-size:11px!important;}
512: .lp-panel,.lp-table-panel{background:white!important;box-shadow:none!important;backdrop-filter:none!important;-webkit-backdrop-filter:none!important;border:none!important;border-radius:0!important;}
513: .lp-table{width:100%!important;border-collapse:collapse!important;}
514: .lp-table th,.lp-table td{border:1px solid #999!important;padding:5px 8px!important;font-size:11px!important;}
515: .lp-table thead tr{background:#eee!important;border-bottom:1px solid #999!important;}
516: .lp-row:hover td{background:none!important;}
517: .lp-status-badge{border:1px solid #999!important;padding:1px 5px!important;}
518: .lp-table-footer{font-size:11px!important;border-top:1px solid #ccc!important;padding:4px 8px!important;}
519: }
520: </style>
521: 
The above content shows the entire, complete file contents of the requested file.
