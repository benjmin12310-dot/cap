<?php
// Patient Demographics Analytics — age, gender, civil status, blood type, occupation, registration trend.

require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_admin();

$page_title = 'Patient Demographics';

// ── Totals ────────────────────────────────────────────────────────────────────
$total_active   = (int)$conn->query("SELECT COUNT(*) as c FROM patients WHERE is_active = 1")->fetch_assoc()['c'];
$total_archived = (int)$conn->query("SELECT COUNT(*) as c FROM patients WHERE is_active = 0")->fetch_assoc()['c'];
$avg_age_row    = $conn->query("SELECT ROUND(AVG(TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()))) as a FROM patients WHERE is_active = 1 AND date_of_birth IS NOT NULL")->fetch_assoc();
$avg_age        = $avg_age_row['a'] ?? null;
$new_this_month = (int)$conn->query("SELECT COUNT(*) as c FROM patients WHERE is_active = 1 AND DATE_FORMAT(created_at,'%Y-%m') = DATE_FORMAT(NOW(),'%Y-%m')")->fetch_assoc()['c'];

// ── Gender breakdown ──────────────────────────────────────────────────────────
$gender_rows = $conn->query("
    SELECT COALESCE(gender,'unknown') as g, COUNT(*) as c
    FROM patients WHERE is_active = 1
    GROUP BY g ORDER BY c DESC
")->fetch_all(MYSQLI_ASSOC);
$gender_labels = []; $gender_values = []; $gender_map = [];
foreach ($gender_rows as $r) { $gender_labels[] = ucfirst($r['g']); $gender_values[] = (int)$r['c']; $gender_map[$r['g']] = (int)$r['c']; }

// ── Age groups ────────────────────────────────────────────────────────────────
$age_groups = ['0–10'=>[0,10],'11–20'=>[11,20],'21–30'=>[21,30],'31–40'=>[31,40],'41–50'=>[41,50],'51–60'=>[51,60],'61+'=> [61,130]];
$age_values = []; $age_labels = array_keys($age_groups);
foreach ($age_groups as $label => [$lo, $hi]) {
    $c = (int)$conn->query("SELECT COUNT(*) as c FROM patients WHERE is_active = 1 AND date_of_birth IS NOT NULL AND TIMESTAMPDIFF(YEAR,date_of_birth,CURDATE()) BETWEEN $lo AND $hi")->fetch_assoc()['c'];
    $age_values[] = $c;
}
$patients_with_dob = (int)$conn->query("SELECT COUNT(*) as c FROM patients WHERE is_active = 1 AND date_of_birth IS NOT NULL")->fetch_assoc()['c'];
$patients_no_dob   = $total_active - $patients_with_dob;

// ── Civil status ──────────────────────────────────────────────────────────────
$civil_rows = $conn->query("
    SELECT COALESCE(civil_status,'unknown') as s, COUNT(*) as c
    FROM patients WHERE is_active = 1
    GROUP BY s ORDER BY c DESC
")->fetch_all(MYSQLI_ASSOC);
$civil_labels = []; $civil_values = [];
foreach ($civil_rows as $r) { $civil_labels[] = ucfirst($r['s']); $civil_values[] = (int)$r['c']; }

// ── Blood type ────────────────────────────────────────────────────────────────
$blood_rows = $conn->query("
    SELECT blood_type, COUNT(*) as c
    FROM patients WHERE is_active = 1 AND blood_type IS NOT NULL AND blood_type != ''
    GROUP BY blood_type ORDER BY c DESC
")->fetch_all(MYSQLI_ASSOC);
$blood_labels = []; $blood_values = [];
foreach ($blood_rows as $r) { $blood_labels[] = $r['blood_type']; $blood_values[] = (int)$r['c']; }

// ── Top occupations ───────────────────────────────────────────────────────────
$occ_rows = $conn->query("
    SELECT TRIM(occupation) as occ, COUNT(*) as c
    FROM patients WHERE is_active = 1 AND occupation != '' AND occupation IS NOT NULL
    GROUP BY occ ORDER BY c DESC LIMIT 8
")->fetch_all(MYSQLI_ASSOC);

// ── Registration trend (last 12 months) ───────────────────────────────────────
$reg_labels = []; $reg_values = [];
for ($i = 11; $i >= 0; $i--) {
    $ts    = strtotime("-$i months");
    $ym    = date('Y-m', $ts);
    $c     = (int)$conn->query("SELECT COUNT(*) as c FROM patients WHERE DATE_FORMAT(created_at,'%Y-%m') = '$ym'")->fetch_assoc()['c'];
    $reg_labels[] = date('M Y', $ts);
    $reg_values[] = $c;
}

// ── Gender by age group (stacked bar) ────────────────────────────────────────
$male_by_age = []; $female_by_age = []; $other_by_age = [];
foreach ($age_groups as $label => [$lo, $hi]) {
    foreach (['male','female','other'] as $g) {
        $c = (int)$conn->query("SELECT COUNT(*) as c FROM patients WHERE is_active = 1 AND date_of_birth IS NOT NULL AND gender = '$g' AND TIMESTAMPDIFF(YEAR,date_of_birth,CURDATE()) BETWEEN $lo AND $hi")->fetch_assoc()['c'];
        if ($g === 'male')   $male_by_age[]   = $c;
        if ($g === 'female') $female_by_age[] = $c;
        if ($g === 'other')  $other_by_age[]  = $c;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include '../../includes/head.php'; ?>
<style>
/* ── Reuse the analytics design tokens from dashboard ─── */
.an-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 22px;
}
@media (max-width: 1100px) { .an-kpi-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 600px)  { .an-kpi-grid { grid-template-columns: 1fr 1fr; } }

.an-kpi-card {
    background: #fff;
    border: 1px solid var(--gray-100);
    border-radius: 16px;
    padding: 18px 20px;
    display: flex;
    align-items: flex-start;
    gap: 14px;
    box-shadow: 0 1px 4px rgba(0,0,0,.05);
    transition: box-shadow 0.2s;
}
.an-kpi-card:hover { box-shadow: 0 4px 16px rgba(37,99,235,.09); }
[data-theme="dark"] .an-kpi-card { background: var(--gray-800); border-color: var(--gray-700); }

.an-kpi-icon {
    width: 42px; height: 42px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem; flex-shrink: 0;
}
.an-kpi-icon.blue   { background: rgba(37,99,235,0.1);  color: #2563eb; }
.an-kpi-icon.green  { background: rgba(22,163,74,0.1);  color: #16a34a; }
.an-kpi-icon.teal   { background: rgba(20,184,166,0.1); color: #0d9488; }
.an-kpi-icon.indigo { background: rgba(99,102,241,0.1); color: #6366f1; }
.an-kpi-icon.amber  { background: rgba(245,158,11,0.1); color: #d97706; }

.an-kpi-label { font-size: 0.72rem; color: var(--gray-500); font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px; }
.an-kpi-value { font-size: 1.9rem; font-weight: 800; line-height: 1.1; color: var(--gray-900); margin-bottom: 5px; }
[data-theme="dark"] .an-kpi-value { color: #e2e8f0; }
.an-kpi-value.sm { font-size: 1.5rem; }

.an-chart-row { display: grid; gap: 20px; margin-bottom: 20px; }
.an-chart-row.cols-2 { grid-template-columns: 1fr 1fr; }
.an-chart-row.cols-5-7 { grid-template-columns: 5fr 7fr; }
.an-chart-row.cols-3   { grid-template-columns: 1fr 1fr 1fr; }
@media (max-width: 900px) {
    .an-chart-row.cols-2,
    .an-chart-row.cols-5-7,
    .an-chart-row.cols-3 { grid-template-columns: 1fr; }
}

.an-card {
    background: #fff;
    border: 1px solid var(--gray-100);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 1px 4px rgba(0,0,0,0.05);
}
[data-theme="dark"] .an-card { background: var(--gray-800); border-color: var(--gray-700); }

.an-card-head {
    padding: 16px 22px;
    border-bottom: 1px solid var(--gray-100);
    display: flex; align-items: center; justify-content: space-between;
}
[data-theme="dark"] .an-card-head { border-bottom-color: var(--gray-700); }
.an-card-head-title { font-size: 0.82rem; font-weight: 700; color: var(--gray-700); display: flex; align-items: center; gap: 6px; }
[data-theme="dark"] .an-card-head-title { color: #b0bec5; }
.an-card-head-sub   { font-size: 0.73rem; color: var(--gray-400); }
.an-card-body { padding: 22px; }

/* Occupation list */
.occ-item {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 0;
    border-bottom: 1px solid var(--gray-100);
    font-size: 0.83rem;
}
[data-theme="dark"] .occ-item { border-bottom-color: var(--gray-700); }
.occ-item:last-child { border-bottom: none; }
.occ-bar-wrap { flex: 1; height: 6px; background: var(--gray-100); border-radius: 4px; overflow: hidden; }
.occ-bar { height: 100%; border-radius: 4px; background: linear-gradient(90deg, #2563eb, #3b82f6); transition: width 0.6s ease; }

/* Stat badge pills */
.stat-pill {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 10px; border-radius: 20px;
    font-size: 0.73rem; font-weight: 600;
}
</style>
</head>
<body>
<?php include '../../includes/sidebar.php'; ?>
<div class="main-content">
    <?php include '../../includes/header.php'; ?>
    <div class="page-content">

        <!-- Page Header -->
        <div class="page-header" style="margin-bottom:22px;">
            <div>
                <h5>Patient Demographics</h5>
                <p style="color:var(--gray-500);font-size:0.85rem;margin:0;">
                    <i class="bi bi-people-fill" style="margin-right:5px;"></i>
                    Breakdowns across all <?php echo number_format($total_active); ?> active patients
                </p>
            </div>
            <a href="dashboard.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-bar-chart-fill"></i> Analytics Dashboard
            </a>
        </div>

        <!-- ── ROW 1: KPI Cards ─────────────────────────────────── -->
        <div class="an-kpi-grid">

            <div class="an-kpi-card">
                <div class="an-kpi-icon blue"><i class="bi bi-people-fill"></i></div>
                <div style="flex:1;min-width:0;">
                    <div class="an-kpi-label">Active Patients</div>
                    <div class="an-kpi-value"><?php echo number_format($total_active); ?></div>
                    <?php if ($total_archived > 0): ?>
                    <span class="stat-pill" style="background:var(--gray-100);color:var(--gray-500);">
                        +<?php echo $total_archived; ?> archived
                    </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="an-kpi-card">
                <div class="an-kpi-icon teal"><i class="bi bi-calendar-plus-fill"></i></div>
                <div style="flex:1;min-width:0;">
                    <div class="an-kpi-label">New This Month</div>
                    <div class="an-kpi-value"><?php echo $new_this_month; ?></div>
                    <span class="stat-pill" style="background:var(--blue-50);color:var(--blue-500);">
                        <?php echo date('F Y'); ?>
                    </span>
                </div>
            </div>

            <div class="an-kpi-card">
                <div class="an-kpi-icon amber"><i class="bi bi-graph-up-arrow"></i></div>
                <div style="flex:1;min-width:0;">
                    <div class="an-kpi-label">Average Age</div>
                    <div class="an-kpi-value"><?php echo $avg_age ?? '—'; ?></div>
                    <span class="stat-pill" style="background:var(--gray-100);color:var(--gray-500);">
                        <?php echo $patients_with_dob; ?> with DOB on file
                    </span>
                </div>
            </div>

            <div class="an-kpi-card">
                <div class="an-kpi-icon green"><i class="bi bi-gender-ambiguous"></i></div>
                <div style="flex:1;min-width:0;">
                    <div class="an-kpi-label">Gender Split</div>
                    <?php
                    $f = $gender_map['female'] ?? 0;
                    $m = $gender_map['male']   ?? 0;
                    $pct_f = $total_active > 0 ? round(($f/$total_active)*100) : 0;
                    $pct_m = $total_active > 0 ? round(($m/$total_active)*100) : 0;
                    ?>
                    <div class="an-kpi-value sm"><?php echo $pct_f; ?>% F</div>
                    <span class="stat-pill" style="background:var(--gray-100);color:var(--gray-500);">
                        <?php echo $pct_m; ?>% Male · <?php echo (100-$pct_f-$pct_m); ?>% Other/Unknown
                    </span>
                </div>
            </div>

        </div><!-- /kpi grid -->

        <!-- ── ROW 2: Age Distribution + Gender Donut ──────────── -->
        <div class="an-chart-row cols-5-7">

            <!-- Gender + Civil Status donuts side by side -->
            <div class="an-card">
                <div class="an-card-head">
                    <div class="an-card-head-title">
                        <i class="bi bi-pie-chart-fill" style="color:#2563eb;"></i>
                        Gender & Civil Status
                    </div>
                    <span class="an-card-head-sub">All active patients</span>
                </div>
                <div class="an-card-body" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start;">
                    <!-- Gender donut -->
                    <div style="display:flex;flex-direction:column;align-items:center;">
                        <div style="font-size:0.73rem;font-weight:700;color:var(--gray-500);text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px;">Gender</div>
                        <div style="position:relative;width:130px;height:130px;">
                            <canvas id="genderChart"></canvas>
                        </div>
                        <div id="genderLegend" style="margin-top:12px;font-size:0.73rem;width:100%;"></div>
                    </div>
                    <!-- Civil status donut -->
                    <div style="display:flex;flex-direction:column;align-items:center;">
                        <div style="font-size:0.73rem;font-weight:700;color:var(--gray-500);text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px;">Civil Status</div>
                        <div style="position:relative;width:130px;height:130px;">
                            <canvas id="civilChart"></canvas>
                        </div>
                        <div id="civilLegend" style="margin-top:12px;font-size:0.73rem;width:100%;"></div>
                    </div>
                </div>
            </div>

            <!-- Age distribution bar chart -->
            <div class="an-card">
                <div class="an-card-head">
                    <div class="an-card-head-title">
                        <i class="bi bi-bar-chart-steps" style="color:#6366f1;"></i>
                        Age Distribution
                    </div>
                    <?php if ($patients_no_dob > 0): ?>
                    <span class="an-card-head-sub"><?php echo $patients_no_dob; ?> patient<?php echo $patients_no_dob !== 1 ? 's' : ''; ?> without DOB excluded</span>
                    <?php else: ?>
                    <span class="an-card-head-sub">By age group</span>
                    <?php endif; ?>
                </div>
                <div class="an-card-body">
                    <div style="position:relative;height:220px;">
                        <canvas id="ageChart"></canvas>
                    </div>
                </div>
            </div>

        </div><!-- /row 2 -->

        <!-- ── ROW 3: Gender × Age Stacked + Blood Type ─────────── -->
        <div class="an-chart-row cols-5-7">

            <!-- Blood type -->
            <div class="an-card">
                <div class="an-card-head">
                    <div class="an-card-head-title">
                        <i class="bi bi-droplet-fill" style="color:#dc2626;"></i>
                        Blood Type Distribution
                    </div>
                    <span class="an-card-head-sub">Patients with blood type on file</span>
                </div>
                <div class="an-card-body" style="display:flex;flex-direction:column;align-items:center;">
                    <?php if (empty($blood_labels)): ?>
                    <div style="text-align:center;padding:40px;color:var(--gray-400);font-size:0.85rem;">
                        <i class="bi bi-droplet" style="font-size:2rem;display:block;margin-bottom:8px;opacity:.4;"></i>
                        No blood type data recorded yet
                    </div>
                    <?php else: ?>
                    <div style="position:relative;width:170px;height:170px;">
                        <canvas id="bloodChart"></canvas>
                    </div>
                    <div id="bloodLegend" style="margin-top:14px;width:100%;font-size:0.73rem;"></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Gender × Age stacked bar -->
            <div class="an-card">
                <div class="an-card-head">
                    <div class="an-card-head-title">
                        <i class="bi bi-bar-chart-fill" style="color:#0d9488;"></i>
                        Gender by Age Group
                    </div>
                    <span class="an-card-head-sub">Patients with DOB on file</span>
                </div>
                <div class="an-card-body">
                    <div style="position:relative;height:220px;">
                        <canvas id="genderAgeChart"></canvas>
                    </div>
                    <div style="display:flex;gap:14px;margin-top:10px;font-size:0.73rem;color:var(--gray-500);justify-content:center;flex-wrap:wrap;">
                        <span><span style="display:inline-block;width:10px;height:10px;background:#2563eb;border-radius:2px;margin-right:4px;vertical-align:middle;"></span>Male</span>
                        <span><span style="display:inline-block;width:10px;height:10px;background:#e879f9;border-radius:2px;margin-right:4px;vertical-align:middle;"></span>Female</span>
                        <span><span style="display:inline-block;width:10px;height:10px;background:#94a3b8;border-radius:2px;margin-right:4px;vertical-align:middle;"></span>Other</span>
                    </div>
                </div>
            </div>

        </div><!-- /row 3 -->

        <!-- ── ROW 4: Registration Trend + Top Occupations ────── -->
        <div class="an-chart-row cols-2">

            <!-- Registration trend (12 months) -->
            <div class="an-card">
                <div class="an-card-head">
                    <div class="an-card-head-title">
                        <i class="bi bi-graph-up" style="color:#16a34a;"></i>
                        Patient Registrations
                    </div>
                    <span class="an-card-head-sub">Last 12 months</span>
                </div>
                <div class="an-card-body">
                    <div style="position:relative;height:210px;">
                        <canvas id="regTrendChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Top occupations -->
            <div class="an-card">
                <div class="an-card-head">
                    <div class="an-card-head-title">
                        <i class="bi bi-briefcase-fill" style="color:#d97706;"></i>
                        Top Occupations
                    </div>
                    <span class="an-card-head-sub">Most common among patients</span>
                </div>
                <div class="an-card-body" style="padding:8px 22px 16px;">
                    <?php if (empty($occ_rows)): ?>
                    <div style="text-align:center;padding:40px;color:var(--gray-400);font-size:0.85rem;">No occupation data recorded</div>
                    <?php else: ?>
                    <?php $max_occ = max(array_column($occ_rows,'c')) ?: 1; ?>
                    <?php $occ_colors = ['#2563eb','#16a34a','#d97706','#e879f9','#0d9488','#6366f1','#f43f5e','#94a3b8']; ?>
                    <?php foreach ($occ_rows as $i => $o): ?>
                    <div class="occ-item">
                        <span style="width:22px;height:22px;border-radius:6px;background:<?php echo $occ_colors[$i % count($occ_colors)]; ?>1a;color:<?php echo $occ_colors[$i % count($occ_colors)]; ?>;display:flex;align-items:center;justify-content:center;font-size:0.68rem;font-weight:800;flex-shrink:0;"><?php echo $i+1; ?></span>
                        <span style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--gray-700);"><?php echo htmlspecialchars(ucwords(strtolower($o['occ']))); ?></span>
                        <div class="occ-bar-wrap" style="width:90px;">
                            <div class="occ-bar" style="width:<?php echo round(($o['c']/$max_occ)*100); ?>%;background:<?php echo $occ_colors[$i % count($occ_colors)]; ?>;"></div>
                        </div>
                        <span style="font-size:0.78rem;font-weight:700;color:var(--gray-600);width:28px;text-align:right;flex-shrink:0;"><?php echo $o['c']; ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div><!-- /row 4 -->

    </div>
</div>

<?php include '../../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
var isDark      = document.documentElement.getAttribute('data-bs-theme') === 'dark'
               || document.documentElement.getAttribute('data-theme') === 'dark';
var gridColor   = isDark ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.05)';
var tickColor   = isDark ? '#8a9bb0' : '#64748b';
var legendColor = isDark ? '#b0bec5' : '#475569';

// ── Shared chart defaults ──────────────────────────────────────
Chart.defaults.font.family = "'Segoe UI', system-ui, sans-serif";
Chart.defaults.font.size   = 11;

// ── Donut builder ──────────────────────────────────────────────
function buildDonut(id, labels, values, colors, legendId) {
    var ctx = document.getElementById(id);
    if (!ctx) return;
    var chart = new Chart(ctx, {
        type: 'doughnut',
        data: { labels: labels, datasets: [{ data: values, backgroundColor: colors, borderWidth: 2, borderColor: isDark ? '#1e293b' : '#fff', hoverBorderColor: isDark ? '#1e293b' : '#fff' }] },
        options: {
            cutout: '62%',
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: function(ctx) {
                    var total = ctx.dataset.data.reduce(function(a,b){return a+b;},0);
                    var pct = total > 0 ? Math.round((ctx.parsed/total)*100) : 0;
                    return ' ' + ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)';
                }}}
            }
        }
    });

    // Build legend
    if (legendId) {
        var total = values.reduce(function(a,b){return a+b;},0);
        var leg = document.getElementById(legendId);
        if (leg) {
            leg.innerHTML = labels.map(function(l,i) {
                var pct = total > 0 ? Math.round((values[i]/total)*100) : 0;
                return '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">'
                    + '<span style="display:flex;align-items:center;gap:5px;color:'+legendColor+';min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">'
                    + '<span style="width:8px;height:8px;border-radius:2px;background:'+colors[i]+';flex-shrink:0;display:inline-block;"></span>'
                    + l + '</span>'
                    + '<span style="font-weight:700;color:'+legendColor+';white-space:nowrap;margin-left:6px;">' + pct + '%</span>'
                    + '</div>';
            }).join('');
        }
    }
    return chart;
}

// ── Gender donut ───────────────────────────────────────────────
buildDonut('genderChart',
    <?php echo json_encode($gender_labels); ?>,
    <?php echo json_encode($gender_values); ?>,
    ['#2563eb','#e879f9','#94a3b8','#cbd5e1'],
    'genderLegend'
);

// ── Civil status donut ─────────────────────────────────────────
buildDonut('civilChart',
    <?php echo json_encode($civil_labels); ?>,
    <?php echo json_encode($civil_values); ?>,
    ['#0d9488','#d97706','#6366f1','#f43f5e','#94a3b8'],
    'civilLegend'
);

// ── Blood type donut ───────────────────────────────────────────
<?php if (!empty($blood_labels)): ?>
buildDonut('bloodChart',
    <?php echo json_encode($blood_labels); ?>,
    <?php echo json_encode($blood_values); ?>,
    ['#dc2626','#b91c1c','#ef4444','#f87171','#fca5a5','#fecaca','#fee2e2','#fef2f2'],
    'bloodLegend'
);
<?php endif; ?>

// ── Age distribution bar ───────────────────────────────────────
new Chart(document.getElementById('ageChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($age_labels); ?>,
        datasets: [{
            label: 'Patients',
            data: <?php echo json_encode($age_values); ?>,
            backgroundColor: 'rgba(99,102,241,0.8)',
            borderRadius: 8,
            borderSkipped: false,
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { color: gridColor }, ticks: { color: tickColor } },
            y: { grid: { color: gridColor }, ticks: { color: tickColor, stepSize: 1 }, beginAtZero: true }
        }
    }
});

// ── Gender × Age stacked bar ───────────────────────────────────
new Chart(document.getElementById('genderAgeChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($age_labels); ?>,
        datasets: [
            { label: 'Male',   data: <?php echo json_encode($male_by_age); ?>,   backgroundColor: 'rgba(37,99,235,0.8)',  borderRadius: 4 },
            { label: 'Female', data: <?php echo json_encode($female_by_age); ?>, backgroundColor: 'rgba(232,121,249,0.8)', borderRadius: 4 },
            { label: 'Other',  data: <?php echo json_encode($other_by_age); ?>,  backgroundColor: 'rgba(148,163,184,0.8)', borderRadius: 4 }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { stacked: true, grid: { color: gridColor }, ticks: { color: tickColor } },
            y: { stacked: true, grid: { color: gridColor }, ticks: { color: tickColor, stepSize: 1 }, beginAtZero: true }
        }
    }
});

// ── Registration trend line ────────────────────────────────────
new Chart(document.getElementById('regTrendChart'), {
    type: 'line',
    data: {
        labels: <?php echo json_encode($reg_labels); ?>,
        datasets: [{
            label: 'New Patients',
            data: <?php echo json_encode($reg_values); ?>,
            borderColor: '#16a34a',
            backgroundColor: 'rgba(22,163,74,0.08)',
            borderWidth: 2.5,
            pointBackgroundColor: '#16a34a',
            pointRadius: 4,
            pointHoverRadius: 6,
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { color: gridColor }, ticks: { color: tickColor, maxRotation: 45 } },
            y: { grid: { color: gridColor }, ticks: { color: tickColor, stepSize: 1 }, beginAtZero: true }
        }
    }
});
</script>
</body>
</html>
