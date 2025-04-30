import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],
  server: {
    port: 3000, // Optional: Port für den Entwicklungsserver
  },
  build: {
    outDir: 'build', // Optional: Ausgabeordner für den Produktions-Build
  },
});