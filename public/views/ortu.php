<?php
defined( 'ABSPATH' ) || exit;
/**
 * Surface Orang Tua — view-only riwayat absensi anak.
 * Gate login + cap `absensi_view_child` sudah ditangani shortcode [absensi_ortu].
 * Data anak dari AbsensiConfig.anakList (server-derived); riwayat via GET /child/logs.
 *
 * Catatan: hindari karakter > / < di nilai atribut Alpine — output shortcode
 * dirender di konten page dan wptexturize memecah tag bila ada > di atribut.
 */
?>
<div x-data="absensiOrtu" x-cloak class="oab-wrap">

  <!-- Blob lokal — position:absolute agar backdrop-filter punya konten berwarna di belakang card -->
  <div class="oab-blob oab-blob--1" aria-hidden="true"></div>
  <div class="oab-blob oab-blob--2" aria-hidden="true"></div>
  <div class="oab-blob oab-blob--3" aria-hidden="true"></div>

  <!-- ══ SINGLE GLASS CARD ══ -->
  <div class="oab-card">

    <!-- ── Header ── -->
    <div class="oab-hdr">
      <div class="oab-hdr__orb oab-hdr__orb--a" aria-hidden="true"></div>
      <div class="oab-hdr__orb oab-hdr__orb--b" aria-hidden="true"></div>

      <div class="oab-hdr__row">
        <div class="oab-hdr__icon" aria-hidden="true">
          <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
        </div>
        <div class="oab-hdr__meta">
          <h1 class="oab-hdr__title"><?php esc_html_e( 'Pantau Absensi Anak', 'absensi-sekolah' ); ?></h1>
          <p class="oab-hdr__sub"><?php esc_html_e( 'Riwayat kehadiran harian', 'absensi-sekolah' ); ?></p>
        </div>
      </div>

      <!-- Pill pilih anak — x-show per item untuk dedup, hindari nested x-data agar selectAnak terikat ke scope ortu -->
      <div x-show="banyakAnak"
           class="oab-pills" style="margin-top:14px;"
           role="group" aria-label="<?php esc_attr_e( 'Pilih anak', 'absensi-sekolah' ); ?>">
        <template x-for="(anak, idx) in anakList" :key="idx">
          <button type="button"
                  x-show="anakList.findIndex(function(b){ return b.siswa_id===anak.siswa_id; })===idx"
                  @click="selectAnak(idx)"
                  class="oab-pill"
                  :class="selectedIndex===idx ? 'oab-pill--on' : ''"
                  :aria-pressed="selectedIndex===idx">
            <span class="oab-pill__av"
                  :style="selectedIndex===idx ? 'background:rgba(255,255,255,.25);color:white' : 'background:hsl(' + ((anak.siswa_id*61)%360) + ',50%,88%);color:hsl(' + ((anak.siswa_id*61)%360) + ',45%,35%)'"
                  x-text="inisial(anak.nama)" aria-hidden="true"></span>
            <span x-text="anak.nama"></span>
          </button>
        </template>
      </div>
    </div><!-- /.oab-hdr -->

    <!-- ── Body ── -->
    <div class="oab-body">

      <!-- Empty state: belum ada anak ter-link -->
      <div x-show="!adaAnak" class="oab-empty" role="status">
        <div class="oab-empty__icon" aria-hidden="true">
          <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
        </div>
        <h2 class="oab-empty__title"><?php esc_html_e( 'Belum Ada Anak Terhubung', 'absensi-sekolah' ); ?></h2>
        <p class="oab-empty__text"><?php esc_html_e( 'Akun Anda belum terhubung dengan data siswa. Hubungi pihak sekolah untuk menautkan akun.', 'absensi-sekolah' ); ?></p>
      </div>

      <!-- Konten utama -->
      <div x-show="adaAnak" class="oab-main-content">

        <!-- Belum Pilih Anak (Placeholder) -->
        <div x-show="banyakAnak && !selectedAnak" class="oab-empty" style="flex:1;display:flex;flex-direction:column;justify-content:center;padding:0;">
          <div class="oab-empty__icon" aria-hidden="true" style="margin-bottom:12px;">
            <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
          </div>
          <p class="oab-empty__text" style="font-size:14.5px; font-weight:800; color:#1E293B; margin-bottom:4px;"><?php esc_html_e( 'Pilih Anak Terlebih Dahulu', 'absensi-sekolah' ); ?></p>
          <p class="oab-empty__text"><?php esc_html_e( 'Pilih nama anak pada menu di atas untuk melihat riwayat kehadirannya.', 'absensi-sekolah' ); ?></p>
        </div>

        <div x-show="selectedAnak">
          <!-- Profil anak terpilih -->
          <div class="oab-profile">
          <div class="oab-profile__av"
               :style="selectedAnak ? 'background:hsl(' + ((selectedAnak.siswa_id * 61) % 360) + ',50%,88%);color:hsl(' + ((selectedAnak.siswa_id * 61) % 360) + ',45%,35%)' : ''"
               x-text="selectedAnak ? inisial(selectedAnak.nama) : ''"
               aria-hidden="true"></div>
          <div class="oab-profile__info">
            <p class="oab-profile__name" x-text="selectedAnak ? selectedAnak.nama : ''"></p>
            <p class="oab-profile__meta">
              NIS:&nbsp;<span style="font-family:monospace;" x-text="selectedAnak ? selectedAnak.nis : ''"></span>
              <span x-show="selectedAnak && selectedAnak.nama_kelas">&nbsp;·&nbsp;<span x-text="selectedAnak ? selectedAnak.nama_kelas : ''"></span></span>
            </p>
          </div>
        </div>

        <!-- Navigasi bulan -->
        <div class="oab-month-nav" aria-label="<?php esc_attr_e( 'Navigasi bulan', 'absensi-sekolah' ); ?>">
          <button type="button" @click="prevBulan()" class="oab-month-btn"
                  aria-label="<?php esc_attr_e( 'Bulan sebelumnya', 'absensi-sekolah' ); ?>">
            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/></svg>
          </button>
          <span class="oab-month-label" x-text="bulanLabel" aria-live="polite"></span>
          <button type="button" @click="nextBulan()" :disabled="isMaxBulan" class="oab-month-btn"
                  aria-label="<?php esc_attr_e( 'Bulan berikutnya', 'absensi-sekolah' ); ?>">
            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
          </button>
        </div>

        <!-- Ringkasan bulan -->
        <div class="oab-summary" aria-live="polite">
          <div class="oab-sum oab-sum--green">
            <span class="oab-sum__num" x-text="summary.hadir"></span>
            <span class="oab-sum__lbl"><?php esc_html_e( 'Hadir', 'absensi-sekolah' ); ?></span>
          </div>
          <div class="oab-sum oab-sum--orange">
            <span class="oab-sum__num" x-text="summary.telat"></span>
            <span class="oab-sum__lbl"><?php esc_html_e( 'Telat', 'absensi-sekolah' ); ?></span>
          </div>
          <div class="oab-sum oab-sum--cyan">
            <span class="oab-sum__num" x-text="summary.izin_sakit"></span>
            <span class="oab-sum__lbl"><?php esc_html_e( 'Izin/Sakit', 'absensi-sekolah' ); ?></span>
          </div>
          <div class="oab-sum oab-sum--red">
            <span class="oab-sum__num" x-text="summary.alpha"></span>
            <span class="oab-sum__lbl"><?php esc_html_e( 'Alpha', 'absensi-sekolah' ); ?></span>
          </div>
        </div>

        <!-- Error -->
        <div x-show="error" class="oab-error" role="alert" aria-live="assertive">
          <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
          <span x-text="error"></span>
        </div>

        <!-- Timeline-wrapper: TIDAK pakai x-show agar flex:1 selalu aktif.
             x-show hanya pada konten di dalamnya (loading vs data). -->
        <div class="oab-timeline-wrapper">

          <!-- Loading state (di dalam wrapper) -->
          <div x-show="loading" class="oab-loading" aria-live="polite" aria-busy="true">
            <div class="oab-spinner" role="status"></div>
            <p><?php esc_html_e( 'Memuat riwayat…', 'absensi-sekolah' ); ?></p>
          </div>

          <!-- Data state (di dalam wrapper) -->
          <div x-show="!loading" class="oab-tl-data">
            <p class="oab-tl-hd"><?php esc_html_e( 'Riwayat Absensi', 'absensi-sekolah' ); ?></p>

            <div class="oab-timeline">
              <template x-for="r in timeline" :key="r.id">
                <div class="oab-trow" :class="'oab-trow--' + r.status">
                  <!-- Tanggal -->
                  <div class="oab-trow__date" aria-hidden="true">
                    <span class="oab-trow__day" x-text="hariLabel(r.tanggal)"></span>
                    <span class="oab-trow__num" x-text="tanggalLabel(r.tanggal)"></span>
                  </div>
                  <!-- Konten -->
                  <div class="oab-trow__body">
                    <div class="oab-trow__top">
                      <span class="oab-badge" :class="statusClass(r.status)" x-text="r.status" style="text-transform:capitalize;"></span>
                    </div>
                    <div class="oab-trow__times">
                      <div class="oab-trow__time-item">
                        <span class="oab-trow__time-lbl"><?php esc_html_e( 'Masuk', 'absensi-sekolah' ); ?></span>
                        <span class="oab-trow__time-val" x-show="r.waktu_masuk" x-text="jam(r.waktu_masuk)"></span>
                        <span class="oab-trow__time-nil" x-show="!r.waktu_masuk" aria-label="<?php esc_attr_e( 'Belum ada', 'absensi-sekolah' ); ?>">—</span>
                      </div>
                      <div class="oab-trow__time-sep" aria-hidden="true"></div>
                      <div class="oab-trow__time-item">
                        <span class="oab-trow__time-lbl"><?php esc_html_e( 'Pulang', 'absensi-sekolah' ); ?></span>
                        <span class="oab-trow__time-val" x-show="r.waktu_keluar" x-text="jam(r.waktu_keluar)"></span>
                        <span class="oab-trow__time-nil" x-show="!r.waktu_keluar" aria-label="<?php esc_attr_e( 'Belum ada', 'absensi-sekolah' ); ?>">—</span>
                      </div>
                    </div>
                  </div>
                </div>
              </template>

              <!-- Empty timeline -->
              <div x-show="!adaTimeline" class="oab-tl-empty">
                <div class="oab-tl-empty__icon" aria-hidden="true">
                  <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.4"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
                </div>
                <p class="oab-tl-empty__text"><?php esc_html_e( 'Belum ada data riwayat absensi di bulan ini.', 'absensi-sekolah' ); ?></p>
              </div>
            </div>
          </div><!-- /oab-tl-data -->

        </div><!-- /oab-timeline-wrapper -->
        </div><!-- /selectedAnak wrapper -->

      </div><!-- /konten utama -->
    </div><!-- /.oab-body -->
  </div><!-- /.oab-card -->
</div><!-- /.oab-wrap -->

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

/* ── Reset tema ── */
html,body{background:linear-gradient(135deg,#F5F7FB 0%,#E2E8F0 100%) fixed !important;min-height:100vh;}
#page,#main,#primary,#content,.site-content,.entry-content,.wp-block-group,
#wpcontent,#wpbody-content,#wpbody{background:transparent !important;}

/* ── Wrap ── */
.oab-wrap *,.oab-wrap *::before,.oab-wrap *::after{box-sizing:border-box;}
.oab-wrap{
  font-family:'Plus Jakarta Sans',-apple-system,sans-serif;
  max-width:680px;
  margin:0 auto;
  padding:16px 0 40px;
  position:relative;
}

/* ── Blobs ── */
.oab-blob{position:absolute;border-radius:50%;filter:blur(90px);pointer-events:none;z-index:0;}
.oab-blob--1{width:360px;height:360px;top:-130px;left:-160px;background:radial-gradient(circle,rgba(129,140,248,.55) 0%,rgba(99,102,241,.25) 65%,transparent 100%);}
.oab-blob--2{width:320px;height:320px;bottom:-100px;right:-130px;background:radial-gradient(circle,rgba(244,114,182,.50) 0%,rgba(219,39,119,.22) 65%,transparent 100%);}
.oab-blob--3{width:280px;height:280px;top:45%;right:-70px;background:radial-gradient(circle,rgba(103,232,249,.50) 0%,rgba(6,182,212,.22) 65%,transparent 100%);}

/* ── Glass Card ── */
.oab-card{
  position:relative;z-index:1;
  background:rgba(255,255,255,.55);
  backdrop-filter:blur(32px) saturate(180%);
  -webkit-backdrop-filter:blur(32px) saturate(180%);
  border:1px solid rgba(255,255,255,.75);
  border-radius:24px;overflow:hidden;
  box-shadow:6px 6px 20px rgba(163,177,198,.25),-6px -6px 20px rgba(255,255,255,.8),inset 0 1px 1px rgba(255,255,255,.7);
}

/* ── Header ── */
.oab-hdr{position:relative;padding:22px 20px 18px;background:rgba(255,255,255,.22);border-bottom:1px solid rgba(0,0,0,.05);z-index:10;}
.oab-hdr__orb{position:absolute;border-radius:50%;pointer-events:none;}
.oab-hdr__orb--a{width:200px;height:200px;top:-80px;right:-60px;background:radial-gradient(circle,rgba(37,99,235,.10) 0%,transparent 70%);filter:blur(38px);}
.oab-hdr__orb--b{width:120px;height:120px;bottom:-40px;left:20px;background:radial-gradient(circle,rgba(124,58,237,.09) 0%,transparent 70%);filter:blur(30px);}
.oab-hdr__row{position:relative;display:flex;align-items:center;gap:12px;margin-bottom:4px;}
.oab-hdr__icon{width:40px;height:40px;border-radius:10px;background:#DBEAFE;color:#2563EB;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.oab-hdr__meta{flex:1;min-width:0;}
.oab-hdr__title{font-size:17px;font-weight:800;color:#1E293B;margin:0 0 2px;letter-spacing:-.3px;}
.oab-hdr__sub{font-size:12px;color:#64748B;margin:0;}

/* ── Pill pilih anak ── */
.oab-pills{display:flex;flex-wrap:wrap;gap:8px;}
.oab-pill{display:inline-flex;align-items:center;gap:8px;padding:7px 14px 7px 7px;border-radius:999px;border:1.5px solid rgba(0,0,0,.08);background:rgba(255,255,255,.55);font-family:inherit;font-size:13px;font-weight:700;color:#475569;cursor:pointer;transition:all .18s;min-height:40px;box-shadow:2px 2px 6px rgba(163,177,198,.2),-2px -2px 6px rgba(255,255,255,.6);}
.oab-pill:hover:not(.oab-pill--on){background:rgba(255,255,255,.8);border-color:rgba(37,99,235,.25);color:#1E293B;}
.oab-pill--on{background:linear-gradient(145deg,#3b82f6,#1d4ed8);color:white;border-color:transparent;box-shadow:3px 3px 8px rgba(37,99,235,.3),-1px -1px 4px rgba(255,255,255,.5),inset 0 1px 1px rgba(255,255,255,.2);}
.oab-pill__av{width:26px;height:26px;border-radius:50%;font-size:10px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;}

/* ── Body ── */
.oab-body{padding:20px;min-height:510px;}


/* ── Empty state ── */
.oab-empty{text-align:center;padding:44px 20px 32px;}
.oab-empty__icon{width:56px;height:56px;border-radius:14px;background:#DBEAFE;color:#2563EB;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;}
.oab-empty__title{font-size:16px;font-weight:800;color:#0F172A;margin:0 0 7px;}
.oab-empty__text{font-size:13px;color:#64748B;margin:0;line-height:1.6;}

/* ── Profil anak ── */
.oab-profile{display:flex;align-items:center;gap:12px;padding:13px 15px;background:rgba(255,255,255,.45);border:1px solid rgba(255,255,255,.65);border-radius:14px;margin-bottom:14px;}
.oab-profile__av{width:44px;height:44px;border-radius:50%;font-size:16px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.oab-profile__info{flex:1;min-width:0;}
.oab-profile__name{font-size:15px;font-weight:800;color:#0F172A;margin:0 0 2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.oab-profile__meta{font-size:11.5px;color:#64748B;margin:0;}

/* ── Navigasi bulan ── */
.oab-month-nav{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:16px;padding:12px 15px;background:rgba(255,255,255,.35);border:1px solid rgba(255,255,255,.55);border-radius:12px;}
.oab-month-btn{width:34px;height:34px;border-radius:9px;border:1.5px solid rgba(0,0,0,.09);background:rgba(255,255,255,.7);color:#374151;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .12s;flex-shrink:0;}
.oab-month-btn:hover:not(:disabled){background:rgba(255,255,255,.95);box-shadow:0 1px 4px rgba(0,0,0,.1);}
.oab-month-btn:disabled{opacity:.35;cursor:not-allowed;}
.oab-month-label{font-size:14px;font-weight:800;color:#0F172A;text-align:center;flex:1;}

/* ── Ringkasan ── */
.oab-summary{display:grid;grid-template-columns:repeat(4,1fr);gap:9px;margin-bottom:18px;}
.oab-sum{border-radius:12px;padding:13px 8px 11px;text-align:center;border:1px solid transparent;}
.oab-sum--green{background:rgba(240,253,244,.85);border-color:rgba(187,247,208,.7);}
.oab-sum--orange{background:rgba(255,251,235,.85);border-color:rgba(253,230,138,.6);}
.oab-sum--cyan{background:rgba(236,254,255,.85);border-color:rgba(165,243,252,.6);}
.oab-sum--red{background:rgba(254,242,242,.85);border-color:rgba(254,202,202,.7);}
.oab-sum__num{display:block;font-size:22px;font-weight:800;line-height:1;}
.oab-sum--green .oab-sum__num{color:#16A34A;}
.oab-sum--orange .oab-sum__num{color:#D97706;}
.oab-sum--cyan .oab-sum__num{color:#0891B2;}
.oab-sum--red .oab-sum__num{color:#DC2626;}
.oab-sum__lbl{display:block;font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#94A3B8;margin-top:5px;}

/* ── Error ── */
.oab-error{display:flex;align-items:center;gap:8px;padding:10px 13px;border-radius:8px;background:rgba(254,242,242,.9);color:#DC2626;border:1px solid rgba(254,202,202,.7);font-size:13px;font-weight:600;margin-bottom:14px;}

/* ── Loading ── */
.oab-loading{text-align:center;padding:40px 0;}
.oab-spinner{display:inline-block;width:24px;height:24px;border-radius:50%;border:3px solid #DBEAFE;border-top-color:#2563EB;animation:oab-spin .7s linear infinite;}
.oab-loading p{font-size:12.5px;color:#94A3B8;margin:10px 0 0;}

/* ── Timeline header ── */
.oab-tl-hd{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#94A3B8;margin:0 0 10px;flex-shrink:0;}

/* ── Timeline list ── */
.oab-timeline-wrapper{max-height:240px;overflow-y:scroll;overflow-x:hidden;-webkit-overflow-scrolling:touch;padding-right:4px;}
.oab-timeline{display:flex;flex-direction:column;gap:8px;}
.oab-timeline-wrapper::-webkit-scrollbar{width:4px;}
.oab-timeline-wrapper::-webkit-scrollbar-track{background:rgba(0,0,0,.02);border-radius:4px;}
.oab-timeline-wrapper::-webkit-scrollbar-thumb{background:rgba(0,0,0,.15);border-radius:4px;}
.oab-trow{display:flex; gap:12px; background:rgba(255,255,255,.5); border:1px solid rgba(255,255,255,.7); border-left-width:3px; border-left-color:rgba(0,0,0,.1); border-radius:12px; padding:12px 14px; transition:border-left-color .15s;}
.oab-trow--hadir{border-left-color:#16A34A;}
.oab-trow--telat{border-left-color:#D97706;}
.oab-trow--alpha{border-left-color:#DC2626;}
.oab-trow--izin{border-left-color:#0891B2;}
.oab-trow--sakit{border-left-color:#0891B2;}

.oab-trow__date{width:42px;flex-shrink:0;display:flex;flex-direction:column;align-items:center;justify-content:center;background:rgba(248,250,252,.8);border-radius:8px;padding:7px 4px;gap:1px;}
.oab-trow__day{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#94A3B8;}
.oab-trow__num{font-size:19px;font-weight:800;color:#0F172A;line-height:1.1;}
.oab-trow__body{flex:1;min-width:0;}
.oab-trow__top{margin-bottom:8px;}
.oab-badge{display:inline-flex;align-items:center;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:700;letter-spacing:.03em;}
.badge-success{background:#DCFCE7;color:#16A34A;}
.badge-warning{background:#FEF3C7;color:#D97706;}
.badge-danger{background:#FEE2E2;color:#DC2626;}
.badge-info{background:#ECFEFF;color:#0891B2;}
.badge-neutral{background:#F1F5F9;color:#64748B;}

.oab-trow__times{display:flex;align-items:stretch;}
.oab-trow__time-item{flex:1;min-width:0;}
.oab-trow__time-sep{width:1px;background:rgba(0,0,0,.07);margin:0 14px;flex-shrink:0;}
.oab-trow__time-lbl{display:block;font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#94A3B8;margin-bottom:3px;}
.oab-trow__time-val{font-size:15px;font-weight:800;font-family:monospace;color:#0F172A;}
.oab-trow__time-nil{font-size:15px;color:#CBD5E1;}

/* ── Empty timeline ── */
.oab-tl-empty{text-align:center;padding:36px 20px;border:2px dashed rgba(0,0,0,.08);border-radius:14px;background:rgba(255,255,255,.3);}
.oab-tl-empty__icon{width:52px;height:52px;border-radius:12px;background:rgba(219,234,254,.4);color:#93C5FD;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;}
.oab-tl-empty__text{font-size:13px;color:#94A3B8;margin:0;}

/* ── Animasi ── */
@keyframes oab-spin{to{transform:rotate(360deg);}}

[x-cloak]{display:none!important;}
</style>
