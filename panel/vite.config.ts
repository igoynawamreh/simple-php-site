import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

export default defineConfig({
  plugins: [vue(), tailwindcss()],
  base: '/',
  resolve: {
    alias: {
      '@': path.resolve(import.meta.dirname, './src'),
    },
  },
  server: {
    port: 5173,
    strictPort: true,
    cors: true,
    origin: 'http://localhost:5173',
  },
  build: {
    outDir: 'build',
    manifest: false,
    rollupOptions: {
      input: fileURLToPath(new URL('./src/main.js', import.meta.url)),
      output: {
        entryFileNames: 'js/main.js',
        chunkFileNames: 'js/chunk-[hash].js',
        assetFileNames: (assetInfo) => {
          const name = assetInfo.names?.[0] ?? '';
          const ext = path.extname(name).slice(1);
          const folders: Record<string, string> = {
            css: 'css',
            png: 'img',
            jpg: 'img',
            jpeg: 'img',
            svg: 'img',
            webp: 'img',
            woff: 'font',
            woff2: 'font',
            ttf: 'font',
          };
          const folder = folders[ext] ?? 'misc';
          return `${folder}/[name][extname]`;
        },
      },
    },
  },
});
