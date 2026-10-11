import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { execSync } from 'node:child_process'
import bundleApi from './scripts/bundleApi.js'

const clientDir = path.dirname(fileURLToPath(import.meta.url))

// Build stamp shown in Admin → Municipality Settings and in /build-info.json,
// so a deployment can be checked against the latest GitHub commit.
function gitCommit() {
  try { return execSync('git rev-parse --short HEAD', { cwd: clientDir, stdio: ['ignore', 'pipe', 'ignore'] }).toString().trim() } catch { return process.env.GIT_COMMIT || 'unknown' }
}
const buildInfo = { version: gitCommit(), built_at: new Date().toISOString() }

export default defineConfig({
  // The PHP API ships inside the build (dist/api) so a Hostinger redeploy never removes it.
  plugins: [react(), bundleApi({ serverDir: path.resolve(clientDir, '../server'), outDir: path.resolve(clientDir, '../dist'), mirrorDirs: [path.resolve(clientDir, 'dist')], buildInfo })],
  define: { __APP_BUILD__: JSON.stringify(buildInfo) },
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
    // Start every build clean so old bundles never pile up or get published.
    emptyOutDir: true,
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