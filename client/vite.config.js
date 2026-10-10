import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import bundleApi from './scripts/bundleApi.js'

const clientDir = path.dirname(fileURLToPath(import.meta.url))

export default defineConfig({
  // The PHP API ships inside the build (dist/api) so a Hostinger redeploy never removes it.
  plugins: [react(), bundleApi({ serverDir: path.resolve(clientDir, '../server'), outDir: path.resolve(clientDir, '../dist') })],
  server: {
    port: 5173,
    host: '0.0.0.0',
    hmr: {
      protocol: 'ws',
      host: 'localhost',
    },
    proxy: {
      '/api': {
        // Development proxy: routes /api calls to backend
        target: 'http://localhost/api',
        changeOrigin: true,
        // Keep the /api prefix when proxying to backend
        rewrite: (path) => path
      }
    }
  },
  build: {
    outDir: '../dist',
    sourcemap: false,
    minify: 'terser',
    chunkSizeWarningLimit: 900,
    rollupOptions: {
      output: {
        manualChunks(id) {
          if (id.includes('node_modules')) {
            if (id.includes('react-router-dom') || id.includes('react') || id.includes('scheduler') || id.includes('react-dom')) {
              return 'react-vendor'
            }
            if (id.includes('recharts')) {
              return 'charting'
            }
            if (id.includes('axios') || id.includes('date-fns') || id.includes('jwt-decode')) {
              return 'data-vendor'
            }
            if (id.includes('react-hot-toast') || id.includes('react-icons')) {
              return 'ui-vendor'
            }
            return 'vendor'
          }
        }
      }
    }
  }
})