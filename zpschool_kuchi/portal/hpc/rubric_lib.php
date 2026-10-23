<?php
/**
 * HPC रुब्रिक wizard (मानक पद्धत) — सामायिक साहाय्यक.
 * पायरी 0 विद्यार्थी माहिती · 1 रुब्रिक (C-code) निवड · 2 शिक्षक अभिप्राय · 3 कामगिरी
 *
 * डेटा स्रोत: includes/hpc_fs_data.php (क्षेत्रे/क्षमता, SCERT पायाभूत स्तर – इ. १ व २),
 * cce_abhipray2 (क्षमतानिहाय २० अभिप्राय), cce_kamgiri (सामर्थ्य/सुधारणा विधाने).
 * निवडी hpc_cards / hpc_domain_assessments मध्येच जातात, त्यामुळे १९-पानी अधिकृत HPC प्रिंटमध्ये दिसतात.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/hpc_fs_data.php';

/** कामगिरी बँकेतील (cce_kamgiri.cg) कोड → विकास क्षेत्र क्रमांक */
function hpc_rb_kamgiri_cg(int $domain_id): string
{
    return $domain_id === 6 ? '5.1' : (string) $domain_id;
}

function hpc_rb_is_girl(array $student): bool
{
    return trim((string) ($student['gender'] ?? '')) === 'मुलगी';
}

/** स्कीमा — नवीन टेबल/कॉलम नसल्यास तयार (idempotent). */
function hpc_rb_ensure_schema(PDO $db): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS hpc_kamgiri (
            id INT AUTO_INCREMENT PRIMARY KEY,
            hpc_card_id INT NOT NULL,
            domain_id INT NOT NULL,
            strengths TEXT,
            improvements TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_card_domain (hpc_card_id, domain_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $cols = [];
        foreach ($db->query("SHOW COLUMNS FROM hpc_domain_assessments") as $c) $cols[strtolower($c['Field'])] = true;
        $add = [];
        if (!isset($cols['abhipray_ids']))       $add[] = 'ADD COLUMN abhipray_ids TEXT NULL';
        if (!isset($cols['abhipray_ids_term2'])) $add[] = 'ADD COLUMN abhipray_ids_term2 TEXT NULL';
        if ($add) $db->exec('ALTER TABLE hpc_domain_assessments ' . implode(', ', $add));
    } catch (Throwable $e) {
        // जुन्या इन्स्टॉलवर हक्क नसल्यास update_tachan.php वापरा
    }
}

/** विद्यार्थी (शाळा-मर्यादित) */
function hpc_rb_student(PDO $db, int $school_id, int $student_id): ?array
{
    $st = $db->prepare("SELECT * FROM students WHERE id = ? AND school_id = ? AND is_active = 1");
    $st->execute([$student_id, $school_id]);
    $row = $st->fetch();
    return $row ?: null;
}

/** चालू वर्षाचे HPC कार्ड (नसल्यास $create=true वर तयार) */
function hpc_rb_card(PDO $db, int $school_id, int $student_id, bool $create = false): ?array
{
    $st = $db->prepare("SELECT * FROM hpc_cards WHERE student_id = ? AND school_id = ? AND academic_year = ? ORDER BY created_at DESC LIMIT 1");
    $st->execute([$student_id, $school_id, academic_year()]);
    $card = $st->fetch();
    if (!$card && $create) {
        // Enforce School Student Quota for HPC cards
        if (function_exists('getSchoolStudentQuota')) {
            $quota = getSchoolStudentQuota($school_id);
            if (!$quota['is_unlimited']) {
                $stCnt = $db->prepare("SELECT COUNT(DISTINCT student_id) FROM hpc_cards WHERE school_id = ? AND academic_year = ?");
                $stCnt->execute([$school_id, academic_year()]);
                $used_cards = (int)$stCnt->fetchColumn();
                if ($used_cards >= (int)$quota['max']) {
                    return null; // Quota exceeded! Do not auto-create card beyond plan limit!
                }
            }
        }
        $db->prepare("INSERT INTO hpc_cards (student_id, school_id, academic_year, status) VALUES (?, ?, ?, 'draft')")
           ->execute([$student_id, $school_id, academic_year()]);
        $st->execute([$student_id, $school_id, academic_year()]);
        $card = $st->fetch();
    }
    return $card ?: null;
}

/** क्षेत्रनिहाय मूल्यमापन ओळी (domain_id => row) */
function hpc_rb_assessments(PDO $db, int $card_id): array
{
    $st = $db->prepare("SELECT * FROM hpc_domain_assessments WHERE hpc_card_id = ? ORDER BY domain_id");
    $st->execute([$card_id]);
    $out = [];
    foreach ($st->fetchAll() as $r) $out[(int) $r['domain_id']] = $r;
    return $out;
}

/** क्षेत्र ओळ नसल्यास तयार करून परत देते */
function hpc_rb_ensure_domain_row(PDO $db, int $card_id, int $domain_id): array
{
    $st = $db->prepare("SELECT * FROM hpc_domain_assessments WHERE hpc_card_id = ? AND domain_id = ? LIMIT 1");
    $st->execute([$card_id, $domain_id]);
    $row = $st->fetch();
    if ($row) return $row;
    $dom = hpc_fs_domains()[$domain_id] ?? ['name' => 'Domain ' . $domain_id, 'name_mr' => 'क्षेत्र ' . $domain_id];
    $db->prepare("INSERT INTO hpc_domain_assessments (hpc_card_id, domain_id, domain_name, domain_name_mr) VALUES (?, ?, ?, ?)")
       ->execute([$card_id, $domain_id, $dom['name'], $dom['name_mr']]);
    $st->execute([$card_id, $domain_id]);
    return $st->fetch();
}

function hpc_rb_json_list($v): array
{
    if (is_array($v)) return array_values($v);
    $d = json_decode((string) $v, true);
    return is_array($d) ? array_values($d) : [];
}

/** क्षेत्र × सत्र साठी निवडलेला C-code (मिनीशाळेप्रमाणे एकच) */
function hpc_rb_selected_code(array $row, int $sem): string
{
    $list = hpc_rb_json_list($row[$sem === 2 ? 'competencies_term2' : 'competencies'] ?? '');
    return $list ? trim((string) $list[0]) : '';
}

/** क्षमतेचे २० अभिप्राय (मुलगा/मुलगी रूप) */
function hpc_rb_abhipray_options(PDO $db, string $code, bool $girl): array
{
    if ($code === '') return [];
    try {
        $st = $db->prepare("SELECT id, num, notes, notes2 FROM cce_abhipray2 WHERE capacity = ? ORDER BY num, id");
        $st->execute([$code]);
        $out = [];
        foreach ($st->fetchAll() as $r) {
            $t = trim((string) ($girl ? ($r['notes2'] ?: $r['notes']) : $r['notes']));
            if ($t !== '') $out[(int) $r['id']] = $t;
        }
        return $out;
    } catch (Throwable $e) {
        return [];
    }
}

/** क्षेत्राची सामर्थ्य/सुधारणा विधाने */
function hpc_rb_kamgiri_options(PDO $db, int $domain_id, string $type, bool $girl): array
{
    try {
        $st = $db->prepare("SELECT id, vidhan, vidhan2 FROM cce_kamgiri WHERE cg = ? AND types = ? ORDER BY id");
        $st->execute([hpc_rb_kamgiri_cg($domain_id), $type]);
        $out = [];
        foreach ($st->fetchAll() as $r) {
            $t = trim((string) ($girl ? ($r['vidhan2'] ?: $r['vidhan']) : $r['vidhan']));
            if ($t !== '') $out[(int) $r['id']] = $t;
        }
        return $out;
    } catch (Throwable $e) {
        return [];
    }
}

/** पायरी 1 जतन: क्षेत्र × सत्र = एक C-code */
function hpc_rb_save_code(PDO $db, int $card_id, int $domain_id, int $sem, string $code): bool
{
    $dom = hpc_fs_domains()[$domain_id] ?? null;
    if (!$dom) return false;
    if ($code !== '' && !isset($dom['competencies'][$code])) return false;
    $row = hpc_rb_ensure_domain_row($db, $card_id, $domain_id);
    $old = hpc_rb_selected_code($row, $sem);
    $cCol = $sem === 2 ? 'competencies_term2' : 'competencies';
    $gCol = $sem === 2 ? 'curricular_goals_term2' : 'curricular_goals';
    $goal = $code !== '' ? ('CG-' . explode('.', substr($code, 2))[0]) : '';
    $sql = "UPDATE hpc_domain_assessments SET $cCol = ?, $gCol = ?";
    $args = [json_encode($code !== '' ? [$code] : [], JSON_UNESCAPED_UNICODE), json_encode($goal !== '' ? [$goal] : [], JSON_UNESCAPED_UNICODE)];
    if ($old !== $code) { // क्षमता बदलली → जुने अभिप्राय रद्द
        $aCol = $sem === 2 ? 'abhipray_ids_term2' : 'abhipray_ids';
        $fCol = $sem === 2 ? 'teacher_feedback_mr_term2' : 'teacher_feedback_mr';
        $sql .= ", $aCol = NULL, $fCol = ''";
    }
    $sql .= " WHERE id = ?";
    $args[] = (int) $row['id'];
    $db->prepare($sql)->execute($args);
    return true;
}

/** पायरी 2 जतन: निवडलेले अभिप्राय (id यादी) → मजकूर */
function hpc_rb_save_abhipray(PDO $db, int $card_id, int $domain_id, int $sem, array $ids, bool $girl): int
{
    $row = hpc_rb_ensure_domain_row($db, $card_id, $domain_id);
    $code = hpc_rb_selected_code($row, $sem);
    $opts = hpc_rb_abhipray_options($db, $code, $girl);
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($i) => isset($opts[$i]))));
    $text = implode("\n", array_map(fn($i) => $opts[$i], $ids));
    $aCol = $sem === 2 ? 'abhipray_ids_term2' : 'abhipray_ids';
    $fCol = $sem === 2 ? 'teacher_feedback_mr_term2' : 'teacher_feedback_mr';
    $db->prepare("UPDATE hpc_domain_assessments SET $aCol = ?, $fCol = ? WHERE id = ?")
       ->execute([json_encode($ids), $text, (int) $row['id']]);
    return count($ids);
}

/** पायरी 3 जतन: सामर्थ्य/सुधारणा → hpc_kamgiri + hpc_cards.summary_domain_N */
function hpc_rb_save_kamgiri(PDO $db, int $card_id, int $domain_id, array $str, array $imp, bool $girl): array
{
    $so = hpc_rb_kamgiri_options($db, $domain_id, 'strength', $girl);
    $io = hpc_rb_kamgiri_options($db, $domain_id, 'improvement', $girl);
    $str = array_values(array_unique(array_filter(array_map('intval', $str), fn($i) => isset($so[$i]))));
    $imp = array_values(array_unique(array_filter(array_map('intval', $imp), fn($i) => isset($io[$i]))));
    $db->prepare("INSERT INTO hpc_kamgiri (hpc_card_id, domain_id, strengths, improvements) VALUES (?, ?, ?, ?)
                  ON DUPLICATE KEY UPDATE strengths = VALUES(strengths), improvements = VALUES(improvements)")
       ->execute([$card_id, $domain_id, json_encode($str), json_encode($imp)]);
    $parts = [];
    if ($str) $parts[] = 'सामर्थ्य: ' . implode(' ', array_map(fn($i) => $so[$i], $str));
    if ($imp) $parts[] = 'सुधारणा: ' . implode(' ', array_map(fn($i) => $io[$i], $imp));
    if ($domain_id >= 1 && $domain_id <= 6) {
        $db->prepare("UPDATE hpc_cards SET summary_domain_{$domain_id} = ? WHERE id = ?")
           ->execute([implode("\n", $parts), $card_id]);
    }
    return [count($str), count($imp)];
}

function hpc_rb_kamgiri_rows(PDO $db, int $card_id): array
{
    $st = $db->prepare("SELECT * FROM hpc_kamgiri WHERE hpc_card_id = ?");
    $st->execute([$card_id]);
    $out = [];
    foreach ($st->fetchAll() as $r) $out[(int) $r['domain_id']] = $r;
    return $out;
}

/** प्रगती: प्रत्येक सत्रात किती क्षेत्रांचा C-code भरला (x/6) */
function hpc_rb_progress(array $assess): array
{
    $p = [1 => 0, 2 => 0];
    foreach (hpc_fs_domains() as $did => $_) {
        foreach ([1, 2] as $sem) {
            if (isset($assess[$did]) && hpc_rb_selected_code($assess[$did], $sem) !== '') $p[$sem]++;
        }
    }
    return $p;
}

/** इयत्ता लेबल (students.grade मध्ये "2" / "इयत्ता २" / "दुसरी" असे मिश्र मूल्य) */
function hpc_rb_grade_label(string $grade): string
{
    $g = trim($grade);
    $mr = ['1' => 'पहिली', '2' => 'दुसरी', '3' => 'तिसरी', '4' => 'चौथी', '5' => 'पाचवी', '6' => 'सहावी', '7' => 'सातवी', '8' => 'आठवी'];
    $n = strtr($g, ['१' => '1', '२' => '2', '३' => '3', '४' => '4', '५' => '5', '६' => '6', '७' => '7', '८' => '8']);
    if (preg_match('/(\d)/', $n, $m) && isset($mr[$m[1]])) return $mr[$m[1]];
    return $g !== '' ? $g : '-';
}

function hpc_rb_csrf(): string
{
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

/** पायरी-पट्टी + विद्यार्थी शीर्षक */
function hpc_rb_header(array $student, int $step, string $back_url): string
{
    $sid = (int) $student['id'];
    $steps = [
        0 => ['विद्यार्थी माहिती', APP_URL . '/hpc/create.php?student_id=' . $sid],
        1 => ['रुब्रिक निवड', APP_URL . '/hpc/step1_rubric.php?student_id=' . $sid],
        2 => ['शिक्षक अभिप्राय', APP_URL . '/hpc/step2_abhipray.php?student_id=' . $sid],
        3 => ['कामगिरी', APP_URL . '/hpc/step3_kamgiri.php?student_id=' . $sid],
    ];
    $name = sanitize((string) ($student['name_mr'] ?: $student['name']));
    $cls = sanitize(hpc_rb_grade_label((string) ($student['grade'] ?? '')) . (($student['section'] ?? '') !== '' ? '-' . $student['section'] : ''));
    $h = '<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">'
       . '<div class="d-flex align-items-center gap-2.5">'
       . '<a href="' . sanitize($back_url) . '" class="btn btn-outline-secondary btn-sm shadow-xs"><i class="bi bi-arrow-left"></i> मागे</a>'
       . '<div><h5 class="mb-0 fw-bold"><a href="' . APP_URL . '/students/edit.php?id=' . $sid . '&return=hpc" class="text-dark text-decoration-none hover-primary" title="विद्यार्थी माहिती, उंची, वजन संपादित करा">' . $name . ' <i class="bi bi-pencil-square text-primary small ms-1"></i></a></h5><div class="text-muted small">' . $cls . ' · ' . sanitize(academic_year()) . '</div></div>'
       . '</div>'
       . '<div><a href="' . APP_URL . '/students/edit.php?id=' . $sid . '&return=hpc" class="btn btn-sm btn-outline-primary shadow-xs"><i class="bi bi-person-gear"></i> माहिती / उंची-वजन संपादन</a></div>'
       . '</div>';
    $h .= '<div class="hpc-steps mb-3">';
    foreach ($steps as $i => [$label, $url]) {
        $cls2 = $i < $step ? 'done' : ($i === $step ? 'cur' : '');
        $h .= '<a class="' . $cls2 . '" href="' . sanitize($url) . '">' . $i . ' · ' . $label . '</a>';
    }
    $h .= '</div>';
    return $h;
}

function hpc_rb_css(): string
{
    return '<style>
.hpc-steps{display:flex;border-radius:.75rem;overflow-x:auto;-webkit-overflow-scrolling:touch;box-shadow:0 1px 3px rgba(0,0,0,.08);background:#e2e8f0}
.hpc-steps a{flex:1;min-width:70px;text-align:center;padding:.55rem .35rem;font-size:.78rem;font-weight:700;color:#475569;text-decoration:none;white-space:nowrap;transition:all .15s ease}
.hpc-steps a.done{background:#16a34a;color:#fff}.hpc-steps a.cur{background:#2563eb;color:#fff}
@media (max-width:575.98px){.hpc-steps a{font-size:.72rem;padding:.45rem .25rem}}
.hpc-info{background:#eff6ff;border:1px solid #dbeafe;color:#1d4ed8;border-radius:.5rem;padding:.7rem 1rem;font-size:.9rem}
.hpc-dom{background:#fff;border:1px solid #e2e8f0;border-radius:.75rem;overflow:hidden;margin-bottom:1rem}
.hpc-dom>.hd{background:#f8fafc;padding:.6rem 1rem;font-weight:600;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;font-size:.95rem}
.hpc-sem{border-radius:.5rem;padding:.75rem;border:1px solid #dbeafe;background:#eff6ff}
.hpc-sem.s2{border-color:#fed7aa;background:#EFF8F4}
.hpc-sem .lbl{font-size:.8rem;font-weight:600;color:#1d4ed8;margin-bottom:.4rem;display:flex;justify-content:space-between}
.hpc-sem.s2 .lbl{color:#13644B}
.hpc-list{max-height:14rem;overflow-y:auto;background:#fff;border:1px solid #e2e8f0;border-radius:.5rem;padding:.4rem}
.hpc-list label{display:flex;gap:.5rem;align-items:flex-start;font-size:.82rem;padding:.25rem .3rem;border-radius:.3rem;cursor:pointer}
.hpc-list label:hover{background:#f8fafc}
.hpc-list input{margin-top:.2rem;flex:0 0 auto}
.hpc-ok{color:#16a34a;font-weight:600;font-size:.8rem}.hpc-pending{color:#94a3b8;font-size:.8rem}
select.hpc-sel.filled{border-color:#4ade80;background:#f0fdf4}
.hpc-toast{position:fixed;bottom:1.5rem;right:1.5rem;background:#16a34a;color:#fff;padding:.6rem 1rem;border-radius:.5rem;font-size:.85rem;z-index:2000;display:none}
</style>';
}
