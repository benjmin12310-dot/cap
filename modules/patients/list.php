<?php
// List all active patients with search, and handle patient deletion.

require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

$page_title = 'Patient Records';

// Hard delete with cascade
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $del_id = secure_int($_POST['delete_id']);
    if ($del_id) {
        // Get name before deleting
        $nr = $conn->prepare("SELECT CONCAT(first_name,' ',last_name) as n FROM patients WHERE id = ? LIMIT 1");
        $nr->bind_param('i', $del_id);
        $nr->execute();
        $pname = $nr->get_result()->fetch_assoc()['n'] ?? 'Unknown';
        $nr->close();

        // Delete all related records first (in correct FK order)
        $conn->query("DELETE FROM bills           WHERE patient_id = $del_id");
        $conn->query("DELETE FROM dental_records  WHERE patient_id = $del_id");
        $conn->query("DELETE FROM appointments    WHERE patient_id = $del_id");
        $conn->query("DELETE FROM patients        WHERE id         = $del_id");

        log_action($conn, $current_user_id, $current_user_name, 'Deleted Patient', 'patients', $del_id, "Hard deleted: $pname and all related records.");
    }
    header('Location: list.php');
    exit();
}

$search   = trim($_GET['search'] ?? '');
$per_page = 20;
$page     = max(1, intval($_GET['page'] ?? 1));

$where = "WHERE p.is_active = 1";
if ($search) {
    $s = $conn->real_escape_string($search);
    $where .= " AND (p.first_name LIKE '%$s%' OR p.last_name LIKE '%$s%' OR p.patient_code LIKE '%$s%' OR p.phone LIKE '%$s%')";
}

$total_count = $conn->query("SELECT COUNT(*) as c FROM patients p $where")->fetch_assoc()['c'];
$total_pages = max(1, ceil($total_count / $per_page));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * $per_page;

$filter_qs = $search ? 'search=' . urlencode($search) . '&' : '';

$patients = $conn->query("
    SELECT p.*, COUNT(a.id) as total_visits
    FROM patients p
    LEFT JOIN appointments a ON a.patient_id = p.id AND a.status = 'completed'
    $where
    GROUP BY p.id
    ORDER BY p.last_name ASC, p.first_name ASC
    LIMIT $per_page OFFSET $offset
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head><?php include '../../includes/head.php'; ?></head>
<body>
<?php include '../../includes/sidebar.php'; ?>
<div class="main-content">
    <?php include '../../includes/header.php'; ?>
    <div class="page-content">

        <div class="page-header">
            <h5>Patient Records</h5>
            <div style="display:flex;gap:8px;">
                <a href="add.php" class="btn btn-primary btn-sm">
                    <i class="bi bi-person-plus"></i> Add Patient
                </a>
            </div>
        </div>

        <form method="GET" class="mb-3">
            <div class="input-group" style="max-width:400px;">
                <input type="text" name="search" class="form-control" placeholder="Search by name, code, or phone..." value="<?php echo htmlspecialchars($search); ?>">
                <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
                <?php if ($search): ?><a href="list.php" class="btn btn-outline-danger">Clear</a><?php endif; ?>
            </div>
        </form>

        <div class="card">
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Gender</th>
                            <th>Phone</th>
                            <th>Visits</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($patients)): ?>
                            <tr><td colspan="7" style="text-align:center;padding:32px;color:var(--gray-400);">
                                <?php echo $search ? 'No results for "'.htmlspecialchars($search).'".' : 'No patients yet.'; ?>
                            </td></tr>
                        <?php else: ?>
                            <?php foreach ($patients as $p): ?>
                            <tr>
                                <td style="font-weight:600;color:var(--blue-500);font-size:0.8rem;"><?php echo htmlspecialchars($p['patient_code']); ?></td>
                                <td style="font-weight:500;"><?php echo htmlspecialchars(ucwords(strtolower($p['last_name'])).', '.ucwords(strtolower($p['first_name']))); ?></td>
                                <td><?php echo ucfirst($p['gender'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($p['phone'] ?? '—'); ?></td>
                                <td><span class="badge bg-primary"><?php echo $p['total_visits']; ?></span></td>
                                <td style="font-size:0.8rem;color:var(--gray-500);"><?php echo date('M d, Y', strtotime($p['created_at'])); ?></td>
                                <td>
                                    <div style="display:flex;gap:6px;">
                                        <a href="view.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-info" title="View Patient">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="edit.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit Patient">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                            onclick="confirmDelete(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars($p['first_name'].' '.$p['last_name'], ENT_QUOTES); ?>')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="pagination-bar">
            <div class="pagination-info">
                Showing <?php echo number_format(($offset+1)); ?>–<?php echo number_format(min($offset+$per_page, $total_count)); ?> of <?php echo number_format($total_count); ?> patients
            </div>
            <div class="pagination-links">
                <?php if ($page > 1): ?>
                <a href="list.php?<?php echo $filter_qs; ?>page=<?php echo $page-1; ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-chevron-left"></i> Prev</a>
                <?php endif; ?>
                <?php for ($pg = max(1,$page-2); $pg <= min($total_pages,$page+2); $pg++): ?>
                <a href="list.php?<?php echo $filter_qs; ?>page=<?php echo $pg; ?>"
                   class="btn btn-sm <?php echo $pg === $page ? 'btn-primary' : 'btn-outline-secondary'; ?>"><?php echo $pg; ?></a>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                <a href="list.php?<?php echo $filter_qs; ?>page=<?php echo $page+1; ?>" class="btn btn-sm btn-outline-secondary">Next <i class="bi bi-chevron-right"></i></a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" style="color:var(--danger);">
                    <i class="bi bi-exclamation-triangle-fill"></i> Delete Patient
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="font-size:0.875rem;">
                Are you sure you want to delete <strong id="deletePatientName"></strong>?
                <div style="margin-top:10px;padding:10px;background:var(--danger-bg);border-radius:6px;font-size:0.78rem;color:var(--danger);">
                    <i class="bi bi-exclamation-triangle-fill"></i> <strong>Warning:</strong> This will permanently delete the patient AND all their appointments, dental records, and payments. This cannot be undone.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" style="margin:0;">
                    <input type="hidden" name="delete_id" id="deletePatientId">
                    <button type="submit" class="btn btn-sm btn-danger">
                        <i class="bi bi-trash"></i> Yes, Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
function confirmDelete(id, name) {
    document.getElementById('deletePatientId').value = id;
    document.getElementById('deletePatientName').textContent = name;
    deleteModal.show();
}
</script>
</body>
</html>
