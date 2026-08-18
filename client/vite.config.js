import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
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
    outDir: 'dist',
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