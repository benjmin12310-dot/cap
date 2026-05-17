<?php
// Edit an existing user account (name, email, role, password).

require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_admin();

$page_title = 'Edit User';

$id = secure_int($_GET['id'] ?? 0);
if (!$id) { header('Location: list.php'); exit(); }

// DATABASE SECURITY: $id is secure_int() — safe positive integer only
$user = $conn->query("SELECT * FROM users WHERE id = $id LIMIT 1")->fetch_assoc();
if (!$user) { header('Location: list.php'); exit(); }

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $role      = $_POST['role'] ?? 'staff';
    $password  = trim($_POST['password'] ?? '');
    $confirm   = trim($_POST['confirm_password'] ?? '');

    if (empty($full_name)) {
        $error = 'Full name is required.';
    } elseif (empty($email)) {
        $error = 'Email address is required.';
    } elseif (!valid_email($email)) {
        $error = 'Please enter a valid email address.';
    } elseif (empty($phone)) {
        $error = 'Phone number is required.';
    } elseif (!valid_phone($phone)) {
        $error = 'Please enter a valid phone number.';
    } elseif (!empty($password) && $password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (!empty($password) && ($pw_err = validate_password($password))) {
        $error = $pw_err;
    } else {
        if (!empty($password)) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, phone=?, role=?, password=? WHERE id=?");
            $stmt->bind_param('sssssi', $full_name, $email, $phone, $role, $hashed, $id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, phone=?, role=? WHERE id=?");
            $stmt->bind_param('ssssi', $full_name, $email, $phone, $role, $id);
        }

        if ($stmt->execute()) {
            log_action($conn, $current_user_id, $current_user_name, 'Edited User', 'users', $id, "Updated user: {$user['username']}");
            $success = 'User updated successfully.';
            $user = $conn->query("SELECT * FROM users WHERE id = $id LIMIT 1")->fetch_assoc();
        } else {
            $error = 'Failed to update. Please try again.';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head><?php include '../../includes/head.php'; ?></head>
<body>
<?php include '../../includes/sidebar.php'; ?>
<div class="main-content">
    <?php include '../../includes/header.php'; ?>
    <div class="page-content">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5>Edit User — <?php echo htmlspecialchars($user['username']); ?></h5>
            <a href="list.php" class="btn btn-sm btn-outline-secondary">Back</a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="card" style="max-width:500px;">
            <div class="card-body">
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="form-control" required value="<?php echo htmlspecialchars($user['full_name']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                            <small class="text-muted">Username cannot be changed.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select" <?php echo $id === $current_user_id ? 'disabled' : ''; ?>>
                                <option value="staff" <?php echo $user['role'] === 'staff' ? 'selected' : ''; ?>>Staff</option>
                                <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            </select>
                            <?php if ($id === $current_user_id): ?>
                                <input type="hidden" name="role" value="<?php echo $user['role']; ?>">
                                <small class="text-muted">Cannot change your own role.</small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required placeholder="user@example.com" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <?php
                                $phone_field_name     = 'phone';
                                $phone_field_value    = $user['phone'] ?? '';
                                $phone_field_label    = 'Phone';
                                $phone_field_required = true;
                                include '../../includes/phone_input.php';
                            ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">New Password <small class="text-muted">(leave blank to keep current)</small></label>
                            <div style="position:relative;">
                                <input type="password" name="password" id="pw" class="form-control"
                                       autocomplete="new-password" placeholder="8–18 chars"
                                       oninput="checkStrength(this.value)">
                                <i class="bi bi-eye" id="togPw" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);color:var(--gray-400);cursor:pointer;"></i>
                            </div>
                            <div id="strengthBar" style="height:4px;border-radius:4px;margin-top:6px;background:var(--gray-200);"><div id="strengthFill" style="height:100%;border-radius:4px;width:0%;transition:width 0.3s,background 0.3s;"></div></div>
                            <div id="strengthText" style="font-size:0.72rem;margin-top:3px;min-height:15px;font-weight:600;"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Confirm New Password</label>
                            <div style="position:relative;">
                                <input type="password" name="confirm_password" id="pwConf" class="form-control"
                                       autocomplete="new-password" oninput="checkMatch()">
                                <i class="bi bi-eye" id="togConf" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);color:var(--gray-400);cursor:pointer;"></i>
                            </div>
                            <div id="matchText" style="font-size:0.72rem;margin-top:3px;min-height:15px;font-weight:600;"></div>
                        </div>
                        <div class="col-12">
                            <div style="background:var(--gray-50);border:1px solid var(--gray-200);border-radius:8px;padding:9px 14px;font-size:0.75rem;color:var(--gray-500);display:flex;gap:14px;flex-wrap:wrap;">
                                <span id="rule_len">⬜ 8–18 chars</span>
                                <span id="rule_upper">⬜ uppercase (A–Z)</span>
                                <span id="rule_lower">⬜ lowercase (a–z)</span>
                                <span id="rule_num">⬜ number (0–9)</span>
                                <span id="rule_spec">⬜ special (@#$!...)</span>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <a href="list.php" class="btn btn-outline-secondary ms-2">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>
<script>
function togglePw(id, icon) {
    var i = document.getElementById(id), t = i.type === 'text';
    i.type = t ? 'password' : 'text';
    icon.className = (t ? 'bi bi-eye' : 'bi bi-eye-slash');
    icon.style.cssText = 'position:absolute;right:10px;top:50%;transform:translateY(-50%);color:var(--gray-400);cursor:pointer;';
}
document.getElementById('togPw').onclick   = function(){ togglePw('pw', this); };
document.getElementById('togConf').onclick = function(){ togglePw('pwConf', this); };

function setRule(id, ok) {
    var e = document.getElementById(id);
    e.textContent = (ok ? '✅' : '⬜') + e.textContent.slice(2);
    e.style.color = ok ? 'var(--success)' : 'var(--gray-500)';
    e.style.fontWeight = ok ? '600' : '400';
}
function checkStrength(v) {
    var rl  = v.length >= 8 && v.length <= 18;
    var ru  = /[A-Z]/.test(v);
    var rlw = /[a-z]/.test(v);
    var rn  = /[0-9]/.test(v);
    var rs  = /[^A-Za-z0-9]/.test(v);
    setRule('rule_len',   rl);
    setRule('rule_upper', ru);
    setRule('rule_lower', rlw);
    setRule('rule_num',   rn);
    setRule('rule_spec',  rs);
    var score = [rl, ru, rlw, rn, rs].filter(Boolean).length;
    var fills = ['0%','20%','40%','60%','80%','100%'];
    var cols  = ['','#ef4444','#f97316','#eab308','#84cc16','#22c55e'];
    var labs  = ['','Weak','Fair','Moderate','Good','Strong ✓'];
    document.getElementById('strengthFill').style.width      = fills[score];
    document.getElementById('strengthFill').style.background = cols[score] || '';
    document.getElementById('strengthText').textContent      = v.length ? labs[score] : '';
    document.getElementById('strengthText').style.color      = cols[score] || '';
    checkMatch();
}
function checkMatch() {
    var pw = document.getElementById('pw').value;
    var cf = document.getElementById('pwConf').value;
    var mt = document.getElementById('matchText');
    if (!cf) { mt.textContent = ''; return; }
    mt.textContent = pw === cf ? '✓ Passwords match' : '✗ Do not match';
    mt.style.color = pw === cf ? 'var(--success)' : 'var(--danger)';
}
</script>
</body>
</html>
