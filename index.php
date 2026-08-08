<?php
// Team Zero - Free Web Hosting + API & Source Code Hub Creator
session_start();

// ───────────────────────────────────────────────
// 1.  HANDLE CUSTOM HUB: host or download
// ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_hub'])) {
    $hub_title      = htmlspecialchars(trim($_POST['hub_title']     ?? 'TEAM ZERO'), ENT_QUOTES);
    $hub_powered    = htmlspecialchars(trim($_POST['hub_powered']   ?? 'TEAM ZERO'), ENT_QUOTES);
    $hub_url        = cleanCustomUrl(trim($_POST['hub_url']         ?? 'my-hub'));
    $action_hub     = $_POST['action_hub'];

    $hub_html = generateHubHtml($hub_title, $hub_powered);

    if ($action_hub === 'download') {
        header('Content-Type: text/html; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $hub_url . '.html"');
        header('Content-Length: ' . strlen($hub_html));
        echo $hub_html;
        exit;
    } elseif ($action_hub === 'host') {
        if (!is_dir('sites')) {
            mkdir('sites', 0777, true);
        }
        $site_path = 'sites/' . $hub_url;
        $file_path = $site_path . '/index.html';
        if (file_exists($site_path)) {
            $_SESSION['hub_error'] = "Yeh URL already le liya gaya hai. Koi aur URL choose karo.";
        } else {
            if (!is_dir($site_path)) mkdir($site_path, 0777, true);
            if (file_put_contents($file_path, $hub_html)) {
                $_SESSION['hub_success'] = "Hub ban gaya! Neeche link copy karo.";
                $_SESSION['hub_url']     = getWebsiteUrl($hub_url);
            } else {
                $_SESSION['hub_error'] = "File save karne mein error. Folder permissions check karo.";
            }
        }
        header("Location: index.php");
        exit;
    }
}

// ───────────────────────────────────────────────
// 1B.  HANDLE CUSTOM BUTTON HOST/DOWNLOAD
// ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_custom'])) {
    $action_c   = $_POST['action_custom'];
    $btn_html   = $_POST['custom_html']   ?? '';
    $custom_url = cleanCustomUrl(trim($_POST['custom_url'] ?? 'my-site'));

    if ($action_c === 'host') {
        if (!is_dir('sites')) mkdir('sites', 0777, true);
        $site_path = 'sites/' . $custom_url;
        $file_path = $site_path . '/index.html';
        if (file_exists($site_path)) {
            $_SESSION['error'] = "Yeh URL already le liya gaya hai. Koi aur URL choose karo.";
        } else {
            if (!is_dir($site_path)) mkdir($site_path, 0777, true);
            if (file_put_contents($file_path, $btn_html)) {
                $_SESSION['success']  = "Website host ho gayi! Neeche link copy karo.";
                $_SESSION['file_url'] = getWebsiteUrl($custom_url);
            } else {
                $_SESSION['error'] = "File save karne mein error. Permissions check karo.";
            }
        }
        header("Location: index.php");
        exit;
    } elseif ($action_c === 'download') {
        header('Content-Type: text/html; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $custom_url . '.html"');
        header('Content-Length: ' . strlen($btn_html));
        echo $btn_html;
        exit;
    }
}

// ───────────────────────────────────────────────
// 2.  HANDLE REGULAR FILE / CODE UPLOAD
// ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action_hub'])) {

    if (isset($_FILES['html_file']) && !empty($_FILES['html_file']['name'])) {
        $file       = $_FILES['html_file'];
        $custom_url = trim($_POST['custom_url']) ?: generateCustomUrl($file['name']);

        if ($file['error'] === UPLOAD_ERR_OK) {
            $file_name = $file['name'];
            $file_tmp  = $file['tmp_name'];
            $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            // ALL file types allowed — koi bhi restriction nahi
            if (!is_dir('sites')) mkdir('sites', 0777, true);
            $custom_url = cleanCustomUrl($custom_url);

            if ($file_ext === 'zip') {
                $result = handleZipUpload($file_tmp, $custom_url, $file_name);
                if ($result['success']) {
                    $website_url = getWebsiteUrl($custom_url);
                    $_SESSION['success']   = "ZIP extract ho gayi! " . $result['message'];
                    $_SESSION['file_url']  = $website_url;
                    $_SESSION['file_name'] = $file_name;
                } else {
                    $_SESSION['error'] = $result['message'];
                }
            } else {
                // Every upload gets its own website folder. This keeps all
                // files under /site-name/filename instead of putting files
                // beside each other in sites/.
                $upload_folder = cleanCustomUrl(pathinfo($file_name, PATHINFO_FILENAME));
                $site_path = 'sites/' . $custom_url . '/' . $upload_folder;
                $stored_name = cleanUploadFilename($file_name);
                $file_path = $site_path . '/' . $stored_name;
                if (!is_dir($site_path)) mkdir($site_path, 0777, true);
                if (file_exists($file_path)) {
                    $_SESSION['error'] = "Yeh URL already le liya gaya hai. Koi aur URL choose karo.";
                } else {
                    if (move_uploaded_file($file_tmp, $file_path)) {
                        $website_url = getWebsiteUrl($custom_url . '/' . $upload_folder . '/' . $stored_name);
                        $_SESSION['success']   = "File upload ho gayi!";
                        $_SESSION['file_url']  = $website_url;
                        $_SESSION['file_name'] = $file_name;
                    } else {
                        $_SESSION['error'] = "File upload mein error. Folder permissions check karo. Error code: " . $file['error'];
                    }
                }
            }
        } else {
            $_SESSION['error'] = "File upload error. Dobara try karo. Error code: " . $file['error'];
        }

    } elseif (isset($_POST['html_code'])) {
        $html_code  = $_POST['html_code'];
        $custom_url = trim($_POST['code_custom_url']) ?: generateCustomUrl('team-zero-site');

        if (!empty($html_code)) {
            if (!is_dir('sites')) mkdir('sites', 0777, true);
            $custom_url = cleanCustomUrl($custom_url);
            $site_path  = 'sites/' . $custom_url;
            $file_path  = $site_path . '/index.html';
            if (!is_dir($site_path)) mkdir($site_path, 0777, true);

            if (file_exists($file_path)) {
                $_SESSION['error'] = "Yeh URL already le liya gaya hai. Koi aur URL choose karo.";
            } else {
                if (file_put_contents($file_path, $html_code)) {
                    $website_url = getWebsiteUrl($custom_url);
                    $_SESSION['success']   = "Website ban gayi!";
                    $_SESSION['file_url']  = $website_url;
                    $_SESSION['file_name'] = $custom_url . '.html';
                } else {
                    $_SESSION['error'] = "File banane mein error. Folder permissions check karo.";
                }
            }
        } else {
            $_SESSION['error'] = "Kuch code likho.";
        }
    }

    header("Location: index.php");
    exit;
}

// ───────────────────────────────────────────────
// 3.  HELPER FUNCTIONS
// ───────────────────────────────────────────────
/* ── Helper: recursively delete a directory ── */
function rmdirRecursive($dir) {
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $fo) {
        $fo->isDir() ? rmdir($fo->getRealPath()) : unlink($fo->getRealPath());
    }
    rmdir($dir);
}

/* ── Helper: move entire directory contents from $src to $dst ── */
function moveDirContents($src, $dst) {
    $src = realpath($src);
    if (!$src) return;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $item) {
        $rel  = substr($item->getRealPath(), strlen($src) + 1);
        $dest = $dst . '/' . $rel;
        if ($item->isDir()) {
            if (!is_dir($dest)) mkdir($dest, 0777, true);
        } else {
            $dd = dirname($dest);
            if (!is_dir($dd)) mkdir($dd, 0777, true);
            rename($item->getRealPath(), $dest);
        }
    }
}

/* ── Helper: find the real site root inside extracted path ──
   Works for ANY file type — not just index.php/html.
   Strategy:
   1. If current dir has any actual files → already at root
   2. Else if there's exactly one subdir with files → go into it
   3. Repeat until we find the dir with actual files
   This handles: mariwebsite/hostedwebsite/login.php (2 levels)
                 or any depth, any file extension             ── */
function findSiteRoot($base) {
    $realBase = realpath($base);
    if (!$realBase) return $base;

    $maxDepth = 10; // prevent infinite loops
    $current  = $realBase;

    for ($d = 0; $d < $maxDepth; $d++) {
        $entries = @scandir($current);
        if (!$entries) break;

        $files   = [];
        $subdirs = [];

        foreach ($entries as $e) {
            if ($e === '.' || $e === '..') continue;
            $fp = $current . '/' . $e;
            if (is_file($fp))  $files[]   = $fp;
            if (is_dir($fp))   $subdirs[] = $fp;
        }

        // If there are actual files here → this is the site root
        if (!empty($files)) return $current;

        // No files, but exactly one subdir → dive in
        if (count($subdirs) === 1) {
            $current = $subdirs[0];
            continue;
        }

        // Multiple subdirs, no files at this level → check which subdir has files
        // Pick the one with most files (heuristic for multi-folder ZIPs)
        $best = null; $bestCount = 0;
        foreach ($subdirs as $sd) {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($sd, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            $cnt = 0;
            foreach ($it as $f) { if ($f->isFile()) $cnt++; }
            if ($cnt > $bestCount) { $bestCount = $cnt; $best = $sd; }
        }
        if ($best) { $current = $best; continue; }

        break; // can't go deeper
    }

    return $current;
}

/* ── Helper: inject <base href> into HTML/PHP files ──
   Fixes internal links: login.php → /sitename/login.php ── */
function injectBaseHref($dir, $custom_url) {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $app_base = dirname(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $app_base = ($app_base === '.' || $app_base === '/') ? '' : '/' . trim($app_base, '/');
    $sites_root = realpath(__DIR__ . '/sites');
    $dir_real   = realpath($dir);
    $site_path  = $custom_url;
    if ($sites_root && $dir_real && strpos(str_replace('\\', '/', $dir_real), rtrim(str_replace('\\', '/', $sites_root), '/') . '/') === 0) {
        $site_path = ltrim(str_replace('\\', '/', substr($dir_real, strlen($sites_root))), '/');
    }

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        if (!$file->isFile()) continue;
        $ext = strtolower($file->getExtension());
        if (!in_array($ext, ['html', 'htm', 'php'])) continue;

        $content = file_get_contents($file->getRealPath());
        // Skip if base already exists
        if (stripos($content, '<base ') !== false) continue;

        // Use the directory of each file, so nested files resolve their own
        // CSS, JavaScript and image assets correctly.
        $relative = str_replace('\\', '/', substr($file->getRealPath(), strlen(realpath($dir)) + 1));
        $relative_dir = dirname($relative);
        $base = $protocol . '://' . $host . $app_base . '/' . trim($site_path, '/') . '/';
        if ($relative_dir !== '.' && $relative_dir !== '') {
            $base .= trim($relative_dir, '/') . '/';
        }

        // Inject right after <head> (or <head ...>)
        $tag = '<base href="' . htmlspecialchars($base, ENT_QUOTES, 'UTF-8') . '">';
        $new = preg_replace('/(<head[^>]*>)/i', '$1' . "\n    " . $tag, $content, 1, $count);
        if ($count > 0) {
            file_put_contents($file->getRealPath(), $new);
        } elseif (stripos($content, '<!doctype') !== false || stripos($content, '<html') !== false) {
            // Has HTML but no <head> — inject at top
            file_put_contents($file->getRealPath(), $tag . "\n" . $content);
        }
    }
}

function handleZipUpload($zip_tmp, $custom_url, $original_name = 'website.zip') {
    if (!class_exists('ZipArchive')) {
        return ['success' => false, 'message' => 'ZIP extraction is server par supported nahi.'];
    }
    $zip    = new ZipArchive();
    $result = ['success' => false, 'message' => ''];

    if ($zip->open($zip_tmp) === TRUE) {
        $extract_path = 'sites/' . $custom_url;
        $extracted_files = [];
        $zip_root_name = cleanCustomUrl(pathinfo($original_name, PATHINFO_FILENAME));
        $has_root_files = false;
        $top_level_dirs = [];

        // If the ZIP has files at its root (instead of a project folder),
        // create a folder from the ZIP name:
        // mariwebsite/hostedwebaite/<files>
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry_name = str_replace('\\', '/', ltrim($zip->getNameIndex($i), '/'));
            if ($entry_name === '' || substr($entry_name, -1) === '/') continue;
            $entry_parts = explode('/', $entry_name);
            if (count($entry_parts) === 1) {
                $has_root_files = true;
                break;
            }
            $top_level_dirs[$entry_parts[0]] = true;
        }
        if ($has_root_files && $zip_root_name !== '') {
            $extract_path .= '/' . $zip_root_name;
        }

        if (!is_dir($extract_path)) mkdir($extract_path, 0777, true);

        // ── Step 1: Extract everything as-is ──
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            if (substr($filename, -1) === '/') continue;

            $file_content = $zip->getFromIndex($i);
            if ($file_content === false) continue;

            $safe_rel = ltrim(str_replace('\\', '/', $filename), '/');
            $safe_rel = preg_replace('/\.\.\//', '', $safe_rel);

            $dest_path = $extract_path . '/' . $safe_rel;
            $dest_dir  = dirname($dest_path);

            if (!is_dir($dest_dir)) mkdir($dest_dir, 0777, true);
            file_put_contents($dest_path, $file_content);
            $extracted_files[] = $safe_rel;
        }
        $zip->close();

        if (!empty($extracted_files)) {
            /* ── Step 2: BASE HREF INJECTION ──
               Saari HTML/PHP files mein <base href="/sitename/">
               inject karo taake internal links sahi kaam karein.
               e.g. login.php → pro.infy.click/mariwebsite/login.php ── */
            injectBaseHref($extract_path, $custom_url);

            $result['success'] = true;
            $result['message'] = count($extracted_files) . ' files extract aur host ho gayin!';
        } else {
            if (is_dir($extract_path)) @rmdir($extract_path);
            $result['message'] = 'ZIP mein koi file nahi mili.';
        }
    } else {
        $result['message'] = 'ZIP file open nahi hui. File corrupt ho sakti hai.';
    }
    return $result;
}

function generateCustomUrl($filename) {
    $name = pathinfo($filename, PATHINFO_FILENAME);
    $name = preg_replace('/[^a-z0-9]/', '-', strtolower($name));
    $name = preg_replace('/-+/', '-', $name);
    return trim($name, '-') ?: 'team-zero';
}

function cleanCustomUrl($url) {
    $url = preg_replace('/[^a-z0-9-]/', '', strtolower($url));
    $url = preg_replace('/-+/', '-', $url);
    return trim($url, '-') ?: 'team-zero';
}

function cleanUploadFilename($filename) {
    $filename = basename(str_replace('\\', '/', (string) $filename));
    $filename = preg_replace('/[^A-Za-z0-9._-]/', '-', $filename);
    $filename = preg_replace('/-+/', '-', $filename);
    return trim($filename, '.-') ?: 'uploaded-file';
}

function getWebsiteUrl($path) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'];
    $script   = dirname($_SERVER['PHP_SELF']);
    $base     = rtrim($protocol . '://' . $host . $script, '/');
    $clean_path = trim(str_replace('\\', '/', $path), '/');

    // For ZIP sites, include the real inner web-root in the generated link.
    // Example: sites/mariwebsite/hostedwebaite/index.php becomes
    // /mariwebsite/hostedwebaite/ instead of only /mariwebsite/.
    $site_dir = __DIR__ . '/sites/' . $clean_path;
    if (is_dir($site_dir)) {
        $root = findSiteRoot($site_dir);
        $real_site = realpath($site_dir);
        $real_root = $root ? realpath($root) : false;
        if ($real_site && $real_root && $real_root !== $real_site &&
            strpos(str_replace('\\', '/', $real_root), rtrim(str_replace('\\', '/', $real_site), '/') . '/') === 0) {
            $inner = ltrim(str_replace('\\', '/', substr($real_root, strlen($real_site))), '/');
            if ($inner !== '') $clean_path .= '/' . $inner;
        }
    }

    // Clean URL — .htaccess routes this to view.php automatically
    return $base . '/' . $clean_path;
}

function getDirectorySize($path) {
    $total = 0;
    foreach (scandir($path) as $file) {
        if ($file === '.' || $file === '..') continue;
        $fp = $path . '/' . $file;
        $total += is_file($fp) ? filesize($fp) : getDirectorySize($fp);
    }
    return $total;
}

function formatSize($bytes) {
    if ($bytes == 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB'];
    $pow   = min(floor(log(max($bytes,1)) / log(1024)), count($units)-1);
    return round($bytes / pow(1024, $pow), 2) . ' ' . $units[$pow];
}

// ───────────────────────────────────────────────
// 4.  GENERATE HUB HTML
// ───────────────────────────────────────────────
function generateHubHtml($title, $powered) {
    $title_safe   = htmlspecialchars($title,  ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $powered_safe = htmlspecialchars($powered, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title_safe}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif}
        :root{--primary:#667eea;--secondary:#764ba2;--accent-hover:#5a6fd8;--text:#333;--text-secondary:#666;--success:#28a745;--card-bg:rgba(255,255,255,.95);--glow:0 10px 20px rgba(102,126,234,.3)}
        body{background:linear-gradient(135deg,var(--primary) 0%,var(--secondary) 100%);color:var(--text);min-height:100vh;background-attachment:fixed;overflow-x:hidden;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:20px}
        .container{max-width:1400px;margin:0 auto;padding:20px;width:100%}
        header{text-align:center;padding:30px 0 10px;position:relative}
        .logo{font-size:2.2rem;font-weight:700;margin-bottom:10px;color:#fff;text-shadow:2px 2px 4px rgba(0,0,0,.1)}
        .tagline{font-size:1.1rem;color:rgba(255,255,255,.8);margin-bottom:20px}
        .menu-tabs{display:flex;justify-content:center;margin:10px 0 30px;gap:10px;flex-wrap:wrap}
        .tab-btn{padding:12px 30px;background:rgba(255,255,255,.9);border:none;border-radius:50px;color:var(--text);font-size:1rem;font-weight:600;cursor:pointer;transition:all .3s;display:flex;align-items:center;gap:8px;box-shadow:0 5px 15px rgba(0,0,0,.1)}
        .tab-btn.active{background:#fff;color:var(--primary);box-shadow:var(--glow)}
        .tab-btn:hover:not(.active){transform:translateY(-2px);box-shadow:0 8px 20px rgba(0,0,0,.15)}
        .content-section{display:none;animation:fadeIn .5s ease}
        .content-section.active{display:block}
        @keyframes fadeIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
        .section-title{font-size:1.8rem;margin-bottom:20px;text-align:center;color:#fff;position:relative;padding-bottom:10px}
        .section-title::after{content:'';position:absolute;bottom:0;left:50%;transform:translateX(-50%);width:100px;height:3px;background:#fff;border-radius:3px}
        .cards-container{display:grid;grid-template-columns:repeat(auto-fill,minmax(350px,1fr));gap:20px;margin-top:30px}
        .card{background:var(--card-bg);border-radius:20px;padding:25px;transition:all .3s;backdrop-filter:blur(10px);position:relative;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,.1)}
        .card:hover{transform:translateY(-5px);box-shadow:var(--glow)}
        .card-title{font-size:1.2rem;margin-bottom:12px;color:var(--text);display:flex;align-items:center;gap:10px}
        .card-icon{font-size:1.3rem;color:var(--primary)}
        .card-description{color:var(--text-secondary);margin-bottom:15px;line-height:1.4;font-size:.9rem}
        .api-url{background:#f8f9fa;padding:12px;border-radius:10px;font-family:monospace;font-size:.8rem;margin-bottom:15px;word-break:break-all;border-left:3px solid var(--primary);max-height:60px;overflow-y:auto}
        .action-buttons{display:flex;gap:8px}
        .btn{padding:10px 15px;border:none;border-radius:50px;font-weight:600;cursor:pointer;transition:all .3s;display:flex;align-items:center;gap:6px;flex:1;justify-content:center;font-size:.85rem}
        .btn-primary{background:var(--primary);color:#fff}
        .btn-primary:hover{background:var(--accent-hover);transform:translateY(-2px);box-shadow:0 5px 15px rgba(102,126,234,.3)}
        .btn-secondary{background:rgba(0,0,0,.05);color:var(--text)}
        .btn-secondary:hover{background:rgba(0,0,0,.1);transform:translateY(-2px)}
        .download-link{display:inline-block;margin-top:12px;padding:12px 16px;background:var(--primary);color:#fff;text-decoration:none;border-radius:50px;font-weight:600;transition:all .3s;text-align:center;width:100%;font-size:.9rem}
        .download-link:hover{background:var(--accent-hover);transform:translateY(-2px);box-shadow:0 5px 15px rgba(102,126,234,.3)}
        footer{text-align:center;margin-top:30px;padding:20px;color:rgba(255,255,255,.8);font-size:.9rem}
        .notification{position:fixed;bottom:20px;right:20px;padding:15px 25px;background:var(--success);color:#fff;border-radius:10px;box-shadow:0 5px 15px rgba(0,0,0,.2);transform:translateY(100px);opacity:0;transition:all .3s;z-index:1000}
        .notification.show{transform:translateY(0);opacity:1}
        .back-btn{padding:12px 20px;background:rgba(255,255,255,.9);color:var(--text);border:none;border-radius:50px;cursor:pointer;margin-bottom:20px;display:flex;align-items:center;gap:8px;transition:all .3s;box-shadow:0 5px 15px rgba(0,0,0,.1)}
        .back-btn:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(0,0,0,.15)}
        .category-card{cursor:pointer}
        .api-card{cursor:default!important}
        @media(max-width:768px){.cards-container{grid-template-columns:1fr}.menu-tabs{flex-direction:column;align-items:center}.tab-btn{width:100%;max-width:300px;justify-content:center}.container{padding:10px}}
    </style>
</head>
<body>
    <div style="width:100%">
        <div class="container">
            <header>
                <h1 class="logo">{$title_safe}</h1>
                <p class="tagline">YOUR ONE-STOP DESTINATION FOR POWERFUL TOOLS AND RESOURCES</p>
            </header>

            <div class="menu-tabs">
                <button class="tab-btn active" data-tab="api"><i class="fas fa-code"></i> API</button>
                <button class="tab-btn" data-tab="source-code"><i class="fas fa-file-code"></i> SOURCE CODE</button>
            </div>

            <section id="api" class="content-section active">
                <h2 class="section-title">API COLLECTION</h2>
                <div class="cards-container">
                    <div class="card api-card"><h3 class="card-title"><i class="fab fa-telegram card-icon"></i> Telegram Story Downloader</h3><p class="card-description">Download stories from Telegram using this API.</p><div class="api-url">https://tgstory-down.apis-bj-devs.workers.dev/?url=t.me/username</div><div class="action-buttons"><button class="btn btn-primary copy-btn" data-url="https://tgstory-down.apis-bj-devs.workers.dev/?url=t.me/username"><i class="fas fa-copy"></i> COPY</button><button class="btn btn-secondary download-api-btn" data-url="https://tgstory-down.apis-bj-devs.workers.dev/?url=t.me/username" data-name="Telegram Story Downloader"><i class="fas fa-download"></i> DOWNLOAD</button></div></div>
                    <div class="card api-card"><h3 class="card-title"><i class="fab fa-spotify card-icon"></i> Spotify Downloader</h3><p class="card-description">Download music from Spotify using this API.</p><div class="api-url">https://spotify-down.apis-bj-devs.workers.dev/?url=https://open.spotify.com/track</div><div class="action-buttons"><button class="btn btn-primary copy-btn" data-url="https://spotify-down.apis-bj-devs.workers.dev/?url=https://open.spotify.com/track"><i class="fas fa-copy"></i> COPY</button><button class="btn btn-secondary download-api-btn" data-url="https://spotify-down.apis-bj-devs.workers.dev/?url=https://open.spotify.com/track" data-name="Spotify Downloader"><i class="fas fa-download"></i> DOWNLOAD</button></div></div>
                    <div class="card api-card"><h3 class="card-title"><i class="fab fa-tiktok card-icon"></i> TikTok Downloader</h3><p class="card-description">Download videos from TikTok using this API.</p><div class="api-url">https://tikwm.com/api/?url=</div><div class="action-buttons"><button class="btn btn-primary copy-btn" data-url="https://tikwm.com/api/?url="><i class="fas fa-copy"></i> COPY</button><button class="btn btn-secondary download-api-btn" data-url="https://tikwm.com/api/?url=" data-name="TikTok Downloader"><i class="fas fa-download"></i> DOWNLOAD</button></div></div>
                    <div class="card api-card"><h3 class="card-title"><i class="fas fa-robot card-icon"></i> Qwen AI API</h3><p class="card-description">AI chat API using Qwen model.</p><div class="api-url">https://qwen-ai.apis-bj-devs.workers.dev/?text=hello</div><div class="action-buttons"><button class="btn btn-primary copy-btn" data-url="https://qwen-ai.apis-bj-devs.workers.dev/?text=hello"><i class="fas fa-copy"></i> COPY</button><button class="btn btn-secondary download-api-btn" data-url="https://qwen-ai.apis-bj-devs.workers.dev/?text=hello" data-name="Qwen AI API"><i class="fas fa-download"></i> DOWNLOAD</button></div></div>
                    <div class="card api-card"><h3 class="card-title"><i class="fas fa-brain card-icon"></i> Gemini API</h3><p class="card-description">Google Gemini AI chat API.</p><div class="api-url">https://gemini-1-5-flash.bjcoderx.workers.dev/?text=hello</div><div class="action-buttons"><button class="btn btn-primary copy-btn" data-url="https://gemini-1-5-flash.bjcoderx.workers.dev/?text=hello"><i class="fas fa-copy"></i> COPY</button><button class="btn btn-secondary download-api-btn" data-url="https://gemini-1-5-flash.bjcoderx.workers.dev/?text=hello" data-name="Gemini API"><i class="fas fa-download"></i> DOWNLOAD</button></div></div>
                    <div class="card api-card"><h3 class="card-title"><i class="fas fa-comment card-icon"></i> GPT-3.5 API</h3><p class="card-description">OpenAI GPT-3.5 chat API.</p><div class="api-url">https://gpt-3-5.apis-bj-devs.workers.dev/?prompt=hello</div><div class="action-buttons"><button class="btn btn-primary copy-btn" data-url="https://gpt-3-5.apis-bj-devs.workers.dev/?prompt=hello"><i class="fas fa-copy"></i> COPY</button><button class="btn btn-secondary download-api-btn" data-url="https://gpt-3-5.apis-bj-devs.workers.dev/?prompt=hello" data-name="GPT-3.5 API"><i class="fas fa-download"></i> DOWNLOAD</button></div></div>
                    <div class="card api-card"><h3 class="card-title"><i class="fas fa-palette card-icon"></i> SeaArt AI API</h3><p class="card-description">AI image generation API.</p><div class="api-url">https://seaart-ai.apis-bj-devs.workers.dev/?Prompt=a+cute+boy</div><div class="action-buttons"><button class="btn btn-primary copy-btn" data-url="https://seaart-ai.apis-bj-devs.workers.dev/?Prompt=a+cute+boy"><i class="fas fa-copy"></i> COPY</button><button class="btn btn-secondary download-api-btn" data-url="https://seaart-ai.apis-bj-devs.workers.dev/?Prompt=a+cute+boy" data-name="SeaArt AI API"><i class="fas fa-download"></i> DOWNLOAD</button></div></div>
                    <div class="card api-card"><h3 class="card-title"><i class="fas fa-image card-icon"></i> Text to Image API</h3><p class="card-description">Convert text prompts to images.</p><div class="api-url">https://text-to-img.apis-bj-devs.workers.dev/?prompt=cute+girl</div><div class="action-buttons"><button class="btn btn-primary copy-btn" data-url="https://text-to-img.apis-bj-devs.workers.dev/?prompt=cute+girl"><i class="fas fa-copy"></i> COPY</button><button class="btn btn-secondary download-api-btn" data-url="https://text-to-img.apis-bj-devs.workers.dev/?prompt=cute+girl" data-name="Text to Image API"><i class="fas fa-download"></i> DOWNLOAD</button></div></div>
                    <div class="card api-card"><h3 class="card-title"><i class="fas fa-code card-icon"></i> DeepSeek AI API</h3><p class="card-description">DeepSeek AI chat API.</p><div class="api-url">https://deepseek-ai.apis-bj-devs.workers.dev/?text=hello</div><div class="action-buttons"><button class="btn btn-primary copy-btn" data-url="https://deepseek-ai.apis-bj-devs.workers.dev/?text=hello"><i class="fas fa-copy"></i> COPY</button><button class="btn btn-secondary download-api-btn" data-url="https://deepseek-ai.apis-bj-devs.workers.dev/?text=hello" data-name="DeepSeek AI API"><i class="fas fa-download"></i> DOWNLOAD</button></div></div>
                    <div class="card api-card"><h3 class="card-title"><i class="fas fa-magic card-icon"></i> Diffusion AI API</h3><p class="card-description">AI image diffusion model API.</p><div class="api-url">https://diffusion-ai.bjcoderx.workers.dev/?prompt=a+cute+baby</div><div class="action-buttons"><button class="btn btn-primary copy-btn" data-url="https://diffusion-ai.bjcoderx.workers.dev/?prompt=a+cute+baby"><i class="fas fa-copy"></i> COPY</button><button class="btn btn-secondary download-api-btn" data-url="https://diffusion-ai.bjcoderx.workers.dev/?prompt=a+cute+baby" data-name="Diffusion AI API"><i class="fas fa-download"></i> DOWNLOAD</button></div></div>
                    <div class="card api-card"><h3 class="card-title"><i class="fas fa-mobile card-icon"></i> APK Search</h3><p class="card-description">Search and download APK files.</p><div class="api-url">https://apk-downloader.bjcoderx.workers.dev/?query=telegram</div><div class="action-buttons"><button class="btn btn-primary copy-btn" data-url="https://apk-downloader.bjcoderx.workers.dev/?query=telegram"><i class="fas fa-copy"></i> COPY</button><button class="btn btn-secondary download-api-btn" data-url="https://apk-downloader.bjcoderx.workers.dev/?query=telegram" data-name="APK Search"><i class="fas fa-download"></i> DOWNLOAD</button></div></div>
                    <div class="card api-card"><h3 class="card-title"><i class="fab fa-pinterest card-icon"></i> Pinterest Search</h3><p class="card-description">Search images on Pinterest.</p><div class="api-url">https://pinterest-search.apis-bj-devs.workers.dev/?search=Anime&limit=5</div><div class="action-buttons"><button class="btn btn-primary copy-btn" data-url="https://pinterest-search.apis-bj-devs.workers.dev/?search=Anime&limit=5"><i class="fas fa-copy"></i> COPY</button><button class="btn btn-secondary download-api-btn" data-url="https://pinterest-search.apis-bj-devs.workers.dev/?search=Anime&limit=5" data-name="Pinterest Search"><i class="fas fa-download"></i> DOWNLOAD</button></div></div>
                    <div class="card api-card"><h3 class="card-title"><i class="fab fa-microsoft card-icon"></i> Bing Search</h3><p class="card-description">Search using Bing search engine.</p><div class="api-url">https://bing-search.apis-bj-devs.workers.dev/?search=cats&limit=2</div><div class="action-buttons"><button class="btn btn-primary copy-btn" data-url="https://bing-search.apis-bj-devs.workers.dev/?search=cats&limit=2"><i class="fas fa-copy"></i> COPY</button><button class="btn btn-secondary download-api-btn" data-url="https://bing-search.apis-bj-devs.workers.dev/?search=cats&limit=2" data-name="Bing Search"><i class="fas fa-download"></i> DOWNLOAD</button></div></div>
                    <div class="card api-card"><h3 class="card-title"><i class="fab fa-google card-icon"></i> Google Search</h3><p class="card-description">Search using Google search engine.</p><div class="api-url">https://google-search.bjcoderx.workers.dev/?q=cats</div><div class="action-buttons"><button class="btn btn-primary copy-btn" data-url="https://google-search.bjcoderx.workers.dev/?q=cats"><i class="fas fa-copy"></i> COPY</button><button class="btn btn-secondary download-api-btn" data-url="https://google-search.bjcoderx.workers.dev/?q=cats" data-name="Google Search"><i class="fas fa-download"></i> DOWNLOAD</button></div></div>
                    <div class="card api-card"><h3 class="card-title"><i class="fas fa-link card-icon"></i> URL to APK</h3><p class="card-description">Convert URLs to APK files.</p><div class="api-url">https://url-to-apk-convert.bjcoderx.workers.dev/create?name=apihub&link=https://bj-bot-hosting.vercel.app/</div><div class="action-buttons"><button class="btn btn-primary copy-btn" data-url="https://url-to-apk-convert.bjcoderx.workers.dev/create?name=apihub&link=https://bj-bot-hosting.vercel.app/"><i class="fas fa-copy"></i> COPY</button><button class="btn btn-secondary download-api-btn" data-url="https://url-to-apk-convert.bjcoderx.workers.dev/create?name=apihub&link=https://bj-bot-hosting.vercel.app/" data-name="URL to APK"><i class="fas fa-download"></i> DOWNLOAD</button></div></div>
                    <div class="card api-card"><h3 class="card-title"><i class="fas fa-globe card-icon"></i> Country Information</h3><p class="card-description">Get information about countries.</p><div class="api-url">https://countrys-information.apis-bj-devs.workers.dev/?name=India</div><div class="action-buttons"><button class="btn btn-primary copy-btn" data-url="https://countrys-information.apis-bj-devs.workers.dev/?name=India"><i class="fas fa-copy"></i> COPY</button><button class="btn btn-secondary download-api-btn" data-url="https://countrys-information.apis-bj-devs.workers.dev/?name=India" data-name="Country Information"><i class="fas fa-download"></i> DOWNLOAD</button></div></div>
                    <div class="card api-card"><h3 class="card-title"><i class="fas fa-language card-icon"></i> Translator</h3><p class="card-description">Translate text between languages.</p><div class="api-url">https://translator.bjcoderx.workers.dev/?text=Hellobrother&fr=en&to=ur</div><div class="action-buttons"><button class="btn btn-primary copy-btn" data-url="https://translator.bjcoderx.workers.dev/?text=Hellobrother&fr=en&to=ur"><i class="fas fa-copy"></i> COPY</button><button class="btn btn-secondary download-api-btn" data-url="https://translator.bjcoderx.workers.dev/?text=Hellobrother&fr=en&to=ur" data-name="Translator"><i class="fas fa-download"></i> DOWNLOAD</button></div></div>
                    <div class="card api-card"><h3 class="card-title"><i class="fas fa-network-wired card-icon"></i> IP Information</h3><p class="card-description">Get information about IP addresses.</p><div class="api-url">https://ip-info.bjcoderx.workers.dev/?ip=149.154.167.91</div><div class="action-buttons"><button class="btn btn-primary copy-btn" data-url="https://ip-info.bjcoderx.workers.dev/?ip=149.154.167.91"><i class="fas fa-copy"></i> COPY</button><button class="btn btn-secondary download-api-btn" data-url="https://ip-info.bjcoderx.workers.dev/?ip=149.154.167.91" data-name="IP Information"><i class="fas fa-download"></i> DOWNLOAD</button></div></div>
                    <div class="card api-card"><h3 class="card-title"><i class="fas fa-money-bill-wave card-icon"></i> Exchange Rate</h3><p class="card-description">Get real-time currency exchange rates.</p><div class="api-url">https://real-time-global-exchange-rates.bjcoderx.workers.dev/?From=USD&Amount=10&To=PKR</div><div class="action-buttons"><button class="btn btn-primary copy-btn" data-url="https://real-time-global-exchange-rates.bjcoderx.workers.dev/?From=USD&Amount=10&To=PKR"><i class="fas fa-copy"></i> COPY</button><button class="btn btn-secondary download-api-btn" data-url="https://real-time-global-exchange-rates.bjcoderx.workers.dev/?From=USD&Amount=10&To=PKR" data-name="Exchange Rate"><i class="fas fa-download"></i> DOWNLOAD</button></div></div>
                    <div class="card api-card"><h3 class="card-title"><i class="fas fa-flag card-icon"></i> Nation Information</h3><p class="card-description">Get information about nations.</p><div class="api-url">https://nation-info.apis-bj-devs.workers.dev/?name=Pakistan</div><div class="action-buttons"><button class="btn btn-primary copy-btn" data-url="https://nation-info.apis-bj-devs.workers.dev/?name=Pakistan"><i class="fas fa-copy"></i> COPY</button><button class="btn btn-secondary download-api-btn" data-url="https://nation-info.apis-bj-devs.workers.dev/?name=Pakistan" data-name="Nation Information"><i class="fas fa-download"></i> DOWNLOAD</button></div></div>
                    <div class="card api-card"><h3 class="card-title"><i class="fas fa-image card-icon"></i> Image Enhance API</h3><p class="card-description">Enhance and improve image quality.</p><div class="api-url">https://image-enhance.apis-bj-devs.workers.dev/?imageurl=</div><div class="action-buttons"><button class="btn btn-primary copy-btn" data-url="https://image-enhance.apis-bj-devs.workers.dev/?imageurl="><i class="fas fa-copy"></i> COPY</button><button class="btn btn-secondary download-api-btn" data-url="https://image-enhance.apis-bj-devs.workers.dev/?imageurl=" data-name="Image Enhance API"><i class="fas fa-download"></i> DOWNLOAD</button></div></div>
                </div>
            </section>

            <section id="source-code" class="content-section">
                <h2 class="section-title">SOURCE CODE COLLECTION</h2>
                <div class="cards-container">
                    <div class="card category-card" data-category="html-website"><h3 class="card-title"><i class="fas fa-code card-icon"></i> HTML WEBSITE CODE</h3><p class="card-description">BEAUTIFUL AND RESPONSIVE HTML WEBSITE TEMPLATES</p><div class="action-buttons"><button class="btn btn-primary"><i class="fas fa-arrow-right"></i> VIEW OPTIONS</button></div></div>
                    <div class="card category-card" data-category="telegram-bot"><h3 class="card-title"><i class="fab fa-telegram card-icon"></i> TELEGRAM BOT CODE</h3><p class="card-description">COMPLETE SOURCE CODE FOR VARIOUS TELEGRAM BOTS</p><div class="action-buttons"><button class="btn btn-primary"><i class="fas fa-arrow-right"></i> VIEW OPTIONS</button></div></div>
                    <div class="card category-card" data-category="termux-tools"><h3 class="card-title"><i class="fas fa-terminal card-icon"></i> TERMUX TOOLS CODE</h3><p class="card-description">USEFUL TOOLS AND SCRIPTS FOR TERMUX ENVIRONMENT</p><div class="action-buttons"><button class="btn btn-primary"><i class="fas fa-arrow-right"></i> VIEW OPTIONS</button></div></div>
                </div>
            </section>

            <section id="html-website-subcategories" class="content-section">
                <button class="back-btn" id="back-to-source-code"><i class="fas fa-arrow-left"></i> BACK TO SOURCE CODE</button>
                <h2 class="section-title">HTML WEBSITE SOURCE CODE</h2>
                <div class="cards-container">
                    <div class="card"><h3 class="card-title"><i class="fas fa-shield-alt card-icon"></i> PRO VPN</h3><p class="card-description">Professional VPN service HTML source code.</p><a href="https://www.mediafire.com/file/vjb91nrbelpxd68/ownvpnprobyteamzero.html/file" class="download-link" target="_blank"><i class="fas fa-download"></i> DOWNLOAD SOURCE CODE</a></div>
                    <div class="card"><h3 class="card-title"><i class="fas fa-laptop-code card-icon"></i> HTML EDITOR</h3><p class="card-description">Advanced HTML Editor tool source code.</p><a href="https://www.mediafire.com/file/s4vpn2nkl1ahh6f/htmlediterbyteamzero.html/file" class="download-link" target="_blank"><i class="fas fa-download"></i> DOWNLOAD SOURCE CODE</a></div>
                    <div class="card"><h3 class="card-title"><i class="fas fa-volleyball-ball card-icon"></i> HD SPORTS</h3><p class="card-description">HD Sports streaming layout source code.</p><a href="https://www.mediafire.com/file/pywvxaqer1l2wsj/hdsportsbyteamzero.html/file" class="download-link" target="_blank"><i class="fas fa-download"></i> DOWNLOAD SOURCE CODE</a></div>
                    <div class="card"><h3 class="card-title"><i class="fas fa-shopping-cart card-icon"></i> QUICK MART</h3><p class="card-description">Quick Mart eCommerce platform source code.</p><a href="https://www.mediafire.com/file/hbzqnvsb3tkgka8/quickmartbyteamzero.html/file" class="download-link" target="_blank"><i class="fas fa-download"></i> DOWNLOAD SOURCE CODE</a></div>
                    <div class="card"><h3 class="card-title"><i class="fas fa-server card-icon"></i> SIM DB</h3><p class="card-description">Sim Database search tool source code.</p><a href="https://www.mediafire.com/file/l9b3fb6vcw5e7e4/simdbbyteamzero.html/file" class="download-link" target="_blank"><i class="fas fa-download"></i> DOWNLOAD SOURCE CODE</a></div>
                    <div class="card"><h3 class="card-title"><i class="fas fa-tools card-icon"></i> DIGITAL TOOLKIT</h3><p class="card-description">Comprehensive Digital Toolkit source code.</p><a href="https://www.mediafire.com/file/ofzg0ic4v1ubur4/digitaltoolkitbyteamzero.html/file" class="download-link" target="_blank"><i class="fas fa-download"></i> DOWNLOAD SOURCE CODE</a></div>
                    <div class="card"><h3 class="card-title"><i class="fas fa-wrench card-icon"></i> TOOLKIT PRO</h3><p class="card-description">Advanced Toolkit Pro version source code.</p><a href="https://www.mediafire.com/file/thp2gdnq1i2r5cg/toolkitprobyteamzero.html/file" class="download-link" target="_blank"><i class="fas fa-download"></i> DOWNLOAD SOURCE CODE</a></div>
                    <div class="card"><h3 class="card-title"><i class="fas fa-image card-icon"></i> HD WALLPAPER</h3><p class="card-description">HD Wallpaper gallery website source code.</p><a href="https://www.mediafire.com/file/7lttcapwslnuzwv/wallpaperbyteamzero.html/file" class="download-link" target="_blank"><i class="fas fa-download"></i> DOWNLOAD SOURCE CODE</a></div>
                    <div class="card"><h3 class="card-title"><i class="fas fa-chart-line card-icon"></i> 92GLORY PREDICTION</h3><p class="card-description">Prediction tool source code for gaming platforms.</p><a href="https://www.mediafire.com/file/dfu8vmpxu5du93w/92GLORY_PREDICTION_SOURCE_CODE.zip/file" class="download-link" target="_blank"><i class="fas fa-download"></i> DOWNLOAD SOURCE CODE</a></div>
                    <div class="card"><h3 class="card-title"><i class="fas fa-download card-icon"></i> APK DOWNLOAD</h3><p class="card-description">APK download website source code.</p><a href="https://www.mediafire.com/file/d3p5i2mgsluqvlk/APK_DOWNLOAD_SOURCE_CODE.zip/file" class="download-link" target="_blank"><i class="fas fa-download"></i> DOWNLOAD SOURCE CODE</a></div>
                    <div class="card"><h3 class="card-title"><i class="fas fa-dice card-icon"></i> BIG SMALL PREDICTION</h3><p class="card-description">Big small prediction game tool source code.</p><a href="https://www.mediafire.com/file/ngqvc212iiz2oun/big-small-prediction-tool_SOURCE_CODE.zip/file" class="download-link" target="_blank"><i class="fas fa-download"></i> DOWNLOAD SOURCE CODE</a></div>
                    <div class="card"><h3 class="card-title"><i class="fas fa-key card-icon"></i> CODE GENERATOR</h3><p class="card-description">Code generator tool source code.</p><a href="https://www.mediafire.com/file/3f5wou99y7rkex9/CODE_GENERATER_SOURCE_CODE.zip/file" class="download-link" target="_blank"><i class="fas fa-download"></i> DOWNLOAD SOURCE CODE</a></div>
                    <div class="card"><h3 class="card-title"><i class="fas fa-edit card-icon"></i> HTML CODE EDITOR</h3><p class="card-description">Online HTML code editor source code.</p><a href="https://www.mediafire.com/file/okxbrc2j348ytr1/HTML_CODE_EDITOR_SOURCE_CODE.zip/file" class="download-link" target="_blank"><i class="fas fa-download"></i> DOWNLOAD SOURCE CODE</a></div>
                    <div class="card"><h3 class="card-title"><i class="fas fa-lock card-icon"></i> HTML ENCRYPTOR TOOL</h3><p class="card-description">HTML code encryption tool source code.</p><a href="https://www.mediafire.com/file/qy6qhgyzt0urksn/HTML_ENCRYPTOR_TOOL_SOURCE_CODE.zip/file" class="download-link" target="_blank"><i class="fas fa-download"></i> DOWNLOAD SOURCE CODE</a></div>
                    <div class="card"><h3 class="card-title"><i class="fas fa-redo card-icon"></i> LUCKY WHEEL</h3><p class="card-description">Lucky wheel game source code.</p><a href="https://www.mediafire.com/file/efltpcr0k9tvi00/LUCKY_WHEEL__SOURCE_CODE.zip/file" class="download-link" target="_blank"><i class="fas fa-download"></i> DOWNLOAD SOURCE CODE</a></div>
                    <div class="card"><h3 class="card-title"><i class="fas fa-database card-icon"></i> SIM DATABASE SECURE</h3><p class="card-description">Secure SIM database website source code.</p><a href="https://www.mediafire.com/file/jjnfpz8bp4um7df/SIM_DATABASE_SECURE_CODE.zip/file" class="download-link" target="_blank"><i class="fas fa-download"></i> DOWNLOAD SOURCE CODE</a></div>
                    <div class="card"><h3 class="card-title"><i class="fas fa-bomb card-icon"></i> SMS BOMBER</h3><p class="card-description">SMS bomber tool website source code.</p><a href="https://www.mediafire.com/file/w0xzuutd49s70hh/SMS_BOMBER_SOURCE_CODE.zip/file" class="download-link" target="_blank"><i class="fas fa-download"></i> DOWNLOAD SOURCE CODE</a></div>
                    <div class="card"><h3 class="card-title"><i class="fas fa-share-alt card-icon"></i> SOCIAL MEDIA DOWNLOADER</h3><p class="card-description">Social media video downloader source code.</p><a href="https://www.mediafire.com/file/qy0yd6wahaxiv37/SOCIAL_MEDIA_DOWNLOADER_SOURCE_CODE.zip/file" class="download-link" target="_blank"><i class="fas fa-download"></i> DOWNLOAD SOURCE CODE</a></div>
                    <div class="card"><h3 class="card-title"><i class="fas fa-water card-icon"></i> WATERMARK TOOL</h3><p class="card-description">Image watermark tool source code.</p><a href="https://www.mediafire.com/file/n4e1cbgx3g809xl/WATERMARK_TOOL_SOURCE_CODE.zip/file" class="download-link" target="_blank"><i class="fas fa-download"></i> DOWNLOAD SOURCE CODE</a></div>
                    <div class="card"><h3 class="card-title"><i class="fas fa-copy card-icon"></i> WEBSITE COPY TOOL</h3><p class="card-description">Website content copying tool source code.</p><a href="https://www.mediafire.com/file/6d4tr3987jfg05d/WEBSITE_COPY_TOOL.zip/file" class="download-link" target="_blank"><i class="fas fa-download"></i> DOWNLOAD SOURCE CODE</a></div>
                    <div class="card"><h3 class="card-title"><i class="fab fa-whatsapp card-icon"></i> WHATSAPP BAN TOOL</h3><p class="card-description">WhatsApp ban tool source code.</p><a href="https://www.mediafire.com/file/a1fc18t5jg6e5ee/WHATSAPP_BAN_TOOL.zip/file" class="download-link" target="_blank"><i class="fas fa-download"></i> DOWNLOAD SOURCE CODE</a></div>
                    <div class="card"><h3 class="card-title"><i class="fab fa-youtube card-icon"></i> YOUTUBE UNLIMITED WATCH TIME</h3><p class="card-description">YouTube watch time tool source code.</p><a href="https://www.mediafire.com/file/am7qk45dmv94vue/YOUTUBE_UNLIMITED_WATCH_TIME_.zip/file" class="download-link" target="_blank"><i class="fas fa-download"></i> DOWNLOAD SOURCE CODE</a></div>
                    <div class="card"><h3 class="card-title"><i class="fas fa-gamepad card-icon"></i> ZOO RULAT TOOL</h3><p class="card-description">Zoo Rulat game tool source code.</p><a href="https://www.mediafire.com/file/34gmgb47yxxvbpz/ZOO_RULAT_TOOL.zip/file" class="download-link" target="_blank"><i class="fas fa-download"></i> DOWNLOAD SOURCE CODE</a></div>
                </div>
            </section>

            <section id="telegram-bot-subcategories" class="content-section">
                <button class="back-btn" id="back-to-source-code-2"><i class="fas fa-arrow-left"></i> BACK TO SOURCE CODE</button>
                <h2 class="section-title">TELEGRAM BOT SOURCE CODE</h2>
                <div class="cards-container">
                    <div class="card"><h3 class="card-title"><i class="fas fa-dice card-icon"></i> ALL GAMES PREDICTION</h3><p class="card-description">Telegram bot for all games prediction.</p><a href="https://www.mediafire.com/file/11iztvs5g7o1sa0/ALL_GAMES_PREDATION.zip/file" class="download-link" target="_blank"><i class="fas fa-download"></i> DOWNLOAD SOURCE CODE</a></div>
                    <div class="card"><h3 class="card-title"><i class="fas fa-image card-icon"></i> IMAGE TO LINK BOT</h3><p class="card-description">Telegram bot for converting images to links.</p><a href="https://www.mediafire.com/file/7wbn8cd7sd982oz/IMAGE_TO_LINK_BOT_SOURCE_CODE.zip/file" class="download-link" target="_blank"><i class="fas fa-download"></i> DOWNLOAD SOURCE CODE</a></div>
                    <div class="card"><h3 class="card-title"><i class="fas fa-tasks card-icon"></i> MULTI-TASKING BOT</h3><p class="card-description">Multi-functional Telegram bot source code.</p><a href="https://www.mediafire.com/file/oqv63d6ro2w9rtb/Multi-Tasking-Bot-main.zip/file" class="download-link" target="_blank"><i class="fas fa-download"></i> DOWNLOAD SOURCE CODE</a></div>
                    <div class="card"><h3 class="card-title"><i class="fas fa-database card-icon"></i> SIM DATABASE BOT</h3><p class="card-description">Telegram bot for SIM database queries.</p><a href="https://www.mediafire.com/file/41hj226hd9lp2l6/SIM_DATABASE_.zip/file" class="download-link" target="_blank"><i class="fas fa-download"></i> DOWNLOAD SOURCE CODE</a></div>
                    <div class="card"><h3 class="card-title"><i class="fas fa-envelope card-icon"></i> SMS EMAIL BOMBER BOT</h3><p class="card-description">Telegram bot for SMS and email bombing.</p><a href="https://www.mediafire.com/file/g9stuuxk4xe60b1/SMS_EMAIL_BOMBER_BOT_.zip/file" class="download-link" target="_blank"><i class="fas fa-download"></i> DOWNLOAD SOURCE CODE</a></div>
                    <div class="card"><h3 class="card-title"><i class="fas fa-box card-icon"></i> TANISHA PKG</h3><p class="card-description">Tanisha package Telegram bot source code.</p><a href="https://www.mediafire.com/file/2g1rbg9jzj7h3gx/TANISHA_PKG_.zip/file" class="download-link" target="_blank"><i class="fas fa-download"></i> DOWNLOAD SOURCE CODE</a></div>
                    <div class="card"><h3 class="card-title"><i class="fas fa-shield-alt card-icon"></i> TEAM LEGENDS HTML PROTECTOR</h3><p class="card-description">HTML protection Telegram bot source code.</p><a href="https://www.mediafire.com/file/gl207j5f17qsfb1/TEAM_LEGENDS_HTML_PROTECTOR.zip/file" class="download-link" target="_blank"><i class="fas fa-download"></i> DOWNLOAD SOURCE CODE</a></div>
                    <div class="card"><h3 class="card-title"><i class="fas fa-comment card-icon"></i> TELEGRAM PROMPT BOT</h3><p class="card-description">AI prompt based Telegram bot source code.</p><a href="https://www.mediafire.com/file/9be49dav2l1ceu1/Telegram_Prompt_Bot.zip/file" class="download-link" target="_blank"><i class="fas fa-download"></i> DOWNLOAD SOURCE CODE</a></div>
                </div>
            </section>

            <section id="termux-tools-subcategories" class="content-section">
                <button class="back-btn" id="back-to-source-code-3"><i class="fas fa-arrow-left"></i> BACK TO SOURCE CODE</button>
                <h2 class="section-title">TERMUX TOOLS SOURCE CODE</h2>
                <div class="cards-container">
                    <div class="card"><h3 class="card-title"><i class="fas fa-bug card-icon"></i> API CRASHER</h3><p class="card-description">API testing and crashing tool for Termux.</p><a href="https://www.mediafire.com/file/27vzc29bwebq4zy/API_CRASHER.zip/file" class="download-link" target="_blank"><i class="fas fa-download"></i> DOWNLOAD SOURCE CODE</a></div>
                    <div class="card"><h3 class="card-title"><i class="fas fa-file card-icon"></i> FILE VIEWER</h3><p class="card-description">Advanced file viewer tool for Termux.</p><a href="https://www.mediafire.com/file/wthvrdrpxsr2sjh/FILE_VIEWER_.zip/file" class="download-link" target="_blank"><i class="fas fa-download"></i> DOWNLOAD SOURCE CODE</a></div>
                    <div class="card"><h3 class="card-title"><i class="fas fa-copy card-icon"></i> HTML + CSS + JS COPY TOOL</h3><p class="card-description">Website code copying tool for Termux.</p><a href="https://www.mediafire.com/file/kqjchfib84je0ao/HTML_%252B_CSS_%252B_JS_COPY_TOOL.zip/file" class="download-link" target="_blank"><i class="fas fa-download"></i> DOWNLOAD SOURCE CODE</a></div>
                    <div class="card"><h3 class="card-title"><i class="fas fa-share-alt card-icon"></i> PAIR SPAMMING</h3><p class="card-description">Pair spamming tool for Termux.</p><a href="https://www.mediafire.com/file/qcjyzoaiwrp6801/PAIR_SPAMMING_.zip/file" class="download-link" target="_blank"><i class="fas fa-download"></i> DOWNLOAD SOURCE CODE</a></div>
                    <div class="card"><h3 class="card-title"><i class="fas fa-user-shield card-icon"></i> SAFEUM UNLIMITED ACCOUNT</h3><p class="card-description">Safeum account tool for Termux.</p><a href="https://www.mediafire.com/file/fwsxaq2wzgne6po/SAFEUM_UNLIMITED_ACCOUNT_.zip/file" class="download-link" target="_blank"><i class="fas fa-download"></i> DOWNLOAD SOURCE CODE</a></div>
                    <div class="card"><h3 class="card-title"><i class="fas fa-sms card-icon"></i> SMS BOMBER</h3><p class="card-description">SMS bomber tool for Termux.</p><a href="https://www.mediafire.com/file/slb6uc9adez8xy2/SMS_BOMBER_.zip/file" class="download-link" target="_blank"><i class="fas fa-download"></i> DOWNLOAD SOURCE CODE</a></div>
                    <div class="card"><h3 class="card-title"><i class="fas fa-search card-icon"></i> WEBSITE API SNIFFER TOOL</h3><p class="card-description">Website API sniffer tool for Termux.</p><a href="https://www.mediafire.com/file/nmmzrgt1w919qlx/WEBSITE_API_SNIFFER_TOOL.zip/file" class="download-link" target="_blank"><i class="fas fa-download"></i> DOWNLOAD SOURCE CODE</a></div>
                </div>
            </section>

            <footer>
                <p>&copy; 2026 ALL RIGHTS RESERVED | POWERED BY {$powered_safe}</p>
            </footer>
        </div>
    </div>

    <div class="notification" id="notification">URL copied to clipboard!</div>

    <script>
        document.querySelectorAll('.tab-btn[data-tab]').forEach(button => {
            button.addEventListener('click', () => {
                document.querySelectorAll('.tab-btn[data-tab]').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.content-section').forEach(s => s.classList.remove('active'));
                button.classList.add('active');
                document.getElementById(button.dataset.tab).classList.add('active');
            });
        });
        document.querySelectorAll('.category-card').forEach(card => {
            card.addEventListener('click', () => {
                document.querySelectorAll('.content-section').forEach(s => s.classList.remove('active'));
                document.getElementById(card.dataset.category + '-subcategories').classList.add('active');
            });
        });
        ['back-to-source-code','back-to-source-code-2','back-to-source-code-3'].forEach(id => {
            document.getElementById(id).addEventListener('click', () => {
                document.querySelectorAll('.content-section').forEach(s => s.classList.remove('active'));
                document.getElementById('source-code').classList.add('active');
            });
        });
        document.querySelectorAll('.copy-btn').forEach(button => {
            button.addEventListener('click', e => {
                e.stopPropagation();
                navigator.clipboard.writeText(button.dataset.url).then(() => showNotification('API URL copied!'));
            });
        });
        document.querySelectorAll('.download-api-btn').forEach(button => {
            button.addEventListener('click', e => {
                e.stopPropagation();
                const blob = new Blob([button.dataset.url], {type:'text/plain'});
                const a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = button.dataset.name.replace(/\s+/g,'_') + '.txt';
                document.body.appendChild(a); a.click();
                document.body.removeChild(a); URL.revokeObjectURL(a.href);
                showNotification('Downloading ' + button.dataset.name + '...');
            });
        });
        function showNotification(msg){
            const n = document.getElementById('notification');
            n.textContent = msg; n.classList.add('show');
            setTimeout(() => n.classList.remove('show'), 3000);
        }
    </script>
</body>
</html>
HTML;
}

// ───────────────────────────────────────────────
// 5.  LOAD EXISTING SITES FOR DISPLAY
// ───────────────────────────────────────────────
$uploaded_sites = [];
$total_size     = 0;

if (is_dir('sites')) {
    $files = scandir('sites');
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $file_path = 'sites/' . $file;

        if (is_dir($file_path)) {
            // FIX: PHP aur tamam file types ko bhi count karo directory mein
            $any_files = [];
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($file_path));
            foreach ($it as $f) {
                if ($f->isFile()) {
                    $any_files[] = $f->getRealPath();
                    break;
                }
            }
            if (!empty($any_files)) {
                $site_size = getDirectorySize($file_path);
                $uploaded_sites[] = [
                    'name'     => $file,
                    'filename' => $file,
                    'url'      => getWebsiteUrl($file),
                    'time'     => filemtime($file_path),
                    'size'     => $site_size,
                    'type'     => 'zip',
                    'is_dir'   => true,
                ];
                $total_size += $site_size;
            }
        } else {
            $file_ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            // FIX: PHP + tamam code files show karo
            $show_types = ['html','htm','css','js','php','py','rb','go','rs','java','ts','tsx','jsx','vue','svelte','md','txt','sql'];
            if (in_array($file_ext, $show_types)) {
                $site_name = pathinfo($file, PATHINFO_FILENAME);
                $site_size = filesize($file_path);
                $uploaded_sites[] = [
                    'name'     => $site_name,
                    'filename' => $file,
                    'url'      => getWebsiteUrl($site_name),
                    'time'     => filemtime($file_path),
                    'size'     => $site_size,
                    'type'     => $file_ext,
                    'is_dir'   => false,
                ];
                $total_size += $site_size;
            }
        }
    }
    usort($uploaded_sites, fn($a, $b) => $b['time'] - $a['time']);
}

// ───────────────────────────────────────────────
// CUSTOM BUTTONS (created via admin panel)
// ───────────────────────────────────────────────
$custom_buttons = [];
$cb_config_file = __DIR__ . '/config/custom_buttons.json';
if (file_exists($cb_config_file)) {
    $cb_raw = @file_get_contents($cb_config_file);
    if ($cb_raw) {
        $cb_decoded = json_decode($cb_raw, true);
        if (is_array($cb_decoded)) $custom_buttons = $cb_decoded;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- ═══ FIX: Google Search Console Verification ═══ -->
    <meta name="google-site-verification" content="n_wF8LVDzI40NSY6twGmaiozunLEDZCPgKgAElxecaI" />

    <!-- ═══ SEO Meta Tags — Google Par No.1 Ranking ke liye ═══ -->
    <title>Team Zero - Free Web Hosting | Host HTML CSS JS PHP Files Free Online</title>
    <meta name="description" content="Team Zero Free Web Hosting — Host your HTML, CSS, JavaScript, PHP, Python files online for FREE with custom URLs. Best free hosting service. Upload ZIP, PHP, HTML files instantly. Team Zero Web Hosting by Pakistan.">
    <meta name="keywords" content="Team Zero Web Hosting, Team Zero Free Hosting, free web hosting, free hosting, host html free, free php hosting, website hosting free, premium web hosting free, best free hosting, host files online free, html hosting, css hosting, javascript hosting, php hosting free, python hosting, free website hosting, hosting free online, Team Zero, tzdb.infy.click">
    <meta name="author" content="Team Zero">
    <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">
    <meta name="theme-color" content="#667eea">
    <link rel="canonical" href="https://<?php echo $_SERVER['HTTP_HOST']; ?>/">

    <!-- Open Graph / Social Media -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="Team Zero - Free Web Hosting | Host Files Free Online">
    <meta property="og:description" content="Host your HTML, CSS, JavaScript, PHP, Python and ZIP files online for FREE with custom URLs. Best free web hosting by Team Zero.">
    <meta property="og:url" content="https://<?php echo $_SERVER['HTTP_HOST']; ?>/">
    <meta property="og:site_name" content="Team Zero Free Web Hosting">
    <meta property="og:image" content="https://<?php echo $_SERVER['HTTP_HOST']; ?>/assets/og-image.png">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Team Zero - Free Web Hosting">
    <meta name="twitter:description" content="Host HTML, CSS, JS, PHP, Python files FREE with custom URLs. Best free hosting by Team Zero.">

    <!-- Schema.org Structured Data -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebSite",
      "name": "Team Zero Free Web Hosting",
      "url": "https://<?php echo $_SERVER['HTTP_HOST']; ?>/",
      "description": "Free web hosting service for HTML, CSS, JavaScript, PHP, Python and ZIP files with custom URLs",
      "potentialAction": {
        "@type": "SearchAction",
        "target": "https://<?php echo $_SERVER['HTTP_HOST']; ?>/?q={search_term_string}",
        "query-input": "required name=search_term_string"
      }
    }
    </script>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Arial', sans-serif; }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            color: white;
            padding: 20px 0;
            margin-bottom: 20px;
        }
        .header h1 { font-size: 2rem; margin-bottom: 8px; }
        .header p  { opacity: .9; font-size: 1rem; }

        .domain-example {
            background: rgba(255,255,255,.2);
            border: 2px dashed rgba(255,255,255,.5);
            border-radius: 6px;
            padding: 10px;
            margin: 10px 0;
            font-family: monospace;
            color: white;
            font-weight: bold;
            font-size: .9rem;
        }

        .main-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        @media (max-width: 768px) { .main-content { grid-template-columns: 1fr; } }

        .card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .card-title {
            font-size: 1.2rem;
            color: #4361ee;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-group { margin-bottom: 15px; }

        label {
            display: block;
            margin-bottom: 6px;
            font-weight: bold;
            color: #333;
            font-size: .9rem;
        }

        input, textarea, select {
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: .9rem;
        }
        input:focus, textarea:focus { outline: none; border-color: #4361ee; }

        textarea {
            min-height: 200px;
            font-family: monospace;
            resize: vertical;
            font-size: .85rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 10px 16px;
            background: #4361ee;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: .85rem;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            transition: all .2s;
        }
        .btn:hover        { background: #3a0ca3; }
        .btn-block        { width: 100%; padding: 12px; }
        .btn-success      { background: #4ade80; }
        .btn-success:hover{ background: #22c55e; }
        .btn-warning      { background: #f59e0b; }
        .btn-warning:hover{ background: #d97706; }
        .btn-orange       { background: #ff7e5f; }
        .btn-orange:hover { background: #e06244; }

        .url-preview {
            background: #f8f9fa;
            border: 2px dashed #4361ee;
            border-radius: 6px;
            padding: 8px;
            margin: 8px 0;
            font-family: monospace;
            color: #4361ee;
            font-weight: bold;
            text-align: center;
            font-size: .8rem;
        }

        .site-list { max-height: 400px; overflow-y: auto; }

        .site-item {
            display: flex;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        .site-item:hover { background: #f8f9ff; }

        .site-icon {
            width: 40px; height: 40px;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            color: white;
            font-size: 1rem;
            margin-right: 12px;
        }
        .html-file { background: #e34c26; }
        .css-file  { background: #264de4; }
        .js-file   { background: #f7df1e; color: #000; }
        .zip-file  { background: #6c757d; }
        .php-file  { background: #8892be; }
        .py-file   { background: #3572A5; }
        .other-file{ background: #28a745; }

        .site-details { flex: 1; min-width: 0; }
        .site-name  { font-weight: bold; color: #333; margin-bottom: 4px; word-break: break-all; font-size: .9rem; }
        .site-url   { color: #666; font-size: .75rem; word-break: break-all; margin-bottom: 4px; }
        .site-meta  { color: #888; font-size: .75rem; display: flex; gap: 12px; }
        .site-actions { display: flex; gap: 6px; }

        .message { padding: 12px; border-radius: 6px; margin-bottom: 15px; text-align: center; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error   { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        footer { text-align: center; color: white; margin-top: 30px; padding: 15px; }

        .tabs { display: flex; margin-bottom: 15px; border-bottom: 2px solid #eee; }
        .tab  { padding: 10px 20px; cursor: pointer; border-bottom: 3px solid transparent; transition: all .2s; color: #666; font-size: .9rem; }
        .tab.active { border-bottom: 3px solid #4361ee; color: #4361ee; font-weight: bold; }
        .tab-content        { display: none; }
        .tab-content.active { display: block; }

        .file-input {
            padding: 20px;
            border: 2px dashed #ddd;
            border-radius: 6px;
            text-align: center;
            cursor: pointer;
            transition: all .2s;
            margin-bottom: 12px;
        }
        .file-input:hover { border-color: #4361ee; background: #f8f9ff; }
        .file-input i { font-size: 2.5rem; color: #666; margin-bottom: 8px; }

        .url-box { background: #f8f9fa; border: 2px solid #e9ecef; border-radius: 6px; padding: 12px; margin: 12px 0; word-break: break-all; }
        .url-box a { color: #4361ee; text-decoration: none; font-weight: bold; }
        .url-box a:hover { text-decoration: underline; }

        .copy-btn      { background: #6c757d; }
        .copy-btn:hover{ background: #5a6268; }

        .stats { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 15px; }

        .stat-card {
            background: linear-gradient(135deg, #4361ee, #3a0ca3);
            color: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-number { font-size: 1.5rem; font-weight: bold; margin-bottom: 4px; }
        .stat-label  { font-size: .8rem; opacity: .9; }

        .notification {
            position: fixed;
            top: 15px; right: 15px;
            background: #4ade80;
            color: white;
            padding: 12px 16px;
            border-radius: 6px;
            box-shadow: 0 3px 10px rgba(0,0,0,.2);
            transform: translateX(150%);
            transition: transform .3s ease;
            z-index: 1000;
        }
        .notification.show { transform: translateX(0); }

        .file-type-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: .65rem;
            font-weight: bold;
            color: white;
            margin-right: 6px;
        }

        .supported-files { display: flex; justify-content: center; gap: 12px; margin: 8px 0; flex-wrap: wrap; }
        .file-type {
            display: flex; align-items: center; gap: 4px;
            padding: 4px 8px;
            background: #f8f9fa;
            border-radius: 15px;
            font-size: .75rem;
            color: #666;
        }

        /* Hub Creator */
        #hub-section { margin-top: 25px; }
        .hub-card { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 5px 15px rgba(0,0,0,0.15); }
        .hub-card-title { font-size: 1.3rem; color: #4361ee; margin-bottom: 5px; display: flex; align-items: center; gap: 8px; }
        .hub-card-subtitle { font-size: .85rem; color: #666; margin-bottom: 20px; }
        .hub-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 768px) { .hub-grid { grid-template-columns: 1fr; } }
        .preview-box { border: 2px solid #4361ee; border-radius: 8px; overflow: hidden; height: 420px; background: #f8f9fa; }
        .preview-box iframe { width: 100%; height: 100%; border: none; }
        .preview-label { font-size: .8rem; font-weight: bold; color: #4361ee; margin-bottom: 6px; }
        .hub-action-btns { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 15px; }

        .hidden { display: none !important; }

        /* APK Builder */
        #web-to-apk-section { margin-top: 25px; }
        #apk-builder-wrap * { -webkit-tap-highlight-color: transparent; }
        #apk-builder-wrap { background: #020617; border-radius: 12px; overflow: hidden; padding: 24px 20px 40px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; color: #f0f6fc; }
        #apk-builder-wrap .apk-wrap { max-width: 1120px; margin: 0 auto; }
        #apk-builder-wrap .studio-header { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; padding: 16px 20px; background: linear-gradient(135deg, rgba(15,23,42,0.9), rgba(3,105,161,0.15)); border: 1px solid rgba(56,189,248,0.25); border-radius: 20px; margin-bottom: 28px; box-shadow: 0 8px 32px rgba(2,6,23,0.6); backdrop-filter: blur(12px); }
        #apk-builder-wrap .studio-brand { display: flex; align-items: center; gap: 14px; }
        #apk-builder-wrap .studio-brand svg { width:40px; height:40px; animation: apkSpinCW 15s linear infinite; }
        #apk-builder-wrap .studio-brand h1 { font-weight: 800; font-size: 1.6rem; background: linear-gradient(135deg,#ffffff,#e0f2fe,#38bdf8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        #apk-builder-wrap .badge { background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.4); padding: 4px 14px; border-radius: 40px; font-size: .65rem; font-weight: 800; color: #fff; letter-spacing: .12em; text-transform: uppercase; }
        @keyframes apkSpinCW { 0%{transform:rotate(0deg)} 100%{transform:rotate(360deg)} }
        @keyframes apkSpinCCW { 0%{transform:rotate(360deg)} 100%{transform:rotate(-360deg)} }
        #apk-builder-wrap .apk-wheel-outer { animation: apkSpinCW 3s linear infinite; transform-origin: 50px 50px; }
        #apk-builder-wrap .apk-wheel-inner { animation: apkSpinCCW 2s linear infinite; transform-origin: 50px 50px; }
        #apk-builder-wrap .menu-embed { display: flex; gap: 6px; padding: 6px; background: rgba(15,23,42,0.7); border-radius: 60px; border: 1px solid rgba(56,189,248,0.15); margin-bottom: 28px; flex-wrap: wrap; box-shadow: 0 4px 25px rgba(0,0,0,0.4); }
        #apk-builder-wrap .apk-menu-item { display: flex; align-items: center; gap: 10px; padding: 12px 26px; border-radius: 40px; font-size: .8rem; font-weight: 600; color: #94a3b8; background: transparent; border: none; cursor: pointer; transition: all .3s; }
        #apk-builder-wrap .apk-menu-item svg { width:18px; height:18px; fill:currentColor; opacity:.5; transition: opacity .3s; }
        #apk-builder-wrap .apk-menu-item.active { background: linear-gradient(135deg,#0284c7,#0369a1); color:#fff; box-shadow: 0 0 25px rgba(2,132,199,.5); }
        #apk-builder-wrap .apk-menu-item.active svg { opacity:1; }
        #apk-builder-wrap .apk-menu-item:hover:not(.active) { color:#fff; background:rgba(56,189,248,.1); }
        #apk-builder-wrap .apk-card { background: linear-gradient(145deg, rgba(15,23,42,.95), rgba(11,17,32,.98)); border: 1px solid rgba(56,189,248,.15); border-radius: 24px; padding: 26px 30px; margin-bottom: 24px; backdrop-filter: blur(16px); box-shadow: 0 10px 40px rgba(2,6,23,.7); }
        #apk-builder-wrap .apk-card:hover { border-color: rgba(56,189,248,.4); }
        #apk-builder-wrap .apk-card-title { display: flex; align-items: center; gap: 10px; font-size: .75rem; text-transform: uppercase; letter-spacing: .1em; color: #fff; font-weight: 800; margin-bottom: 20px; }
        #apk-builder-wrap .apk-card-title svg { width:18px; height:18px; fill:#fff; }
        #apk-builder-wrap .apk-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        #apk-builder-wrap .apk-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }
        #apk-builder-wrap .apk-field { display: flex; flex-direction: column; gap: 6px; }
        #apk-builder-wrap .apk-field label { font-size: .75rem; font-weight: 600; color: #cbd5e1; letter-spacing: .03em; }
        #apk-builder-wrap .apk-field input, #apk-builder-wrap .apk-field textarea, #apk-builder-wrap .apk-field select { background: rgba(2,6,23,.8); border: 1px solid rgba(56,189,248,.2); border-radius: 14px; padding: 14px 18px; font-size: .9rem; color: #fff; width: 100%; }
        #apk-builder-wrap .apk-field input:focus, #apk-builder-wrap .apk-field textarea:focus, #apk-builder-wrap .apk-field select:focus { outline:none; border-color:#fff; }
        #apk-builder-wrap .apk-field textarea { min-height:130px; resize:vertical; }
        #apk-builder-wrap .apk-field .hint { font-size:.65rem; color:#64748b; margin-top:3px; }
        #apk-builder-wrap .apk-btn-ghost { display: inline-flex; align-items: center; gap: 8px; background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.2); padding: 10px 20px; border-radius: 40px; color: #fff; font-size: .75rem; font-weight: 600; cursor: pointer; }
        #apk-builder-wrap .apk-btn-ghost svg { width:15px; height:15px; fill:currentColor; }
        #apk-builder-wrap .apk-btn-ghost:hover { background:rgba(255,255,255,.15); border-color:#fff; }
        #apk-builder-wrap .apk-check-group { display:flex; flex-wrap:wrap; gap:14px 24px; margin-top:10px; }
        #apk-builder-wrap .apk-check-item { display:flex; align-items:center; gap:10px; font-size:.8rem; color:#e2e8f0; cursor:pointer; font-weight:500; }
        #apk-builder-wrap .apk-check-item input[type="checkbox"], #apk-builder-wrap .apk-check-item input[type="radio"] { appearance:none; width:20px; height:20px; background:#020617; border:1px solid rgba(255,255,255,.3); border-radius:6px; cursor:pointer; position:relative; flex-shrink:0; }
        #apk-builder-wrap .apk-check-item input[type="radio"] { border-radius:50%; }
        #apk-builder-wrap .apk-check-item input[type="checkbox"]:checked, #apk-builder-wrap .apk-check-item input[type="radio"]:checked { background:linear-gradient(135deg,#0ea5e9,#fff); border-color:#fff; }
        #apk-builder-wrap .apk-check-item input[type="checkbox"]:checked::after { content:"✓"; color:#020617; font-size:14px; font-weight:900; position:absolute; top:-1px; left:4px; }
        #apk-builder-wrap .apk-check-item input[type="radio"]:checked::after { content:""; width:8px; height:8px; background:#020617; border-radius:50%; position:absolute; top:5px; left:5px; }
        #apk-builder-wrap .apk-build-btn { width:100%; padding:18px; background:linear-gradient(135deg,#0284c7,#38bdf8,#0284c7); background-size:200% auto; border:1px solid rgba(255,255,255,.4); border-radius:60px; font-size:1.1rem; font-weight:800; color:#fff; cursor:pointer; letter-spacing:.05em; margin-top:12px; display:flex; align-items:center; justify-content:center; gap:12px; box-shadow: 0 10px 30px rgba(2,132,199,.5); text-transform:uppercase; transition: all .4s; }
        #apk-builder-wrap .apk-build-btn:hover { background-position:right center; border-color:#fff; transform:translateY(-2px); }
        #apk-builder-wrap .apk-build-btn:disabled { opacity:.3; pointer-events:none; }
        #apk-builder-wrap .apk-build-btn svg { width:24px; height:24px; fill:currentColor; }
        #apk-builder-wrap #apk-status { margin-top:18px; padding:16px 20px; border-radius:16px; display:none; font-size:.85rem; background:rgba(15,23,42,.8); border:1px solid rgba(255,255,255,.2); font-weight:600; }
        #apk-builder-wrap #apk-status.building, #apk-builder-wrap #apk-status.done, #apk-builder-wrap #apk-status.failed { display:flex; align-items:center; gap:12px; }
        #apk-builder-wrap .apk-pulse-dot { width:12px; height:12px; border-radius:50%; flex-shrink:0; background:#fff; animation:apkPulse 1.4s infinite; }
        #apk-builder-wrap #apk-status.done .apk-pulse-dot { background:#10b981; }
        #apk-builder-wrap #apk-status.failed .apk-pulse-dot { background:#ef4444; }
        @keyframes apkPulse { 0%{transform:scale(.95);opacity:.8} 50%{transform:scale(1.3);opacity:1} 100%{transform:scale(.95);opacity:.8} }
        #apk-builder-wrap #apk-logHeader { display:none; margin-top:16px; background:#020617; padding:12px 18px 0; border-radius:16px 16px 0 0; border:1px solid rgba(56,189,248,.2); border-bottom:none; }
        #apk-builder-wrap #apk-log { font-family:monospace; font-size:.75rem; line-height:1.7; white-space:pre-wrap; background:#020617; padding:0 18px 18px; border-radius:0 0 16px 16px; display:none; color:#e0f2fe; border:1px solid rgba(56,189,248,.2); border-top:none; max-height:240px; overflow-y:auto; }
        #apk-builder-wrap .apk-dl-box { display:none; margin-top:24px; text-align:center; }
        #apk-builder-wrap .apk-dl-box a { display:inline-block; padding:16px 45px; background:linear-gradient(135deg,#10b981,#059669); border:1px solid rgba(255,255,255,.4); border-radius:60px; color:#fff; font-weight:800; text-decoration:none; text-transform:uppercase; }
        #apk-builder-wrap .apk-upload-zone { border:2px dashed rgba(255,255,255,.3); border-radius:20px; padding:40px; text-align:center; color:#cbd5e1; cursor:pointer; transition:all .3s; display:flex; flex-direction:column; align-items:center; gap:12px; background:rgba(2,6,23,.5); }
        #apk-builder-wrap .apk-upload-zone svg { width:40px; height:40px; fill:#fff; }
        #apk-builder-wrap .apk-upload-zone:hover { border-color:#fff; }
        #apk-builder-wrap .apk-file-list { display:flex; flex-direction:column; gap:8px; margin-top:14px; }
        #apk-builder-wrap .apk-file-item { display:flex; align-items:center; justify-content:space-between; background:rgba(2,6,23,.7); border-radius:12px; padding:10px 14px 10px 18px; border:1px solid rgba(255,255,255,.1); }
        #apk-builder-wrap .apk-file-item .apk-fn { font-size:.8rem; color:#fff; font-weight:600; }
        #apk-builder-wrap .apk-file-item .apk-fsz { font-size:.65rem; color:#94a3b8; }
        #apk-builder-wrap .apk-file-item .apk-fremove { background:transparent; border:none; color:#ef4444; cursor:pointer; padding:4px 8px; border-radius:8px; font-size:1.1rem; font-weight:bold; }
        #apk-builder-wrap .apk-icon-uploader { display:flex; align-items:center; gap:16px; }
        #apk-builder-wrap .apk-icon-preview { width:52px; height:52px; border-radius:14px; background:#020617; border:1px solid rgba(56,189,248,.3); display:flex; align-items:center; justify-content:center; overflow:hidden; flex-shrink:0; }
        #apk-builder-wrap .apk-icon-preview img { width:100%; height:100%; object-fit:cover; }
        #apk-builder-wrap .apk-icon-preview .apk-empty { font-size:.6rem; color:#64748b; text-transform:uppercase; letter-spacing:.05em; font-weight:600; }
        #apk-builder-wrap .apk-slider-wrap { position:relative; width:100%; margin:10px 0; }
        #apk-builder-wrap .apk-slider-track { height:6px; background:#020617; border-radius:6px; border:1px solid rgba(255,255,255,.2); }
        #apk-builder-wrap .apk-slider-fill { height:100%; width:60%; background:linear-gradient(90deg,#0284c7,#fff); border-radius:6px; }
        #apk-builder-wrap input[type="range"] { -webkit-appearance:none; width:100%; background:transparent; margin-top:-8px; cursor:pointer; }
        #apk-builder-wrap input[type="range"]::-webkit-slider-thumb { -webkit-appearance:none; width:20px; height:20px; border-radius:50%; background:#fff; border:3px solid #0284c7; }
        #apk-builder-wrap .apk-page-section { display:none; }
        #apk-builder-wrap .apk-page-section.active { display:block; animation:apkPageFade .4s ease; }
        @keyframes apkPageFade { from{opacity:0;transform:translateY(15px)} to{opacity:1;transform:translateY(0)} }
        #apk-builder-wrap .apk-footer { margin-top:40px; padding-top:24px; border-top:1px solid rgba(255,255,255,.15); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; font-size:.75rem; color:#94a3b8; letter-spacing:.06em; font-weight:700; }
        #apk-builder-wrap .apk-preview-pane { position:fixed; inset:0; z-index:9998; background:#020617; display:none; flex-direction:column; }
        #apk-builder-wrap .apk-preview-pane iframe { flex:1; border:none; background:white; }
        @media(max-width:700px){ #apk-builder-wrap .apk-grid-2, #apk-builder-wrap .apk-grid-3 { grid-template-columns:1fr; gap:12px; } }
    </style>
</head>
<body>
    <!-- Right-click & source protection -->
    <script>
        document.addEventListener('contextmenu', function(e){ e.preventDefault(); return false; });
        document.addEventListener('keydown', function(e){
            if((e.ctrlKey||e.metaKey) && (e.key==='u'||e.key==='U'||e.key==='s'||e.key==='S'||e.key==='p'||e.key==='P')) e.preventDefault();
            if(e.key==='F12') e.preventDefault();
        });
    </script>

    <div class="notification" id="notification">✅ URL COPIED TO CLIPBOARD!</div>

    <div class="container">

        <!-- HEADER -->
        <div class="header">
            <h1>🌐 Team Zero HOSTING</h1>
            <p>HOST YOUR HTML, CSS, JAVASCRIPT, PHP, PYTHON FILES WITH CUSTOM URLS!</p>
            <div class="supported-files">
                <div class="file-type"><i class="fas fa-file-code" style="color:#e34c26;"></i> HTML</div>
                <div class="file-type"><i class="fab fa-css3-alt" style="color:#264de4;"></i> CSS</div>
                <div class="file-type"><i class="fab fa-js-square" style="color:#f7df1e;"></i> JAVASCRIPT</div>
                <div class="file-type"><i class="fas fa-file-archive" style="color:#6c757d;"></i> ZIP</div>
                <div class="file-type"><i class="fab fa-php" style="color:#8892be;"></i> PHP</div>
                <div class="file-type"><i class="fab fa-python" style="color:#3572A5;"></i> PYTHON</div>
            </div>
            <div class="domain-example" id="domain-example">
                https://<?php echo $_SERVER['HTTP_HOST']; ?>/your-site-name
            </div>
        </div>

        <!-- SUCCESS / ERROR MESSAGES -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="message success">
                <div style="margin-bottom:12px;">✅ <?php echo $_SESSION['success']; ?></div>
                <?php if (isset($_SESSION['file_url'])): ?>
                    <div class="url-box">
                        <strong>YOUR WEBSITE URL:</strong><br>
                        <a href="<?php echo $_SESSION['file_url']; ?>" target="_blank"><?php echo $_SESSION['file_url']; ?></a>
                        <button onclick="copyToClipboard('<?php echo $_SESSION['file_url']; ?>')" class="btn copy-btn" style="width:100%;margin-top:8px;">
                            <i class="fas fa-copy"></i> COPY URL
                        </button>
                    </div>
                <?php endif; ?>
                <?php unset($_SESSION['success'], $_SESSION['file_url'], $_SESSION['file_name'], $_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="message error">
                ❌ <?php echo $_SESSION['error']; ?>
                <?php unset($_SESSION['error'], $_SESSION['success'], $_SESSION['file_url']); ?>
            </div>
        <?php endif; ?>

        <!-- MAIN CONTENT GRID -->
        <div class="main-content">

            <!-- LEFT: Upload -->
            <div>
                <div class="card">
                    <h2 class="card-title"><i class="fas fa-upload"></i> Upload Files</h2>

                    <div class="tabs" style="align-items:center; flex-wrap:wrap; gap:4px;">
                        <div class="tab active" onclick="switchTab('single-tab', this)">📄 UPLOAD FILE</div>
                        <div class="tab"        onclick="switchTab('code-tab',   this)">📝 CODE EDITOR</div>
                        <button onclick="toggleHub()" id="hub-toggle-btn" style="margin-left:auto;padding:7px 14px;background:linear-gradient(135deg,#ff7e5f,#feb47b);color:white;border:none;border-radius:20px;font-size:.8rem;font-weight:bold;cursor:pointer;display:flex;align-items:center;gap:5px;white-space:nowrap;box-shadow:0 2px 8px rgba(255,126,95,.4);transition:all .2s;">
                            <i class="fas fa-magic"></i> CREATE HUB
                        </button>
                        <?php foreach ($custom_buttons as $cb): ?>
                        <button
                            onclick="toggleCustomBtn('<?php echo htmlspecialchars($cb['id'], ENT_QUOTES); ?>')"
                            id="cb-toggle-<?php echo htmlspecialchars($cb['id'], ENT_QUOTES); ?>"
                            data-origtext="<?php echo htmlspecialchars($cb['name'], ENT_QUOTES); ?>"
                            style="padding:7px 14px;background:<?php echo htmlspecialchars($cb['color'] ?? '#6c757d', ENT_QUOTES); ?>;color:white;border:none;border-radius:20px;font-size:.8rem;font-weight:bold;cursor:pointer;display:flex;align-items:center;gap:5px;white-space:nowrap;box-shadow:0 2px 8px rgba(0,0,0,.3);transition:all .2s;">
                            <i class="fas <?php echo htmlspecialchars($cb['icon'] ?? 'fa-star', ENT_QUOTES); ?>"></i> <?php echo htmlspecialchars($cb['name']); ?>
                        </button>
                        <?php endforeach; ?>
                    </div>

                    <!-- File upload tab -->
                    <div class="tab-content active" id="single-tab">
                        <form method="POST" enctype="multipart/form-data" id="upload-form">
                            <div class="form-group">
                                <label>WEBSITE NAME</label>
                                <input type="text" name="custom_url" placeholder="team-zero-site" id="single-url" required>
                                <div class="url-preview">
                                    https://<?php echo $_SERVER['HTTP_HOST']; ?>/<span id="single-preview-url">team-zero-site</span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>UPLOAD FILE</label>
                                <div class="file-input" onclick="document.getElementById('html_file').click()">
                                    <i class="fas fa-file-upload"></i>
                                    <p>CLICK TO UPLOAD FILE</p>
                                    <small>.HTML .CSS .JS .ZIP .PHP .PY .TS .GO .RS .JAVA .SWIFT + ALL CODE FILES</small>
                                </div>
                                <input type="file" id="html_file" name="html_file" accept=".html,.htm,.css,.js,.zip,.php,.py,.rb,.go,.rs,.java,.c,.cpp,.h,.cs,.ts,.tsx,.jsx,.vue,.svelte,.swift,.kt,.dart,.lua,.sql,.txt,.md,.json,.xml,.yaml,.yml,.toml,.sh,.bash,.htaccess" style="display:none;" required>
                                <div id="file-name" style="margin-top:8px;color:#666;font-size:.8rem;"></div>
                            </div>
                            <button type="submit" class="btn btn-block">
                                <i class="fas fa-cloud-upload-alt"></i> Upload &amp; Host
                            </button>
                        </form>
                    </div>

                    <!-- Code editor tab -->
                    <div class="tab-content" id="code-tab">
                        <form method="POST">
                            <div class="form-group">
                                <label>WEBSITE NAME</label>
                                <input type="text" name="code_custom_url" placeholder="team-zero" id="code-url" required>
                                <div class="url-preview">
                                    https://<?php echo $_SERVER['HTTP_HOST']; ?>/<span id="code-preview-url">team-zero</span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>YOUR CODE <span style="color:#4361ee;font-weight:normal;font-size:.75rem;">(HTML, PHP, Python, JS, CSS — koi bhi language)</span></label>
                                <div style="display:flex;gap:6px;margin-bottom:6px;">
                                    <!-- FIX: Paste button improved with fallback -->
                                    <button type="button" class="btn" style="background:#6c757d;padding:7px 14px;font-size:.8rem;" onclick="pasteToCodeEditor()">
                                        <i class="fas fa-paste"></i> PASTE
                                    </button>
                                    <button type="button" class="btn" style="background:#dc3545;padding:7px 14px;font-size:.8rem;" onclick="clearCodeEditor()">
                                        <i class="fas fa-trash"></i> CLEAR
                                    </button>
                                </div>
                                <textarea name="html_code" id="code-editor-area" placeholder="Apna code yahan paste karo... (HTML, PHP, Python, JavaScript, CSS, ya koi bhi language)" required><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Zero Hosting</title>
    <style>
        body { font-family: Arial, sans-serif; margin:0; padding:20px;
               background: linear-gradient(135deg,#667eea,#764ba2);
               color:white; text-align:center; }
        .container { max-width:800px; margin:50px auto;
                     background:rgba(255,255,255,.1);
                     padding:30px; border-radius:15px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Welcome to Team Zero!</h1>
        <p>This website is hosted for free!</p>
    </div>
</body>
</html></textarea>
                            </div>
                            <button type="submit" class="btn btn-block">
                                <i class="fas fa-code"></i> CREATE WEBSITE
                            </button>
                        </form>
                    </div>
                </div>

                <div class="stats">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($uploaded_sites); ?></div>
                        <div class="stat-label">SITES HOSTED</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo formatSize($total_size); ?></div>
                        <div class="stat-label">TOTAL SIZE</div>
                    </div>
                </div>
            </div>

            <!-- RIGHT: Sites list -->
            <div>
                <div class="card">
                    <h2 class="card-title"><i class="fas fa-globe"></i> YOUR SITES</h2>
                    <div class="site-list">
                        <?php if (!empty($uploaded_sites)): ?>
                            <?php foreach ($uploaded_sites as $site): ?>
                                <?php
                                    $file_type  = $site['type'];
                                    $icon_class = 'html-file';
                                    if ($file_type === 'css')  $icon_class = 'css-file';
                                    elseif ($file_type === 'js')   $icon_class = 'js-file';
                                    elseif ($file_type === 'zip')  $icon_class = 'zip-file';
                                    elseif ($file_type === 'php')  $icon_class = 'php-file';
                                    elseif ($file_type === 'py')   $icon_class = 'py-file';
                                    elseif (!in_array($file_type, ['html','htm','css','js','zip'])) $icon_class = 'other-file';
                                ?>
                                <div class="site-item">
                                    <div class="site-icon <?php echo $icon_class; ?>">
                                        <i class="fas fa-file-code"></i>
                                    </div>
                                    <div class="site-details">
                                        <div class="site-name">
                                            <span class="file-type-badge <?php echo $icon_class; ?>"><?php echo strtoupper($file_type); ?></span>
                                            <?php echo htmlspecialchars($site['name']); ?>
                                            <?php if ($site['is_dir']): ?>
                                                <span style="color:#6c757d;font-size:.7rem;">(MULTIPLE FILES)</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="site-url"><?php echo htmlspecialchars($site['url']); ?></div>
                                        <div class="site-meta">
                                            <span><?php echo formatSize($site['size']); ?></span>
                                            <span><?php echo date('M j, Y', $site['time']); ?></span>
                                        </div>
                                    </div>
                                    <div class="site-actions">
                                        <a href="<?php echo htmlspecialchars($site['url']); ?>" target="_blank" class="btn btn-success">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <button onclick="copyToClipboard('<?php echo htmlspecialchars($site['url']); ?>')" class="btn copy-btn">
                                            <i class="fas fa-copy"></i> Copy
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align:center;padding:30px;color:#666;">
                                <i class="fas fa-inbox" style="font-size:2.5rem;margin-bottom:10px;opacity:.5;"></i>
                                <h3>NO SITES HOSTED YET</h3>
                                <p>UPLOAD YOUR FIRST FILE TO GET STARTED!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- HUB SECTION -->
        <?php
            $hub_auto_open = (isset($_SESSION['hub_success']) || isset($_SESSION['hub_error']));
        ?>
        <div id="hub-section" style="margin-top:25px; display:<?php echo $hub_auto_open ? 'block' : 'none'; ?>;">
        <?php if ($hub_auto_open): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function(){
                const hb = document.getElementById('hub-toggle-btn');
                if (hb) { hb.style.background='linear-gradient(135deg,#3a0ca3,#4361ee)'; hb.innerHTML='<i class="fas fa-times"></i> CLOSE HUB'; }
            });
        </script>
        <?php endif; ?>

            <?php if (isset($_SESSION['hub_success'])): ?>
                <div class="message success" style="margin-bottom:15px;">
                    ✅ <?php echo $_SESSION['hub_success']; ?>
                    <?php if (isset($_SESSION['hub_url'])): ?>
                        <div class="url-box" style="margin-top:10px;">
                            <strong>YOUR HUB URL:</strong><br>
                            <a href="<?php echo $_SESSION['hub_url']; ?>" target="_blank"><?php echo $_SESSION['hub_url']; ?></a>
                            <button onclick="copyToClipboard('<?php echo $_SESSION['hub_url']; ?>')" class="btn copy-btn" style="width:100%;margin-top:8px;">
                                <i class="fas fa-copy"></i> COPY URL
                            </button>
                        </div>
                    <?php endif; ?>
                    <?php unset($_SESSION['hub_success'], $_SESSION['hub_url']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['hub_error'])): ?>
                <div class="message error" style="margin-bottom:15px;">
                    ❌ <?php echo $_SESSION['hub_error']; ?>
                    <?php unset($_SESSION['hub_error']); ?>
                </div>
            <?php endif; ?>

            <div class="hub-card">
                <h2 class="hub-card-title"><i class="fas fa-magic"></i> Create Own API &amp; Source Code Hub</h2>
                <p class="hub-card-subtitle">Apna naam dalo, live preview dekho, aur apni website host karo ya download karo!</p>
                <div class="hub-grid">
                    <div class="hub-form-area">
                        <div class="form-group">
                            <label>🏷️ WEBSITE NAME (URL ke liye)</label>
                            <input type="text" id="hub-url-input" placeholder="mera-hub" oninput="updateHubUrl(this.value); updatePreview();">
                            <div class="url-preview">https://<?php echo $_SERVER['HTTP_HOST']; ?>/<span id="hub-url-preview">mera-hub</span></div>
                        </div>
                        <div class="form-group">
                            <label>✏️ TITLE (Website ka naam)</label>
                            <input type="text" id="hub-title-input" placeholder="MERA HUB" value="TEAM ZERO" oninput="updatePreview();">
                        </div>
                        <div class="form-group">
                            <label>⚡ POWERED BY (Tumhara naam)</label>
                            <input type="text" id="hub-powered-input" placeholder="TEAM XYZ" value="TEAM ZERO" oninput="updatePreview();">
                        </div>
                        <form method="POST" id="hub-host-form" style="display:none;">
                            <input type="hidden" name="action_hub"  value="host">
                            <input type="hidden" name="hub_url"     id="form-hub-url">
                            <input type="hidden" name="hub_title"   id="form-hub-title">
                            <input type="hidden" name="hub_powered" id="form-hub-powered">
                        </form>
                        <form method="POST" id="hub-dl-form" style="display:none;">
                            <input type="hidden" name="action_hub"  value="download">
                            <input type="hidden" name="hub_url"     id="form-dl-url">
                            <input type="hidden" name="hub_title"   id="form-dl-title">
                            <input type="hidden" name="hub_powered" id="form-dl-powered">
                        </form>
                        <div class="hub-action-btns" style="grid-template-columns:1fr;">
                            <button class="btn btn-block" style="background:linear-gradient(135deg,#4361ee,#3a0ca3);font-size:1rem;padding:14px;" onclick="submitHubHost()"><i class="fas fa-cloud-upload-alt"></i> HOST YOUR WEBSITE</button>
                        </div>
                    </div>
                    <div class="hub-preview-area">
                        <div class="preview-label">👁️ LIVE PREVIEW</div>
                        <div class="preview-box">
                            <iframe id="hub-preview-frame" sandbox="allow-scripts allow-same-origin"></iframe>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- WEB TO APK SECTION -->
        <div id="web-to-apk-section" style="display:none;">
            <div style="background:#0f172a;border:1px solid rgba(56,189,248,.3);border-radius:16px 16px 0 0;padding:18px 24px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
                <label style="color:#38bdf8;font-weight:700;font-size:.95rem;white-space:nowrap;"><i class="fas fa-user" style="margin-right:6px;"></i> Apna Naam Likho:</label>
                <input type="text" id="apk-owner-name" placeholder="TEAM ZERO" value="TEAM ZERO" oninput="apkUpdateOwnerName(this.value)" style="flex:1;padding:10px 16px;background:rgba(2,6,23,.8);border:1px solid rgba(56,189,248,.3);border-radius:10px;color:#fff;font-size:.95rem;font-weight:700;min-width:180px;outline:none;">
                <p style="color:#64748b;font-size:.75rem;width:100%;margin-top:4px;">Yahan jo naam likho ge, APK Builder mein jahan bhi "TEAM ZERO" hoga wahan wo naam show hoga.</p>
            </div>

            <div id="apk-builder-wrap">
              <div class="apk-wrap">
                <header class="studio-header">
                    <div class="studio-brand">
                        <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                            <circle class="apk-wheel-outer" cx="50" cy="50" r="42" stroke="#ffffff" stroke-width="6" fill="none" stroke-dasharray="150 50" stroke-linecap="round" opacity="0.9"/>
                            <circle class="apk-wheel-inner" cx="50" cy="50" r="24" stroke="#38bdf8" stroke-width="5" fill="none" stroke-dasharray="70 30" stroke-linecap="round"/>
                            <circle cx="50" cy="50" r="8" fill="#ffffff"/>
                        </svg>
                        <h1 id="apk-brand-name">TEAM ZERO</h1>
                        <span class="badge">APK STUDIO</span>
                    </div>
                </header>

                <div class="menu-embed" id="apkMenuEmbed">
                    <button class="apk-menu-item active" data-apkpage="html">
                        <svg viewBox="0 0 24 24"><path d="M4.14 3L5.82 17.93 12 19.68l6.18-1.75L19.86 3H4.14z"/></svg> HTML
                    </button>
                    <button class="apk-menu-item" data-apkpage="url">
                        <svg viewBox="0 0 24 24"><path d="M10.59 13.41c.41.39.41 1.03 0 1.42-.39.39-1.03.39-1.42 0a5.003 5.003 0 0 1 0-7.07l3.54-3.54a5.003 5.003 0 0 1 7.07 0 5.003 5.003 0 0 1 0 7.07l-1.49 1.49c.01-.82-.12-1.64-.4-2.42l.47-.48a2.982 2.982 0 0 0 0-4.24 2.982 2.982 0 0 0-4.24 0l-3.53 3.53a2.982 2.982 0 0 0 0 4.24z"/></svg> URL
                    </button>
                    <button class="apk-menu-item" data-apkpage="upload">
                        <svg viewBox="0 0 24 24"><path d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM14 13v4h-4v-4H7l5-5 5 5h-3z"/></svg> Upload
                    </button>
                </div>

                <div class="apk-page-section active" id="apk-page-html">
                    <div class="apk-card">
                        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                            <div class="apk-card-title" style="margin-bottom:0;">
                                <svg viewBox="0 0 24 24"><path d="M4.14 3L5.82 17.93 12 19.68l6.18-1.75L19.86 3H4.14z"/></svg> HTML Content
                            </div>
                            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                <button class="apk-btn-ghost" onclick="apkPreview()"><svg viewBox="0 0 24 24"><path d="M12 6a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm8-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2z"/></svg> Preview</button>
                                <button class="apk-btn-ghost" onclick="apkHostHtml()" style="background:rgba(16,185,129,.15);border-color:rgba(16,185,129,.5);"><svg viewBox="0 0 24 24" style="fill:#10b981;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"/></svg><span style="color:#10b981;">Host HTML</span></button>
                            </div>
                        </div>
                        <div class="apk-field" style="margin-top:14px;">
                            <div style="display:flex;gap:8px;margin-bottom:8px;">
                                <button class="apk-btn-ghost" onclick="showPasteModal(document.getElementById('apk-html'))" style="background:rgba(67,97,238,.2);border-color:rgba(67,97,238,.6);">
                                    <svg viewBox="0 0 24 24"><path d="M19 2h-4.18C14.4.84 13.3 0 12 0c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm7 18H5V4h2v3h10V4h2v16z"/></svg>
                                    📋 PASTE CODE
                                </button>
                                <button class="apk-btn-ghost" onclick="document.getElementById('apk-html').value='';showNotification('🗑️ Clear ho gaya');" style="background:rgba(239,68,68,.15);border-color:rgba(239,68,68,.4);color:#fca5a5;">
                                    <svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                                    CLEAR
                                </button>
                            </div>
                            <textarea id="apk-html" placeholder="📋 Upar wale PASTE CODE button se apna HTML yahan paste karo..." style="min-height:160px;"></textarea>
                        </div>
                        <button class="apk-btn-ghost" style="margin-top:12px;" onclick="document.getElementById('apkExtraInputs').classList.toggle('hidden')"><svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg> CSS / JS</button>
                        <div id="apkExtraInputs" class="hidden" style="margin-top:14px;">
                            <div class="apk-field"><label>CSS</label><textarea id="apk-css" style="min-height:75px;" placeholder="body { background: #020617; }"></textarea></div>
                            <div class="apk-field" style="margin-top:12px;"><label>JS</label><textarea id="apk-js" style="min-height:75px;" placeholder="console.log('Ready')"></textarea></div>
                        </div>
                    </div>
                </div>

                <div class="apk-page-section" id="apk-page-url">
                    <div class="apk-card">
                        <div class="apk-card-title"><svg viewBox="0 0 24 24"><path d="M10.59 13.41c.41.39.41 1.03 0 1.42z"/></svg> Website URL</div>
                        <div class="apk-field"><input id="apk-url" placeholder="https://example.com" type="url"></div>
                        <div class="apk-check-group" style="margin-top:14px;">
                            <label class="apk-check-item"><input type="checkbox" id="apk-blockAds" checked> Block ads</label>
                            <label class="apk-check-item"><input type="checkbox" id="apk-adguardDns"> AdGuard DNS</label>
                        </div>
                    </div>
                </div>

                <div class="apk-page-section" id="apk-page-upload">
                    <div class="apk-card">
                        <div class="apk-card-title"><svg viewBox="0 0 24 24"><path d="M19.35 10.04C18.67 6.59 15.64 4 12 4z"/></svg> Upload Project</div>
                        <div class="apk-upload-zone" onclick="document.getElementById('apk-projectFiles').click()">
                            <svg viewBox="0 0 24 24"><path d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM14 13v4h-4v-4H7l5-5 5 5h-3z"/></svg>
                            <span>Drop or select files</span>
                            <div style="font-size:.65rem;color:#94a3b8;">.html .css .js .json .zip .png etc.</div>
                        </div>
                        <input type="file" id="apk-projectFiles" multiple style="display:none" onchange="apkHandleFiles(this)">
                        <div id="apk-fileList" class="apk-file-list"></div>
                    </div>
                </div>

                <div class="apk-card">
                    <div class="apk-card-title"><svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10z"/></svg> App Details</div>
                    <div class="apk-grid-2">
                        <div class="apk-field"><label>App Name</label><input id="apk-appName" placeholder="My APK App" value="My APK App"></div>
                        <div class="apk-field"><label>Package ID</label><input id="apk-pkg" placeholder="com.myapp.app" value="com.myapp.app"></div>
                    </div>
                    <div class="apk-grid-3" style="margin-top:8px;">
                        <div class="apk-field"><label>Version</label><input id="apk-version" placeholder="1.0" value="1.0"></div>
                        <div class="apk-field" style="grid-column:span 2;">
                            <label>Icon (PNG)</label>
                            <div class="apk-icon-uploader">
                                <div class="apk-icon-preview" id="apk-iconPreview"><span class="apk-empty">icon</span></div>
                                <button class="apk-btn-ghost" onclick="document.getElementById('apk-iconFile').click()">Choose PNG</button>
                                <input type="file" id="apk-iconFile" accept="image/png" style="display:none" onchange="apkPreviewIcon(this)">
                            </div>
                        </div>
                    </div>
                    <button class="apk-btn-ghost" style="margin-top:18px;" onclick="document.getElementById('apk-advancedOpts').classList.toggle('hidden')">
                        <svg viewBox="0 0 24 24"><path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2z"/></svg> Advanced
                    </button>
                    <div id="apk-advancedOpts" class="hidden" style="margin-top:16px;border-top:1px solid rgba(255,255,255,.15);padding-top:18px;">
                        <div class="apk-grid-2">
                            <div class="apk-field"><label>Theme Color</label><input type="color" id="apk-themeColor" value="#0284c7"></div>
                            <div class="apk-field"><label>Splash Animation</label><select id="apk-splashAnimation"><option value="fade">Fade</option><option value="slide">Slide</option><option value="none">None</option></select></div>
                        </div>
                        <div class="apk-check-group" style="margin-top:12px;">
                            <label class="apk-check-item"><input type="checkbox" id="apk-pullRefresh" checked> Pull-Refresh</label>
                            <label class="apk-check-item"><input type="checkbox" id="apk-hideScrollbars" checked> Hide scrollbars</label>
                            <label class="apk-check-item"><input type="checkbox" id="apk-transparentNav"> Transparent nav</label>
                            <label class="apk-check-item"><input type="checkbox" id="apk-pinchZoom"> Pinch zoom</label>
                            <label class="apk-check-item"><input type="checkbox" id="apk-disableCopy"> Disable copy</label>
                            <label class="apk-check-item"><input type="checkbox" id="apk-splashEnabled" onchange="document.getElementById('apk-splashOpts').classList.toggle('hidden',!this.checked)" checked> Splash screen</label>
                        </div>
                        <div id="apk-splashOpts" style="margin-top:14px;background:rgba(2,6,23,.7);border:1px solid rgba(255,255,255,.15);border-radius:16px;padding:18px;">
                            <div class="apk-grid-2">
                                <div class="apk-field"><label>Duration (s)</label><input type="number" id="apk-splashDuration" value="2.5" min="1" max="6" step="0.5"></div>
                                <div class="apk-field"><label>Background Color</label><input type="color" id="apk-splashColor" value="#020617"></div>
                            </div>
                            <div class="apk-field" style="margin-top:10px;"><label>Image Size</label>
                                <div class="apk-slider-wrap"><div class="apk-slider-track"><div class="apk-slider-fill" style="width:65%"></div></div>
                                <input type="range" id="apk-splashImageSize" min="20" max="90" value="65" oninput="this.parentElement.querySelector('.apk-slider-fill').style.width=this.value+'%'"></div>
                            </div>
                            <div style="display:flex;gap:14px;margin-top:12px;flex-wrap:wrap;align-items:center;">
                                <label class="apk-check-item"><input type="radio" name="apkSplashSrc" value="icon" checked> App icon</label>
                                <label class="apk-check-item"><input type="radio" name="apkSplashSrc" value="custom"> Custom</label>
                                <input type="file" id="apk-splashImageFile" accept="image/png" style="display:none" onchange="apkPreviewSplash(this)">
                                <button class="apk-btn-ghost" onclick="document.getElementById('apk-splashImageFile').click()">Choose image</button>
                            </div>
                        </div>
                        <div class="apk-check-item" style="margin-top:16px;">
                            <input type="checkbox" id="apk-releaseSign" onchange="document.getElementById('apk-signOpts').classList.toggle('hidden',!this.checked)"> Release signing (keystore)
                        </div>
                        <div id="apk-signOpts" class="hidden" style="margin-top:14px;background:rgba(2,6,23,.7);border:1px solid rgba(255,255,255,.15);border-radius:16px;padding:18px;">
                            <div class="apk-grid-2">
                                <div class="apk-field"><label>Keystore (file)</label><input type="file" id="apk-keystoreFile" accept=".jks,.keystore" onchange="apkLoadKeystore(this)"></div>
                                <div class="apk-field"><label>Keystore Password</label><input type="password" id="apk-ksPass" placeholder="••••••"></div>
                                <div class="apk-field"><label>Key Alias</label><input id="apk-keyAlias" placeholder="alias"></div>
                                <div class="apk-field"><label>Key Password</label><input type="password" id="apk-keyPass" placeholder="(optional)"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- HOST WEBSITE button — Web to APK section mein -->
                <button onclick="apkHostHtml()" style="width:100%;padding:16px;margin-bottom:12px;background:linear-gradient(135deg,#059669,#10b981);color:white;border:none;border-radius:14px;font-size:1rem;font-weight:800;cursor:pointer;letter-spacing:.05em;display:flex;align-items:center;justify-content:center;gap:10px;box-shadow:0 4px 20px rgba(16,185,129,.4);">
                    <svg viewBox="0 0 24 24" style="width:22px;height:22px;fill:white;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
                    🌐 HOST WEBSITE (Web to APK HTML se)
                </button>

                <button class="apk-build-btn" id="apk-btn" onclick="apkBuild()">
                    <svg viewBox="0 0 24 24"><path d="M17.25 18H7v-2h10.25l1.5-1.5-1.5-1.5H7V4h2v13z"/></svg> Build APK
                </button>
                <div id="apk-status"></div>
                <div id="apk-logHeader"><div style="display:flex;gap:6px;padding-bottom:6px;"><span style="width:10px;height:10px;border-radius:50%;background:#ef4444;"></span><span style="width:10px;height:10px;border-radius:50%;background:#f59e0b;"></span><span style="width:10px;height:10px;border-radius:50%;background:#10b981;"></span></div></div>
                <div id="apk-log"></div>
                <div class="apk-dl-box" id="apk-dl"></div>

                <div class="apk-footer">
                    <span>APK BUILDER · POWERED BY <strong id="apk-footer-name" style="color:#fff;">TEAM ZERO</strong></span>
                    <span style="color:#fff;">✦ OCEAN BLUE &amp; WHITE EDITION ✦</span>
                </div>
              </div>
            </div>

            <div id="apk-previewPane" style="position:fixed;inset:0;z-index:9998;background:#020617;display:none;flex-direction:column;">
                <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 20px;background:#0f172a;border-bottom:1px solid rgba(56,189,248,.3);">
                    <span id="apk-preview-title" style="font-size:.85rem;color:#fff;font-weight:700;">Preview</span>
                    <button onclick="apkClosePreview()" style="background:transparent;border:none;color:#94a3b8;font-size:1.3rem;cursor:pointer;font-weight:bold;">✕</button>
                </div>
                <iframe id="apk-previewFrame" style="flex:1;border:none;background:white;"></iframe>
            </div>
        </div>

        <!-- CUSTOM BUTTONS SECTIONS -->
        <?php foreach ($custom_buttons as $cb):
            $cbid     = htmlspecialchars($cb['id'], ENT_QUOTES);
            $cbname   = htmlspecialchars($cb['name']);
            $cbtarget = htmlspecialchars($cb['replace_target'] ?? 'TEAM ZERO', ENT_QUOTES);
            $cbmode   = $cb['mode'] ?? 'both';
            $cbcolor  = htmlspecialchars($cb['color'] ?? '#667eea', ENT_QUOTES);
            $cbhtml   = htmlspecialchars($cb['html'] ?? '');
        ?>
        <div id="cb-section-<?php echo $cbid; ?>" style="display:none;margin-top:25px;">
            <div style="background:white;border-radius:12px;padding:24px;box-shadow:0 5px 15px rgba(0,0,0,.12);">
                <h2 style="color:<?php echo $cbcolor; ?>;display:flex;align-items:center;gap:8px;margin-bottom:16px;font-size:1.2rem;">
                    <i class="<?php echo htmlspecialchars(in_array($cb['icon'] ?? 'fa-star', ['fa-android','fa-whatsapp','fa-telegram']) ? 'fab' : 'fas', ENT_QUOTES); ?> <?php echo htmlspecialchars($cb['icon'] ?? 'fa-star', ENT_QUOTES); ?>"></i> <?php echo $cbname; ?>
                </h2>
                <div style="background:#f8f9ff;border:2px solid #e8eaff;border-radius:8px;padding:14px;margin-bottom:14px;">
                    <label style="font-weight:bold;font-size:.85rem;color:#333;display:block;margin-bottom:8px;">
                        ✏️ Apna Naam Likho:
                        <span style="color:#888;font-weight:normal;font-size:.75rem;">(jahan "<strong><?php echo $cbtarget; ?></strong>" likha hai wahan tumhara naam aa jayega)</span>
                    </label>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <input type="text" id="cb-name-<?php echo $cbid; ?>" placeholder="<?php echo $cbtarget; ?>" oninput="applyCustomName('<?php echo $cbid; ?>','<?php echo addslashes($cb['replace_target'] ?? 'TEAM ZERO'); ?>')" style="flex:1;padding:10px 14px;border:2px solid #ddd;border-radius:8px;font-size:.95rem;min-width:180px;">
                        <button onclick="applyCustomName('<?php echo $cbid; ?>','<?php echo addslashes($cb['replace_target'] ?? 'TEAM ZERO'); ?>')" class="btn" style="background:<?php echo $cbcolor; ?>;padding:10px 18px;"><i class="fas fa-sync-alt"></i> Apply</button>
                    </div>
                </div>
                <div style="margin-bottom:14px;">
                    <label style="font-weight:bold;font-size:.85rem;color:#333;display:block;margin-bottom:4px;">🌐 Website URL Name</label>
                    <input type="text" id="cb-url-<?php echo $cbid; ?>" placeholder="meri-website" oninput="cbUrlPreview('<?php echo $cbid; ?>',this.value)" style="width:100%;padding:10px;border:2px solid #ddd;border-radius:6px;font-size:.9rem;box-sizing:border-box;">
                    <div class="url-preview">https://<?php echo $_SERVER['HTTP_HOST']; ?>/<span id="cb-url-preview-<?php echo $cbid; ?>">meri-website</span></div>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;">
                    <?php if ($cbmode === 'host' || $cbmode === 'both'): ?>
                    <button onclick="hostCustomHtml('<?php echo $cbid; ?>')" class="btn btn-block" style="flex:1;padding:12px;background:<?php echo $cbcolor; ?>;"><i class="fas fa-cloud-upload-alt"></i> HOST WEBSITE</button>
                    <?php endif; ?>
                    <?php if ($cbmode === 'download' || $cbmode === 'both'): ?>
                    <button onclick="downloadCustomHtml('<?php echo $cbid; ?>')" class="btn btn-block" style="flex:1;padding:12px;background:linear-gradient(135deg,#ff7e5f,#feb47b);"><i class="fas fa-download"></i> DOWNLOAD HTML</button>
                    <?php endif; ?>
                </div>
                <div style="border:2px solid #ddd;border-radius:8px;overflow:hidden;">
                    <div style="background:#f0f0f0;padding:6px 14px;font-size:.75rem;color:#666;border-bottom:1px solid #ddd;display:flex;align-items:center;gap:6px;">
                        <span style="width:10px;height:10px;border-radius:50%;background:#ef4444;display:inline-block;"></span>
                        <span style="width:10px;height:10px;border-radius:50%;background:#f59e0b;display:inline-block;"></span>
                        <span style="width:10px;height:10px;border-radius:50%;background:#10b981;display:inline-block;"></span>
                        <span style="margin-left:8px;">👁️ LIVE PREVIEW</span>
                    </div>
                    <iframe id="cb-preview-<?php echo $cbid; ?>" style="width:100%;height:360px;border:none;display:block;" sandbox="allow-scripts allow-same-origin"></iframe>
                </div>
                <textarea id="cb-html-store-<?php echo $cbid; ?>" style="display:none;"><?php echo $cbhtml; ?></textarea>
                <textarea id="cb-html-orig-<?php echo $cbid; ?>"  style="display:none;"><?php echo $cbhtml; ?></textarea>
            </div>
        </div>
        <?php endforeach; ?>

        <footer>
            <p style="font-size:.9rem;">DEVELOPED BY <strong style="color:#4cc9f0;">Team Zero</strong> | FREE WEB HOSTING</p>
        </footer>
    </div>

    <script>
    /* ── TAB SWITCHER ── */
    function switchTab(tabId, el) {
        document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.getElementById(tabId).classList.add('active');
        el.classList.add('active');
    }

    /* ── URL PREVIEWS ── */
    function bindUrlPreview(inputId, previewId) {
        document.getElementById(inputId).addEventListener('input', function() {
            let v = this.value.trim().toLowerCase().replace(/[^a-z0-9-]/g,'-').replace(/-+/g,'-').replace(/^-|-$/g,'') || 'team-zero';
            document.getElementById(previewId).textContent = v;
        });
    }
    bindUrlPreview('single-url', 'single-preview-url');
    bindUrlPreview('code-url',   'code-preview-url');

    /* ── FILE INPUT DISPLAY ── */
    document.getElementById('html_file').addEventListener('change', function() {
        const fn  = document.getElementById('file-name');
        if (!this.files.length) { fn.innerHTML = ''; return; }
        fn.innerHTML = '<i class="fas fa-file"></i> Selected: ' + this.files[0].name;
        fn.style.color = '#4361ee';
        const base = this.files[0].name.replace(/\.[^/.]+$/,'').toLowerCase().replace(/[^a-z0-9]/g,'-');
        document.getElementById('single-url').value = base;
        document.getElementById('single-preview-url').textContent = base;
    });

    /* ── COPY TO CLIPBOARD ── */
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(() => showNotification('✅ URL COPIED TO CLIPBOARD!')).catch(() => showNotification('❌ FAILED TO COPY URL'));
    }
    function showNotification(msg) {
        const n = document.getElementById('notification');
        n.textContent = msg; n.classList.add('show');
        setTimeout(() => n.classList.remove('show'), 2500);
    }

    /* ── FORM VALIDATION ── */
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {
            const u = this.querySelector('input[type="text"]');
            if (u) u.value = u.value.trim().toLowerCase().replace(/[^a-z0-9-]/g,'-').replace(/-+/g,'-').replace(/^-|-$/g,'') || 'team-zero';
        });
    });

    /* ── HUB CREATOR ── */
    function updateHubUrl(val) {
        const clean = val.trim().toLowerCase().replace(/[^a-z0-9-]/g,'-').replace(/-+/g,'-').replace(/^-|-$/g,'') || 'mera-hub';
        document.getElementById('hub-url-preview').textContent = clean;
    }

    function buildHubHtml(title, powered) {
        const esc = s => s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        const t = esc(title || 'TEAM ZERO');
        const p = esc(powered || 'TEAM ZERO');
        return `<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>${t}</title><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"><style>*{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',sans-serif}body{background:linear-gradient(135deg,#667eea,#764ba2);color:#333;min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:20px}.container{max-width:1400px;margin:0 auto;padding:20px;width:100%}header{text-align:center;padding:30px 0 10px}.logo{font-size:2.2rem;font-weight:700;color:#fff;margin-bottom:10px}.tagline{font-size:1.1rem;color:rgba(255,255,255,.8);margin-bottom:20px}.menu-tabs{display:flex;justify-content:center;margin:10px 0 30px;gap:10px;flex-wrap:wrap}.tab-btn{padding:12px 30px;background:rgba(255,255,255,.9);border:none;border-radius:50px;font-size:1rem;font-weight:600;cursor:pointer;transition:all .3s;display:flex;align-items:center;gap:8px}.tab-btn.active{background:#fff;color:#667eea}.content-section{display:none}.content-section.active{display:block}.section-title{font-size:1.8rem;margin-bottom:20px;text-align:center;color:#fff;padding-bottom:10px;position:relative}.section-title::after{content:'';position:absolute;bottom:0;left:50%;transform:translateX(-50%);width:100px;height:3px;background:#fff;border-radius:3px}.cards-container{display:grid;grid-template-columns:repeat(auto-fill,minmax(350px,1fr));gap:20px;margin-top:30px}.card{background:rgba(255,255,255,.95);border-radius:20px;padding:25px;transition:all .3s;box-shadow:0 10px 30px rgba(0,0,0,.1)}.card:hover{transform:translateY(-5px)}.card-title{font-size:1.2rem;margin-bottom:12px;display:flex;align-items:center;gap:10px}.card-icon{color:#667eea;font-size:1.3rem}.card-description{color:#666;margin-bottom:15px;line-height:1.4;font-size:.9rem}.api-url{background:#f8f9fa;padding:12px;border-radius:10px;font-family:monospace;font-size:.8rem;margin-bottom:15px;word-break:break-all;border-left:3px solid #667eea}.action-buttons{display:flex;gap:8px}.btn{padding:10px 15px;border:none;border-radius:50px;font-weight:600;cursor:pointer;transition:all .3s;display:flex;align-items:center;gap:6px;flex:1;justify-content:center;font-size:.85rem}.btn-primary{background:#667eea;color:#fff}.btn-secondary{background:rgba(0,0,0,.05);color:#333}.download-link{display:inline-block;margin-top:12px;padding:12px 16px;background:#667eea;color:#fff;text-decoration:none;border-radius:50px;font-weight:600;transition:all .3s;text-align:center;width:100%;font-size:.9rem}.back-btn{padding:12px 20px;background:rgba(255,255,255,.9);color:#333;border:none;border-radius:50px;cursor:pointer;margin-bottom:20px;display:flex;align-items:center;gap:8px;transition:all .3s}.category-card{cursor:pointer}.api-card{cursor:default!important}footer{text-align:center;margin-top:30px;padding:20px;color:rgba(255,255,255,.8);font-size:.9rem}.notification{position:fixed;bottom:20px;right:20px;padding:15px 25px;background:#28a745;color:#fff;border-radius:10px;transform:translateY(100px);opacity:0;transition:all .3s;z-index:1000}.notification.show{transform:translateY(0);opacity:1}@media(max-width:768px){.cards-container{grid-template-columns:1fr}.menu-tabs{flex-direction:column;align-items:center}}</style></head><body><div style="width:100%"><div class="container"><header><h1 class="logo">${t}</h1><p class="tagline">YOUR ONE-STOP DESTINATION FOR POWERFUL TOOLS AND RESOURCES</p></header><div class="menu-tabs"><button class="tab-btn active" data-tab="api"><i class="fas fa-code"></i> API</button><button class="tab-btn" data-tab="source-code"><i class="fas fa-file-code"></i> SOURCE CODE</button></div><section id="api" class="content-section active"><h2 class="section-title">API COLLECTION</h2><div class="cards-container"><div class="card api-card"><h3 class="card-title"><i class="fab fa-telegram card-icon"></i> Telegram Story Downloader</h3><p class="card-description">Download stories from Telegram.</p><div class="api-url">https://tgstory-down.apis-bj-devs.workers.dev/?url=t.me/username</div><div class="action-buttons"><button class="btn btn-primary copy-btn" data-url="https://tgstory-down.apis-bj-devs.workers.dev/?url=t.me/username"><i class="fas fa-copy"></i> COPY</button></div></div><div class="card api-card"><h3 class="card-title"><i class="fab fa-tiktok card-icon"></i> TikTok Downloader</h3><p class="card-description">Download TikTok videos.</p><div class="api-url">https://tikwm.com/api/?url=</div><div class="action-buttons"><button class="btn btn-primary copy-btn" data-url="https://tikwm.com/api/?url="><i class="fas fa-copy"></i> COPY</button></div></div></div></section><section id="source-code" class="content-section"><h2 class="section-title">SOURCE CODE COLLECTION</h2><div class="cards-container"><div class="card category-card" data-category="html-website"><h3 class="card-title"><i class="fas fa-code card-icon"></i> HTML WEBSITE CODE</h3><p class="card-description">Beautiful HTML templates.</p><div class="action-buttons"><button class="btn btn-primary"><i class="fas fa-arrow-right"></i> VIEW</button></div></div></div></section><section id="html-website-subcategories" class="content-section"><button class="back-btn" id="back1"><i class="fas fa-arrow-left"></i> BACK</button><h2 class="section-title">HTML SOURCE CODE</h2><div class="cards-container"></div></section><footer><p>&copy; 2026 ALL RIGHTS RESERVED | POWERED BY ${p}</p></footer></div></div><div class="notification" id="notification">Copied!</div><script>document.querySelectorAll('.tab-btn[data-tab]').forEach(b=>{b.addEventListener('click',()=>{document.querySelectorAll('.tab-btn[data-tab]').forEach(x=>x.classList.remove('active'));document.querySelectorAll('.content-section').forEach(x=>x.classList.remove('active'));b.classList.add('active');document.getElementById(b.dataset.tab).classList.add('active');});});document.querySelectorAll('.category-card').forEach(c=>{c.addEventListener('click',()=>{document.querySelectorAll('.content-section').forEach(x=>x.classList.remove('active'));document.getElementById(c.dataset.category+'-subcategories').classList.add('active');});});const b1=document.getElementById('back1');if(b1)b1.addEventListener('click',()=>{document.querySelectorAll('.content-section').forEach(x=>x.classList.remove('active'));document.getElementById('source-code').classList.add('active');});document.querySelectorAll('.copy-btn').forEach(b=>{b.addEventListener('click',e=>{e.stopPropagation();navigator.clipboard.writeText(b.dataset.url).then(()=>{const n=document.getElementById('notification');n.textContent='Copied!';n.classList.add('show');setTimeout(()=>n.classList.remove('show'),2000);});});});<\/script></body></html>`;
    }

    function updatePreview() {
        const title   = document.getElementById('hub-title-input').value   || 'TEAM ZERO';
        const powered = document.getElementById('hub-powered-input').value || 'TEAM ZERO';
        const html    = buildHubHtml(title, powered);
        const frame   = document.getElementById('hub-preview-frame');
        const blob    = new Blob([html], { type: 'text/html' });
        const url     = URL.createObjectURL(blob);
        frame.src     = url;
    }

    function toggleHub() {
        const sec    = document.getElementById('hub-section');
        const btn    = document.getElementById('hub-toggle-btn');
        const apkSec = document.getElementById('web-to-apk-section');
        const apkBtn = document.getElementById('apk-toggle-btn');
        const isHidden = sec.style.display === 'none';
        if (isHidden && apkSec.style.display !== 'none') {
            apkSec.style.display = 'none';
            apkBtn.style.background = 'linear-gradient(135deg,#0284c7,#38bdf8)';
            apkBtn.innerHTML = '<i class="fas fa-android"></i> CREATE WEB TO APK';
        }
        sec.style.display = isHidden ? 'block' : 'none';
        if (isHidden) {
            sec.scrollIntoView({ behavior: 'smooth', block: 'start' });
            updatePreview();
            btn.style.background = 'linear-gradient(135deg,#3a0ca3,#4361ee)';
            btn.innerHTML = '<i class="fas fa-times"></i> CLOSE HUB';
        } else {
            btn.style.background = 'linear-gradient(135deg,#ff7e5f,#feb47b)';
            btn.innerHTML = '<i class="fas fa-magic"></i> CREATE HUB';
        }
    }

    function submitHubHost() {
        const urlVal = document.getElementById('hub-url-input').value.trim().toLowerCase().replace(/[^a-z0-9-]/g,'-').replace(/-+/g,'-').replace(/^-|-$/g,'') || 'mera-hub';
        document.getElementById('form-hub-url').value     = urlVal;
        document.getElementById('form-hub-title').value   = document.getElementById('hub-title-input').value   || 'TEAM ZERO';
        document.getElementById('form-hub-powered').value = document.getElementById('hub-powered-input').value || 'TEAM ZERO';
        document.getElementById('hub-host-form').submit();
    }

    function submitHubDownload() {
        const urlVal = document.getElementById('hub-url-input').value.trim().toLowerCase().replace(/[^a-z0-9-]/g,'-').replace(/-+/g,'-').replace(/^-|-$/g,'') || 'mera-hub';
        document.getElementById('form-dl-url').value     = urlVal;
        document.getElementById('form-dl-title').value   = document.getElementById('hub-title-input').value   || 'TEAM ZERO';
        document.getElementById('form-dl-powered').value = document.getElementById('hub-powered-input').value || 'TEAM ZERO';
        document.getElementById('hub-dl-form').submit();
    }

    window.addEventListener('load', updatePreview);

    /* ── APK BUILDER ── */
    function toggleApkBuilder() {
        const sec    = document.getElementById('web-to-apk-section');
        const btn    = document.getElementById('apk-toggle-btn');
        const hubSec = document.getElementById('hub-section');
        const hubBtn = document.getElementById('hub-toggle-btn');
        const isHidden = sec.style.display === 'none';
        if (isHidden && hubSec.style.display !== 'none') {
            hubSec.style.display = 'none';
            hubBtn.style.background = 'linear-gradient(135deg,#ff7e5f,#feb47b)';
            hubBtn.innerHTML = '<i class="fas fa-magic"></i> CREATE HUB';
        }
        sec.style.display = isHidden ? 'block' : 'none';
        if (isHidden) {
            sec.scrollIntoView({ behavior: 'smooth', block: 'start' });
            btn.style.background = 'linear-gradient(135deg,#0369a1,#0284c7)';
            btn.innerHTML = '<i class="fas fa-times"></i> CLOSE APK BUILDER';
        } else {
            btn.style.background = 'linear-gradient(135deg,#0284c7,#38bdf8)';
            btn.innerHTML = '<i class="fas fa-android"></i> CREATE WEB TO APK';
        }
    }

    /* ── CUSTOM BUTTON LOGIC ── */
    function closeAllSections() {
        const hSec = document.getElementById('hub-section');
        const hBtn = document.getElementById('hub-toggle-btn');
        if (hSec && hSec.style.display !== 'none') {
            hSec.style.display = 'none';
            if (hBtn) { hBtn.style.background='linear-gradient(135deg,#ff7e5f,#feb47b)'; hBtn.innerHTML='<i class="fas fa-magic"></i> CREATE HUB'; }
        }
        const aSec = document.getElementById('web-to-apk-section');
        const aBtn = document.getElementById('apk-toggle-btn');
        if (aSec && aSec.style.display !== 'none') {
            aSec.style.display = 'none';
            if (aBtn) { aBtn.style.background='linear-gradient(135deg,#0284c7,#38bdf8)'; aBtn.innerHTML='<i class="fas fa-android"></i> CREATE WEB TO APK'; }
        }
        document.querySelectorAll('[id^="cb-section-"]').forEach(s => {
            if (s.style.display !== 'none') {
                s.style.display = 'none';
                const bid = s.id.replace('cb-section-', '');
                const b = document.getElementById('cb-toggle-' + bid);
                if (b) { const orig = b.dataset.origtext || ''; b.innerHTML = '<i class="fas fa-star"></i> ' + orig; }
            }
        });
    }

    function toggleCustomBtn(id) {
        const sec = document.getElementById('cb-section-' + id);
        const btn = document.getElementById('cb-toggle-' + id);
        if (!sec) return;
        const isHidden = sec.style.display === 'none';
        closeAllSections();
        if (isHidden) {
            sec.style.display = 'block';
            sec.scrollIntoView({ behavior: 'smooth', block: 'start' });
            if (btn) { const orig = btn.dataset.origtext || ''; btn.innerHTML = '<i class="fas fa-times"></i> CLOSE ' + orig; }
            const orig = document.getElementById('cb-html-orig-' + id);
            if (orig && orig.value.trim()) updateCbPreview(id, orig.value);
        }
    }

    function applyCustomName(id, target) {
        const nameEl  = document.getElementById('cb-name-' + id);
        const origEl  = document.getElementById('cb-html-orig-' + id);
        const storeEl = document.getElementById('cb-html-store-' + id);
        if (!nameEl || !origEl || !storeEl) return;
        const name = nameEl.value.trim() || target;
        const re = new RegExp(target.replace(/[.*+?^${}()|[\]\\]/g,'\\$&'), 'g');
        const replaced = origEl.value.replace(re, name);
        storeEl.value = replaced;
        updateCbPreview(id, replaced);
    }

    function updateCbPreview(id, html) {
        const frame = document.getElementById('cb-preview-' + id);
        if (!frame) return;
        const blob = new Blob([html], { type: 'text/html' });
        const old  = frame.src;
        frame.src  = URL.createObjectURL(blob);
        if (old && old.startsWith('blob:')) URL.revokeObjectURL(old);
    }

    function cbUrlPreview(id, val) {
        const clean = val.trim().toLowerCase().replace(/[^a-z0-9-]/g,'-').replace(/-+/g,'-').replace(/^-|-$/g,'') || 'meri-website';
        const prev  = document.getElementById('cb-url-preview-' + id);
        if (prev) prev.textContent = clean;
    }

    function hostCustomHtml(id) {
        const storeEl = document.getElementById('cb-html-store-' + id);
        const urlEl   = document.getElementById('cb-url-' + id);
        if (!storeEl) return;
        const urlVal = (urlEl ? urlEl.value.trim().toLowerCase().replace(/[^a-z0-9-]/g,'-').replace(/-+/g,'-').replace(/^-|-$/g,'') : '') || 'my-site-' + id;
        const form = document.createElement('form');
        form.method = 'POST'; form.style.display = 'none';
        const fields = { action_custom: 'host', custom_url: urlVal };
        for (const [k,v] of Object.entries(fields)) { const i = document.createElement('input'); i.name = k; i.value = v; form.appendChild(i); }
        const ta = document.createElement('textarea'); ta.name = 'custom_html'; ta.value = storeEl.value; form.appendChild(ta);
        document.body.appendChild(form); form.submit();
    }

    function downloadCustomHtml(id) {
        const storeEl = document.getElementById('cb-html-store-' + id);
        const urlEl   = document.getElementById('cb-url-' + id);
        if (!storeEl) return;
        const urlVal = (urlEl ? urlEl.value.trim().toLowerCase().replace(/[^a-z0-9-]/g,'-').replace(/-+/g,'-').replace(/^-|-$/g,'') : '') || 'my-site';
        const form = document.createElement('form');
        form.method = 'POST'; form.style.display = 'none';
        const fields = { action_custom: 'download', custom_url: urlVal };
        for (const [k,v] of Object.entries(fields)) { const i = document.createElement('input'); i.name = k; i.value = v; form.appendChild(i); }
        const ta = document.createElement('textarea'); ta.name = 'custom_html'; ta.value = storeEl.value; form.appendChild(ta);
        document.body.appendChild(form); form.submit();
    }

    /* ── APK: Host Website — HTML tab + URL tab dono support — FIXED ── */
    function apkHostHtml() {
        const ownerRaw = document.getElementById('apk-owner-name') ? document.getElementById('apk-owner-name').value.trim() : '';
        const slug = (ownerRaw || 'apk-site').toLowerCase().replace(/[^a-z0-9]/g,'-').replace(/-+/g,'-').replace(/^-|-$/g,'') || 'apk-site';
        const uniqueSlug = slug + '-' + Date.now().toString(36);

        // Konsa tab active hai check karo
        const urlPage    = document.getElementById('apk-page-url');
        const uploadPage = document.getElementById('apk-page-upload');
        const isUrlTab    = urlPage    && urlPage.classList.contains('active');

        var finalHtml = '';

        if (isUrlTab) {
            // URL tab — iframe wrapper page banao
            const url = (document.getElementById('apk-url') || {}).value || '';
            if (!url.trim()) {
                showNotification('❌ URL tab mein website URL dalo (example: https://example.com)');
                return;
            }
            finalHtml = '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0"><title>' + slug + '<\/title><style>*{margin:0;padding:0;box-sizing:border-box}html,body{width:100%;height:100vh;overflow:hidden}iframe{width:100%;height:100vh;border:none;display:block}<\/style><\/head><body><iframe src="' + url.trim() + '" allowfullscreen allow="fullscreen"><\/iframe><\/body><\/html>';
        } else {
            // HTML tab (default)
            const htmlEl = document.getElementById('apk-html');
            const html   = htmlEl ? htmlEl.value.trim() : '';
            if (!html) {
                // HTML empty hai — paste modal kholo taake user seedha code paste kare
                showNotification('📋 Pehle HTML box mein code paste karo!');
                showPasteModal(document.getElementById('apk-html'));
                // Paste hone ke baad user dobara HOST WEBSITE dabayega
                return;
            }
            const css = document.getElementById('apk-css') ? document.getElementById('apk-css').value : '';
            const js  = document.getElementById('apk-js')  ? document.getElementById('apk-js').value  : '';
            // Agar code mein already DOCTYPE hai to wrap mat karo, seedha use karo
            if (html.trim().toLowerCase().indexOf('<!doctype') === 0 || html.trim().toLowerCase().indexOf('<html') === 0) {
                finalHtml = html;
            } else {
                finalHtml = '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><style>' + css + '<\/style><\/head><body>' + html + '<scr'+'ipt>' + js + '<\/scr'+'ipt><\/body><\/html>';
            }
        }

        const form = document.createElement('form');
        form.method = 'POST'; form.style.display = 'none';
        const ta = document.createElement('textarea'); ta.name = 'html_code'; ta.value = finalHtml; form.appendChild(ta);
        const ui = document.createElement('input'); ui.name = 'code_custom_url'; ui.value = uniqueSlug; form.appendChild(ui);
        document.body.appendChild(form); form.submit();
    }

    /* ── PASTE BUTTON — 100% kaam karta hai HTTP + HTTPS + Mobile + Desktop ── */
    /* Modal approach: kisi bhi browser ya device pe guaranteed kaam karta hai  */
    function pasteToCodeEditor() {
        const ta = document.getElementById('code-editor-area');
        if (!ta) return;
        // STEP 1: Try clipboard API (HTTPS pe seedha paste)
        if (navigator.clipboard && typeof navigator.clipboard.readText === 'function') {
            navigator.clipboard.readText()
                .then(function(text) {
                    if (text && text.trim()) {
                        ta.value = text;
                        ta.scrollTop = 0;
                        showNotification('✅ CODE PASTE HO GAYA!');
                    } else {
                        showPasteModal(ta);
                    }
                })
                .catch(function() { showPasteModal(ta); });
        } else {
            // HTTP ya mobile pe clipboard API nahi chalta — modal open karo
            showPasteModal(ta);
        }
    }

    /* ── UNIVERSAL PASTE MODAL — 100% guaranteed ── */
    function showPasteModal(targetTA) {
        var existing = document.getElementById('paste-modal-overlay');
        if (existing) existing.remove();

        var overlay = document.createElement('div');
        overlay.id = 'paste-modal-overlay';
        overlay.style.cssText = 'position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,0.75);display:flex;align-items:center;justify-content:center;padding:16px;';
        overlay.innerHTML =
            '<div style="background:#fff;border-radius:18px;padding:26px;width:100%;max-width:500px;box-shadow:0 20px 60px rgba(0,0,0,0.55);">' +
            '<h3 style="margin-bottom:8px;color:#333;font-size:1.1rem;display:flex;align-items:center;gap:8px;"><span style="font-size:1.4rem;">📋</span> Code Paste Karo</h3>' +
            '<p style="font-size:.85rem;color:#666;margin-bottom:14px;">Neeche box mein apna code paste karo: <strong>Ctrl+V</strong> (desktop) ya <strong>press & hold → Paste</strong> (mobile)</p>' +
            '<textarea id="paste-modal-ta" style="width:100%;min-height:220px;padding:13px;border:2px solid #4361ee;border-radius:10px;font-family:monospace;font-size:.82rem;resize:vertical;outline:none;" placeholder="✂️ Yahan apna code paste karo..."></textarea>' +
            '<div style="display:flex;gap:10px;margin-top:14px;">' +
            '<button onclick="applyPasteModal()" style="flex:1;padding:13px;background:#4361ee;color:#fff;border:none;border-radius:10px;font-weight:bold;font-size:.95rem;cursor:pointer;transition:all .2s;" onmouseover="this.style.background=\'#3a0ca3\'" onmouseout="this.style.background=\'#4361ee\'">✅ APPLY (Code Lagao)</button>' +
            '<button onclick="document.getElementById(\'paste-modal-overlay\').remove()" style="padding:13px 18px;background:#f1f3f5;color:#333;border:none;border-radius:10px;font-weight:bold;cursor:pointer;">✕</button>' +
            '</div></div>';
        document.body.appendChild(overlay);
        window._pasteModalTarget = targetTA;
        // Auto focus for immediate paste on any device
        setTimeout(function(){ var mta = document.getElementById('paste-modal-ta'); if(mta) mta.focus(); }, 80);
    }

    function applyPasteModal() {
        var modalTA  = document.getElementById('paste-modal-ta');
        var targetTA = window._pasteModalTarget;
        if (modalTA && targetTA) {
            if (modalTA.value.trim()) {
                targetTA.value = modalTA.value;
                targetTA.scrollTop = 0;
                showNotification('✅ CODE PASTE HO GAYA!');
            } else {
                showNotification('⚠️ Pehle code paste karo phir Apply dabao!');
                return;
            }
        }
        var overlay = document.getElementById('paste-modal-overlay');
        if (overlay) overlay.remove();
    }

    function clearCodeEditor() {
        const ta = document.getElementById('code-editor-area');
        if (ta) ta.value = '';
        showNotification('🗑️ Editor clear ho gaya');
    }

    /* ── APK Owner Name Update ── */
    function apkUpdateOwnerName(val) {
        const name = val.trim() || 'TEAM ZERO';
        document.getElementById('apk-brand-name').textContent = name;
        document.getElementById('apk-footer-name').textContent = name;
        document.getElementById('apk-preview-title').textContent = name + ' · Preview';
        const appNameEl = document.getElementById('apk-appName');
        if(!appNameEl._userModified) { appNameEl.value = name + ' App'; apkUpdatePackageId(); }
    }
    document.getElementById('apk-appName').addEventListener('input', function(){ this._userModified = true; apkUpdatePackageId(); });

    function apkUpdatePackageId() {
        const name = document.getElementById('apk-appName').value.trim() || 'app';
        let clean = name.toLowerCase().replace(/[^a-z0-9]/g,'.').replace(/\.+/g,'.').replace(/^\.|\.$/g,'');
        if(!clean) clean='app';
        document.getElementById('apk-pkg').value = 'com.myapp.' + clean;
    }

    /* ── APK Menu ── */
    const apkMenuItems = document.querySelectorAll('#apkMenuEmbed .apk-menu-item');
    const apkPages = { html: document.getElementById('apk-page-html'), url: document.getElementById('apk-page-url'), upload: document.getElementById('apk-page-upload') };
    apkMenuItems.forEach(item => {
        item.addEventListener('click', function() {
            apkMenuItems.forEach(i => i.classList.remove('active'));
            this.classList.add('active');
            Object.values(apkPages).forEach(p => p.classList.remove('active'));
            if(apkPages[this.dataset.apkpage]) apkPages[this.dataset.apkpage].classList.add('active');
        });
    });

    /* ── APK Icon Preview ── */
    let apkIconBase64 = '';
    function apkPreviewIcon(input) {
        const f = input.files[0], p = document.getElementById('apk-iconPreview');
        if(!f){ p.innerHTML='<span class="apk-empty">icon</span>'; apkIconBase64=''; return; }
        const reader = new FileReader();
        reader.onload = function(){ p.innerHTML='<img src="'+reader.result+'">'; apkIconBase64=reader.result.split(',')[1]; };
        reader.readAsDataURL(f);
    }

    let apkSplashImageBase64 = '';
    function apkPreviewSplash(input) {
        const f = input.files[0];
        if(!f){ apkSplashImageBase64=''; return; }
        const reader = new FileReader();
        reader.onload = function(){ apkSplashImageBase64=reader.result.split(',')[1]; };
        reader.readAsDataURL(f);
    }

    let apkKeystoreBase64 = '';
    function apkLoadKeystore(input) {
        const f = input.files[0];
        if(!f){ apkKeystoreBase64=''; return; }
        const reader = new FileReader();
        reader.onload = function(){ apkKeystoreBase64=reader.result.split(',')[1]; };
        reader.readAsDataURL(f);
    }

    let apkUploadedFiles = {}, apkUploadedImages = {}, apkFileList = [];
    function apkHandleFiles(input) {
        apkUploadedFiles={}; apkUploadedImages={}; apkFileList=[];
        const fl = document.getElementById('apk-fileList');
        if(!input.files.length){ fl.innerHTML=''; return; }
        let pending=0;
        for(const f of input.files){
            pending++;
            const reader = new FileReader();
            const isImage = f.type.startsWith('image/') || /\.(png|jpg|jpeg|gif|svg|webp|bmp|avif|ico)$/i.test(f.name);
            reader.onload = function(){
                if(isImage) apkUploadedImages[f.name]=reader.result.split(',')[1];
                else apkUploadedFiles[f.name]=reader.result;
                apkFileList.push({name:f.name,size:f.size});
                pending--;
                if(!pending) apkUpdateFileList();
            };
            if(isImage) reader.readAsDataURL(f);
            else reader.readAsText(f);
        }
    }
    function apkUpdateFileList(){
        let html='';
        for(let i=0;i<apkFileList.length;i++){
            const f=apkFileList[i];
            html+='<div class="apk-file-item"><span class="apk-fn">'+f.name+'</span><span class="apk-fsz">'+(f.size<1024?f.size+'B':(f.size/1024).toFixed(1)+'KB')+'</span><button class="apk-fremove" onclick="apkRemoveFile('+i+')">✕</button></div>';
        }
        document.getElementById('apk-fileList').innerHTML=html;
    }
    function apkRemoveFile(i){ const f=apkFileList[i]; delete apkUploadedFiles[f.name]; delete apkUploadedImages[f.name]; apkFileList.splice(i,1); apkUpdateFileList(); }

    function apkPreview(){
        const ta=document.getElementById('apk-html');
        if(!ta.value.trim()) return;
        const h=ta.value, c=document.getElementById('apk-css').value||'', j=document.getElementById('apk-js').value||'';
        const blob=new Blob(['<!DOCTYPE html><html><head><meta charset="utf-8"><style>',c,'</style></head><body>',h,'<script>',j,'<\/script></body></html>'],{type:'text/html'});
        document.getElementById('apk-previewFrame').src=URL.createObjectURL(blob);
        document.getElementById('apk-previewPane').style.display='flex';
    }
    function apkClosePreview(){ document.getElementById('apk-previewPane').style.display='none'; document.getElementById('apk-previewFrame').src='about:blank'; }

    async function apkBuild(){
        const btn=document.getElementById('apk-btn'), st=document.getElementById('apk-status');
        const log=document.getElementById('apk-log'), dl=document.getElementById('apk-dl');
        const ownerName = (document.getElementById('apk-owner-name').value.trim() || 'TEAM ZERO');
        btn.disabled=true;
        st.className='building'; st.innerHTML='<span class="apk-pulse-dot"></span> Building '+ownerName+' APK...';
        log.style.display='none'; dl.className='apk-dl-box'; dl.innerHTML='';
        const pkg=document.getElementById('apk-pkg').value||'com.myapp.app';
        if(!/^[a-zA-Z][a-zA-Z0-9_]*(\.[a-zA-Z][a-zA-Z0-9_]*)+$/.test(pkg)){ st.className='failed'; st.innerHTML='<span class="apk-pulse-dot"></span>Invalid package name'; btn.disabled=false; return; }
        const body={ app_name: document.getElementById('apk-appName').value || ownerName + ' App', package_name: pkg, icon: apkIconBase64, pull_refresh: document.getElementById('apk-pullRefresh').checked, theme_color: document.getElementById('apk-themeColor').value, transparent_nav: document.getElementById('apk-transparentNav').checked, adguard_dns: document.getElementById('apk-adguardDns').checked, zoom_enabled: document.getElementById('apk-pinchZoom').checked, disable_copy_text: document.getElementById('apk-disableCopy').checked, hide_scrollbars: document.getElementById('apk-hideScrollbars').checked, splash_enabled: document.getElementById('apk-splashEnabled').checked, splash_duration: parseFloat(document.getElementById('apk-splashDuration').value)*1000, splash_color: document.getElementById('apk-splashColor').value, splash_image: apkSplashImageBase64, splash_use_icon: document.querySelector('input[name="apkSplashSrc"]:checked').value==='icon', splash_image_size: parseInt(document.getElementById('apk-splashImageSize').value), splash_animation: document.getElementById('apk-splashAnimation').value, version: document.getElementById('apk-version').value };
        const activePage=document.querySelector('#apkMenuEmbed .apk-menu-item.active').dataset.apkpage;
        if(activePage==='html'){ body.html=document.getElementById('apk-html').value; body.css=document.getElementById('apk-css').value; body.js=document.getElementById('apk-js').value; }
        else if(activePage==='upload'){ if(!apkUploadedFiles['index.html']){st.className='failed';st.innerHTML='<span class="apk-pulse-dot"></span>Missing index.html';btn.disabled=false;return;} body.html=apkUploadedFiles['index.html']; body.asset_files={}; for(const k in apkUploadedFiles){ if(k==='index.html') continue; body.asset_files[k]=btoa(unescape(encodeURIComponent(apkUploadedFiles[k]))); } for(const k in apkUploadedImages){ body.asset_files[k]=apkUploadedImages[k]; } }
        else { body.url=document.getElementById('apk-url').value; body.block_ads=document.getElementById('apk-blockAds').checked; }
        if(document.getElementById('apk-releaseSign').checked){ if(!apkKeystoreBase64||!document.getElementById('apk-ksPass').value||!document.getElementById('apk-keyAlias').value){st.className='failed';st.innerHTML='<span class="apk-pulse-dot"></span>Keystore required';btn.disabled=false;return;} body.keystore=apkKeystoreBase64; body.ks_pass=document.getElementById('apk-ksPass').value; body.key_alias=document.getElementById('apk-keyAlias').value; body.key_pass=document.getElementById('apk-keyPass').value; }
        try{
            const r=await fetch('/api/build',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
            const d=await r.json();
            if(!d.success){st.className='failed';st.innerHTML='<span class="apk-pulse-dot"></span>'+d.error;btn.disabled=false;return;}
            apkStreamLog(d.build_id, ownerName);
        }catch(e){ st.className='failed';st.innerHTML='<span class="apk-pulse-dot"></span>Network error';btn.disabled=false; }
    }

    function apkStreamLog(id, ownerName){
        const st=document.getElementById('apk-status'),log=document.getElementById('apk-log');
        const lh=document.getElementById('apk-logHeader'),dl=document.getElementById('apk-dl'),btn=document.getElementById('apk-btn');
        st.className='building';st.innerHTML='<span class="apk-pulse-dot"></span> Building '+ownerName+' APK...';
        lh.style.display='block';log.style.display='block';log.innerHTML='';
        const es=new EventSource('/api/log/'+id);
        let lines=[];
        es.onmessage=function(e){ lines.push(e.data); log.innerHTML=lines.map(l=>l.replace(/</g,'&lt;').replace(/>/g,'&gt;')).join('\n'); log.scrollTop=log.scrollHeight; };
        es.addEventListener('done',function(e){ es.close();st.className='done';st.innerHTML='<span class="apk-pulse-dot"></span>'+ownerName+' Build complete!'; dl.innerHTML='<a href="/api/download/'+id+'" download>Download '+e.data+'</a>'; dl.style.display='block';btn.disabled=false; });
        es.addEventListener('failed',function(e){ es.close();st.className='failed';st.innerHTML='<span class="apk-pulse-dot"></span>'+(e.data||'Build failed');btn.disabled=false; });
        es.onerror=function(){ es.close();st.className='failed';st.innerHTML='<span class="apk-pulse-dot"></span>Connection lost';btn.disabled=false; };
    }
    </script>
</body>
</html>
