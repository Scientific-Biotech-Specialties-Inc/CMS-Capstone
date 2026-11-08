import path from 'node:path'
import { defineConfig } from 'vite'
import tailwindcss from '@tailwindcss/vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  base: '/cms/',
  plugins: [vue(), tailwindcss()],
  optimizeDeps: { include: ['pdf-lib'] },
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  server: {
    host: '0.0.0.0',
    port: 5001,
    allowedHosts: true,
    watch: {
      usePolling: true,
    },
  },
})
