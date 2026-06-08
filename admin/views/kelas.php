<?php
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'ABSENSI_ADMIN_ASSETS' ) ) :
    define( 'ABSENSI_ADMIN_ASSETS', true ); ?>
<link rel="stylesheet" href="<?php echo esc_url( ABSENSI_PLUGIN_URL . 'assets/dist/app.css' ); ?>">
<script type="module" src="<?php echo esc_url( ABSENSI_PLUGIN_URL . 'assets/dist/admin.js' ); ?>"></script>
<?php endif; ?>
<script>
if (typeof AbsensiAdmin === 'undefined') {
    window.AbsensiAdmin = {
        restUrl: <?php echo wp_json_encode( rest_url( 'absensi/v1/' ) ); ?>,
        nonce:   <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>
    };
}
</script>
<?php global $wpdb;
$table = $wpdb->prefix . 'absensi_kelas';
$saved_msg = null;
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['_wpnonce_kelas'] ) ) {
    if ( ! wp_verify_nonce( $_POST['_wpnonce_kelas'], 'absensi_kelas_action' ) ) wp_die( 'Token keamanan tidak valid.' );
    $action     = sanitize_key( $_POST['action_type']  ?? '' );
    $nama_kelas = sanitize_text_field( $_POST['nama_kelas'] ?? '' );
    $tingkat    = absint( $_POST['tingkat']    ?? 0 );
    $guru_id    = absint( $_POST['guru_id']    ?? 0 ) ?: null;
    $kelas_id   = absint( $_POST['kelas_id']   ?? 0 );
    if ( in_array( $action, [ 'add', 'edit' ], true ) && $nama_kelas !== '' ) {
        if ( $action === 'add' ) $wpdb->insert( $table, compact( 'nama_kelas', 'tingkat', 'guru_id' ) );
        else if ( $kelas_id ) $wpdb->update( $table, compact( 'nama_kelas', 'tingkat', 'guru_id' ), [ 'id' => $kelas_id ] );
        $saved_msg = 'saved';
    }
    if ( $action === 'delete' && $kelas_id ) {
        $wpdb->delete( $table, [ 'id' => $kelas_id ], [ '%d' ] );
        $saved_msg = 'deleted';
    }
}
$kelas_list = $wpdb->get_results( "SELECT k.*, u.display_name AS nama_guru FROM {$table} k LEFT JOIN {$wpdb->users} u ON u.ID = k.guru_id ORDER BY k.tingkat ASC, k.nama_kelas ASC" );
$guru_list  = get_users( [ 'role__in' => [ 'administrator', 'guru', 'absensi_admin' ], 'fields' => [ 'ID', 'display_name' ] ] );
$edit = null;
if ( ! $saved_msg && isset( $_GET['edit_id'] ) ) {
    $edit = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $_GET['edit_id'] ) ) );
}
?>
<div class="wrap absensi-admin-wrap" x-data="{ showModal: <?php echo ( $edit ? 'true' : 'false' ); ?> }">
  <hr class="wp-header-end" style="margin:0;">

  <!-- Page Header -->
  <div class="absensi-page-header">
    <div>
      <h1 class="absensi-page-title"><?php esc_html_e( 'Manajemen Kelas', 'absensi-sekolah' ); ?></h1>
      <p class="absensi-page-subtitle"><?php esc_html_e( 'Kelola kelas dan wali kelas', 'absensi-sekolah' ); ?></p>
    </div>
    <button type="button" @click="showModal = true" class="absensi-btn absensi-btn-primary">
      <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
      <?php esc_html_e( 'Tambah Kelas', 'absensi-sekolah' ); ?>
    </button>
  </div>

  <!-- Notices -->
  <?php if ( $saved_msg === 'saved' || isset( $_GET['saved'] ) ) : ?>
  <div class="absensi-alert absensi-alert-success" style="margin-bottom:16px;">
    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    <?php esc_html_e( 'Data kelas berhasil disimpan.', 'absensi-sekolah' ); ?>
  </div>
  <?php endif; ?>
  <?php if ( $saved_msg === 'deleted' || isset( $_GET['deleted'] ) ) : ?>
  <div class="absensi-alert absensi-alert-warning" style="margin-bottom:16px;">
    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
    <?php esc_html_e( 'Kelas berhasil dihapus.', 'absensi-sekolah' ); ?>
  </div>
  <?php endif; ?>

  <!-- Table -->
  <div style="background:white;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">
    <?php if ( empty( $kelas_list ) ) : ?>
      <div style="display:flex;flex-direction:column;align-items:center;padding:56px;text-align:center;color:#9ca3af;">
        <svg width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" style="margin-bottom:12px;color:#d1d5db;"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5"/></svg>
        <p style="font-size:14px;font-weight:600;color:#374151;margin:0 0 4px;"><?php esc_html_e( 'Belum Ada Kelas', 'absensi-sekolah' ); ?></p>
        <p style="font-size:12px;margin:0;"><?php esc_html_e( 'Klik "Tambah Kelas" di atas untuk mulai.', 'absensi-sekolah' ); ?></p>
      </div>
    <?php else : ?>
      <table class="absensi-table">
        <thead>
          <tr>
            <th><?php esc_html_e( 'Nama Kelas', 'absensi-sekolah' ); ?></th>
            <th><?php esc_html_e( 'Tingkat', 'absensi-sekolah' ); ?></th>
            <th><?php esc_html_e( 'Wali Kelas', 'absensi-sekolah' ); ?></th>
            <th style="width:160px;"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ( $kelas_list as $k ) : ?>
          <tr>
            <td style="font-weight:700;font-size:14px;color:#111827;"><?php echo esc_html( $k->nama_kelas ); ?></td>
            <td>
              <?php if ( $k->tingkat ) : ?>
                <span style="display:inline-flex;align-items:center;padding:2px 9px;border-radius:999px;font-size:11.5px;font-weight:600;background:#EFF6FF;color:#2563EB;">
                  <?php echo esc_html( 'Kelas ' . $k->tingkat ); ?>
                </span>
              <?php else : ?><span style="color:#d1d5db;">—</span><?php endif; ?>
            </td>
            <td style="color:#6b7280;font-size:13px;"><?php echo esc_html( $k->nama_guru ?? '—' ); ?></td>
            <td style="text-align:right;white-space:nowrap;">
              <a href="<?php echo esc_url( admin_url( 'admin.php?page=absensi-kelas&edit_id=' . $k->id ) ); ?>"
                 class="absensi-btn absensi-btn-secondary absensi-btn-sm" style="margin-right:5px;text-decoration:none;">
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
                <?php esc_html_e( 'Edit', 'absensi-sekolah' ); ?>
              </a>
              <form method="post" style="display:inline;" onsubmit="return confirm('<?php echo esc_js( __( 'Hapus kelas ini?', 'absensi-sekolah' ) ); ?>')">
                <?php wp_nonce_field( 'absensi_kelas_action', '_wpnonce_kelas' ); ?>
                <input type="hidden" name="action_type" value="delete">
                <input type="hidden" name="kelas_id"    value="<?php echo esc_attr( $k->id ); ?>">
                <button type="submit" class="absensi-btn absensi-btn-danger absensi-btn-sm">
                  <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                  <?php esc_html_e( 'Hapus', 'absensi-sekolah' ); ?>
                </button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <!-- Modal -->
  <div x-show="showModal" x-cloak class="absensi-modal-overlay" @keydown.escape.window="showModal = false">
    <div class="absensi-modal-box" @click.stop>
      <div class="absensi-modal-header">
        <h2 class="absensi-modal-title">
          <?php echo $edit ? esc_html__( 'Edit Kelas', 'absensi-sekolah' ) : esc_html__( 'Tambah Kelas Baru', 'absensi-sekolah' ); ?>
        </h2>
        <button type="button" @click="showModal = false" class="absensi-modal-close" aria-label="Tutup">
          <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <form method="post" style="display:flex;flex-direction:column;gap:14px;">
        <?php wp_nonce_field( 'absensi_kelas_action', '_wpnonce_kelas' ); ?>
        <input type="hidden" name="action_type" value="<?php echo $edit ? 'edit' : 'add'; ?>">
        <?php if ( $edit ) : ?>
          <input type="hidden" name="kelas_id" value="<?php echo esc_attr( $edit->id ); ?>">
        <?php endif; ?>

        <div class="absensi-input-group">
          <label class="absensi-label" for="nama_kelas"><?php esc_html_e( 'Nama Kelas', 'absensi-sekolah' ); ?> <span style="color:#DC2626;">*</span></label>
          <input type="text" name="nama_kelas" id="nama_kelas" required
                 value="<?php echo esc_attr( $edit->nama_kelas ?? '' ); ?>"
                 placeholder="<?php esc_attr_e( 'Contoh: X IPA 1', 'absensi-sekolah' ); ?>"
                 class="absensi-input">
        </div>
        <div class="absensi-input-group">
          <label class="absensi-label" for="tingkat"><?php esc_html_e( 'Tingkat', 'absensi-sekolah' ); ?></label>
          <select name="tingkat" id="tingkat" class="absensi-input">
            <option value="0"><?php esc_html_e( '— Pilih Tingkat —', 'absensi-sekolah' ); ?></option>
            <?php for ( $t = 1; $t <= 12; $t++ ) : ?>
              <option value="<?php echo $t; ?>" <?php selected( (int) ( $edit->tingkat ?? 0 ), $t ); ?>>
                <?php echo esc_html( sprintf( __( 'Kelas %d', 'absensi-sekolah' ), $t ) ); ?>
              </option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="absensi-input-group">
          <label class="absensi-label" for="guru_id"><?php esc_html_e( 'Wali Kelas', 'absensi-sekolah' ); ?></label>
          <select name="guru_id" id="guru_id" class="absensi-input">
            <option value=""><?php esc_html_e( '— Pilih Guru —', 'absensi-sekolah' ); ?></option>
            <?php foreach ( $guru_list as $g ) : ?>
              <option value="<?php echo esc_attr( $g->ID ); ?>" <?php selected( (int) ( $edit->guru_id ?? 0 ), (int) $g->ID ); ?>>
                <?php echo esc_html( $g->display_name ); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div style="display:flex;gap:10px;margin-top:6px;">
          <button type="button" @click="showModal = false" class="absensi-btn absensi-btn-secondary" style="flex:1;">
            <?php esc_html_e( 'Batal', 'absensi-sekolah' ); ?>
          </button>
          <button type="submit" class="absensi-btn absensi-btn-primary" style="flex:1;">
            <?php echo $edit ? esc_html__( 'Simpan Perubahan', 'absensi-sekolah' ) : esc_html__( 'Tambah Kelas', 'absensi-sekolah' ); ?>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
.absensi-admin-wrap{font-family:'Plus Jakarta Sans',sans-serif!important;background:#F5F7FB;min-height:100vh;}
.absensi-page-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin:12px 0 24px;padding-bottom:16px;border-bottom:1px solid #e5e7eb;}
.absensi-page-title{font-size:19px;font-weight:700;color:#111827;margin:0 0 3px;}
.absensi-page-subtitle{font-size:13px;color:#6b7280;margin:0;}
.absensi-input-group{display:flex;flex-direction:column;gap:5px;}
.absensi-label{font-size:11.5px;font-weight:600;color:#374151;}
.absensi-input{border:1px solid #d1d5db;border-radius:8px;padding:8px 12px;font-size:13.5px;min-height:40px;font-family:inherit;background:white;color:#111827;outline:none;width:100%;box-sizing:border-box;transition:border-color .15s,box-shadow .15s;}
.absensi-input:focus{border-color:#2563EB;box-shadow:0 0 0 3px rgba(37,99,235,.1);}
.absensi-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:8px 15px;border-radius:8px;font-size:13.5px;font-weight:600;cursor:pointer;border:none;min-height:38px;font-family:inherit;transition:background .12s,border-color .12s;text-decoration:none;}
.absensi-btn-sm{padding:5px 11px;font-size:12.5px;min-height:32px;}
.absensi-btn-primary{background:#2563EB;color:white;}
.absensi-btn-primary:hover{background:#1D4ED8;}
.absensi-btn-secondary{background:white;color:#374151;border:1px solid #d1d5db;}
.absensi-btn-secondary:hover{background:#f9fafb;border-color:#9ca3af;}
.absensi-btn-danger{background:#dc2626;color:white;}
.absensi-btn-danger:hover{background:#b91c1c;}
.absensi-alert{display:flex;align-items:flex-start;gap:9px;padding:11px 14px;border-radius:8px;font-size:13px;font-weight:600;}
.absensi-alert-success{background:#F0FDF4;border:1px solid #bbf7d0;color:#16A34A;}
.absensi-alert-warning{background:#FFFBEB;border:1px solid #fde68a;color:#D97706;}
.absensi-table{width:100%;border-collapse:collapse;font-size:13.5px;}
.absensi-table thead tr{background:#f9fafb;border-bottom:1px solid #e5e7eb;}
.absensi-table th{text-align:left;padding:10px 14px;color:#6b7280;font-weight:600;font-size:11.5px;text-transform:uppercase;letter-spacing:.04em;}
.absensi-table td{padding:11px 14px;border-bottom:1px solid #f3f4f6;vertical-align:middle;}
.absensi-table tbody tr:hover td{background:#f9fafb;}
.absensi-modal-overlay{position:fixed;inset:0;background:rgba(17,24,39,.5);z-index:99999;display:flex;align-items:center;justify-content:center;padding:16px;}
.absensi-modal-box{background:white;border-radius:14px;padding:24px;width:100%;max-width:440px;box-shadow:0 20px 50px rgba(0,0,0,.15);}
.absensi-modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;}
.absensi-modal-title{font-size:16px;font-weight:700;color:#111827;margin:0;}
.absensi-modal-close{display:flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:6px;background:transparent;border:none;cursor:pointer;color:#9ca3af;transition:background .12s,color .12s;}
.absensi-modal-close:hover{background:#f3f4f6;color:#374151;}
[x-cloak]{display:none!important;}
</style>
