<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: ../patients/list.php'); exit(); }

$patient = $conn->query("SELECT * FROM patients WHERE id = $id LIMIT 1")->fetch_assoc();
if (!$patient) { header('Location: ../patients/list.php'); exit(); }

$records = $conn->query("
    SELECT dr.*, s.service_name, u.full_name as recorded_by_name
    FROM dental_records dr
    LEFT JOIN services s ON dr.service_id = s.id
    LEFT JOIN users u ON dr.recorded_by = u.id
    WHERE dr.patient_id = $id
    ORDER BY dr.visit_date DESC
")->fetch_all(MYSQLI_ASSOC);

$appointments = $conn->query("
    SELECT a.*, s.service_name FROM appointments a
    LEFT JOIN services s ON a.service_id = s.id
    WHERE a.patient_id = $id ORDER BY a.appointment_date DESC LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

$age = $patient['date_of_birth'] ? date_diff(date_create($patient['date_of_birth']), date_create('today'))->y . ' yrs' : '—';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Patient Profile — <?php echo e($patient['patient_code']); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/print.css">
</head>
<body>
<div class="print-toolbar">
    <button onclick="window.print()" style="padding:8px 16px;background:#2563eb;color:#fff;border:none;border-radius:8px;cursor:pointer;font-family:inherit;">🖨️ Print Profile</button>
    <button onclick="downloadPDF('patientProfile','<?php echo e($patient["patient_code"]); ?>-profile')" style="padding:8px 16px;background:#fff;color:#2563eb;border:1.5px solid #2563eb;border-radius:8px;cursor:pointer;font-family:inherit;">📄 Download PDF</button>
    <a href="../patients/view.php?id=<?php echo $id; ?>" style="padding:8px 16px;background:#fff;color:#64748b;border:1.5px solid #e2e8f0;border-radius:8px;text-decoration:none;">← Back</a>
</div>

<div class="print-page" id="patientProfile">
    <div class="doc-header">
        <div class="clinic-info">
            <div class="clinic-logo">🦷</div>
            <div>
                <div class="clinic-name">DentalCare Clinic</div>
                <div class="clinic-sub">Patient Record Sheet</div>
            </div>
        </div>
        <div class="doc-meta">
            <div class="doc-title">Patient Profile</div>
            <div class="doc-code"><?php echo e($patient['patient_code']); ?></div>
            <div class="doc-date">Printed: <?php echo date('M d, Y h:i A'); ?></div>
        </div>
    </div>

    <div class="doc-section">
        <div class="section-title">Personal Information</div>
        <div class="info-grid cols-3">
            <div class="info-item"><div class="info-label">Full Name</div><div class="info-value"><?php echo e($patient['last_name'].', '.$patient['first_name'].' '.($patient['middle_name']??'')); ?></div></div>
            <div class="info-item"><div class="info-label">Patient Code</div><div class="info-value"><?php echo e($patient['patient_code']); ?></div></div>
            <div class="info-item"><div class="info-label">Age</div><div class="info-value"><?php echo $age; ?></div></div>
            <div class="info-item"><div class="info-label">Date of Birth</div><div class="info-value"><?php echo $patient['date_of_birth'] ? date('M d, Y', strtotime($patient['date_of_birth'])) : '—'; ?></div></div>
            <div class="info-item"><div class="info-label">Gender</div><div class="info-value"><?php echo ucfirst($patient['gender'] ?? '—'); ?></div></div>
            <div class="info-item"><div class="info-label">Civil Status</div><div class="info-value"><?php echo ucfirst($patient['civil_status'] ?? '—'); ?></div></div>
            <div class="info-item"><div class="info-label">Blood Type</div><div class="info-value"><?php echo e($patient['blood_type'] ?? '—'); ?></div></div>
            <div class="info-item"><div class="info-label">Phone</div><div class="info-value"><?php echo e($patient['phone'] ?? '—'); ?></div></div>
            <div class="info-item"><div class="info-label">Email</div><div class="info-value"><?php echo e($patient['email'] ?? '—'); ?></div></div>
            <div class="info-item"><div class="info-label">Occupation</div><div class="info-value"><?php echo e($patient['occupation'] ?? '—'); ?></div></div>
        </div>
        <div class="info-grid cols-1" style="margin-top:8px;">
            <div class="info-item"><div class="info-label">Address</div><div class="info-value"><?php echo e($patient['address'] ?? '—'); ?></div></div>
        </div>
    </div>

    <div class="doc-section">
        <div class="section-title">Emergency Contact</div>
        <div class="info-grid">
            <div class="info-item"><div class="info-label">Contact Name</div><div class="info-value"><?php echo e($patient['emergency_contact_name'] ?? '—'); ?></div></div>
            <div class="info-item"><div class="info-label">Contact Phone</div><div class="info-value"><?php echo e($patient['emergency_contact_phone'] ?? '—'); ?></div></div>
        </div>
    </div>

    <div class="doc-section">
        <div class="section-title">Medical Background</div>
        <div class="info-grid">
            <div class="info-item"><div class="info-label">Known Allergies</div><div class="info-value"><?php echo e($patient['allergies'] ?? '—'); ?></div></div>
            <div class="info-item"><div class="info-label">Medical Notes</div><div class="info-value"><?php echo e($patient['medical_notes'] ?? '—'); ?></div></div>
        </div>
        <?php if (!empty($patient['illness_history'])): ?>
        <div class="info-grid cols-1" style="margin-top:8px;">
            <div class="info-item"><div class="info-label">History of Illness</div><div class="info-value"><?php echo nl2br(e($patient['illness_history'])); ?></div></div>
        </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($records)): ?>
    <div class="doc-section">
        <div class="section-title">Dental Treatment History</div>
        <table class="doc-table">
            <thead><tr><th>Date</th><th>Service</th><th>Tooth</th><th>Chief Complaint</th><th>Treatment Done</th><th>By</th></tr></thead>
            <tbody>
                <?php foreach ($records as $r): ?>
                <tr>
                    <td><?php echo date('M d, Y', strtotime($r['visit_date'])); ?></td>
                    <td><?php echo e($r['service_name'] ?? '—'); ?></td>
                    <td><?php echo e($r['tooth_number'] ?? '—'); ?></td>
                    <td><em><?php echo e($r['chief_complaint'] ?: '—'); ?></em></td>
                    <td><?php echo e($r['treatment_done']); ?></td>
                    <td><?php echo e($r['recorded_by_name'] ?? '—'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <div class="sig-row">
        <div class="sig-item"><div class="sig-line"></div><div class="sig-label">Patient / Guardian Signature</div></div>
        <div class="sig-item"><div class="sig-line"></div><div class="sig-label">Dentist / Staff Signature</div></div>
    </div>

    <div class="doc-footer">DentalCare Clinic — Registered: <?php echo date('M d, Y', strtotime($patient['created_at'])); ?></div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
function downloadPDF(elementId, filename) {
    html2pdf().set({ margin:8, filename: filename+'.pdf', image:{type:'jpeg',quality:0.98}, html2canvas:{scale:2}, jsPDF:{unit:'mm',format:'a4',orientation:'portrait'} }).from(document.getElementById(elementId)).save();
}
</script>
</body>
</html>
