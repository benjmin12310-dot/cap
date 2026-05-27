<?php
// footer.php — Closing scripts included at the bottom of every admin page.
$_bs_js_local = $GLOBALS['_bootstrap_js_local'] ?? file_exists(dirname(__DIR__) . '/assets/js/bootstrap.bundle.min.js');
?>
<?php if ($_bs_js_local): ?>
<script src="<?php echo BASE_URL; ?>assets/js/bootstrap.bundle.min.js"></script>
<?php else: ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php endif; ?>
<script src="<?php echo BASE_URL; ?>assets/js/app.js"></script>
<script src="<?php echo BASE_URL; ?>assets/js/accessibility.js"></script>
<script src="<?php echo BASE_URL; ?>assets/js/pwa.js"></script>

<!-- Global phone input sync script (used by phone_input.php component) -->
<script>
function phoneInputSync(pid) {
    var sel   = document.getElementById(pid + '_country');
    var local = document.getElementById(pid + '_local');
    var full  = document.getElementById(pid + '_full');
    var hint  = document.getElementById(pid + '_hint');
    if (!sel || !local || !full) return;
    var code   = sel.value;
    var maxlen = parseInt(sel.selectedOptions[0]?.dataset?.maxlen || 15);
    var num    = local.value.replace(/\D/g, '');
    // Strip leading zero for countries where it's a trunk prefix (PH, UK, etc.)
    if (num.startsWith('0')) num = num.substring(1);
    if (num.length > maxlen) num = num.substring(0, maxlen);
    local.value = num;
    full.value  = num ? code + num : '';
    if (hint) hint.textContent = code + ' + local number without leading zero (max ' + maxlen + ' digits)';
}
// On page load, sync all phone inputs so hidden fields are pre-filled
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[id$="_wrap"].phone-input-wrap').forEach(function(wrap) {
        var pid = wrap.id.replace('_wrap', '');
        phoneInputSync(pid);
    });
});
</script>
