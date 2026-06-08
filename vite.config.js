import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
  build: {
    outDir: 'assets/dist',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: {
        siswa:  resolve(__dirname, 'assets/src/siswa.js'),
        guru:   resolve(__dirname, 'assets/src/guru.js'),
        admin:  resolve(__dirname, 'assets/src/admin.js'),
        ortu:   resolve(__dirname, 'assets/src/ortu.js'),
        app:    resolve(__dirname, 'assets/src/app.css'),
      },
      output: {
        entryFileNames: '[name].js',
        chunkFileNames: '[name]-[hash].js',
        assetFileNames: '[name][extname]',
      },
    },
  },
  server: {
    origin: 'http://localhost:5173',
  },
});
