/**
 * Copies the PHP API (../server) into the build output as dist/api.
 *
 * Hostinger's GitHub deployment publishes only the build output to public_html and
 * replaces what was there, so the API must be part of the build or it is deleted on
 * every redeploy. Secrets and user data are never copied: .env files, tests, logs,
 * and every uploaded or generated file (resumes, photos, seals, documents) stay out
 * of the build. Persistent data lives outside public_html (see server/config/paths.php).
 */
import fs from 'node:fs'
import path from 'node:path'

const EXCLUDED_DIRS = new Set(['tests', 'logs', 'node_modules', '.git'])
// Uploads and storage hold personal data; only the folders and their access guards are shipped.
const PRIVATE_DIRS = ['storage', 'uploads']
const PLACEHOLDERS = new Set(['.htaccess', '.gitkeep', '.keep'])

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
      const isFile = fs.statSync(source).isFile()
      if (isFile && PRIVATE_DIRS.some((dir) => rel.startsWith(dir + '/')) && !PLACEHOLDERS.has(name)) return false
      if (isFile) files++
      return true
    },
  })
  return { target, files }
}

/** Vite plugin: run after the frontend bundle is written. */
/**
 * Vite plugin: run after the frontend bundle is written.
 * `mirrorDirs` receive an identical copy of the complete site, so the deployment works
 * whichever output folder the host publishes (dist/ or client/dist/).
 */
export default function bundleApi({ serverDir, outDir, mirrorDirs = [], buildInfo = {} }) {
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
      // Lets anyone check which version is live: https://YOUR_DOMAIN/build-info.json
      fs.writeFileSync(path.join(outDir, 'build-info.json'), JSON.stringify(buildInfo, null, 2))
      console.log(`\n[employsmart] PHP API bundled into ${path.relative(process.cwd(), target) || target} (${files} files)`)
      for (const mirror of mirrorDirs) {
        if (path.resolve(mirror) === path.resolve(outDir)) continue
        fs.rmSync(mirror, { recursive: true, force: true })
        fs.cpSync(outDir, mirror, { recursive: true })
        console.log(`[employsmart] Site mirrored to ${path.relative(process.cwd(), mirror) || mirror}`)
      }
      console.log(`[employsmart] Build ${buildInfo.version || ''} ${buildInfo.built_at || ''}`)
    },
  }
}
