<?php
/**
 * Locations for files that must survive redeployments.
 *
 * Hostinger's GitHub deployment replaces public_html with the fresh build on every
 * deploy, so .env, uploaded files and resumes cannot live inside the deployed API
 * folder. They are kept in a private folder next to public_html instead:
 *
 *   /home/uXXXXXXXXX/domains/example.com/
 *   ├── employsmart_private/   ← persistent: .env, uploads/, storage/resumes/
 *   └── public_html/           ← replaced on each deploy (frontend + api/)
 *
 * DATA_DIR overrides the location. During local development (server/ inside the
 * repository) everything stays in server/ as before.
 */

/** Absolute path of the API folder (server/ locally, public_html/api when deployed). */
function apiRootDir(): string {
    return dirname(__DIR__);
}

/** The conventional private folder beside the web root, whether or not it exists yet. */
function defaultPrivateDir(): string {
    return dirname(apiRootDir(), 2) . DIRECTORY_SEPARATOR . 'employsmart_private';
}

/** True when running from a deployed build (public_html/api) rather than the repository's server/ folder. */
function isDeployedBuild(): bool {
    return basename(apiRootDir()) === 'api' && is_file(dirname(apiRootDir()) . '/index.html');
}

/** Root folder for persistent data. */
function dataDir(): string {
    static $dir = null;
    if ($dir !== null) return $dir;

    $configured = trim((string)(function_exists('env') ? env('DATA_DIR', '') : ''));
    if ($configured !== '') {
        if (!is_dir($configured)) @mkdir($configured, 0750, true);
        return $dir = rtrim($configured, '/\\');
    }
    $private = defaultPrivateDir();
    if (is_dir($private)) return $dir = $private;
    if (isDeployedBuild()) {
        if (@mkdir($private, 0750, true)) return $dir = $private;
        error_log('EmploySmart: could not create ' . $private . '; uploads will be lost on redeploy. Set DATA_DIR in .env.');
    }
    return $dir = apiRootDir();
}

/** Persistent upload folder, e.g. uploadsDir('avatars') or uploadsDir('verification_docs/employers'). */
function uploadsDir(string $subdir = ''): string {
    $subdir = trim(str_replace('\\', '/', $subdir), '/');
    if (str_contains($subdir, '..')) throw new InvalidArgumentException('Invalid upload folder');
    $dir = dataDir() . '/uploads' . ($subdir !== '' ? '/' . $subdir : '');
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir;
}

/**
 * Resolve a stored relative path such as "uploads/avatars/x.jpg" or "api/uploads/landing/y.png"
 * to an existing file: the persistent data folder first, then files shipped with the build.
 */
function resolveUploadPath(?string $stored): ?string {
    $stored = ltrim(str_replace('\\', '/', (string)$stored), '/');
    $stored = preg_replace('#^api/#', '', $stored);
    if ($stored === '' || !str_starts_with($stored, 'uploads/') || str_contains($stored, '..')) return null;
    foreach (array_unique([dataDir(), apiRootDir()]) as $base) {
        $baseReal = realpath($base);
        $path = $baseReal ? realpath($baseReal . '/' . $stored) : false;
        if ($path !== false && str_starts_with($path, $baseReal . DIRECTORY_SEPARATOR) && is_file($path)) return $path;
    }
    return null;
}

/** The stored upload path when its file still exists, otherwise null (e.g. lost in an old redeploy). */
function existingUploadPath(?string $stored): ?string {
    return $stored && resolveUploadPath($stored) !== null ? $stored : null;
}

/**
 * Whether uploaded files are kept somewhere a redeploy cannot delete them.
 * @return array{persistent: bool, path: string, writable: bool}
 */
function uploadStorageStatus(): array {
    $dir = dataDir();
    $insideDeployment = isDeployedBuild() && realpath($dir) === realpath(apiRootDir());
    return ['persistent' => !$insideDeployment, 'path' => $insideDeployment ? 'public_html/api (deleted on redeploy)' : basename(dirname($dir)) . '/' . basename($dir), 'writable' => is_writable($dir)];
}
