<?php
$page_title = 'विद्यार्थी यादी व माहिती पूर्णता';
require_once __DIR__ . '/../config/database.php';
requireLogin();

$db = getDB();
$school_id = (int)$_SESSION['school_id'];

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$search = trim($_GET['search'] ?? '');
$std_filter = (int)($_GET['std'] ?? 0);
$tScope = function_exists('get_current_teacher_scope') ? get_current_teacher_scope() : null;
if ($tScope && !empty($tScope['stds'])) {
    $std_filter = (int)$tScope['std'];
}
$status_filter = trim($_GET['status'] ?? ''); // 'complete', 'imp_only', 'pending'
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

$where = "WHERE s.school_id = ? AND (s.is_active = 1 OR s.is_active IS NULL)";
$params = [$school_id];

if ($search !== '') {
    $where .= " AND (s.name LIKE ? OR s.name_mr LIKE ? OR s.roll_no LIKE ? OR s.gr_no LIKE ? OR s.student_pen LIKE ? OR s.apaar_id LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($std_filter > 0) {
    $mr_std = function_exists('to_mr_digits') ? to_mr_digits($std_filter) : $std_filter;
    $where .= " AND (s.std = ? OR s.grade = ? OR s.grade = ? OR s.grade LIKE ? OR s.grade LIKE ?)";
    $params[] = $std_filter;
    $params[] = 'इयत्ता ' . $std_filter;
    $params[] = 'इयत्ता ' . $mr_std;
    $params[] = '%' . $std_filter . '%';
    $params[] = '%' . $mr_std . '%';
}

// Helper to compute data completeness progress for a student
if (!function_exists('computeStudentDataProgress')) {
function computeStudentDataProgress($s) {
    // 1. Important fields (Count 10)
    $imp_fields = [
        !empty($s['name_mr']) || !empty($s['name']),
        !empty($s['gender']),
        !empty($s['grade']) || !empty($s['std']),
        !empty($s['section']),
        !empty($s['roll_no']),
        !empty($s['gr_no']),
        !empty($s['date_of_birth']) || !empty($s['dob']),
        !empty($s['father_name']) || !empty($s['mother_name']),
        !empty($s['phone']) || !empty($s['father_mobile']) || !empty($s['mother_mobile']),
        !empty($s['aadhar_no']) || !empty($s['apaar_id']) || !empty($s['student_pen']) || !empty($s['saral_id'])
    ];
    $imp_filled = count(array_filter($imp_fields));
    $imp_total = count($imp_fields);
    $imp_pct = round(($imp_filled / $imp_total) * 100);

    // 2. Extra fields (Count 10)
    $extra_fields = [
        (!empty($s['height_sem1']) && (float)$s['height_sem1'] > 0) || (!empty($s['weight_sem1']) && (float)$s['weight_sem1'] > 0),
        (!empty($s['height_sem2']) && (float)$s['height_sem2'] > 0) || (!empty($s['weight_sem2']) && (float)$s['weight_sem2'] > 0),
        !empty($s['religion']) || !empty($s['caste']),
        !empty($s['caste_category']),
        !empty($s['birth_place']) || !empty($s['address_line1']) || !empty($s['address']),
        !empty($s['bank_name']) || !empty($s['bank_account']),
        !empty($s['favourite_color']) || !empty($s['favourite_food']),
        !empty($s['favourite_sport']) || !empty($s['favourite_subject']),
        !empty($s['aspiration']),
        !empty($s['best_friend1']) || !empty($s['photo'])
    ];
    $extra_filled = count(array_filter($extra_fields));
    $extra_total = count($extra_fields);
    $extra_pct = round(($extra_filled / $extra_total) * 100);

    // Overall total: Weighted 60% Important, 40% Extra
    $overall_pct = (int)round(($imp_pct * 0.6) + ($extra_pct * 0.4));
    
    return [
        'overall' => $overall_pct,
        'imp_pct' => $imp_pct,
        'imp_filled' => $imp_filled,
        'imp_total' => $imp_total,
        'extra_pct' => $extra_pct,
        'extra_filled' => $extra_filled,
        'extra_total' => $extra_total,
        'is_complete' => ($overall_pct >= 85),
        'is_imp_only' => ($imp_pct >= 70 && $extra_pct < 50)
    ];
}
}

// Fetch all students for stats
$stat_stmt = $db->prepare("SELECT * FROM students s WHERE s.school_id = ? AND s.is_active = 1");
$stat_stmt->execute([$school_id]);
$all_school_students = $stat_stmt->fetchAll(PDO::FETCH_ASSOC);

$total_school_students = count($all_school_students);
$count_complete = 0;
$count_imp_only = 0;
$sum_pct = 0;

foreach ($all_school_students as $st_row) {
    $p = computeStudentDataProgress($st_row);
    $sum_pct += $p['overall'];
    if ($p['is_complete']) $count_complete++;
    if ($p['is_imp_only']) $count_imp_only++;
}
$avg_progress = $total_school_students > 0 ? round($sum_pct / $total_school_students) : 0;

// Filtered count
$stmt = $db->prepare("SELECT COUNT(*) FROM students s $where");
$stmt->execute($params);
$total = $stmt->fetchColumn();
$total_pages = ceil($total / $per_page);

// Fetch paginated students
$stmt = $db->prepare("
    SELECT s.*, t.name_mr as teacher_name_mr, t.name as teacher_name 
    FROM students s 
    LEFT JOIN teachers t ON s.teacher_id = t.id 
    $where 
    ORDER BY CAST(s.std AS UNSIGNED) ASC, CAST(s.roll_no AS UNSIGNED) ASC, s.name_mr ASC 
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$students = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.stat-card-progress {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 14px 18px;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.stat-card-progress:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 14px rgba(0,0,0,0.06);
}
.student-avatar {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    object-fit: cover;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    color: #64748b;
    flex-shrink: 0;
}
.progress-pie-svg {
    transform: rotate(-90deg);
}
</style>

<div class="mb-4">
    <!-- Header Row -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h2 class="mb-0 fw-bold d-flex align-items-center gap-2" style="font-size:1.45rem;">
                <i class="bi bi-people-fill text-primary"></i> विद्यार्थी यादी व माहिती पूर्णता ट्रॅकर
            </h2>
            <p class="text-muted small mb-0 mt-0.5">
                प्रत्येक विद्यार्थ्याची भरलेली माहिती (महत्त्वाची व अतिरिक्त) प्रोग्रेस बार व टक्केवारीनुसार पहा.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="<?= APP_URL ?>/modules/classes/index.php?tab=students" class="btn btn-sm btn-outline-primary fw-semibold">
                <i class="bi bi-mortarboard me-1"></i> वर्ग व्यवस्थापन हब
            </a>
            <a href="<?= APP_URL ?>/modules/classes/import_udise.php" class="btn btn-sm btn-warning fw-bold text-dark shadow-xs">
                <i class="bi bi-file-earmark-excel-fill text-success me-1"></i> UDISE+ आयात
            </a>
            <a href="<?= APP_URL ?>/students/csv_upload.php" class="btn btn-sm btn-outline-success fw-semibold">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i> CSV आयात
            </a>
            <a href="<?= APP_URL ?>/students/add.php<?= $std_filter ? '?std=' . $std_filter : '' ?>" class="btn btn-sm btn-primary fw-bold shadow-xs">
                <i class="bi bi-person-plus-fill me-1"></i> + नवीन विद्यार्थी जोडा
            </a>
        </div>
    </div>

    <!-- Live Data Completion KPI Cards -->
    <div class="row g-2 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card-progress shadow-xs" style="border-left: 4px solid #4338ca !important;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">एकूण विद्यार्थी</div>
                        <div class="fs-4 fw-bold text-dark mt-0.5"><?= $total_school_students ?></div>
                    </div>
                    <div class="text-primary fs-2 opacity-50"><i class="bi bi-people"></i></div>
                </div>
                <div class="small text-muted mt-1" style="font-size:0.75rem;">शाळेतील नोंदणीकृत</div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="stat-card-progress shadow-xs" style="border-left: 4px solid #10b981 !important;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">संपूर्ण माहिती पूर्ण (100%)</div>
                        <div class="fs-4 fw-bold text-success mt-0.5"><?= $count_complete ?></div>
                    </div>
                    <div class="text-success fs-2 opacity-50"><i class="bi bi-check-circle-fill"></i></div>
                </div>
                <div class="small text-success mt-1" style="font-size:0.75rem;">सर्व अतिरिक्त माहितीसह भरलेले</div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="stat-card-progress shadow-xs" style="border-left: 4px solid #f59e0b !important;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">केवळ महत्त्वाची माहिती</div>
                        <div class="fs-4 fw-bold text-warning mt-0.5"><?= $count_imp_only ?></div>
                    </div>
                    <div class="text-warning fs-2 opacity-50"><i class="bi bi-clock-history"></i></div>
                </div>
                <div class="small text-muted mt-1" style="font-size:0.75rem;">अतिरिक्त माहिती अद्याप रिक्त</div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="stat-card-progress shadow-xs" style="border-left: 4px solid #0284c7 !important;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">सरासरी पूर्णता दर</div>
                        <div class="fs-4 fw-bold text-info mt-0.5"><?= $avg_progress ?>%</div>
                    </div>
                    <div class="text-info fs-2 opacity-50"><i class="bi bi-pie-chart-fill"></i></div>
                </div>
                <div class="progress mt-1.5" style="height: 5px;">
                    <div class="progress-bar bg-info" style="width: <?= $avg_progress ?>%;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Freemium Notification Alert -->
    <?php 
    $school_plan = getSchool();
    $max_stu = (int)($school_plan['max_students'] ?? 5);
    if ($total >= $max_stu && ($school_plan['plan_id'] == 1 || ($school_plan['price'] ?? 0) == 0)): 
    ?>
    <div class="alert alert-warning border-warning d-flex flex-wrap align-items-center justify-content-between p-3 mb-4 shadow-xs" style="border-radius: 12px;">
        <div class="d-flex align-items-center gap-3">
            <i class="bi bi-star-fill fs-2 text-warning"></i>
            <div>
                <strong class="d-block text-dark">स्मार्ट शाळा मोफत ट्रायल: ५/५ विद्यार्थी नोंदणी पूर्ण!</strong>
                <span class="text-muted small">तुम्ही मोफत चाचणीचे सर्व ५ विद्यार्थी जोडले आहेत. संपूर्ण शाळेचे काम डिजिटल करण्यासाठी व अमर्यादित विद्यार्थी जोडण्यासाठी प्रीमियम योजना निवडा.</span>
            </div>
        </div>
        <a href="<?= APP_URL ?>/subscription/plans.php" class="btn btn-warning text-dark fw-bold mt-2 mt-sm-0 shadow-xs">
            <i class="bi bi-gem"></i> योजना अपग्रेड करा
        </a>
    </div>
    <?php endif; ?>

    <!-- Standard (Class) Sub-pills Filter Bar -->
    <div class="card border-0 shadow-sm mb-3" style="border-radius:12px;">
        <div class="card-body p-2 bg-light rounded-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="d-flex flex-wrap align-items-center gap-1">
                <span class="small fw-bold text-muted me-2"><i class="bi bi-funnel"></i> इयत्ता निवडा:</span>
                <?php if (!$tScope): ?>
                <a href="?search=<?= urlencode($search) ?>" class="btn btn-sm <?= $std_filter === 0 ? 'btn-primary' : 'btn-outline-secondary bg-white' ?> py-1 px-2.5 fw-semibold" style="border-radius:8px;">
                    सर्व वर्ग (All)
                </a>
                <?php endif; ?>
                <?php 
                $st_stds = ($tScope && !empty($tScope['stds'])) ? $tScope['stds'] : range(1, 8);
                foreach ($st_stds as $s_i): ?>
                    <a href="?std=<?= $s_i ?>&search=<?= urlencode($search) ?>" 
                       class="btn btn-sm <?= $std_filter === $s_i ? 'btn-primary' : 'btn-outline-secondary bg-white' ?> py-1 px-2.5 fw-semibold" 
                       style="border-radius:8px;">
                        इ. <?= $s_i ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Search Form -->
            <form method="GET" class="d-flex align-items-center gap-1.5 m-0">
                <?php if ($std_filter > 0): ?><input type="hidden" name="std" value="<?= $std_filter ?>"><?php endif; ?>
                <div class="input-group input-group-sm" style="width: 260px;">
                    <input type="text" class="form-control" name="search" placeholder="नाव, GR किंवा रोल नं..." value="<?= sanitize($search) ?>">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
                    <?php if ($search): ?>
                        <a href="?std=<?= $std_filter ?>" class="btn btn-outline-secondary" title="रीसेट"><i class="bi bi-x"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Students Table with Data Progress Tracker -->
    <div class="card border-0 shadow-sm overflow-hidden" style="border-radius:14px;">
        <div class="card-body p-0">
            <?php if (empty($students)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox fs-1"></i>
                    <p class="mt-2 fw-semibold">कोणताही विद्यार्थी सापडला नाही.</p>
                    <a href="<?= APP_URL ?>/students/add.php<?= $std_filter ? '?std=' . $std_filter : '' ?>" class="btn btn-primary btn-sm fw-bold">
                        <i class="bi bi-plus-circle me-1"></i> नवीन विद्यार्थी जोडा
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-secondary" style="font-size:0.85rem;">
                            <tr>
                                <th style="width: 45px;">#</th>
                                <th style="width: 50px;">फोटो</th>
                                <th>विद्यार्थ्याचे नाव</th>
                                <th style="width: 80px;">रोल नं.</th>
                                <th style="width: 90px;">इयत्ता</th>
                                <th style="min-width: 220px;">माहिती पूर्णता (Data Progress)</th>
                                <th style="width: 80px;">HPC</th>
                                <th style="width: 130px;" class="text-end">क्रिया</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $i => $s): 
                                $prog = computeStudentDataProgress($s);
                                
                                // Color Scheme based on completion rate
                                if ($prog['overall'] >= 85) {
                                    $prog_color = '#10b981'; // Green
                                    $bg_class = 'bg-success';
                                    $badge_class = 'bg-success-subtle text-success border border-success-subtle';
                                    $status_text = 'पूर्ण';
                                } elseif ($prog['overall'] >= 60) {
                                    $prog_color = '#0284c7'; // Blue
                                    $bg_class = 'bg-info';
                                    $badge_class = 'bg-info-subtle text-info-emphasis border border-info-subtle';
                                    $status_text = 'चांगली नोंद';
                                } elseif ($prog['overall'] >= 40) {
                                    $prog_color = '#f59e0b'; // Amber
                                    $bg_class = 'bg-warning';
                                    $badge_class = 'bg-warning-subtle text-dark border border-warning-subtle';
                                    $status_text = 'मध्यम';
                                } else {
                                    $prog_color = '#ef4444'; // Red
                                    $bg_class = 'bg-danger';
                                    $badge_class = 'bg-danger-subtle text-danger border border-danger-subtle';
                                    $status_text = 'अपूर्ण';
                                }
                            ?>
                            <tr>
                                <td class="text-muted small"><?= $offset + $i + 1 ?></td>
                                <td>
                                    <?php if ($s['photo']): ?>
                                        <img src="<?= APP_URL . '/' . $s['photo'] ?>" alt="" class="student-avatar">
                                    <?php else: ?>
                                        <div class="student-avatar">
                                            <i class="bi bi-person"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div>
                                        <a href="<?= APP_URL ?>/students/edit.php?id=<?= $s['id'] ?>" class="text-decoration-none text-dark fw-bold" title="विद्यार्थी माहिती संपादित करा">
                                            <?= sanitize($s['name_mr'] ?: $s['name']) ?>
                                        </a>
                                        <?php if (!empty($s['name_mr']) && $s['name_mr'] !== $s['name']): ?>
                                            <div class="text-muted" style="font-size:0.75rem; font-weight:500;"><?= sanitize($s['name']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-muted" style="font-size:0.75rem;">
                                        GR: <strong><?= sanitize($s['gr_no'] ?: '-') ?></strong> · तुकडी: <strong><?= sanitize($s['section'] ?: 'अ') ?></strong>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= sanitize($s['roll_no'] ?: '-') ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-purple-subtle text-purple border" style="background:#F5F3FF; color:#7C3AED; border-color:#DDD6FE;">
                                        <?= sanitize($s['grade'] ?: ('इ.' . $s['std'])) ?>
                                    </span>
                                </td>
                                
                                <!-- Data Completeness Progress Bar + Mini Pie Ring -->
                                <td>
                                    <div class="d-flex align-items-center gap-2.5">
                                        <!-- Mini Circular Pie/Ring Chart -->
                                        <div style="flex-shrink:0;">
                                            <svg width="34" height="34" viewBox="0 0 36 36" class="progress-pie-svg">
                                                <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#f1f5f9" stroke-width="4" />
                                                <path stroke-dasharray="<?= $prog['overall'] ?>, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="<?= $prog_color ?>" stroke-width="4" stroke-linecap="round" />
                                            </svg>
                                        </div>

                                        <!-- Progress Bar & Text Breakdown -->
                                        <div class="flex-grow-1" style="min-width:0;">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="badge <?= $badge_class ?>" style="font-size:0.68rem; padding: 2px 5px;"><?= $status_text ?></span>
                                                <span class="small fw-bold text-dark" style="font-size:0.8rem;"><?= $prog['overall'] ?>%</span>
                                            </div>
                                            <div class="progress" style="height: 6px; border-radius: 4px; background: #e2e8f0;">
                                                <div class="progress-bar <?= $bg_class ?>" role="progressbar" style="width: <?= $prog['overall'] ?>%;" aria-valuenow="<?= $prog['overall'] ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                            <div class="d-flex justify-content-between text-muted mt-1" style="font-size: 0.7rem;">
                                                <span>महत्त्वाची: <strong><?= $prog['imp_pct'] ?>%</strong></span>
                                                <span>अतिरिक्त: <strong><?= $prog['extra_pct'] ?>%</strong></span>
                                            </div>
                                            <?php if ($prog['extra_pct'] < 70): ?>
                                                <a href="<?= APP_URL ?>/students/edit.php?id=<?= $s['id'] ?>&tab=extra" class="badge text-decoration-none py-0.5 px-1.5 mt-1 d-inline-flex align-items-center gap-1" style="background:#FFFBEB; color:#B45309; border:1px solid #FCD34D; font-size:0.68rem;" title="उर्वरित अतिरिक्त माहिती भरा">
                                                    <i class="bi bi-pencil-square"></i> + अतिरिक्त माहिती भरा
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <?php
                                    $stmt2 = $db->prepare("SELECT id, status FROM hpc_cards WHERE student_id = ? ORDER BY created_at DESC LIMIT 1");
                                    $stmt2->execute([$s['id']]);
                                    $hpc = $stmt2->fetch();
                                    if ($hpc && $hpc['status'] === 'completed'):
                                    ?>
                                        <a href="<?= APP_URL ?>/cards/view.php?id=<?= $hpc['id'] ?>" class="badge bg-success text-decoration-none">पूर्ण</a>
                                    <?php elseif ($hpc): ?>
                                        <a href="<?= APP_URL ?>/cards/create.php?student_id=<?= $s['id'] ?>" class="badge bg-warning text-dark text-decoration-none">मसुदा</a>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border">नाही</span>
                                    <?php endif; ?>
                                </td>

                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= APP_URL ?>/students/edit.php?id=<?= $s['id'] ?>" class="btn btn-outline-primary" title="माहिती संपादित करा"><i class="bi bi-pencil"></i></a>
                                        <a href="<?= APP_URL ?>/cards/create.php?student_id=<?= $s['id'] ?>" class="btn btn-outline-success" title="HPC तयार करा"><i class="bi bi-card-checklist"></i></a>
                                        <form method="POST" action="<?= APP_URL ?>/students/delete.php" class="d-inline" onsubmit="return confirm('खात्री आहे? हा विद्यार्थी हटवला जाईल.')">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm" title="हटवा"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav class="p-3 border-top">
                    <ul class="pagination justify-content-center mb-0">
                        <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                            <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $p ?>&std=<?= $std_filter ?>&search=<?= urlencode($search) ?>"><?= $p ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
