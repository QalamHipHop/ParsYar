import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'node:path';

export default defineConfig({
  plugins: [react()],
  build: {
    outDir: 'build',
    emptyOutDir: true,
    rollupOptions: {
      input: {
        main: path.resolve(__dirname, 'src/main.jsx'),
      },
    },
  },
  server: {
    port: 5173,
    proxy: {
      '/wp-json': 'http://localhost:8080',
    },
  },
});
