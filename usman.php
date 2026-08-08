<?php
// ═══════════════════════════════════════════════════════════
//  USMAN.PHP — Admin Panel
//  Password: Usman0124
// ═══════════════════════════════════════════════════════════
session_start();

define('ADMIN_PASS',    'Usman0124');
define('CB_FILE',       __DIR__ . '/config/custom_buttons.json');
define('SITES_DIR',     __DIR__ . '/sites');

// ── Ensure config dir exists ──
if (!is_dir(__DIR__ . '/config')) mkdir(__DIR__ . '/config', 0777, true);

// ── Load / Save helpers ──
function loadButtons() {
    if (!file_exists(CB_FILE)) return [];
    $d = json_decode(@file_get_contents(CB_FILE), true);
    return is_array($d) ? $d : [];
}
function saveButtons(array $btns) {
    file_put_contents(CB_FILE, json_encode(array_values($btns), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
function generateId() {
    return 'btn_' . uniqid('', true);
}

// ── Handle Login / Logout ──
if (isset($_POST['admin_login'])) {
    if ($_POST['admin_pass'] === ADMIN_PASS) {
        $_SESSION['usman_admin'] = true;
    } else {
        $_SESSION['usman_error'] = 'Wrong password! Try again.';
    }
    header('Location: usman.php');
    exit;
}
if (isset($_GET['logout'])) {
    unset($_SESSION['usman_admin']);
    header('Location: usman.php');
    exit;
}

$loggedIn = !empty($_SESSION['usman_admin']);

// ── Admin Actions (only if logged in) ──
if ($loggedIn && $_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── CREATE button ──
    if (isset($_POST['action']) && $_POST['action'] === 'create') {
        $buttons  = loadButtons();
        $buttons[] = [
            'id'             => generateId(),
            'name'           => trim($_POST['btn_name']           ?? 'My Button'),
            'color'          => trim($_POST['btn_color']          ?? '#667eea'),
            'icon'           => trim($_POST['btn_icon']           ?? 'fa-star'),
            'html'           => $_POST['btn_html']                ?? '',
            'replace_target' => trim($_POST['btn_replace']        ?? 'TEAM ZERO'),
            'mode'           => trim($_POST['btn_mode']           ?? 'both'),
            'created_at'     => time(),
        ];
        saveButtons($buttons);
        $_SESSION['usman_msg'] = '✅ Button ban gaya!';
        header('Location: usman.php');
        exit;
    }

    // ── EDIT button ──
    if (isset($_POST['action']) && $_POST['action'] === 'edit') {
        $buttons = loadButtons();
        $editId  = $_POST['edit_id'] ?? '';
        foreach ($buttons as &$b) {
            if ($b['id'] === $editId) {
                $b['name']           = trim($_POST['btn_name']    ?? $b['name']);
                $b['color']          = trim($_POST['btn_color']   ?? $b['color']);
                $b['icon']           = trim($_POST['btn_icon']    ?? ($b['icon'] ?? 'fa-star'));
                $b['html']           = $_POST['btn_html']         ?? $b['html'];
                $b['replace_target'] = trim($_POST['btn_replace'] ?? $b['replace_target']);
                $b['mode']           = trim($_POST['btn_mode']    ?? $b['mode']);
                break;
            }
        }
        unset($b);
        saveButtons($buttons);
        $_SESSION['usman_msg'] = '✅ Button update ho gaya!';
        header('Location: usman.php');
        exit;
    }

    // ── DELETE button ──
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $buttons = loadButtons();
        $delId   = $_POST['del_id'] ?? '';
        $buttons = array_filter($buttons, fn($b) => $b['id'] !== $delId);
        saveButtons($buttons);
        $_SESSION['usman_msg'] = '🗑️ Button delete ho gaya!';
        header('Location: usman.php');
        exit;
    }

    // ── DELETE HOSTED SITE ──
    if (isset($_POST['action']) && $_POST['action'] === 'delete_site') {
        $siteName = basename(trim($_POST['site_name'] ?? ''));
        if ($siteName && preg_match('/^[a-z0-9-]+$/', $siteName)) {
            $paths = [
                SITES_DIR . '/' . $siteName . '.html',
                SITES_DIR . '/' . $siteName . '.htm',
                SITES_DIR . '/' . $siteName,
            ];
            foreach ($paths as $p) {
                if (is_file($p)) { unlink($p); break; }
                if (is_dir($p)) {
                    $it = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($p, RecursiveDirectoryIterator::SKIP_DOTS),
                        RecursiveIteratorIterator::CHILD_FIRST
                    );
                    foreach ($it as $fi) {
                        $fi->isDir() ? rmdir($fi->getRealPath()) : unlink($fi->getRealPath());
                    }
                    rmdir($p);
                    break;
                }
            }
            $_SESSION['usman_msg'] = '🗑️ Site delete ho gayi!';
        }
        header('Location: usman.php');
        exit;
    }
}

// ── Load data for display ──
$buttons       = $loggedIn ? loadButtons() : [];
$editingId     = $_GET['edit'] ?? null;
$editingBtn    = null;
if ($editingId) {
    foreach ($buttons as $b) {
        if ($b['id'] === $editingId) { $editingBtn = $b; break; }
    }
}

// ── Load hosted sites ──
$hosted_sites = [];
if ($loggedIn && is_dir(SITES_DIR)) {
    foreach (scandir(SITES_DIR) as $f) {
        if ($f === '.' || $f === '..') continue;
        $fp   = SITES_DIR . '/' . $f;
        $name = pathinfo($f, PATHINFO_FILENAME);
        $ext  = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        if (is_dir($fp) || in_array($ext, ['html','htm','css','js','php','txt','json'])) {
            $proto   = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $base    = $proto . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
            $base    = rtrim(str_replace('usman.php','', $base), '/');
            $url     = $base . '/' . ($is_dir = is_dir($fp) ? $f : $name);
            $size    = is_dir($fp) ? '—' : round(filesize($fp)/1024, 1) . ' KB';
            $hosted_sites[] = [
                'display_name' => is_dir($fp) ? $f : $name,
                'raw'          => is_dir($fp) ? $f : $name,
                'url'          => $url,
                'size'         => $size,
                'time'         => filemtime($fp),
                'is_dir'       => is_dir($fp),
            ];
        }
    }
    usort($hosted_sites, fn($a,$b) => $b['time'] - $a['time']);
}

$msg = $_SESSION['usman_msg']   ?? null;
$err = $_SESSION['usman_error'] ?? null;
unset($_SESSION['usman_msg'], $_SESSION['usman_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Usman Admin Panel</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',Arial,sans-serif}
body{background:linear-gradient(135deg,#0f0c29,#302b63,#24243e);min-height:100vh;padding:20px;color:#fff}
.container{max-width:1100px;margin:0 auto}

/* Login */
.login-wrap{display:flex;align-items:center;justify-content:center;min-height:80vh}
.login-card{background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.15);backdrop-filter:blur(18px);border-radius:24px;padding:40px 36px;width:100%;max-width:420px;text-align:center}
.login-card h1{font-size:1.8rem;font-weight:800;margin-bottom:8px;background:linear-gradient(135deg,#fff,#a5b4fc);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.login-card p{color:#a5b4fc;margin-bottom:28px;font-size:.9rem}
.login-card input[type=password]{width:100%;padding:14px 18px;border:1px solid rgba(255,255,255,.2);border-radius:14px;background:rgba(255,255,255,.08);color:#fff;font-size:1rem;margin-bottom:16px}
.login-card input[type=password]:focus{outline:none;border-color:#a5b4fc}
.login-btn{width:100%;padding:14px;border:none;border-radius:14px;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;font-size:1rem;font-weight:700;cursor:pointer;letter-spacing:.04em;transition:all .3s}
.login-btn:hover{transform:translateY(-2px);box-shadow:0 8px 25px rgba(102,126,234,.5)}
.error-msg{background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.4);padding:12px;border-radius:10px;color:#fca5a5;margin-bottom:16px;font-size:.85rem}

/* Admin Layout */
.admin-header{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:16px;padding:16px 24px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px}
.admin-header h1{font-size:1.5rem;font-weight:800;background:linear-gradient(135deg,#fff,#a5b4fc);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.logout-btn{padding:8px 20px;border:1px solid rgba(255,255,255,.3);border-radius:30px;background:transparent;color:#fff;cursor:pointer;font-size:.85rem;transition:all .2s}
.logout-btn:hover{background:rgba(255,255,255,.1)}

.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:20px}
@media(max-width:768px){.grid-2{grid-template-columns:1fr}}

.panel{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);border-radius:18px;padding:24px}
.panel-title{font-size:1rem;font-weight:700;color:#a5b4fc;text-transform:uppercase;letter-spacing:.08em;margin-bottom:20px;display:flex;align-items:center;gap:8px}

/* Form elements */
.form-group{margin-bottom:16px}
.form-group label{display:block;font-size:.78rem;font-weight:600;color:#cbd5e1;margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em}
.form-group input[type=text],
.form-group input[type=color],
.form-group select,
.form-group textarea{width:100%;padding:12px 16px;border:1px solid rgba(255,255,255,.15);border-radius:12px;background:rgba(2,6,23,.6);color:#fff;font-size:.9rem;font-family:'Segoe UI',monospace}
.form-group textarea{min-height:220px;resize:vertical;font-family:monospace;font-size:.8rem;line-height:1.6}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{outline:none;border-color:#a5b4fc}
.form-group .hint{font-size:.7rem;color:#64748b;margin-top:4px}

.color-row{display:flex;align-items:center;gap:10px}
.color-row input[type=color]{width:52px;height:44px;padding:4px;cursor:pointer;border-radius:10px}
.color-row input[type=text]{flex:1}

.submit-btn{width:100%;padding:14px;border:none;border-radius:14px;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;font-size:.95rem;font-weight:700;cursor:pointer;transition:all .3s;display:flex;align-items:center;justify-content:center;gap:8px}
.submit-btn:hover{transform:translateY(-2px);box-shadow:0 8px 25px rgba(102,126,234,.4)}
.submit-btn.danger{background:linear-gradient(135deg,#ef4444,#dc2626)}
.submit-btn.warning{background:linear-gradient(135deg,#f59e0b,#d97706)}

/* Button list */
.btn-list{display:flex;flex-direction:column;gap:12px;max-height:420px;overflow-y:auto;padding-right:4px}
.btn-item{background:rgba(2,6,23,.5);border:1px solid rgba(255,255,255,.1);border-radius:14px;padding:16px;display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.btn-swatch{width:36px;height:36px;border-radius:10px;flex-shrink:0;border:1px solid rgba(255,255,255,.2)}
.btn-info{flex:1;min-width:0}
.btn-info .btn-name{font-weight:700;font-size:.95rem;word-break:break-word}
.btn-info .btn-meta{font-size:.72rem;color:#94a3b8;margin-top:3px}
.btn-actions{display:flex;gap:6px;flex-shrink:0}
.icon-btn{width:34px;height:34px;border-radius:8px;border:1px solid rgba(255,255,255,.15);background:rgba(255,255,255,.05);color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.85rem;transition:all .2s}
.icon-btn:hover{background:rgba(255,255,255,.15)}
.icon-btn.red:hover{background:rgba(239,68,68,.3);border-color:#ef4444}

/* Sites list */
.site-list{display:flex;flex-direction:column;gap:8px;max-height:340px;overflow-y:auto;padding-right:4px}
.site-item{background:rgba(2,6,23,.5);border:1px solid rgba(255,255,255,.1);border-radius:12px;padding:12px 14px;display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.site-item .site-name{font-weight:600;font-size:.85rem;flex:1;word-break:break-all}
.site-item .site-url{font-size:.72rem;color:#38bdf8;word-break:break-all;width:100%}
.site-item .site-meta{font-size:.7rem;color:#64748b}
.site-item .site-btns{display:flex;gap:6px;margin-left:auto}

/* Notification */
.flash{padding:14px 20px;border-radius:12px;margin-bottom:20px;font-size:.9rem;font-weight:600}
.flash.success{background:rgba(16,185,129,.15);border:1px solid rgba(16,185,129,.4);color:#6ee7b7}
.flash.error{background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.4);color:#fca5a5}

/* Preview inline */
.html-preview-box{border:1px solid rgba(255,255,255,.15);border-radius:12px;overflow:hidden;margin-top:10px}
.html-preview-box iframe{width:100%;height:260px;border:none;background:white;display:block}

/* Paste toolbar */
.editor-toolbar{display:flex;gap:6px;margin-bottom:6px;flex-wrap:wrap}
.editor-toolbar button{padding:6px 14px;border:1px solid rgba(255,255,255,.2);border-radius:8px;background:rgba(255,255,255,.07);color:#fff;font-size:.75rem;cursor:pointer;transition:all .2s}
.editor-toolbar button:hover{background:rgba(255,255,255,.15)}

/* Mode badges */
.badge-mode{display:inline-block;padding:2px 10px;border-radius:20px;font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em}
.badge-host{background:rgba(16,185,129,.2);color:#6ee7b7;border:1px solid rgba(16,185,129,.3)}
.badge-download{background:rgba(249,115,22,.2);color:#fdba74;border:1px solid rgba(249,115,22,.3)}
.badge-both{background:rgba(102,126,234,.2);color:#a5b4fc;border:1px solid rgba(102,126,234,.3)}

.empty-state{text-align:center;padding:30px;color:#475569;font-size:.85rem}
.empty-state i{font-size:2rem;opacity:.3;display:block;margin-bottom:8px}

/* Scrollbar */
::-webkit-scrollbar{width:4px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:rgba(255,255,255,.2);border-radius:4px}

/* ── Smart Designer ── */
.smart-designer{background:linear-gradient(135deg,rgba(15,23,42,0.95),rgba(3,105,161,0.12));border:1px solid rgba(56,189,248,.2);border-radius:20px;padding:24px;margin-bottom:26px;box-shadow:0 8px 32px rgba(0,0,0,.5)}
.smart-header{display:flex;align-items:center;gap:14px;margin-bottom:20px;flex-wrap:wrap}
.smart-icon{width:46px;height:46px;background:linear-gradient(135deg,#0284c7,#38bdf8);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0;box-shadow:0 4px 14px rgba(2,132,199,.4)}
.smart-title{font-size:1.1rem;font-weight:800;color:#fff}
.smart-subtitle{font-size:.75rem;color:#64748b;margin-top:2px}
.smart-badge{background:rgba(56,189,248,.15);border:1px solid rgba(56,189,248,.4);color:#38bdf8;font-size:.6rem;font-weight:800;padding:3px 10px;border-radius:20px;text-transform:uppercase;letter-spacing:.08em;margin-left:auto}
.smart-inputs{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px}
@media(max-width:600px){.smart-inputs{grid-template-columns:1fr}}
.smart-field label{font-size:.72rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;display:block;margin-bottom:5px}
.smart-field input,.smart-field textarea{width:100%;padding:11px 14px;background:rgba(2,6,23,.8);border:1px solid rgba(255,255,255,.12);border-radius:12px;color:#fff;font-size:.85rem;resize:none}
.smart-field input:focus,.smart-field textarea:focus{outline:none;border-color:#38bdf8}
.smart-field textarea{min-height:80px;font-family:monospace;font-size:.78rem;line-height:1.5}
.auto-btn{display:inline-flex;align-items:center;gap:8px;padding:11px 22px;background:linear-gradient(135deg,#0284c7,#38bdf8);border:none;border-radius:40px;color:#fff;font-size:.82rem;font-weight:800;cursor:pointer;letter-spacing:.04em;transition:all .3s;box-shadow:0 4px 16px rgba(2,132,199,.4)}
.auto-btn:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(2,132,199,.55)}
.auto-btn.scanning{background:linear-gradient(135deg,#7c3aed,#a78bfa);animation:scanPulse 1.2s ease-in-out infinite}
@keyframes scanPulse{0%,100%{opacity:1}50%{opacity:.6}}
.ai-result-bar{margin-top:14px;padding:14px 18px;background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.3);border-radius:14px;display:none;align-items:center;gap:12px;flex-wrap:wrap}
.ai-result-bar.visible{display:flex}
.ai-result-dot{width:10px;height:10px;border-radius:50%;background:#10b981;box-shadow:0 0 8px #10b981;flex-shrink:0;animation:dotPulse 1.4s ease-in-out infinite}
@keyframes dotPulse{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(1.4);opacity:.6}}
.ai-result-text{font-size:.82rem;color:#e2e8f0;flex:1}
.ai-result-text strong{color:#6ee7b7}

/* ── Live Preview Box ── */
.live-preview-wrap{background:rgba(2,6,23,.7);border:1px solid rgba(255,255,255,.1);border-radius:16px;padding:18px;margin-top:16px;display:none}
.live-preview-wrap.visible{display:block}
.live-preview-label{font-size:.7rem;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.08em;margin-bottom:10px}
.live-preview-stage{display:flex;align-items:center;justify-content:center;min-height:80px;gap:12px;flex-wrap:wrap}

/* ── Design Cards ── */
.designs-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px}
.design-card{background:rgba(2,6,23,.6);border:2px solid rgba(255,255,255,.1);border-radius:18px;padding:20px;display:flex;flex-direction:column;gap:12px;transition:all .25s;position:relative;cursor:pointer}
.design-card:hover{border-color:rgba(255,255,255,.3);transform:translateY(-3px);box-shadow:0 10px 28px rgba(0,0,0,.5)}

/* SELECTED — bright blue glow, heavy border */
.design-card.selected{
  border-color:#38bdf8 !important;
  background:rgba(2,56,100,.55) !important;
  box-shadow:0 0 0 3px rgba(56,189,248,.45), 0 8px 28px rgba(2,132,199,.4) !important;
  transform:translateY(-4px);
}
/* AI PICK — bright purple glow, heavy border */
.design-card.ai-pick{
  border-color:#a78bfa !important;
  background:rgba(55,20,110,.45) !important;
  box-shadow:0 0 0 3px rgba(167,139,250,.5), 0 8px 28px rgba(124,58,237,.4) !important;
  transform:translateY(-4px);
}
.design-card-top{display:flex;align-items:center;justify-content:space-between}
.design-card-num{font-size:.65rem;font-weight:800;color:#334155;text-transform:uppercase;letter-spacing:.08em}
.design-card-name{font-size:.9rem;font-weight:800;color:#e2e8f0}
.design-card-desc{font-size:.72rem;color:#64748b;line-height:1.55}
.design-select-btn{margin-top:auto;padding:9px 0;border-radius:10px;border:1px solid rgba(255,255,255,.15);background:rgba(255,255,255,.05);color:#fff;font-size:.78rem;font-weight:700;cursor:pointer;transition:all .2s;width:100%}
.design-select-btn:hover{background:rgba(255,255,255,.13);border-color:#a5b4fc}
.design-select-btn.picked{background:linear-gradient(135deg,#0284c7,#38bdf8) !important;border-color:#38bdf8 !important;color:#fff;font-size:.82rem}
.design-card.ai-pick .design-select-btn{border-color:#a78bfa;color:#a78bfa}

/* Badges */
.selected-badge{display:none;position:absolute;top:-10px;left:50%;transform:translateX(-50%);font-size:.62rem;font-weight:900;padding:4px 12px;border-radius:20px;text-transform:uppercase;letter-spacing:.07em;white-space:nowrap;z-index:5}
.design-card.selected .selected-badge{display:block;background:#38bdf8;color:#020617;box-shadow:0 3px 10px rgba(56,189,248,.6)}
.ai-pick-ribbon{display:none;position:absolute;top:-10px;left:50%;transform:translateX(-50%);background:linear-gradient(135deg,#7c3aed,#a78bfa);color:#fff;font-size:.62rem;font-weight:900;padding:4px 12px;border-radius:20px;text-transform:uppercase;letter-spacing:.07em;white-space:nowrap;z-index:5;box-shadow:0 3px 10px rgba(124,58,237,.5)}
.design-card.ai-pick .ai-pick-ribbon{display:block}
.design-card.selected .ai-pick-ribbon{display:none}

/* Confirm panel */
.confirm-panel{background:linear-gradient(135deg,rgba(2,6,23,.95),rgba(3,105,161,.15));border:2px solid rgba(56,189,248,.35);border-radius:20px;padding:24px;margin-top:22px;display:none}
.confirm-panel.visible{display:block;animation:fadeUp .3s ease}
@keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
.confirm-title{font-size:1rem;font-weight:800;color:#fff;margin-bottom:16px;display:flex;align-items:center;gap:8px}
.confirm-summary{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:18px}
@media(max-width:560px){.confirm-summary{grid-template-columns:1fr}}
.confirm-field label{display:block;font-size:.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px}
.confirm-field input,.confirm-field select,.confirm-field textarea{width:100%;padding:10px 13px;background:rgba(2,6,23,.8);border:1px solid rgba(255,255,255,.13);border-radius:10px;color:#fff;font-size:.85rem}
.confirm-field textarea{min-height:90px;font-family:monospace;font-size:.78rem;resize:vertical}
.confirm-field input:focus,.confirm-field select:focus,.confirm-field textarea:focus{outline:none;border-color:#38bdf8}
.confirm-btn-big{width:100%;padding:16px;background:linear-gradient(135deg,#10b981,#059669);border:none;border-radius:14px;color:#fff;font-size:1rem;font-weight:900;cursor:pointer;letter-spacing:.05em;transition:all .3s;display:flex;align-items:center;justify-content:center;gap:10px;box-shadow:0 8px 24px rgba(16,185,129,.4);text-transform:uppercase;margin-top:6px}
.confirm-btn-big:hover{transform:translateY(-2px);box-shadow:0 12px 30px rgba(16,185,129,.55)}

/* ── Icon Picker ── */
.icon-picker-section{margin:16px 0;background:rgba(2,6,23,.7);border:1px solid rgba(255,255,255,.1);border-radius:16px;padding:16px}
.icon-picker-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;gap:8px}
.icon-picker-title{font-size:.75rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.06em}
.ai-icon-badge{font-size:.6rem;font-weight:800;padding:3px 9px;border-radius:20px;background:rgba(167,139,250,.2);border:1px solid rgba(167,139,250,.4);color:#a78bfa;text-transform:uppercase;letter-spacing:.06em}
.icon-grid{display:flex;flex-wrap:wrap;gap:7px}
.icon-chip{width:36px;height:36px;border-radius:10px;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.05);color:#94a3b8;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.85rem;transition:all .18s;position:relative;flex-shrink:0}
.icon-chip:hover{background:rgba(255,255,255,.14);border-color:rgba(255,255,255,.35);color:#fff;transform:scale(1.12)}
.icon-chip.selected{background:linear-gradient(135deg,#0284c7,#38bdf8);border-color:#38bdf8;color:#fff;box-shadow:0 4px 12px rgba(2,132,199,.4);transform:scale(1.1)}
.icon-chip.ai-icon{border-color:#a78bfa;background:rgba(167,139,250,.15);color:#a78bfa}
.icon-chip .icon-tip{display:none;position:absolute;bottom:calc(100% + 6px);left:50%;transform:translateX(-50%);background:#1e293b;color:#e2e8f0;font-size:.6rem;padding:3px 7px;border-radius:6px;white-space:nowrap;pointer-events:none;z-index:10;border:1px solid rgba(255,255,255,.15)}
.icon-chip:hover .icon-tip{display:block}
.selected-icon-label{font-size:.72rem;color:#64748b;margin-top:10px}<br>
</style>
</head>
<body>
<div class="container">

<?php if (!$loggedIn): ?>
<!-- ═══════ LOGIN SCREEN ═══════ -->
<div class="login-wrap">
    <div class="login-card">
        <h1>🔐 Admin Panel</h1>
        <p>Sirf Usman ka access hai — password enter karo</p>
        <?php if ($err): ?><div class="error-msg"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($err); ?></div><?php endif; ?>
        <form method="POST">
            <input type="password" name="admin_pass" placeholder="Password enter karo..." autofocus>
            <button type="submit" name="admin_login" class="login-btn">
                <i class="fas fa-unlock"></i> LOGIN
            </button>
        </form>
    </div>
</div>

<?php else: ?>
<!-- ═══════ ADMIN PANEL ═══════ -->

<!-- Header -->
<div class="admin-header">
    <div style="display:flex;align-items:center;gap:14px;">
        <h1>⚙️ Usman Admin Panel</h1>
        <a href="index.php" target="_blank" style="color:#38bdf8;font-size:.8rem;text-decoration:none;border:1px solid rgba(56,189,248,.3);padding:5px 12px;border-radius:20px;">
            <i class="fas fa-external-link-alt"></i> Main Site
        </a>
    </div>
    <a href="?logout" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<?php if ($msg): ?><div class="flash success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

<!-- Tabs nav -->
<div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;">
    <button onclick="showTab('tab-buttons')" id="nav-buttons" class="tab-nav active">
        <i class="fas fa-th-large"></i> Custom Buttons
    </button>
    <button onclick="showTab('tab-sites')" id="nav-sites" class="tab-nav">
        <i class="fas fa-globe"></i> Hosted Sites
    </button>
    <button onclick="showTab('tab-designs')" id="nav-designs" class="tab-nav">
        <i class="fas fa-palette"></i> Button Designs
    </button>
</div>

<!-- ══════ TAB: CUSTOM BUTTONS ══════ -->
<div id="tab-buttons">
<div class="grid-2">

    <!-- LEFT: Create / Edit Form -->
    <div class="panel">
        <div class="panel-title">
            <?php if ($editingBtn): ?>
            <i class="fas fa-edit"></i> Button Edit Karo
            <?php else: ?>
            <i class="fas fa-plus-circle"></i> Naya Button Banao
            <?php endif; ?>
        </div>

        <form method="POST" id="main-form">
            <input type="hidden" name="action" value="<?php echo $editingBtn ? 'edit' : 'create'; ?>">
            <?php if ($editingBtn): ?>
            <input type="hidden" name="edit_id" value="<?php echo htmlspecialchars($editingBtn['id']); ?>">
            <?php endif; ?>

            <div class="form-group">
                <label>Button Ka Naam</label>
                <input type="text" name="btn_name"
                    value="<?php echo htmlspecialchars($editingBtn['name'] ?? ''); ?>"
                    placeholder="e.g. MY TEMPLATE" required>
            </div>

            <!-- ── ICON PICKER ── -->
            <div class="form-group">
                <label><i class="fas fa-icons"></i> Button Ka Icon</label>
                <input type="hidden" name="btn_icon" id="cb-icon-hidden" value="<?php echo htmlspecialchars($editingBtn['icon'] ?? 'fa-star'); ?>">
                <div id="cb-icon-label" style="font-size:.75rem;color:#38bdf8;margin-bottom:8px;font-weight:700;">
                    <i class="fas <?php echo htmlspecialchars($editingBtn['icon'] ?? 'fa-star'); ?>"></i>
                    Selected: <?php echo htmlspecialchars($editingBtn['icon'] ?? 'fa-star'); ?>
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:6px;padding:12px;background:rgba(0,0,0,.3);border-radius:12px;border:1px solid rgba(255,255,255,.1);">
                    <?php
                    $icons = [
                        'fa-star'=>'Star','fa-magic'=>'Magic','fa-globe'=>'Web','fa-download'=>'Download',
                        'fa-upload'=>'Upload','fa-code'=>'Code','fa-robot'=>'Bot/AI','fa-android'=>'Android',
                        'fa-terminal'=>'Terminal','fa-cog'=>'Settings','fa-tools'=>'Tools','fa-file-code'=>'File Code',
                        'fa-layer-group'=>'Template','fa-paint-brush'=>'Design','fa-image'=>'Image','fa-video'=>'Video',
                        'fa-music'=>'Music','fa-gamepad'=>'Game','fa-bolt'=>'Bolt','fa-fire'=>'Fire',
                        'fa-crown'=>'VIP','fa-heart'=>'Heart','fa-lock'=>'Lock','fa-key'=>'Key',
                        'fa-shield-alt'=>'Shield','fa-link'=>'Link','fa-copy'=>'Copy','fa-info-circle'=>'Info',
                        'fa-plus-circle'=>'Add','fa-trash'=>'Delete','fa-pencil-alt'=>'Edit','fa-eye'=>'View',
                        'fa-search'=>'Search','fa-user'=>'User','fa-users'=>'Team','fa-share-alt'=>'Share',
                        'fa-cloud'=>'Cloud','fa-database'=>'Data','fa-chart-bar'=>'Stats',
                    ];
                    $curIcon = $editingBtn['icon'] ?? 'fa-star';
                    foreach ($icons as $cls => $tip):
                        $isBrand = in_array($cls, ['fa-android','fa-telegram','fa-whatsapp','fa-github']);
                        $lib = $isBrand ? 'fab' : 'fas';
                        $sel = $cls === $curIcon ? 'style="background:linear-gradient(135deg,#0284c7,#38bdf8);border-color:#38bdf8;color:#fff;transform:scale(1.1);"' : '';
                    ?>
                    <div title="<?php echo $tip; ?>" onclick="cbPickIcon(this,'<?php echo $cls; ?>','<?php echo $lib; ?>')"
                         <?php echo $sel; ?>
                         style="width:36px;height:36px;border-radius:10px;border:1px solid rgba(255,255,255,.15);background:rgba(255,255,255,.07);color:#94a3b8;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.88rem;transition:all .18s;">
                        <i class="<?php echo $lib; ?> <?php echo $cls; ?>"></i>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ── COLOR SWATCHES ── -->
            <div class="form-group">
                <label><i class="fas fa-palette"></i> Button Ka Colour</label>
                <input type="hidden" name="btn_color" id="colorHidden"
                    value="<?php echo htmlspecialchars($editingBtn['color'] ?? '#667eea'); ?>">

                <!-- Animated gradient swatches -->
                <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px;">
                    <?php
                    $swatches = [
                        ['#667eea,#764ba2','#667eea','💜 Purple'],['#0284c7,#38bdf8','#0284c7','🔵 Blue'],
                        ['#10b981,#059669','#10b981','🟢 Green'],['#f59e0b,#ef4444','#f59e0b','🔴 Fire'],
                        ['#ec4899,#f43f5e','#ec4899','🩷 Pink'],['#8b5cf6,#a78bfa','#8b5cf6','🔮 Violet'],
                        ['#06b6d4,#0ea5e9','#06b6d4','🩵 Cyan'],['#f97316,#fb923c','#f97316','🟠 Orange'],
                        ['#ef4444,#dc2626','#ef4444','🔴 Red'],['#14b8a6,#0d9488','#14b8a6','🌊 Teal'],
                        ['#a855f7,#d946ef','#a855f7','🎆 Fuchsia'],['#1e293b,#334155','#1e293b','🖤 Dark'],
                        ['#f59e0b,#d97706','#f59e0b','🟡 Gold'],['#22c55e,#16a34a','#22c55e','🍀 Lime'],
                        ['#3b82f6,#1d4ed8','#3b82f6','🔷 Indigo'],['#ff6b6b,#ee5a24','#ff6b6b','🌶️ Coral'],
                    ];
                    $curColor = $editingBtn['color'] ?? '#667eea';
                    foreach ($swatches as $sw):
                        $active = (strpos($curColor, explode(',',$sw[0])[0]) !== false) ? 'outline:3px solid #fff;outline-offset:2px;' : '';
                    ?>
                    <div onclick="cbPickColor(this,'<?php echo $sw[1]; ?>','<?php echo $sw[0]; ?>')"
                         title="<?php echo $sw[2]; ?>"
                         style="width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,<?php echo $sw[0]; ?>);cursor:pointer;transition:all .2s;<?php echo $active; ?>box-shadow:0 3px 8px rgba(0,0,0,.4);flex-shrink:0;">
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Manual hex input -->
                <div class="color-row">
                    <input type="color" id="colorPicker"
                        value="<?php echo htmlspecialchars($editingBtn['color'] ?? '#667eea'); ?>"
                        oninput="cbPickColor(null,this.value,this.value)">
                    <input type="text" id="colorText"
                        value="<?php echo htmlspecialchars($editingBtn['color'] ?? '#667eea'); ?>"
                        placeholder="#667eea"
                        oninput="cbPickColor(null,this.value,this.value)">
                </div>
                <div id="cb-color-preview" style="margin-top:8px;padding:10px 16px;border-radius:10px;text-align:center;font-weight:800;font-size:.85rem;color:#fff;background:linear-gradient(135deg,<?php echo $editingBtn['color'] ?? '#667eea,#764ba2'; ?>);box-shadow:0 4px 14px rgba(0,0,0,.3);">
                    <i class="fas fa-star" id="cb-preview-icon"></i>
                    <span id="cb-preview-name"><?php echo htmlspecialchars($editingBtn['name'] ?? 'Button Preview'); ?></span>
                </div>
            </div>

            <div class="form-group">
                <label>Replace Target <span style="color:#64748b;font-weight:normal;">(yeh text replace hoga)</span></label>
                <input type="text" name="btn_replace"
                    value="<?php echo htmlspecialchars($editingBtn['replace_target'] ?? 'TEAM ZERO'); ?>"
                    placeholder="TEAM ZERO">
                <div class="hint">Jab user apna naam likhe ga, is text ki jagah us ka naam aayega (e.g. "TEAM ZERO" → "Usman")</div>
            </div>

            <div class="form-group">
                <label>Mode</label>
                <select name="btn_mode">
                    <option value="both"     <?php echo (($editingBtn['mode'] ?? 'both') === 'both'     ? 'selected' : ''); ?>>🔀 Host + Download dono</option>
                    <option value="host"     <?php echo (($editingBtn['mode'] ?? '') === 'host'         ? 'selected' : ''); ?>>☁️ Sirf Host karo</option>
                    <option value="download" <?php echo (($editingBtn['mode'] ?? '') === 'download'     ? 'selected' : ''); ?>>⬇️ Sirf Download karo</option>
                </select>
            </div>

            <div class="form-group">
                <label>HTML Content</label>
                <div class="editor-toolbar">
                    <button type="button" onclick="pasteHtml()"><i class="fas fa-paste"></i> Paste</button>
                    <button type="button" onclick="clearHtml()"><i class="fas fa-trash"></i> Clear</button>
                    <button type="button" onclick="previewHtml()"><i class="fas fa-eye"></i> Preview</button>
                </div>
                <textarea name="btn_html" id="htmlEditor"
                    placeholder="<h1>Hello {{TEAM ZERO}}</h1>&#10;<p>Apna HTML yahan paste karo...</p>"><?php echo htmlspecialchars($editingBtn['html'] ?? ''); ?></textarea>
            </div>

            <!-- Inline preview box -->
            <div class="html-preview-box" id="previewBox" style="display:none;">
                <iframe id="previewFrame" sandbox="allow-scripts allow-same-origin"></iframe>
            </div>

            <button type="submit" class="submit-btn" onclick="syncColor()" style="margin-top:6px;">
                <i class="fas fa-<?php echo $editingBtn ? 'save' : 'plus'; ?>"></i>
                <?php echo $editingBtn ? 'Update Button' : 'Button Banao'; ?>
            </button>

            <?php if ($editingBtn): ?>
            <a href="usman.php" style="display:block;text-align:center;margin-top:10px;color:#94a3b8;font-size:.82rem;text-decoration:none;">
                ← Cancel, naya button banao
            </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- RIGHT: Existing Buttons List -->
    <div class="panel">
        <div class="panel-title"><i class="fas fa-list"></i> Banaye Gaye Buttons (<?php echo count($buttons); ?>)</div>
        <?php if (empty($buttons)): ?>
        <div class="empty-state"><i class="fas fa-inbox"></i>Abhi koi button nahi hai. Pehla button banao!</div>
        <?php else: ?>
        <div class="btn-list">
            <?php foreach ($buttons as $b): ?>
            <div class="btn-item">
                <div class="btn-swatch" style="background:<?php echo htmlspecialchars($b['color'] ?? '#667eea'); ?>;"></div>
                <div class="btn-info">
                    <div class="btn-name"><?php echo htmlspecialchars($b['name']); ?></div>
                    <div class="btn-meta">
                        Replace: <code style="color:#38bdf8;"><?php echo htmlspecialchars($b['replace_target'] ?? 'TEAM ZERO'); ?></code>
                        &nbsp;|&nbsp;
                        <?php
                        $mode = $b['mode'] ?? 'both';
                        $badgeClass = $mode === 'host' ? 'badge-host' : ($mode === 'download' ? 'badge-download' : 'badge-both');
                        $modeLabel  = $mode === 'host' ? 'Host Only' : ($mode === 'download' ? 'Download Only' : 'Host + Download');
                        echo "<span class='badge-mode $badgeClass'>$modeLabel</span>";
                        ?>
                        &nbsp;|&nbsp;
                        <span style="color:#64748b;"><?php echo date('d M Y', $b['created_at'] ?? time()); ?></span>
                    </div>
                    <div class="btn-meta" style="margin-top:4px;color:#475569;font-size:.65rem;">
                        HTML: <?php echo strlen($b['html'] ?? ''); ?> chars
                    </div>
                </div>
                <div class="btn-actions">
                    <a href="?edit=<?php echo htmlspecialchars($b['id']); ?>" class="icon-btn" title="Edit">
                        <i class="fas fa-edit"></i>
                    </a>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete karo?')">
                        <input type="hidden" name="action"  value="delete">
                        <input type="hidden" name="del_id"  value="<?php echo htmlspecialchars($b['id']); ?>">
                        <button type="submit" class="icon-btn red" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
</div><!-- tab-buttons -->

<!-- ══════ TAB: HOSTED SITES ══════ -->
<div id="tab-sites" style="display:none;">
<div class="panel">
    <div class="panel-title"><i class="fas fa-globe"></i> Hosted Sites (<?php echo count($hosted_sites); ?>)</div>
    <?php if (empty($hosted_sites)): ?>
    <div class="empty-state"><i class="fas fa-inbox"></i>Abhi koi site hosted nahi hai.</div>
    <?php else: ?>
    <div class="site-list" style="max-height:600px;">
        <?php foreach ($hosted_sites as $site): ?>
        <div class="site-item">
            <div style="min-width:0;flex:1;">
                <div class="site-name">
                    <?php if ($site['is_dir']): ?><i class="fas fa-folder" style="color:#f59e0b;"></i><?php else: ?><i class="fas fa-file-code" style="color:#38bdf8;"></i><?php endif; ?>
                    <?php echo htmlspecialchars($site['display_name']); ?>
                    <span style="color:#64748b;font-size:.7rem;margin-left:6px;"><?php echo $site['size']; ?></span>
                </div>
                <div class="site-url"><?php echo htmlspecialchars($site['url']); ?></div>
                <div class="site-meta"><?php echo date('d M Y, H:i', $site['time']); ?></div>
            </div>
            <div class="site-btns">
                <a href="<?php echo htmlspecialchars($site['url']); ?>" target="_blank" class="icon-btn" title="Open Site" style="color:#38bdf8;">
                    <i class="fas fa-external-link-alt"></i>
                </a>
                <button class="icon-btn" title="Copy URL" onclick="copyUrl('<?php echo htmlspecialchars($site['url'], ENT_QUOTES); ?>')">
                    <i class="fas fa-copy"></i>
                </button>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Is site ko delete karo?')">
                    <input type="hidden" name="action"    value="delete_site">
                    <input type="hidden" name="site_name" value="<?php echo htmlspecialchars($site['raw'], ENT_QUOTES); ?>">
                    <button type="submit" class="icon-btn red" title="Delete Site">
                        <i class="fas fa-trash"></i>
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
</div><!-- tab-sites -->

<!-- ══════ TAB: BUTTON DESIGNS ══════ -->
<div id="tab-designs" style="display:none;">
<div class="panel">
    <div class="panel-title"><i class="fas fa-magic"></i> Smart Auto-Designer — Button Ka Naam + HTML Dekh Kar Best Design Choose Karo</div>

    <!-- ── Smart Input Area ── -->
    <div class="smart-designer">
        <div class="smart-header">
            <div class="smart-icon"><i class="fas fa-robot"></i></div>
            <div>
                <div class="smart-title">Smart Design Analyzer</div>
                <div class="smart-subtitle">Button ka naam aur HTML paste karo — system khud best design dhundh lega</div>
            </div>
            <span class="smart-badge">✨ Auto AI</span>
        </div>

        <div class="smart-inputs">
            <div class="smart-field">
                <label><i class="fas fa-tag"></i> Button Ka Naam</label>
                <input type="text" id="ai-btn-name" placeholder="e.g. MY TEMPLATE, DOWNLOAD SITE, TEAM HUB..."
                    oninput="liveAnalyze()" />
            </div>
            <div class="smart-field">
                <label><i class="fas fa-code"></i> HTML Code (optional — paste karo)</label>
                <textarea id="ai-btn-html" placeholder="<h1>Hello {{TEAM ZERO}}</h1>&#10;<p>Apna HTML yahan paste karo...</p>"
                    oninput="liveAnalyze()"></textarea>
            </div>
        </div>

        <!-- ── Icon Picker ── -->
        <div class="icon-picker-section">
            <div class="icon-picker-header">
                <div class="icon-picker-title"><i class="fas fa-icons"></i> Button Ka Shuru Wala Icon Chunein</div>
                <span class="ai-icon-badge" id="ai-icon-badge" style="display:none;">🤖 AI ne choose kiya</span>
            </div>
            <div class="icon-grid" id="icon-grid">
                <!-- Row 1: Common actions (jaise APK & Hub mein the) -->
                <div class="icon-chip" data-icon="fa-android"        data-kw="apk,android,mobile,app"    onclick="pickIcon(this)"><i class="fab fa-android"></i><span class="icon-tip">Android / APK</span></div>
                <div class="icon-chip" data-icon="fa-magic"          data-kw="hub,magic,create,template" onclick="pickIcon(this)"><i class="fas fa-magic"></i><span class="icon-tip">Magic / Hub</span></div>
                <div class="icon-chip" data-icon="fa-globe"          data-kw="web,site,host,url,link"    onclick="pickIcon(this)"><i class="fas fa-globe"></i><span class="icon-tip">Web / Site</span></div>
                <div class="icon-chip" data-icon="fa-download"       data-kw="download,get,save,file"    onclick="pickIcon(this)"><i class="fas fa-download"></i><span class="icon-tip">Download</span></div>
                <div class="icon-chip" data-icon="fa-upload"         data-kw="upload,send,host,submit"   onclick="pickIcon(this)"><i class="fas fa-upload"></i><span class="icon-tip">Upload / Host</span></div>
                <div class="icon-chip" data-icon="fa-code"           data-kw="code,dev,api,script,html"  onclick="pickIcon(this)"><i class="fas fa-code"></i><span class="icon-tip">Code / Dev</span></div>
                <div class="icon-chip" data-icon="fa-robot"          data-kw="bot,ai,auto,robot,smart"   onclick="pickIcon(this)"><i class="fas fa-robot"></i><span class="icon-tip">Bot / AI</span></div>
                <div class="icon-chip" data-icon="fa-terminal"       data-kw="terminal,script,cmd,hack"  onclick="pickIcon(this)"><i class="fas fa-terminal"></i><span class="icon-tip">Terminal</span></div>
                <div class="icon-chip" data-icon="fa-cog"            data-kw="setting,config,admin,tool" onclick="pickIcon(this)"><i class="fas fa-cog"></i><span class="icon-tip">Settings</span></div>
                <div class="icon-chip" data-icon="fa-tools"          data-kw="tool,fix,build,make,setup" onclick="pickIcon(this)"><i class="fas fa-tools"></i><span class="icon-tip">Tools</span></div>
                <!-- Row 2: Content & Media -->
                <div class="icon-chip" data-icon="fa-file-code"      data-kw="source,code,file,html,php" onclick="pickIcon(this)"><i class="fas fa-file-code"></i><span class="icon-tip">Source Code</span></div>
                <div class="icon-chip" data-icon="fa-layer-group"    data-kw="template,layer,pack,stack" onclick="pickIcon(this)"><i class="fas fa-layer-group"></i><span class="icon-tip">Template / Pack</span></div>
                <div class="icon-chip" data-icon="fa-paint-brush"    data-kw="design,style,theme,color"  onclick="pickIcon(this)"><i class="fas fa-paint-brush"></i><span class="icon-tip">Design / Theme</span></div>
                <div class="icon-chip" data-icon="fa-image"          data-kw="image,photo,pic,gallery"   onclick="pickIcon(this)"><i class="fas fa-image"></i><span class="icon-tip">Image / Photo</span></div>
                <div class="icon-chip" data-icon="fa-video"          data-kw="video,media,watch,play"    onclick="pickIcon(this)"><i class="fas fa-video"></i><span class="icon-tip">Video</span></div>
                <div class="icon-chip" data-icon="fa-music"          data-kw="music,audio,song,mp3"      onclick="pickIcon(this)"><i class="fas fa-music"></i><span class="icon-tip">Music / Audio</span></div>
                <!-- Row 3: Social & Team -->
                <div class="icon-chip" data-icon="fa-users"          data-kw="team,group,members,people" onclick="pickIcon(this)"><i class="fas fa-users"></i><span class="icon-tip">Team / Group</span></div>
                <div class="icon-chip" data-icon="fa-share-alt"      data-kw="share,social,link,spread"  onclick="pickIcon(this)"><i class="fas fa-share-alt"></i><span class="icon-tip">Share / Social</span></div>
                <div class="icon-chip" data-icon="fa-telegram-plane" data-kw="telegram,message,chat,tg"  onclick="pickIcon(this)"><i class="fab fa-telegram-plane"></i><span class="icon-tip">Telegram</span></div>
                <div class="icon-chip" data-icon="fa-whatsapp"       data-kw="whatsapp,wa,chat,message"  onclick="pickIcon(this)"><i class="fab fa-whatsapp"></i><span class="icon-tip">WhatsApp</span></div>
                <div class="icon-chip" data-icon="fa-youtube"        data-kw="youtube,video,channel,yt"  onclick="pickIcon(this)"><i class="fab fa-youtube"></i><span class="icon-tip">YouTube</span></div>
                <div class="icon-chip" data-icon="fa-instagram"      data-kw="instagram,insta,photo,ig"  onclick="pickIcon(this)"><i class="fab fa-instagram"></i><span class="icon-tip">Instagram</span></div>
                <!-- Row 4: Actions & Status -->
                <div class="icon-chip" data-icon="fa-bolt"           data-kw="fast,quick,power,boost"    onclick="pickIcon(this)"><i class="fas fa-bolt"></i><span class="icon-tip">Bolt / Power</span></div>
                <div class="icon-chip" data-icon="fa-fire"           data-kw="hot,fire,trending,viral"   onclick="pickIcon(this)"><i class="fas fa-fire"></i><span class="icon-tip">Fire / Hot</span></div>
                <div class="icon-chip" data-icon="fa-crown"          data-kw="premium,vip,pro,king,gold" onclick="pickIcon(this)"><i class="fas fa-crown"></i><span class="icon-tip">Crown / VIP</span></div>
                <div class="icon-chip" data-icon="fa-star"           data-kw="star,favorite,best,top"    onclick="pickIcon(this)"><i class="fas fa-star"></i><span class="icon-tip">Star / Best</span></div>
                <div class="icon-chip" data-icon="fa-heart"          data-kw="love,like,heart,fav"       onclick="pickIcon(this)"><i class="fas fa-heart"></i><span class="icon-tip">Heart / Like</span></div>
                <div class="icon-chip" data-icon="fa-lock"           data-kw="lock,secure,private,safe"  onclick="pickIcon(this)"><i class="fas fa-lock"></i><span class="icon-tip">Lock / Secure</span></div>
                <div class="icon-chip" data-icon="fa-key"            data-kw="key,pass,login,access"     onclick="pickIcon(this)"><i class="fas fa-key"></i><span class="icon-tip">Key / Access</span></div>
                <div class="icon-chip" data-icon="fa-shield-alt"     data-kw="shield,protect,safe,anti"  onclick="pickIcon(this)"><i class="fas fa-shield-alt"></i><span class="icon-tip">Shield / Safe</span></div>
                <!-- Row 5: Misc -->
                <div class="icon-chip" data-icon="fa-link"           data-kw="link,url,connect,redirect" onclick="pickIcon(this)"><i class="fas fa-link"></i><span class="icon-tip">Link / URL</span></div>
                <div class="icon-chip" data-icon="fa-external-link-alt" data-kw="open,visit,redirect"   onclick="pickIcon(this)"><i class="fas fa-external-link-alt"></i><span class="icon-tip">Open / Visit</span></div>
                <div class="icon-chip" data-icon="fa-copy"           data-kw="copy,clone,duplicate"      onclick="pickIcon(this)"><i class="fas fa-copy"></i><span class="icon-tip">Copy</span></div>
                <div class="icon-chip" data-icon="fa-info-circle"    data-kw="info,about,detail,help"    onclick="pickIcon(this)"><i class="fas fa-info-circle"></i><span class="icon-tip">Info / About</span></div>
                <div class="icon-chip" data-icon="fa-plus-circle"    data-kw="add,new,plus,create"       onclick="pickIcon(this)"><i class="fas fa-plus-circle"></i><span class="icon-tip">Add / New</span></div>
                <div class="icon-chip" data-icon="fa-trash"          data-kw="delete,remove,trash,clear" onclick="pickIcon(this)"><i class="fas fa-trash"></i><span class="icon-tip">Delete / Remove</span></div>
                <div class="icon-chip" data-icon="fa-pencil-alt"     data-kw="edit,write,pencil,update"  onclick="pickIcon(this)"><i class="fas fa-pencil-alt"></i><span class="icon-tip">Edit / Pencil</span></div>
                <div class="icon-chip" data-icon="fa-eye"            data-kw="view,see,preview,watch"    onclick="pickIcon(this)"><i class="fas fa-eye"></i><span class="icon-tip">View / Preview</span></div>
                <div class="icon-chip" data-icon="fa-search"         data-kw="search,find,lookup,query"  onclick="pickIcon(this)"><i class="fas fa-search"></i><span class="icon-tip">Search</span></div>
            </div>
            <div class="selected-icon-label" id="selected-icon-label">Icon: <strong id="icon-selected-name" style="color:#e2e8f0;">Koi nahi (AI choose karega)</strong></div>
        </div>

        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <button class="auto-btn" id="auto-analyze-btn" onclick="runAutoAnalyze()">
                <i class="fas fa-search-plus"></i> Auto Design + Icon Dhundo
            </button>
            <button class="auto-btn" onclick="syncFromForm()" style="background:linear-gradient(135deg,#7c3aed,#a78bfa);box-shadow:0 4px 16px rgba(124,58,237,.4);">
                <i class="fas fa-sync-alt"></i> Form Se Load Karo
            </button>
            <span style="font-size:.72rem;color:#475569;">— Ya oopar type karo, real-time suggest hoga</span>
        </div>

        <!-- AI Result -->
        <div class="ai-result-bar" id="ai-result-bar">
            <div class="ai-result-dot"></div>
            <div class="ai-result-text" id="ai-result-text">Analyzing...</div>
        </div>

        <!-- Live Preview -->
        <div class="live-preview-wrap" id="live-preview-wrap">
            <div class="live-preview-label">✦ Live Button Preview — Aisa dikhega aapka button</div>
            <div class="live-preview-stage" id="live-preview-stage"></div>
        </div>
    </div>

    <!-- ── Design Cards Grid ── -->
    <p style="font-size:.78rem;color:#475569;margin-bottom:14px;"><i class="fas fa-hand-pointer"></i> Ya neeche se kisi bhi design card par click karke manually select karo:</p>
    <div class="designs-grid">

        <!-- Design 1 -->
        <div class="design-card" id="dcard-1" onclick="selectDesign(1,'linear-gradient(135deg,#0284c7,#38bdf8)','#0284c7')">
            <span class="ai-pick-ribbon">🤖 AI Pick</span>
            <span class="selected-badge">✓ Selected</span>
            <div class="design-card-top">
                <div class="design-card-num">Design 1</div>
            </div>
            <div class="design-card-name">Sky Blue Gradient</div>
            <button style="padding:8px 16px;background:linear-gradient(135deg,#0284c7,#38bdf8);color:white;border:none;border-radius:20px;font-size:.78rem;font-weight:bold;cursor:default;display:inline-flex;align-items:center;gap:5px;box-shadow:0 4px 12px rgba(2,132,199,.45);pointer-events:none;" id="dpreview-1">
                <i class="fas fa-star"></i> <span class="dpname-1">MY BUTTON</span>
            </button>
            <div class="design-card-desc">Sky blue → cyan gradient. Jaisa "Create Web To APK" wala tha. Downloads, tools, apps ke liye best.</div>
            <button class="design-select-btn" id="dsbtn-1"><i class="fas fa-check"></i> Yeh Select Karo</button>
        </div>

        <!-- Design 2 -->
        <div class="design-card" id="dcard-2" onclick="selectDesign(2,'linear-gradient(135deg,#667eea,#764ba2)','#667eea')">
            <span class="ai-pick-ribbon">🤖 AI Pick</span>
            <span class="selected-badge">✓ Selected</span>
            <div class="design-card-top"><div class="design-card-num">Design 2</div></div>
            <div class="design-card-name">Purple Royal</div>
            <button style="padding:8px 16px;background:linear-gradient(135deg,#667eea,#764ba2);color:white;border:none;border-radius:20px;font-size:.78rem;font-weight:bold;cursor:default;display:inline-flex;align-items:center;gap:5px;box-shadow:0 4px 12px rgba(102,126,234,.45);pointer-events:none;" id="dpreview-2">
                <i class="fas fa-star"></i> <span class="dpname-2">MY BUTTON</span>
            </button>
            <div class="design-card-desc">Purple-violet elegant gradient. Admin panels, hub, dashboard ke liye perfect.</div>
            <button class="design-select-btn" id="dsbtn-2"><i class="fas fa-check"></i> Yeh Select Karo</button>
        </div>

        <!-- Design 3 -->
        <div class="design-card" id="dcard-3" onclick="selectDesign(3,'linear-gradient(135deg,#ff7e5f,#feb47b)','#ff7e5f')">
            <span class="ai-pick-ribbon">🤖 AI Pick</span>
            <span class="selected-badge">✓ Selected</span>
            <div class="design-card-top"><div class="design-card-num">Design 3</div></div>
            <div class="design-card-name">Fire Orange</div>
            <button style="padding:8px 16px;background:linear-gradient(135deg,#ff7e5f,#feb47b);color:white;border:none;border-radius:20px;font-size:.78rem;font-weight:bold;cursor:default;display:inline-flex;align-items:center;gap:5px;box-shadow:0 4px 12px rgba(255,126,95,.45);pointer-events:none;" id="dpreview-3">
                <i class="fas fa-fire"></i> <span class="dpname-3">MY BUTTON</span>
            </button>
            <div class="design-card-desc">Orange-peach warm gradient. Hub, magic, create, template ke liye energetic look.</div>
            <button class="design-select-btn" id="dsbtn-3"><i class="fas fa-check"></i> Yeh Select Karo</button>
        </div>

        <!-- Design 4 -->
        <div class="design-card" id="dcard-4" onclick="selectDesign(4,'linear-gradient(135deg,#11998e,#38ef7d)','#11998e')">
            <span class="ai-pick-ribbon">🤖 AI Pick</span>
            <span class="selected-badge">✓ Selected</span>
            <div class="design-card-top"><div class="design-card-num">Design 4</div></div>
            <div class="design-card-name">Emerald Green</div>
            <button style="padding:8px 16px;background:linear-gradient(135deg,#11998e,#38ef7d);color:white;border:none;border-radius:20px;font-size:.78rem;font-weight:bold;cursor:default;display:inline-flex;align-items:center;gap:5px;box-shadow:0 4px 12px rgba(17,153,142,.45);pointer-events:none;" id="dpreview-4">
                <i class="fas fa-leaf"></i> <span class="dpname-4">MY BUTTON</span>
            </button>
            <div class="design-card-desc">Fresh green gradient. Host, upload, success, save actions ke liye best.</div>
            <button class="design-select-btn" id="dsbtn-4"><i class="fas fa-check"></i> Yeh Select Karo</button>
        </div>

        <!-- Design 5 -->
        <div class="design-card" id="dcard-5" onclick="selectDesign(5,'linear-gradient(135deg,#f953c6,#b91d73)','#f953c6')">
            <span class="ai-pick-ribbon">🤖 AI Pick</span>
            <span class="selected-badge">✓ Selected</span>
            <div class="design-card-top"><div class="design-card-num">Design 5</div></div>
            <div class="design-card-name">Hot Pink</div>
            <button style="padding:8px 16px;background:linear-gradient(135deg,#f953c6,#b91d73);color:white;border:none;border-radius:20px;font-size:.78rem;font-weight:bold;cursor:default;display:inline-flex;align-items:center;gap:5px;box-shadow:0 4px 12px rgba(249,83,198,.45);pointer-events:none;" id="dpreview-5">
                <i class="fas fa-heart"></i> <span class="dpname-5">MY BUTTON</span>
            </button>
            <div class="design-card-desc">Pink-magenta bold gradient. Trendy, fun, team, social ke liye eye-catching.</div>
            <button class="design-select-btn" id="dsbtn-5"><i class="fas fa-check"></i> Yeh Select Karo</button>
        </div>

        <!-- Design 6 -->
        <div class="design-card" id="dcard-6" onclick="selectDesign(6,'linear-gradient(135deg,#f7971e,#ffd200)','#f7971e')">
            <span class="ai-pick-ribbon">🤖 AI Pick</span>
            <span class="selected-badge">✓ Selected</span>
            <div class="design-card-top"><div class="design-card-num">Design 6</div></div>
            <div class="design-card-name">Golden Premium</div>
            <button style="padding:8px 16px;background:linear-gradient(135deg,#f7971e,#ffd200);color:#1a1a1a;border:none;border-radius:20px;font-size:.78rem;font-weight:bold;cursor:default;display:inline-flex;align-items:center;gap:5px;box-shadow:0 4px 12px rgba(247,151,30,.45);pointer-events:none;" id="dpreview-6">
                <i class="fas fa-crown"></i> <span class="dpname-6">MY BUTTON</span>
            </button>
            <div class="design-card-desc">Gold-yellow premium gradient. VIP, premium, special content ke liye luxurious feel.</div>
            <button class="design-select-btn" id="dsbtn-6"><i class="fas fa-check"></i> Yeh Select Karo</button>
        </div>

        <!-- Design 7 -->
        <div class="design-card" id="dcard-7" onclick="selectDesign(7,'#0f0f0f','#00b894')">
            <span class="ai-pick-ribbon">🤖 AI Pick</span>
            <span class="selected-badge">✓ Selected</span>
            <div class="design-card-top"><div class="design-card-num">Design 7</div></div>
            <div class="design-card-name">Neon Cyber</div>
            <button style="padding:8px 16px;background:#0f0f0f;color:#00ffcc;border:2px solid #00ffcc;border-radius:20px;font-size:.78rem;font-weight:bold;cursor:default;display:inline-flex;align-items:center;gap:5px;box-shadow:0 0 10px rgba(0,255,204,.35);text-shadow:0 0 6px #00ffcc;pointer-events:none;" id="dpreview-7">
                <i class="fas fa-code"></i> <span class="dpname-7">MY BUTTON</span>
            </button>
            <div class="design-card-desc">Dark background + neon glow. Hacker, code, bot, API ke liye cyberpunk style.</div>
            <button class="design-select-btn" id="dsbtn-7"><i class="fas fa-check"></i> Yeh Select Karo</button>
        </div>

        <!-- Design 8 -->
        <div class="design-card" id="dcard-8" onclick="selectDesign(8,'rgba(255,255,255,0.12)','#ffffff')">
            <span class="ai-pick-ribbon">🤖 AI Pick</span>
            <span class="selected-badge">✓ Selected</span>
            <div class="design-card-top"><div class="design-card-num">Design 8</div></div>
            <div class="design-card-name">Glass Blur</div>
            <button style="padding:8px 16px;background:rgba(255,255,255,.12);color:white;border:1px solid rgba(255,255,255,.4);border-radius:20px;font-size:.78rem;font-weight:bold;cursor:default;display:inline-flex;align-items:center;gap:5px;backdrop-filter:blur(10px);box-shadow:0 4px 18px rgba(0,0,0,.3);pointer-events:none;" id="dpreview-8">
                <i class="fas fa-gem"></i> <span class="dpname-8">MY BUTTON</span>
            </button>
            <div class="design-card-desc">Transparent glassmorphism. Modern, minimal, elegant — dark backgrounds pe beautiful.</div>
            <button class="design-select-btn" id="dsbtn-8"><i class="fas fa-check"></i> Yeh Select Karo</button>
        </div>

        <!-- Design 9 -->
        <div class="design-card" id="dcard-9" onclick="selectDesign(9,'linear-gradient(135deg,#c0392b,#e74c3c)','#c0392b')">
            <span class="ai-pick-ribbon">🤖 AI Pick</span>
            <span class="selected-badge">✓ Selected</span>
            <div class="design-card-top"><div class="design-card-num">Design 9</div></div>
            <div class="design-card-name">Red Alert</div>
            <button style="padding:8px 16px;background:linear-gradient(135deg,#c0392b,#e74c3c);color:white;border:none;border-radius:20px;font-size:.78rem;font-weight:bold;cursor:default;display:inline-flex;align-items:center;gap:5px;box-shadow:0 4px 12px rgba(192,57,43,.45);pointer-events:none;" id="dpreview-9">
                <i class="fas fa-bolt"></i> <span class="dpname-9">MY BUTTON</span>
            </button>
            <div class="design-card-desc">Bold red gradient. Delete, danger, alert, warning ke liye strong feel.</div>
            <button class="design-select-btn" id="dsbtn-9"><i class="fas fa-check"></i> Yeh Select Karo</button>
        </div>

    </div>

    <!-- ── Confirm & Add Panel ── -->
    <div class="confirm-panel" id="confirm-panel">
        <div class="confirm-title">
            <span id="confirm-design-swatch" style="width:28px;height:28px;border-radius:8px;display:inline-block;flex-shrink:0;"></span>
            <span>✅ Design Select Hua — Ab Confirm Karo &amp; Button Add Karo</span>
        </div>

        <!-- Hidden form that submits to PHP backend -->
        <form method="POST" action="usman.php" id="quick-add-form">
            <input type="hidden" name="action" value="create">

            <div class="confirm-summary">
                <div class="confirm-field">
                    <label><i class="fas fa-tag"></i> Button Ka Naam *</label>
                    <input type="text" name="btn_name" id="confirm-btn-name"
                        placeholder="e.g. MY TEMPLATE" required>
                </div>
                <div class="confirm-field">
                    <label><i class="fas fa-exchange-alt"></i> Replace Target</label>
                    <input type="text" name="btn_replace" id="confirm-btn-replace"
                        value="TEAM ZERO" placeholder="TEAM ZERO">
                </div>
                <div class="confirm-field">
                    <label><i class="fas fa-palette"></i> Selected Design Color</label>
                    <input type="text" name="btn_color" id="confirm-color-display"
                        readonly style="cursor:not-allowed;opacity:.7;">
                    <input type="hidden" name="btn_color_hidden" id="confirm-color-hidden">
                </div>
                <div class="confirm-field">
                    <label><i class="fas fa-sliders-h"></i> Mode</label>
                    <select name="btn_mode" id="confirm-mode">
                        <option value="both">🔀 Host + Download dono</option>
                        <option value="host">☁️ Sirf Host karo</option>
                        <option value="download">⬇️ Sirf Download karo</option>
                    </select>
                </div>
            </div>

            <div class="confirm-field" style="margin-bottom:14px;">
                <label><i class="fas fa-code"></i> HTML Content</label>
                <textarea name="btn_html" id="confirm-btn-html"
                    placeholder="<h1>Hello {{TEAM ZERO}}</h1>&#10;<p>Apna HTML paste karo...</p>"></textarea>
            </div>

            <!-- Live mini preview -->
            <div style="margin-bottom:16px;padding:14px;background:rgba(2,6,23,.6);border:1px solid rgba(255,255,255,.1);border-radius:12px;text-align:center;">
                <div style="font-size:.65rem;color:#475569;text-transform:uppercase;letter-spacing:.07em;margin-bottom:10px;">Aapka button aisa dikhega index.php mein</div>
                <div id="confirm-live-btn"></div>
            </div>

            <button type="submit" class="confirm-btn-big" onclick="return validateQuickAdd()">
                <i class="fas fa-plus-circle"></i> BUTTON ADD KARO — INDEX.PHP MEIN DIKHE GA
            </button>
        </form>
    </div>

</div>
</div>
</div><!-- tab-designs -->

<?php endif; /* end logged-in */ ?>

</div><!-- .container -->

<!-- Notification -->
<div id="notify" style="position:fixed;top:16px;right:16px;background:#10b981;color:#fff;padding:12px 20px;border-radius:10px;font-size:.85rem;font-weight:700;transform:translateX(200%);transition:transform .3s;z-index:9999;">
    ✅ Copied!
</div>

<style>
.tab-nav{padding:9px 20px;border:1px solid rgba(255,255,255,.15);border-radius:30px;background:transparent;color:#94a3b8;font-size:.85rem;font-weight:600;cursor:pointer;transition:all .2s}
.tab-nav.active{background:rgba(102,126,234,.25);border-color:rgba(102,126,234,.6);color:#a5b4fc}
</style>

<script>
/* ── Tab switching ── */
function showTab(id) {
    ['tab-buttons','tab-sites','tab-designs'].forEach(t => {
        document.getElementById(t).style.display = t === id ? 'block' : 'none';
    });
    ['nav-buttons','nav-sites','nav-designs'].forEach(n => {
        document.getElementById(n).classList.toggle('active', n === 'nav-' + id.replace('tab-',''));
    });
}

/* ═══════════════════════════════════════
   SMART AUTO-DESIGNER
═══════════════════════════════════════ */
var selectedDesign  = 0;
var aiPickedDesign  = 0;
var liveAnalyzeTimer = null;

var DESIGNS = {
    1: { name:'Sky Blue Gradient',  hex:'#0284c7', bg:'linear-gradient(135deg,#0284c7,#38bdf8)', textColor:'#fff',
         keywords:['download','apk','app','mobile','tool','web','site','create','build','make','banao'] },
    2: { name:'Purple Royal',       hex:'#667eea', bg:'linear-gradient(135deg,#667eea,#764ba2)', textColor:'#fff',
         keywords:['admin','panel','hub','dashboard','manage','control','setting','config'] },
    3: { name:'Fire Orange',        hex:'#ff7e5f', bg:'linear-gradient(135deg,#ff7e5f,#feb47b)', textColor:'#fff',
         keywords:['magic','hub','template','source','fire','hot','code','tool','edit','pencil'] },
    4: { name:'Emerald Green',      hex:'#11998e', bg:'linear-gradient(135deg,#11998e,#38ef7d)', textColor:'#fff',
         keywords:['host','upload','save','success','go','send','submit','launch','active','live'] },
    5: { name:'Hot Pink',           hex:'#f953c6', bg:'linear-gradient(135deg,#f953c6,#b91d73)', textColor:'#fff',
         keywords:['team','social','follow','share','fun','party','vibe','link','join','invite'] },
    6: { name:'Golden Premium',     hex:'#f7971e', bg:'linear-gradient(135deg,#f7971e,#ffd200)', textColor:'#1a1a1a',
         keywords:['premium','vip','gold','pro','special','exclusive','unlock','paid','star'] },
    7: { name:'Neon Cyber',         hex:'#00b894', bg:'#0f0f0f',                                textColor:'#00ffcc',
         keywords:['code','api','bot','script','hack','cyber','dev','developer','program','terminal'] },
    8: { name:'Glass Blur',         hex:'#ffffff', bg:'rgba(255,255,255,0.12)',                  textColor:'#fff',
         keywords:['glass','minimal','clean','light','elegant','modern','simple','dark','blur'] },
    9: { name:'Red Alert',          hex:'#c0392b', bg:'linear-gradient(135deg,#c0392b,#e74c3c)', textColor:'#fff',
         keywords:['delete','remove','danger','alert','warn','error','stop','block','urgent','important'] }
};

/* Default icons per design */
var DESIGN_ICONS = {1:'fa-download', 2:'fa-cog', 3:'fa-magic', 4:'fa-upload',
                   5:'fa-heart', 6:'fa-crown', 7:'fa-code', 8:'fa-gem', 9:'fa-bolt'};

/* Currently selected icon */
var selectedIcon = '';
var isIconLibrary = false; /* true = fab (brands), false = fas */

/* ── Pick an icon manually ── */
function pickIcon(el) {
    /* Deselect all */
    document.querySelectorAll('.icon-chip').forEach(function(c){
        c.classList.remove('selected','ai-icon');
    });
    el.classList.add('selected');
    selectedIcon   = el.getAttribute('data-icon');
    var isBrand    = el.querySelector('i').className.indexOf('fab ') > -1;
    isIconLibrary  = isBrand;

    /* Update label */
    var tip = el.querySelector('.icon-tip');
    var lbl = document.getElementById('icon-selected-name');
    if(lbl) lbl.textContent = (tip ? tip.textContent : selectedIcon) + '  (fa-class: ' + selectedIcon + ')';

    /* Remove AI badge (manual pick) */
    var badge = document.getElementById('ai-icon-badge');
    if(badge){ badge.textContent='✋ Manually Chosen'; badge.style.display='inline-block'; badge.style.background='rgba(56,189,248,.15)'; badge.style.borderColor='rgba(56,189,248,.4)'; badge.style.color='#38bdf8'; }

    /* Re-render ALL previews with new icon */
    if(selectedDesign){
        renderLivePreview(selectedDesign);
        updateConfirmLiveBtn(selectedDesign);
    }
}

/* ── AI auto-pick icon based on keywords ── */
function autoPickIcon(combined) {
    var chips = document.querySelectorAll('.icon-chip');
    var bestChip = null, bestScore = 0;
    chips.forEach(function(chip){
        var kws = (chip.getAttribute('data-kw') || '').split(',');
        var score = 0;
        kws.forEach(function(kw){ if(kw && combined.indexOf(kw.trim()) > -1) score++; });
        if(score > bestScore){ bestScore = score; bestChip = chip; }
    });
    /* Clear all */
    chips.forEach(function(c){ c.classList.remove('selected','ai-icon'); });
    if(bestChip){
        bestChip.classList.add('ai-icon');
        selectedIcon  = bestChip.getAttribute('data-icon');
        isIconLibrary = bestChip.querySelector('i').className.indexOf('fab ') > -1;
        var tip  = bestChip.querySelector('.icon-tip');
        var lbl  = document.getElementById('icon-selected-name');
        if(lbl) lbl.textContent = (tip ? tip.textContent : selectedIcon) + '  (fa-class: '+selectedIcon+')';
        var badge = document.getElementById('ai-icon-badge');
        if(badge){ badge.textContent='🤖 AI ne choose kiya'; badge.style.display='inline-block'; badge.style.background='rgba(167,139,250,.2)'; badge.style.borderColor='rgba(167,139,250,.4)'; badge.style.color='#a78bfa'; }
        /* Scroll chip into view */
        bestChip.scrollIntoView({behavior:'smooth', block:'nearest'});
    }
}

/* ── Select a design (click or auto) ── */
function selectDesign(num, bgVal, hexVal) {
    /* Clear all cards */
    for(var i=1;i<=9;i++){
        var c=document.getElementById('dcard-'+i);
        if(!c) continue;
        c.classList.remove('selected','ai-pick');
        var sb=document.getElementById('dsbtn-'+i);
        if(sb){ sb.classList.remove('picked'); sb.innerHTML='<i class="fas fa-check"></i> Yeh Select Karo'; }
    }

    /* Mark selected */
    var chosen=document.getElementById('dcard-'+num);
    if(chosen){
        chosen.classList.add('selected');
        chosen.scrollIntoView({behavior:'smooth',block:'center'});
    }
    var chosenBtn=document.getElementById('dsbtn-'+num);
    if(chosenBtn){
        chosenBtn.classList.add('picked');
        chosenBtn.innerHTML='<i class="fas fa-check-circle"></i> ✓ SELECTED!';
    }
    selectedDesign=num;

    /* Apply to main form color fields */
    var d=DESIGNS[num];
    var colorVal = d ? d.hex : (hexVal||'#667eea');
    ['colorPicker','colorText','colorHidden'].forEach(function(id){
        var el=document.getElementById(id);
        if(el) el.value = colorVal;
    });

    /* ── Fill Confirm Panel ── */
    fillConfirmPanel(num);

    /* Update live preview */
    renderLivePreview(num);
}

/* ── Fill confirm panel with selected design info ── */
function fillConfirmPanel(num){
    var d = DESIGNS[num];
    if(!d) return;

    /* Sync name from AI input if filled */
    var aiName = (document.getElementById('ai-btn-name').value||'').trim();
    var aiHtml = (document.getElementById('ai-btn-html').value||'').trim();

    var nameField = document.getElementById('confirm-btn-name');
    var htmlField = document.getElementById('confirm-btn-html');
    var colorDisp = document.getElementById('confirm-color-display');
    var colorHid  = document.getElementById('confirm-color-hidden');
    var swatch    = document.getElementById('confirm-design-swatch');

    if(nameField && aiName) nameField.value = aiName;
    if(htmlField && aiHtml) htmlField.value = aiHtml;
    if(colorDisp) colorDisp.value = d.hex;
    if(colorHid)  colorHid.value  = d.hex;
    if(swatch){   swatch.style.background = d.bg; }

    /* Show confirm panel */
    var panel = document.getElementById('confirm-panel');
    if(panel){ panel.classList.add('visible'); panel.scrollIntoView({behavior:'smooth',block:'nearest'}); }

    /* Render live mini button inside confirm */
    updateConfirmLiveBtn(num);

    /* Watch for name/html changes to update mini preview */
    var nm=document.getElementById('confirm-btn-name');
    var ht=document.getElementById('confirm-btn-html');
    if(nm) nm.oninput = function(){ updateConfirmLiveBtn(num); };
    if(ht) ht.oninput = function(){ updateConfirmLiveBtn(num); };
}

/* ── Mini button preview inside confirm panel ── */
function updateConfirmLiveBtn(num){
    var d   = DESIGNS[num]; if(!d) return;
    var el  = document.getElementById('confirm-live-btn'); if(!el) return;
    var nm  = (document.getElementById('confirm-btn-name').value||'MY BUTTON').toUpperCase().substring(0,22);
    var ico = selectedIcon || DESIGN_ICONS[num] || 'fa-star';
    var lib = isIconLibrary ? 'fab' : 'fas';
    var ex  = num===7 ? 'border:2px solid #00ffcc;text-shadow:0 0 8px #00ffcc;' : '';
    el.innerHTML='<button style="padding:10px 22px;background:'+d.bg+';color:'+d.textColor+
        ';border:none;border-radius:22px;font-size:.88rem;font-weight:800;display:inline-flex;'+
        'align-items:center;gap:8px;cursor:default;box-shadow:0 6px 20px rgba(0,0,0,.4);'+ex+'">'+
        '<i class="'+lib+' '+ico+'"></i> '+nm+'</button>';
}

/* ── Validate before submit ── */
function validateQuickAdd(){
    var nm = document.getElementById('confirm-btn-name').value.trim();
    if(!nm){ alert('Button ka naam zaroor likhein!'); return false; }
    if(!selectedDesign){ alert('Pehle koi design select karein!'); return false; }
    /* sync color */
    var d=DESIGNS[selectedDesign];
    if(d){
        document.getElementById('confirm-color-display').value = d.hex;
        document.getElementById('confirm-color-hidden').value  = d.hex;
    }
    return true;
}

/* ── Sync name from main form ── */
function syncFromForm(){
    var nameField=document.querySelector('input[name="btn_name"]');
    var htmlField=document.getElementById('htmlEditor');
    if(nameField) document.getElementById('ai-btn-name').value = nameField.value;
    if(htmlField) document.getElementById('ai-btn-html').value = htmlField.value;
    runAutoAnalyze();
}

/* ── Live analyze (debounced) ── */
function liveAnalyze(){
    clearTimeout(liveAnalyzeTimer);
    liveAnalyzeTimer = setTimeout(runAutoAnalyze, 600);
}

/* ── Core AI analyzer ── */
function runAutoAnalyze(){
    var name  = (document.getElementById('ai-btn-name').value  || '').toLowerCase();
    var html  = (document.getElementById('ai-btn-html').value  || '').toLowerCase();
    var combined = name + ' ' + html;

    /* Remove old ai-pick */
    for(var i=1;i<=9;i++){
        var c=document.getElementById('dcard-'+i);
        if(c) c.classList.remove('ai-pick');
    }

    var bar=document.getElementById('ai-result-bar');
    var txt=document.getElementById('ai-result-text');

    if(!combined.trim() || combined.trim().length < 2){
        bar.classList.remove('visible');
        return;
    }

    /* Score each design */
    var scores={};
    Object.keys(DESIGNS).forEach(function(k){
        scores[k]=0;
        DESIGNS[k].keywords.forEach(function(kw){
            if(combined.indexOf(kw)>-1) scores[k]+=10;
        });
        /* Bonus: name length weight */
        if(name.length > 3) scores[k] += 1;
    });

    /* Extra HTML-specific signals */
    if(combined.indexOf('download')>-1||combined.indexOf('apk')>-1) scores[1]+=15;
    if(combined.indexOf('hub')>-1||combined.indexOf('admin')>-1)    scores[2]+=15;
    if(combined.indexOf('magic')>-1||combined.indexOf('template')>-1)scores[3]+=15;
    if(combined.indexOf('host')>-1||combined.indexOf('upload')>-1)  scores[4]+=15;
    if(combined.indexOf('team')>-1||combined.indexOf('social')>-1)  scores[5]+=15;
    if(combined.indexOf('premium')>-1||combined.indexOf('vip')>-1)  scores[6]+=15;
    if(combined.indexOf('api')>-1||combined.indexOf('bot')>-1||combined.indexOf('code')>-1) scores[7]+=15;
    if(combined.indexOf('glass')>-1||combined.indexOf('minimal')>-1)scores[8]+=15;
    if(combined.indexOf('delete')>-1||combined.indexOf('delet')>-1||combined.indexOf('danger')>-1) scores[9]+=15;

    /* Find winner */
    var best=1, bestScore=0;
    Object.keys(scores).forEach(function(k){
        if(scores[k]>bestScore){ bestScore=scores[k]; best=parseInt(k); }
    });

    /* If all zero — pick based on first letter of name */
    if(bestScore===0){
        var firstLetter=name.charAt(0);
        var letterMap={a:1,b:4,c:3,d:9,e:4,f:3,g:2,h:3,i:7,j:5,k:2,l:8,m:5,n:7,o:1,p:6,q:7,r:9,s:4,t:3,u:6,v:2,w:1,x:7,y:5,z:6};
        best=letterMap[firstLetter]||2;
    }

    aiPickedDesign=best;
    /* Also clear manual selected highlight so AI pick stands out */
    for(var j=1;j<=9;j++){
        var cc=document.getElementById('dcard-'+j);
        if(cc && j!==best) cc.classList.remove('selected','ai-pick');
    }
    var chosen=document.getElementById('dcard-'+best);
    if(chosen){ chosen.classList.add('ai-pick'); chosen.scrollIntoView({behavior:'smooth',block:'center'}); }
    /* Mark its select button too */
    var csb=document.getElementById('dsbtn-'+best);
    if(csb){ csb.innerHTML='<i class="fas fa-robot"></i> 🤖 AI Pick!'; csb.style.borderColor='#a78bfa'; csb.style.color='#a78bfa'; }

    /* Auto-pick icon too */
    autoPickIcon(combined);

    /* Fill confirm panel automatically */
    selectedDesign = best;
    fillConfirmPanel(best);

    /* Apply color to main form */
    var dd=DESIGNS[best];
    if(dd){ ['colorPicker','colorText','colorHidden'].forEach(function(id){ var el=document.getElementById(id); if(el) el.value=dd.hex; }); }

    /* Show result bar */
    bar.classList.add('visible');
    var displayName = name ? ('"'+name.toUpperCase()+'"') : 'aapke button';
    var iconMsg = selectedIcon ? ' + icon <strong><i class="fas '+selectedIcon+'"></i></strong>' : '';
    txt.innerHTML='🤖 AI ne select kiya — <strong>'+DESIGNS[best].name+'</strong>'+iconMsg+' <span style="color:#6ee7b7">— Neeche confirm panel mein naam check karo aur ADD KARO dabao!</span>';

    /* Update all card previews with typed name */
    updateCardPreviews(name);

    /* Show live preview for AI pick */
    renderLivePreview(best);

    /* Animate scan button */
    var btn=document.getElementById('auto-analyze-btn');
    if(btn){
        var orig=btn.innerHTML;
        btn.classList.add('scanning');
        btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Scanning...';
        setTimeout(function(){
            btn.classList.remove('scanning');
            btn.innerHTML='<i class="fas fa-search-plus"></i> Auto Design Dhundo';
        },900);
    }
}

/* ── Update preview button text on all cards ── */
function updateCardPreviews(name){
    var displayName = name ? name.toUpperCase().substring(0,18) : 'MY BUTTON';
    for(var i=1;i<=9;i++){
        var spans=document.querySelectorAll('.dpname-'+i);
        spans.forEach(function(s){ s.textContent=displayName; });
    }
}

/* ── Render live big preview ── */
function renderLivePreview(num){
    var d=DESIGNS[num];
    if(!d) return;
    var wrap=document.getElementById('live-preview-wrap');
    var stage=document.getElementById('live-preview-stage');
    if(!wrap||!stage) return;

    var name=(document.getElementById('ai-btn-name').value||'MY BUTTON').toUpperCase().substring(0,20);

    /* Use manually/AI selected icon, fallback to design default */
    var icon    = selectedIcon || DESIGN_ICONS[num] || 'fa-star';
    var iconLib = isIconLibrary ? 'fab' : 'fas';

    /* Extra style for neon */
    var extraStyle='';
    if(num===7) extraStyle='text-shadow:0 0 8px #00ffcc;border:2px solid #00ffcc;';

    var btnHtml='<button style="padding:11px 24px;background:'+d.bg+';color:'+d.textColor+
        ';border:none;border-radius:24px;font-size:.92rem;font-weight:800;display:inline-flex;align-items:center;gap:9px;'+
        'box-shadow:0 6px 22px rgba(0,0,0,.4);letter-spacing:.05em;cursor:default;'+extraStyle+'">'+
        '<i class="'+iconLib+' '+icon+'"></i> '+name+
        '</button>';

    /* Also render all 9 design mini-previews with this icon */
    for(var i=1;i<=9;i++){
        var dd=DESIGNS[i];
        if(!dd) continue;
        var ex2 = i===7 ? 'text-shadow:0 0 6px #00ffcc;border:2px solid #00ffcc;' : '';
        var mini='<button style="padding:8px 16px;background:'+dd.bg+';color:'+dd.textColor+
            ';border:none;border-radius:20px;font-size:.78rem;font-weight:bold;display:inline-flex;align-items:center;gap:5px;'+
            'box-shadow:0 4px 12px rgba(0,0,0,.3);pointer-events:none;'+ex2+'">'+
            '<i class="'+iconLib+' '+icon+'"></i> <span class="dpname-'+i+'">'+name+'</span></button>';
        var pv=document.getElementById('dpreview-'+i);
        if(pv) pv.innerHTML = mini;  /* innerHTML preserves the container div so it stays findable */
    }

    stage.innerHTML=btnHtml;
    wrap.classList.add('visible');
}

function goToButtons() { showTab('tab-buttons'); }

/* ══════════════════════════════════════════
   CUSTOM BUTTONS TAB — Icon & Color pickers
   ══════════════════════════════════════════ */

/* Pick icon in Custom Buttons form */
function cbPickIcon(el, iconClass, iconLib) {
    /* Deselect all swatches */
    if(el) {
        el.parentElement.querySelectorAll('div').forEach(function(d){
            d.style.background = 'rgba(255,255,255,.07)';
            d.style.borderColor = 'rgba(255,255,255,.15)';
            d.style.color = '#94a3b8';
            d.style.transform = '';
        });
        el.style.background = 'linear-gradient(135deg,#0284c7,#38bdf8)';
        el.style.borderColor = '#38bdf8';
        el.style.color = '#fff';
        el.style.transform = 'scale(1.15)';
    }
    /* Save value */
    document.getElementById('cb-icon-hidden').value = iconClass;

    /* Update label */
    var lbl = document.getElementById('cb-icon-label');
    if(lbl) lbl.innerHTML = '<i class="'+(iconLib||'fas')+' '+iconClass+'"></i> Selected: '+iconClass;

    /* Update live preview icon */
    var prevIcon = document.getElementById('cb-preview-icon');
    if(prevIcon) { prevIcon.className = (iconLib||'fas')+' '+iconClass; }
}

/* Pick color in Custom Buttons form */
function cbPickColor(el, hexVal, gradientVal) {
    /* Deselect all swatches */
    var swatchWrap = document.querySelector('#cb-color-preview') && document.querySelector('#cb-color-preview').parentElement;
    if(swatchWrap) {
        swatchWrap.querySelectorAll('div[onclick^="cbPickColor"]').forEach(function(d){
            d.style.outline = '';
            d.style.outlineOffset = '';
        });
    }
    if(el) {
        el.style.outline = '3px solid #fff';
        el.style.outlineOffset = '2px';
    }

    /* Save hex to hidden + visible fields */
    var hid = document.getElementById('colorHidden');
    var pic = document.getElementById('colorPicker');
    var txt = document.getElementById('colorText');
    if(hid) hid.value = hexVal;
    if(pic && hexVal.match(/^#[0-9a-fA-F]{6}$/)) pic.value = hexVal;
    if(txt) txt.value = hexVal;

    /* Update live preview background */
    var prev = document.getElementById('cb-color-preview');
    if(prev) {
        var grad = gradientVal && gradientVal.indexOf(',') > -1
            ? 'linear-gradient(135deg,'+gradientVal+')'
            : hexVal;
        prev.style.background = grad;
    }
}

/* Sync preview name as user types button name */
(function(){
    var nameIn = document.querySelector('#main-form input[name="btn_name"]');
    if(!nameIn) return;
    nameIn.addEventListener('input', function(){
        var sp = document.getElementById('cb-preview-name');
        if(sp) sp.textContent = this.value || 'Button Preview';
    });
})();

/* ── HTML Editor helpers ── */
function pasteHtml() {
    navigator.clipboard.readText().then(t => {
        document.getElementById('htmlEditor').value = t;
    }).catch(() => alert('Paste nahi hua — browser permission check karo'));
}
function clearHtml() {
    if (confirm('Clear karo?')) document.getElementById('htmlEditor').value = '';
}
function previewHtml() {
    const html  = document.getElementById('htmlEditor').value;
    const box   = document.getElementById('previewBox');
    const frame = document.getElementById('previewFrame');
    if (!html.trim()) { box.style.display='none'; return; }
    const blob  = new Blob([html], { type:'text/html' });
    frame.src   = URL.createObjectURL(blob);
    box.style.display = 'block';
}

/* ── Sync hidden color input ── */
function syncColor() {
    document.getElementById('colorHidden').value = document.getElementById('colorText').value;
}
document.getElementById('colorText')?.addEventListener('input', function() {
    document.getElementById('colorHidden').value = this.value;
});

/* ── Copy URL ── */
function copyUrl(url) {
    navigator.clipboard.writeText(url).then(() => {
        const n = document.getElementById('notify');
        n.style.transform = 'translateX(0)';
        setTimeout(() => n.style.transform = 'translateX(200%)', 2500);
    });
}

/* Fix: color field name before submit */
document.querySelector('#main-form')?.addEventListener('submit', function() {
    const btn = this.querySelector('input[name="btn_color_hidden"]');
    const txt = document.getElementById('colorText');
    const colorField = this.querySelector('input[name="btn_color"]');
    if (colorField) colorField.value = txt?.value || '#667eea';
});
</script>
</body>
</html>
