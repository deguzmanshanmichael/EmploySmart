/**
 * Copies the PHP API (../server) into the build output as dist/api.
 *
 * Hostinger's GitHub deployment publishes only the build output to public_html and
 * replaces what was there, so the API must be part of the build or it is deleted on
 * every redeploy. Secrets and user data are never copied: .env files, tests, logs,
 * and uploaded resumes stay out of the build. Persistent data lives outside
 * public_html (see server/config/paths.php).
 */
import fs from 'node:fs'
import path from 'node:path'

const EXCLUDED_DIRS = new Set(['tests', 'logs', 'node_modules', '.git'])
// Uploaded resumes are personal data; only the folder guards are shipped.
const PRIVATE_DIRS = ['storage/resumes', 'storage/generated', 'uploads/resumes']

export function copyApi(serverDir, outDir) {
  const target = path.join(outDir, 'api')
  fs.rmSync(target, { recursive: true, force: true })
  let files = 0
  fs.cpSync(serverDir, target, {
    recursive: true,
    filter: (source) => {
      const rel = path.relative(serverDir, source).split(path.sep).join('/')
      if (rel === '') return true
      const name = path.basename(source)
      if (name === '.env' || name.startsWith('.env.') || name.endsWith('.log')) return false
      if (EXCLUDED_DIRS.has(rel.split('/')[0])) return false
      for (const dir of PRIVATE_DIRS) {
        if (rel.startsWith(dir + '/') && name !== '.htaccess') return false
      }
      if (fs.statSync(source).isFile()) files++
      return true
    },
  })
  return { target, files }
}

/** Vite plugin: run after the frontend bundle is written. */
export default function bundleApi({ serverDir, outDir }) {
  return {
    name: 'employsmart-bundle-api',
    apply: 'build',
    closeBundle() {
      if (!fs.existsSync(path.join(serverDir, 'index.php'))) {
        throw new Error(`PHP API not found at ${serverDir}; cannot include it in the build.`)
      }
      if (!fs.existsSync(path.join(serverDir, 'vendor', 'autoload.php'))) {
        throw new Error('server/vendor is missing; run "composer install --no-dev" in server/ before building.')
      }
      const { target, files } = copyApi(serverDir, outDir)
      console.log(`\n[employsmart] PHP API bundled into ${path.relative(process.cwd(), target) || target} (${files} files)`)
    },
  }
}
