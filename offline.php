<?php
// Offline fallback page — shown by the service worker when the network is unavailable.
// No PHP session / DB needed here; this page is served from the SW cache.
$title = 'You\'re Offline';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#2563eb">
<title>Offline — DentalCare</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--blue:#2563eb;--blue-dark:#03112b;--gray:#64748b;--light:#f1f5f9;}
body{
    font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
    background:var(--blue-dark);
    color:#e2e8f0;
    min-height:100vh;
    display:flex;align-items:center;justify-content:center;
    padding:24px;
    text-align:center;
}
.card{
    background:rgba(255,255,255,0.05);
    border:1px solid rgba(255,255,255,0.1);
    border-radius:20px;
    padding:48px 40px;
    max-width:400px;
    width:100%;
}
.icon-wrap{
    width:72px;height:72px;
    background:rgba(37,99,235,0.2);
    border-radius:20px;
    display:flex;align-items:center;justify-content:center;
    margin:0 auto 24px;
}
.icon-wrap svg{width:36px;height:36px;stroke:#60a5fa;fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;}
h1{font-size:1.4rem;font-weight:800;color:#f1f5f9;margin-bottom:10px;}
p{font-size:0.9rem;color:#94a3b8;line-height:1.6;margin-bottom:28px;}
.btn{
    display:inline-flex;align-items:center;gap:8px;
    background:#2563eb;color:#fff;
    padding:12px 28px;border-radius:12px;
    font-size:0.9rem;font-weight:600;
    text-decoration:none;border:none;cursor:pointer;
    transition:background 0.2s,transform 0.1s;
}
.btn:hover{background:#1d4ed8;transform:translateY(-1px);}
.btn:active{transform:none;}
.btn svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
.status{
    margin-top:20px;font-size:0.75rem;color:#475569;
    display:flex;align-items:center;justify-content:center;gap:6px;
}
.dot{width:8px;height:8px;border-radius:50%;background:#475569;animation:pulse 2s infinite;}
@keyframes pulse{0%,100%{opacity:.4}50%{opacity:1}}
</style>
</head>
<body>
<div class="card">

    <div class="icon-wrap">
        <!-- wifi-off icon -->
        <svg viewBox="0 0 24 24">
            <line x1="1" y1="1" x2="23" y2="23"/>
            <path d="M16.72 11.06A10.94 10.94 0 0 1 19 12.55"/>
            <path d="M5 12.55a10.94 10.94 0 0 1 5.17-2.39"/>
            <path d="M10.71 5.05A16 16 0 0 1 22.56 9"/>
            <path d="M1.42 9a15.91 15.91 0 0 1 4.7-2.88"/>
            <path d="M8.53 16.11a6 6 0 0 1 6.95 0"/>
            <line x1="12" y1="20" x2="12.01" y2="20"/>
        </svg>
    </div>

    <h1>You're offline</h1>
    <p>The app can't reach the server right now. Check your connection and try again.</p>

    <button class="btn" onclick="tryReload()">
        <svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.49"/></svg>
        Try Again
    </button>

    <div class="status">
        <span class="dot" id="statusDot"></span>
        <span id="statusText">Waiting for connection…</span>
    </div>
</div>

<script>
function tryReload() {
    window.location.reload();
}

// Automatically reload when network comes back
window.addEventListener('online', function() {
    document.getElementById('statusDot').style.background = '#16a34a';
    document.getElementById('statusText').textContent = 'Connection restored — reloading…';
    setTimeout(function(){ window.location.reload(); }, 800);
});

// Check every 5 seconds
setInterval(function() {
    if (navigator.onLine) {
        fetch(window.location.origin + '/dashboard.php', { method: 'HEAD', cache: 'no-store' })
            .then(function(r){ if(r.ok) window.location.reload(); })
            .catch(function(){});
    }
}, 5000);
</script>
</body>
</html>
