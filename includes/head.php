<?php
$title = ($page_title ?? 'Page') . ' | ' . APP_NAME;
$_base_path = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/') . '/';
// Detect app root path on disk for asset-existence checks
$_script = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');
$_app_root = '';
foreach (['/modules/', '/includes/', '/api/'] as $_sub) {
    $_pos = strpos($_script, $_sub);
    if ($_pos !== false) { $_app_root = substr($_script, 0, $_pos); break; }
}
if (!$_app_root) $_app_root = dirname($_script);
$_app_root = rtrim($_app_root, '/');
$_bootstrap_css_local  = file_exists($_app_root . '/assets/css/bootstrap.min.css');
$_bootstrap_js_local   = file_exists($_app_root . '/assets/js/bootstrap.bundle.min.js');
$_bootstrap_icons_local= file_exists($_app_root . '/assets/css/bootstrap-icons.css');
?>
<meta charset="UTF-8">
<meta http-equiv="Cache-Control" content="max-age=3600">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $title; ?></title>
<style>
/* Use system font — zero load time, looks identical */
:root { 
    --font-sans: 'Segoe UI', system-ui, -apple-system, sans-serif; 
}
body { font-family: var(--font-sans); }
</style>

<!-- Prefetch pages on hover = instant clicks -->
<script src="https://instant.page/5.2.0" type="module" 
    integrity="sha384-jnZyxPjiipYXnSU0ygqeac2q7CVYMbh84q0uHVRRxEtvFPiQYbXWUorga2aqZJ0z">
</script>

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
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/nprogress@0.2.0/nprogress.min.css">
<script src="https://cdn.jsdelivr.net/npm/nprogress@0.2.0/nprogress.min.js"></script>
<script>NProgress.configure({ showSpinner: false, speed: 200 });</script>
<?php
// Store for footer use
$GLOBALS['_bootstrap_js_local'] = $_bootstrap_js_local;
?>
