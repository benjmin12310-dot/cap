<?php
$title = ($page_title ?? 'Page') . ' | ' . APP_NAME;
$_script = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');
$_app_root = '';
foreach (['/modules/', '/includes/', '/api/'] as $_sub) {
    $_pos = strpos($_script, $_sub);
    if ($_pos !== false) { $_app_root = substr($_script, 0, $_pos); break; }
}
if (!$_app_root) $_app_root = dirname($_script);
$_app_root = rtrim($_app_root, '/');
$_bootstrap_css_local   = file_exists($_app_root . '/assets/css/bootstrap.min.css');
$_bootstrap_js_local    = file_exists($_app_root . '/assets/js/bootstrap.bundle.min.js');
$_bootstrap_icons_local = file_exists($_app_root . '/assets/css/bootstrap-icons.css');
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $title; ?></title>

<!-- Favicon -->
<link rel="icon"             type="image/x-icon"     href="<?php echo BASE_URL; ?>assets/images/favicon.ico">
<link rel="icon"             type="image/svg+xml"     href="<?php echo BASE_URL; ?>assets/images/favicon.svg">
<link rel="icon"             type="image/png" sizes="32x32" href="<?php echo BASE_URL; ?>assets/images/favicon-32.png">
<link rel="icon"             type="image/png" sizes="16x16" href="<?php echo BASE_URL; ?>assets/images/favicon-16.png">
<link rel="apple-touch-icon" sizes="180x180"         href="<?php echo BASE_URL; ?>assets/images/favicon.svg">
<meta name="theme-color" content="#2563eb">

<!-- PWA manifest -->
<link rel="manifest" href="<?php echo BASE_URL; ?>manifest.json">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="DentalCare">
<meta name="sw-path" content="<?php echo rtrim(parse_url(BASE_URL, PHP_URL_PATH), '/'); ?>/sw.js">

<style>
/* PWA Install Banner */
#pwa-install-banner {
    position: fixed; bottom: 0; left: 0; right: 0; z-index: 9999;
    padding: 0 16px 12px; transform: translateY(110%);
    transition: transform 0.32s cubic-bezier(0.34,1.56,0.64,1); pointer-events: none;
}
#pwa-install-banner.pwa-banner--visible { transform: translateY(0); pointer-events: auto; }
.pwa-banner-inner {
    max-width: 520px; margin: 0 auto; background: var(--blue-900, #071d40);
    border: 1px solid rgba(255,255,255,0.12); border-radius: 16px; padding: 14px 16px;
    display: flex; align-items: center; gap: 12px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.35), 0 0 0 1px rgba(37,99,235,0.3);
}
.pwa-banner-icon {
    width: 44px; height: 44px; background: rgba(37,99,235,0.25); border-radius: 12px;
    display: flex; align-items: center; justify-content: center; color: #60a5fa; flex-shrink: 0;
}
.pwa-banner-text { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.pwa-banner-text strong { font-size: 0.88rem; font-weight: 700; color: #f1f5f9; }
.pwa-banner-text span   { font-size: 0.75rem; color: #94a3b8; }
.pwa-banner-btn {
    background: #2563eb; color: #fff; border: none; border-radius: 10px;
    padding: 8px 18px; font-size: 0.82rem; font-weight: 700; cursor: pointer;
    white-space: nowrap; flex-shrink: 0; transition: background 0.15s, transform 0.1s;
}
.pwa-banner-btn:hover  { background: #1d4ed8; transform: translateY(-1px); }
.pwa-banner-btn:active { transform: none; }
.pwa-banner-close {
    background: rgba(255,255,255,0.08); border: none; border-radius: 8px;
    width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;
    cursor: pointer; color: #94a3b8; flex-shrink: 0; transition: background 0.15s;
}
.pwa-banner-close:hover { background: rgba(255,255,255,0.15); color: #f1f5f9; }
#pwa-update-toast {
    position: fixed; top: 16px; left: 50%;
    transform: translateX(-50%) translateY(-80px); background: #1e40af; color: #fff;
    padding: 10px 18px; border-radius: 12px; font-size: 0.83rem; font-weight: 500;
    z-index: 10000; box-shadow: 0 4px 16px rgba(0,0,0,0.3); display: flex; align-items: center;
    transition: transform 0.3s cubic-bezier(0.34,1.56,0.64,1), opacity 0.3s;
    opacity: 0; pointer-events: none;
}
#pwa-update-toast.pwa-toast--visible {
    transform: translateX(-50%) translateY(0); opacity: 1; pointer-events: auto;
}
@media (max-width: 480px) {
    .pwa-banner-text span { display: none; }
    .pwa-banner-inner { gap: 8px; }
}
</style>

<?php if ($_bootstrap_css_local): ?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/bootstrap.min.css">
<?php else: ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<?php endif; ?>

<?php if ($_bootstrap_icons_local): ?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/bootstrap-icons.css">
<?php else: ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<?php endif; ?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/accessibility.css">
<script>
(function () {
    var t = localStorage.getItem('theme');
    if (t === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
        document.documentElement.setAttribute('data-bs-theme', 'dark');
    }
})();
</script>
<?php
$GLOBALS['_bootstrap_js_local'] = $_bootstrap_js_local;
?>
