/**
 * Shared HID buffer/parser untuk RFID USB scanner.
 * Scanner bertindak sebagai keyboard HID — "mengetik" karakter + Enter.
 *
 * Cara pakai:
 *   import { createRfidListener } from './rfid.js';
 *   const stop = createRfidListener(targetEl, { onScan, onInvalid });
 *   // ...nanti panggil stop() untuk cleanup
 */

const TERMINATOR     = 'Enter';
const MAX_UID_LENGTH = 32;
const MIN_UID_LENGTH = 4;
// Baca dari AbsensiAdmin — wp_localize_script mengirim angka sebagai string, wajib parseInt
const DEBOUNCE_MS    = (parseInt(window.AbsensiAdmin?.rfidDebounce ?? '3', 10) || 3) * 1000;

/**
 * Normalisasi UID: strip non-hex, uppercase.
 * @param {string} raw
 * @returns {string}
 */
export function normalizeUid(raw) {
  return raw.replace(/[^0-9a-fA-F]/g, '').toUpperCase();
}

/**
 * Validasi UID hasil normalize.
 * @param {string} uid
 * @returns {boolean}
 */
export function isValidUid(uid) {
  return uid.length >= MIN_UID_LENGTH && uid.length <= MAX_UID_LENGTH;
}

/**
 * Pasang listener keyboard HID pada elemen target (biasanya input hidden).
 *
 * @param {HTMLElement} targetEl  — elemen yang menerima fokus + keydown
 * @param {{ onScan: (uid: string) => void, onInvalid?: (raw: string) => void }} callbacks
 * @returns {() => void}  — fungsi cleanup (panggil untuk lepas listener)
 */
export function createRfidListener(targetEl, { onScan, onInvalid }) {
  let buffer       = '';
  let lastUid      = '';
  let lastScanTime = 0;

  function handleKeydown(e) {
    if (e.key === TERMINATOR) {
      e.preventDefault();
      const uid = normalizeUid(buffer);
      buffer = '';

      if (!isValidUid(uid)) {
        onInvalid?.(uid);
        return;
      }

      const now = Date.now();
      if (uid === lastUid && now - lastScanTime < DEBOUNCE_MS) {
        // double-tap dalam window — abaikan
        return;
      }

      lastUid      = uid;
      lastScanTime = now;
      onScan(uid);
      return;
    }

    // Karakter biasa — tambah ke buffer; abaikan tombol modifier
    if (e.key.length === 1) {
      buffer += e.key;
      if (buffer.length > MAX_UID_LENGTH * 2) {
        buffer = ''; // bufer terlalu panjang — kemungkinan bukan scanner
      }
    }
  }

  function handleBlur() {
    // Kembalikan fokus hanya jika tidak ada elemen interaktif yang sedang aktif
    // (SELECT, INPUT, BUTTON, A) — agar dropdown kelas / form bisa dipakai normal
    setTimeout(() => {
      const active = document.activeElement;
      const interactive = ['INPUT', 'SELECT', 'TEXTAREA', 'BUTTON', 'A'];
      if (!active || !interactive.includes(active.tagName)) {
        targetEl.focus();
      }
    }, 50);
  }

  targetEl.addEventListener('keydown', handleKeydown);
  targetEl.addEventListener('blur', handleBlur);

  return function cleanup() {
    targetEl.removeEventListener('keydown', handleKeydown);
    targetEl.removeEventListener('blur', handleBlur);
  };
}
