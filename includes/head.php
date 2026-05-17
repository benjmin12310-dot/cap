<?php
$title = ($page_title ?? 'Page') . ' | ' . APP_NAME;
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
<link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/bootstrap.min.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/bootstrap-icons.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
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
