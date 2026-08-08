<?php
// view.php - Ultimate flexible file and folder router (FIXED)
session_start();

$site_req = isset($_GET['site']) ? rawurldecode(trim((string) $_GET['site'])) : '';

if (empty($site_req)) {
    header("Location: index.php");
    exit;
}

$site_req = trim(str_replace('\\', '/', $site_req), '/');

// Never allow a request to leave the sites/ directory.
if ($site_req === '' || strpos($site_req, "\0") !== false ||
    preg_match('#(^|/)\.\.?(/|$)#', $site_req)) {
    http_response_code(400);
    showError("Invalid file path.");
    exit;
}

// Base directory for sites — create if missing
$sites_dir = __DIR__ . '/sites';
if (!is_dir($sites_dir)) {
    mkdir($sites_dir, 0777, true);
}
$base_dir = realpath($sites_dir);

if (!$base_dir) {
    http_response_code(500);
    showError("Sites directory ban nahi saki. Server permissions check karo.");
    exit;
}

// 1. Pehle check karein ke kya direct folder ya file maujood hai.
// This also supports nested URLs such as:
// /hostingwebsite/login.php
// /hostingwebsite/assets/style.css
$target_path = realpath($base_dir . '/' . $site_req);
$resolved_from_legacy_url = false;

// Security Check: Path Traversal Attack rokne ke liye
if ($target_path !== false && isPathInside($target_path, $base_dir)) {
    // Agar yeh folder hai, toh index file dhundho
    if (is_dir($target_path)) {
        // FIX: index.php bhi include kiya gaya
        $index_files = ['index.html', 'index.htm', 'index.php', 'main.html'];
        $found = false;

        foreach ($index_files as $index) {
            $possible_index = $target_path . '/' . $index;
            if (file_exists($possible_index)) {
                $target_path = $possible_index;
                $found = true;
                break;
            }
        }

        if (!$found) {
            // Recursively subfolders mein bhi dhundho (ZIP ke nested folders ke liye)
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($target_path, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $ext = strtolower($file->getExtension());
                    // FIX: php bhi search karo
                    if (in_array($ext, ['html', 'htm', 'php'])) {
                        $basename = strtolower($file->getBasename());
                        if (in_array($basename, ['index.html', 'index.htm', 'index.php'])) {
                            $target_path = $file->getRealPath();
                            $found = true;
                            break;
                        } elseif (!$found) {
                            $target_path = $file->getRealPath();
                            $found = true;
                            // Don't break — keep looking for index.html/index.php
                        }
                    }
                }
            }
        }

        // FIX: Agar HTML/PHP nahi mili, toh pehli koi bhi file serve karo
        if (!$found) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($target_path, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $target_path = $file->getRealPath();
                    $found = true;
                    break;
                }
            }
        }

        if (!$found) {
            http_response_code(404);
            showError("Is site mein koi file nahi mili.");
            exit;
        }
    }
} else {
    // Support old uploads where the ZIP's top folder was flattened during
    // extraction, while still allowing the correct nested URL format.
    $legacy_path = findLegacyHostedPath($site_req, $base_dir);
    if ($legacy_path !== false) {
        $target_path = $legacy_path;
        $resolved_from_legacy_url = true;
    } else {
    // 2. Agar direct match nahi hua, toh extensions ke saath try karo
    $try_extensions = ['html', 'htm', 'php', 'txt', 'js', 'css', 'py', 'rb', 'go', 'rs', 'java', 'ts'];
    $found_path = false;

    foreach ($try_extensions as $ext) {
        $possible_file = $base_dir . '/' . $site_req . '.' . $ext;
        $resolved_file = realpath($possible_file);
        if ($resolved_file !== false && isPathInside($resolved_file, $base_dir) && file_exists($resolved_file)) {
            $found_path = $resolved_file;
            break;
        }
    }

    if ($found_path) {
        $target_path = $found_path;
    } else {
        // Agar kuch bhi nahi mila, toh 404 error
        http_response_code(404);
        showError("Requested site or file not found - '$site_req'");
        exit;
    }
    }
}

// Final check ke file exist karti hai ya nahi
if (!file_exists($target_path) || is_dir($target_path)) {
    http_response_code(404);
    showError("Requested resource is not a valid file.");
    exit;
}

// Do not redirect compatibility URLs.
//
// InfinityFree can internally rewrite a clean URL to view.php while keeping
// the original request path in the server variables. Redirecting a legacy
// path to a calculated "canonical" path in that situation can make the
// browser follow the same rewrite repeatedly (ERR_TOO_MANY_REDIRECTS).
// The resolved file is served directly instead. Base href injection below
// still makes relative links work from both old and nested URLs.

// File extension
$file_ext = strtolower(pathinfo($target_path, PATHINFO_EXTENSION));

// ── PHP files: execute + base href inject ──
if ($file_ext === 'php') {
    $old_dir = getcwd();
    chdir(dirname($target_path));

    // Output buffer mein capture karo
    ob_start();
    include $target_path;
    $php_out = ob_get_clean();

    chdir($old_dir);

    // Agar HTML output hai toh base href inject karo
    if (stripos($php_out, '<html') !== false || stripos($php_out, '<!DOCTYPE') !== false) {
        $rel_path  = ltrim(str_replace(['\\', $base_dir], ['/', ''], $target_path), '/');
        // Use the actual resolved file location. This also works when the
        // short legacy URL resolves inside an inner project folder.
        $dir_part  = dirname($rel_path);
        $app_base = getAppBasePath();
        $base_href = $app_base . (($dir_part !== '.' && $dir_part !== '')
            ? '/' . trim(str_replace('\\', '/', $dir_part), '/') . '/'
            : '/');

        if (stripos($php_out, '<base ') === false) {
            $base_tag = '<base href="' . htmlspecialchars($base_href, ENT_QUOTES) . '">';
            if (preg_match('/<head[^>]*>/i', $php_out)) {
                $php_out = preg_replace('/(<head[^>]*>)/i', '$1' . $base_tag, $php_out, 1);
            } else {
                $php_out = $base_tag . $php_out;
            }
        }
        header('Content-Type: text/html; charset=UTF-8');
    }

    echo $php_out;
    exit;
}

// Complete Content-Type / MIME Types setup — All Languages Support
$content_types = [
    // Web / Markup
    'html'  => 'text/html; charset=UTF-8',
    'htm'   => 'text/html; charset=UTF-8',
    'xhtml' => 'application/xhtml+xml; charset=UTF-8',
    'xml'   => 'application/xml; charset=UTF-8',
    'xsl'   => 'application/xml; charset=UTF-8',
    'rss'   => 'application/rss+xml; charset=UTF-8',
    'atom'  => 'application/atom+xml; charset=UTF-8',

    // Stylesheets
    'css'   => 'text/css; charset=UTF-8',
    'sass'  => 'text/x-sass; charset=UTF-8',
    'scss'  => 'text/x-scss; charset=UTF-8',
    'less'  => 'text/x-less; charset=UTF-8',

    // JavaScript / TypeScript
    'js'    => 'application/javascript; charset=UTF-8',
    'mjs'   => 'application/javascript; charset=UTF-8',
    'cjs'   => 'application/javascript; charset=UTF-8',
    'ts'    => 'application/typescript; charset=UTF-8',
    'jsx'   => 'application/javascript; charset=UTF-8',
    'tsx'   => 'application/typescript; charset=UTF-8',

    // Data formats
    'json'  => 'application/json; charset=UTF-8',
    'jsonld'=> 'application/ld+json; charset=UTF-8',
    'geojson'=> 'application/geo+json; charset=UTF-8',
    'yaml'  => 'text/yaml; charset=UTF-8',
    'yml'   => 'text/yaml; charset=UTF-8',
    'toml'  => 'application/toml; charset=UTF-8',
    'csv'   => 'text/csv; charset=UTF-8',
    'tsv'   => 'text/tab-separated-values; charset=UTF-8',

    // Plain text & code
    'txt'   => 'text/plain; charset=UTF-8',
    'md'    => 'text/markdown; charset=UTF-8',
    'markdown' => 'text/markdown; charset=UTF-8',
    'log'   => 'text/plain; charset=UTF-8',
    'ini'   => 'text/plain; charset=UTF-8',
    'cfg'   => 'text/plain; charset=UTF-8',
    'conf'  => 'text/plain; charset=UTF-8',
    'env'   => 'text/plain; charset=UTF-8',
    'sh'    => 'text/x-shellscript; charset=UTF-8',
    'bash'  => 'text/x-shellscript; charset=UTF-8',
    'bat'   => 'text/plain; charset=UTF-8',
    'cmd'   => 'text/plain; charset=UTF-8',
    'ps1'   => 'text/plain; charset=UTF-8',
    'py'    => 'text/x-python; charset=UTF-8',
    'rb'    => 'text/x-ruby; charset=UTF-8',
    'java'  => 'text/x-java-source; charset=UTF-8',
    'c'     => 'text/x-csrc; charset=UTF-8',
    'cpp'   => 'text/x-c++src; charset=UTF-8',
    'h'     => 'text/x-chdr; charset=UTF-8',
    'cs'    => 'text/x-csharp; charset=UTF-8',
    'go'    => 'text/x-go; charset=UTF-8',
    'rs'    => 'text/x-rustsrc; charset=UTF-8',
    'swift' => 'text/x-swift; charset=UTF-8',
    'kt'    => 'text/x-kotlin; charset=UTF-8',
    'kts'   => 'text/x-kotlin; charset=UTF-8',
    'dart'  => 'application/vnd.dart; charset=UTF-8',
    'lua'   => 'text/x-lua; charset=UTF-8',
    'r'     => 'text/x-r; charset=UTF-8',
    'pl'    => 'text/x-perl; charset=UTF-8',
    'sql'   => 'application/sql; charset=UTF-8',
    'graphql'=> 'application/graphql; charset=UTF-8',
    'vue'   => 'text/x-vue; charset=UTF-8',
    'svelte'=> 'text/x-svelte; charset=UTF-8',
    'astro' => 'text/plain; charset=UTF-8',

    // Images (raster)
    'png'   => 'image/png',
    'jpg'   => 'image/jpeg',
    'jpeg'  => 'image/jpeg',
    'gif'   => 'image/gif',
    'webp'  => 'image/webp',
    'bmp'   => 'image/bmp',
    'ico'   => 'image/x-icon',
    'tiff'  => 'image/tiff',
    'tif'   => 'image/tiff',
    'avif'  => 'image/avif',
    'heic'  => 'image/heic',
    'heif'  => 'image/heif',

    // Images (vector / other)
    'svg'   => 'image/svg+xml',
    'svgz'  => 'image/svg+xml',

    // Fonts
    'woff'  => 'font/woff',
    'woff2' => 'font/woff2',
    'ttf'   => 'font/ttf',
    'otf'   => 'font/otf',
    'eot'   => 'application/vnd.ms-fontobject',

    // Audio
    'mp3'   => 'audio/mpeg',
    'ogg'   => 'audio/ogg',
    'oga'   => 'audio/ogg',
    'wav'   => 'audio/wav',
    'aac'   => 'audio/aac',
    'flac'  => 'audio/flac',
    'm4a'   => 'audio/mp4',
    'opus'  => 'audio/opus',
    'mid'   => 'audio/midi',
    'midi'  => 'audio/midi',

    // Video
    'mp4'   => 'video/mp4',
    'webm'  => 'video/webm',
    'ogv'   => 'video/ogg',
    'avi'   => 'video/x-msvideo',
    'mov'   => 'video/quicktime',
    'mkv'   => 'video/x-matroska',
    'flv'   => 'video/x-flv',
    'm4v'   => 'video/mp4',
    '3gp'   => 'video/3gpp',
    '3g2'   => 'video/3gpp2',

    // Documents / Office
    'pdf'   => 'application/pdf',
    'doc'   => 'application/msword',
    'docx'  => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls'   => 'application/vnd.ms-excel',
    'xlsx'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'ppt'   => 'application/vnd.ms-powerpoint',
    'pptx'  => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'odt'   => 'application/vnd.oasis.opendocument.text',
    'ods'   => 'application/vnd.oasis.opendocument.spreadsheet',
    'odp'   => 'application/vnd.oasis.opendocument.presentation',
    'epub'  => 'application/epub+zip',
    'rtf'   => 'application/rtf',

    // Archives
    'zip'   => 'application/zip',
    'tar'   => 'application/x-tar',
    'gz'    => 'application/gzip',
    'bz2'   => 'application/x-bzip2',
    'rar'   => 'application/vnd.rar',
    '7z'    => 'application/x-7z-compressed',

    // Web App Manifest / Service Worker
    'webmanifest' => 'application/manifest+json',
    'manifest'    => 'text/cache-manifest; charset=UTF-8',

    // Maps / Geo
    'map'   => 'application/json; charset=UTF-8',

    // Binary / Executables
    'apk'   => 'application/vnd.android.package-archive',
    'exe'   => 'application/vnd.microsoft.portable-executable',
    'dmg'   => 'application/x-apple-diskimage',
    'deb'   => 'application/vnd.debian.binary-package',

    // 3D / Game
    'glb'   => 'model/gltf-binary',
    'gltf'  => 'model/gltf+json',
    'obj'   => 'model/obj',
    'stl'   => 'model/stl',

    // Misc
    'wasm'  => 'application/wasm',
    'vcf'   => 'text/vcard; charset=UTF-8',
    'ics'   => 'text/calendar; charset=UTF-8',
];

if (isset($content_types[$file_ext])) {
    header('Content-Type: ' . $content_types[$file_ext]);
} else {
    header('Content-Type: application/octet-stream');
}

// Cache headers for static assets
if (!in_array($file_ext, ['html', 'htm', 'php'])) {
    header('Cache-Control: public, max-age=86400');
}

// ── HTML files: <base href> inject karo taa ke CSS/JS/images sahi route hon ──
if (in_array($file_ext, ['html', 'htm', 'xhtml'])) {
    $html_content = file_get_contents($target_path);

    // Relative path of the file inside sites/
    // e.g. target = /srv/sites/mysite/index.html  → rel = mysite/index.html
    $rel_path  = ltrim(str_replace(['\\', $base_dir], ['/', ''], $target_path), '/');
    $dir_part  = dirname($rel_path);

    // Base href = /mysite/  so browser resolves style.css → /mysite/style.css
    if ($dir_part === '.' || $dir_part === '') {
        $base_href = '/';
    } else {
        $base_href = '/' . trim(str_replace('\\', '/', $dir_part), '/') . '/';
    }

    // Inject <base href> only if not already present
    if (stripos($html_content, '<base ') === false && stripos($html_content, "<base\t") === false) {
        $base_tag = '<base href="' . $base_href . '">';
        if (preg_match('/<head[^>]*>/i', $html_content)) {
            $html_content = preg_replace('/(<head[^>]*>)/i', '$1' . $base_tag, $html_content, 1);
        } elseif (stripos($html_content, '<html') !== false) {
            $html_content = preg_replace('/(<html[^>]*>)/i', '$1<head>' . $base_tag . '</head>', $html_content, 1);
        } else {
            $html_content = $base_tag . $html_content;
        }
    }

    header('Content-Length: ' . strlen($html_content));
    echo $html_content;
    exit;
}

// All other files: direct output
readfile($target_path);
exit;

function isPathInside($path, $base) {
    $path = rtrim(str_replace('\\', '/', (string) $path), '/');
    $base = rtrim(str_replace('\\', '/', (string) $base), '/');
    return $path === $base || strpos($path, $base . '/') === 0;
}

function getRelativeHostedPath($path, $base) {
    $path = realpath($path);
    $base = realpath($base);
    if ($path === false || $base === false || !isPathInside($path, $base)) {
        return false;
    }
    return ltrim(str_replace('\\', '/', substr($path, strlen($base))), '/');
}

function encodeHostedPath($path) {
    $parts = array_filter(explode('/', trim(str_replace('\\', '/', $path), '/')), 'strlen');
    return implode('/', array_map('rawurlencode', $parts));
}

/**
 * Resolve compatibility URLs for older uploads.
 *
 * Preferred:
 *   /my-site/nested-folder/login.php
 * Legacy flattened upload:
 *   /my-site/login.php
 *
 * A root-level /login.php is only resolved when exactly one matching file
 * exists, so one site cannot accidentally expose another site's file.
 */
function findLegacyHostedPath($request, $base) {
    $parts = array_values(array_filter(explode('/', trim($request, '/')), 'strlen'));
    if (empty($parts)) return false;

    $site_root = realpath($base . '/' . $parts[0]);
    if ($site_root !== false && is_dir($site_root) && isPathInside($site_root, $base)) {
        $relative = implode('/', array_slice($parts, 1));
        if ($relative !== '') {
            $direct = realpath($site_root . '/' . $relative);
            if ($direct !== false && is_file($direct) && isPathInside($direct, $site_root)) {
                return $direct;
            }

            // Legacy ZIP layout support:
            // /mariwebsite/login.php
            // can resolve to /mariwebsite/hostedwebaite/login.php
            // when the ZIP contains one inner wrapper directory.
            $child_dirs = [];
            foreach (@scandir($site_root) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') continue;
                $child = realpath($site_root . '/' . $entry);
                if ($child !== false && is_dir($child) && isPathInside($child, $site_root)) {
                    $child_dirs[] = $child;
                }
            }
            foreach ($child_dirs as $child_dir) {
                $nested = realpath($child_dir . '/' . $relative);
                if ($nested !== false && is_file($nested) && isPathInside($nested, $child_dir)) {
                    return $nested;
                }
            }

            // Older versions flattened a ZIP wrapper folder. Match the
            // requested filename only when it is unambiguous in this site.
            $filename = basename($relative);
            $matches = [];
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($site_root, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isFile() && strcasecmp($file->getBasename(), $filename) === 0) {
                    $matches[] = $file->getRealPath();
                    if (count($matches) > 1) break;
                }
            }
            if (count($matches) === 1 && isPathInside($matches[0], $site_root)) {
                return $matches[0];
            }

            // If an old upload flattened a wrapper directory and the request
            // points to that directory itself, use its unique index file.
            if (pathinfo($relative, PATHINFO_EXTENSION) === '') {
                $index_matches = [];
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($site_root, RecursiveDirectoryIterator::SKIP_DOTS)
                );
                foreach ($iterator as $file) {
                    if ($file->isFile() && in_array(strtolower($file->getBasename()), [
                        'index.php', 'index.html', 'index.htm'
                    ], true)) {
                        $index_matches[] = $file->getRealPath();
                        if (count($index_matches) > 1) break;
                    }
                }
                if (count($index_matches) === 1 && isPathInside($index_matches[0], $site_root)) {
                    return $index_matches[0];
                }
            }
        }
    }

    // Compatibility for the exact URL visible in the screenshot:
    // /login.php. Only serve it if one unique match exists across sites.
    if (count($parts) === 1) {
        $matches = [];
        $entries = @scandir($base) ?: [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $root = realpath($base . '/' . $entry);
            if ($root === false || !is_dir($root)) continue;
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isFile() && strcasecmp($file->getBasename(), $parts[0]) === 0) {
                    $matches[] = $file->getRealPath();
                    if (count($matches) > 1) return false;
                }
            }
        }
        if (count($matches) === 1) return $matches[0];
    }

    return false;
}

function getAppBasePath() {
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/view.php');
    $dir = dirname($script);
    if ($dir === '.' || $dir === '/') return '';
    return '/' . trim($dir, '/');
}

function showError($message) {
    $home_url = getAppBasePath() . '/';
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Error - Team Zero Hosting</title>
        <style>
            body { 
                font-family: Arial, sans-serif; 
                margin: 0;
                padding: 40px;
                background: linear-gradient(135deg, #667eea, #764ba2);
                color: white;
                text-align: center;
            }
            .error-container {
                max-width: 600px;
                margin: 50px auto;
                background: rgba(255,255,255,0.1);
                padding: 40px;
                border-radius: 15px;
                backdrop-filter: blur(10px);
            }
            h1 { color: #ff6b6b; margin-bottom: 20px; }
            .btn {
                display: inline-block;
                background: #4361ee;
                color: white;
                padding: 12px 24px;
                text-decoration: none;
                border-radius: 8px;
                margin-top: 20px;
                transition: background 0.3s;
            }
            .btn:hover { background: #3a0ca3; }
        </style>
    </head>
    <body>
        <div class='error-container'>
            <h1>❌ Error</h1>
            <p style='font-size: 1.2rem; line-height: 1.6;'>$message</p>
            <a href='" . htmlspecialchars($home_url, ENT_QUOTES, 'UTF-8') . "' class='btn'>← Back to Hosting</a>
        </div>
    </body>
    </html>";
}
?>
