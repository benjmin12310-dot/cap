/**
 * pwa.js — PWA install prompt + service worker registration
 * Loaded from footer.php on every page.
 */

// ── 1. Register the Service Worker ───────────────────────────────────────────
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
        // Resolve the SW path relative to the app root (works on subpaths like /cap/)
        var swUrl = document.querySelector('meta[name="sw-path"]')?.content
                  || '/sw.js';
        navigator.serviceWorker.register(swUrl)
            .then(function (reg) {
                console.log('[PWA] Service Worker registered, scope:', reg.scope);

                // Notify user if a new version is waiting
                reg.addEventListener('updatefound', function () {
                    var newWorker = reg.installing;
                    newWorker.addEventListener('statechange', function () {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            showUpdateToast();
                        }
                    });
                });
            })
            .catch(function (err) {
                console.warn('[PWA] Service Worker registration failed:', err);
            });
    });
}

// ── 2. Install Prompt (Add to Home Screen) ───────────────────────────────────
var _deferredInstallPrompt = null;
var _installBanner = null;

window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    _deferredInstallPrompt = e;

    // Don't show if already dismissed this session
    if (sessionStorage.getItem('pwa_banner_dismissed')) return;

    // Small delay so it doesn't flash on every page load
    setTimeout(showInstallBanner, 1500);
});

// Hide banner when app is installed
window.addEventListener('appinstalled', function () {
    hideBanner();
    sessionStorage.setItem('pwa_banner_dismissed', '1');
    console.log('[PWA] App installed successfully.');
});

function showInstallBanner() {
    if (_installBanner) return; // already shown

    _installBanner = document.createElement('div');
    _installBanner.id = 'pwa-install-banner';
    _installBanner.setAttribute('role', 'complementary');
    _installBanner.setAttribute('aria-label', 'Install app');
    _installBanner.innerHTML = [
        '<div class="pwa-banner-inner">',
        '  <div class="pwa-banner-icon" aria-hidden="true">',
        '    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">',
        '      <path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2z"/>',
        '      <path d="M8 12l4 4 4-4"/><line x1="12" y1="8" x2="12" y2="16"/>',
        '    </svg>',
        '  </div>',
        '  <div class="pwa-banner-text">',
        '    <strong>Install DentalCare</strong>',
        '    <span>Add to your home screen for quick access</span>',
        '  </div>',
        '  <button class="pwa-banner-btn" id="pwa-install-btn" aria-label="Install app">Install</button>',
        '  <button class="pwa-banner-close" id="pwa-dismiss-btn" aria-label="Dismiss install prompt">',
        '    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
        '  </button>',
        '</div>',
    ].join('');

    document.body.appendChild(_installBanner);

    // Animate in
    requestAnimationFrame(function () {
        requestAnimationFrame(function () {
            _installBanner.classList.add('pwa-banner--visible');
        });
    });

    document.getElementById('pwa-install-btn').addEventListener('click', triggerInstall);
    document.getElementById('pwa-dismiss-btn').addEventListener('click', function () {
        hideBanner();
        sessionStorage.setItem('pwa_banner_dismissed', '1');
    });
}

function triggerInstall() {
    if (!_deferredInstallPrompt) return;
    hideBanner();
    _deferredInstallPrompt.prompt();
    _deferredInstallPrompt.userChoice.then(function (choice) {
        if (choice.outcome === 'accepted') console.log('[PWA] Install accepted');
        _deferredInstallPrompt = null;
    });
}

function hideBanner() {
    if (!_installBanner) return;
    _installBanner.classList.remove('pwa-banner--visible');
    setTimeout(function () {
        if (_installBanner && _installBanner.parentNode) {
            _installBanner.parentNode.removeChild(_installBanner);
        }
        _installBanner = null;
    }, 320);
}

// ── 3. "App Updated" toast ───────────────────────────────────────────────────
function showUpdateToast() {
    var toast = document.createElement('div');
    toast.id = 'pwa-update-toast';
    toast.setAttribute('role', 'alert');
    toast.innerHTML = [
        '<span>A new version is available.</span>',
        '<button onclick="window.location.reload()" style="margin-left:12px;padding:4px 10px;',
        'border-radius:8px;background:rgba(255,255,255,0.25);color:#fff;border:none;cursor:pointer;',
        'font-size:0.78rem;font-weight:600;">Refresh</button>',
    ].join('');
    document.body.appendChild(toast);
    requestAnimationFrame(function () {
        requestAnimationFrame(function () { toast.classList.add('pwa-toast--visible'); });
    });
}
