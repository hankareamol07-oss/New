<?php
$page_title = 'वर्णनात्मक नोंदी';
require_once __DIR__ . '/../includes/cce_functions.php';
requireLogin();

$school_id = $_SESSION['school_id'];
$db = cce_db($school_id);
$year = academic_year();
$standards = cce_school_standards($school_id);

$tScope = function_exists('get_current_teacher_scope') ? get_current_teacher_scope() : null;
if ($tScope && !empty($tScope['stds'])) {
    $allowed_map = array_flip($tScope['stds']);
    $standards = array_filter($standards, function($k) use ($allowed_map) {
        return isset($allowed_map[$k]);
    }, ARRAY_FILTER_USE_KEY);
}

$std = (int)($_REQUEST['std'] ?? array_key_first($standards) ?? 1);
if ($tScope && !empty($tScope['stds']) && !in_array($std, $tScope['stds'], true)) {
    $std = (int)$tScope['stds'][0];
}
$semester = (int)($_REQUEST['semester'] ?? 1) === 2 ? 2 : 1;
$student_id = (int)($_REQUEST['student_id'] ?? 0);
$selected_slot = (int)($_REQUEST['slot'] ?? 0);

$students = cce_students($school_id, $std);
if (!$student_id && $students) $student_id = (int)$students[0]['id'];
$student = null;
$cur_index = -1;
foreach ($students as $idx => $s) {
    if ((int)$s['id'] === $student_id) {
        $student = $s;
        $cur_index = $idx;
    }
}
$prev_student = ($cur_index > 0) ? $students[$cur_index - 1] : null;
$next_student = ($cur_index >= 0 && $cur_index < count($students) - 1) ? $students[$cur_index + 1] : null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $student) {
    cce_check_csrf();
    $is_girl = cce_is_female_student($student);
    $all_areas = cce_class_note_areas($school_id, $std);
    $stmt = $db->prepare("INSERT OR REPLACE INTO cce_student_notes (school_id, student_id, academic_year, semester, slot_no, note_text)
                          VALUES (?,?,?,?,?,?)");
    $mysql = null;
    $mysql_stmt = null;
    try {
        $mysql = getMainDB();
        $mysql_stmt = $mysql->prepare("INSERT INTO cce_student_notes (school_id, student_id, academic_year, semester, slot_no, note_text)
                                       VALUES (?,?,?,?,?,?)
                                       ON DUPLICATE KEY UPDATE note_text = VALUES(note_text)");
    } catch (Throwable $e) {}

    $notes_to_sync = [];
    foreach (($_POST['note'] ?? []) as $slot => $text) {
        $slot = (int)$slot;
        if (!isset($all_areas[$slot])) continue;
        $clean = trim((string)$text);
        if ($clean !== '' && $is_girl) {
            $clean = cce_apply_gender_inflection($clean, $student, $slot === 3 ? 3 : 1);
        }
        $stmt->execute([$school_id, $student_id, $year, $semester, $slot, $clean]);
        if ($mysql_stmt) {
            try {
                $mysql_stmt->execute([$school_id, $student_id, $year, $semester, $slot, $clean]);
            } catch (Throwable $e) {}
        }
        $notes_to_sync[] = [
            'school_id' => $school_id,
            'student_id' => $student_id,
            'academic_year' => $year,
            'semester' => $semester,
            'slot_no' => $slot,
            'note_text' => $clean
        ];
    }
    if (!empty($notes_to_sync) && function_exists('sync_notes_to_mysql')) {
        sync_notes_to_mysql($school_id, $notes_to_sync);
    }
    flash('success', 'नोंदी जतन केल्या.');
    $next_id = ($_POST['action'] ?? '') === 'save_and_next' && $next_student ? (int)$next_student['id'] : $student_id;
    redirect(APP_URL . "/cce/remarks_entry.php?std=$std&semester=$semester&student_id=$next_id&slot=$selected_slot");
}

$saved = [];
if ($student) {
    try {
        $stmt = $db->prepare("SELECT slot_no, note_text FROM cce_student_notes WHERE student_id = ? AND academic_year = ? AND semester = ?");
        $stmt->execute([$student_id, $year, $semester]);
        foreach ($stmt->fetchAll() as $r) {
            if (trim((string)$r['note_text']) !== '') {
                $saved[(int)$r['slot_no']] = $r['note_text'];
            }
        }
    } catch (Throwable $e) {}

    // Fallback to MySQL if SQLite is empty
    if (empty($saved)) {
        try {
            $mysql = getMainDB();
            $mstmt = $mysql->prepare("SELECT slot_no, note_text FROM cce_student_notes WHERE student_id = ? AND academic_year = ? AND semester = ?");
            $mstmt->execute([$student_id, $year, $semester]);
            $my_rows = $mstmt->fetchAll();
            foreach ($my_rows as $r) {
                if (trim((string)$r['note_text']) !== '') {
                    $saved[(int)$r['slot_no']] = $r['note_text'];
                }
            }
            if (!empty($saved)) {
                $ins = $db->prepare("INSERT OR REPLACE INTO cce_student_notes (school_id, student_id, academic_year, semester, slot_no, note_text) VALUES (?,?,?,?,?,?)");
                foreach ($saved as $s_no => $s_txt) {
                    $ins->execute([$school_id, $student_id, $year, $semester, $s_no, $s_txt]);
                }
            }
        } catch (Throwable $e) {}
    } else {
        // SQLite has notes; make sure MySQL is synced as well
        try {
            if (function_exists('sync_notes_to_mysql')) {
                $sync_batch = [];
                foreach ($saved as $s_no => $s_txt) {
                    $sync_batch[] = [
                        'school_id' => $school_id,
                        'student_id' => $student_id,
                        'academic_year' => $year,
                        'semester' => $semester,
                        'slot_no' => $s_no,
                        'note_text' => $s_txt
                    ];
                }
                sync_notes_to_mysql($school_id, $sync_batch);
            }
        } catch (Throwable $e) {}
    }
}

$all_areas = cce_class_note_areas($school_id, $std);
$areas = ($selected_slot > 0 && isset($all_areas[$selected_slot])) ? [$selected_slot => $all_areas[$selected_slot]] : $all_areas;

$is_girl = cce_is_female_student($student);
$slot_suggestions = [];
$default_notes = [];
foreach ($areas as $slot => $label) {
    $sugs = cce_note_suggestions($std, $semester, $slot, $student);
    $slot_suggestions[$slot] = $sugs;
    if ($sugs) {
        $pick_idx = ((int)($student['roll_no'] ?? 1) + $slot) % count($sugs);
        $default_notes[$slot] = $sugs[$pick_idx];
    } else {
        $default_notes[$slot] = '';
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2 class="fw-bold mb-1" style="color: var(--aura-slate-900, #0f172a);"><i class="bi bi-chat-left-text" style="color: var(--aura-primary, #4F46E5);"></i> वर्णनात्मक नोंदी</h2>
        <p class="text-muted small mb-0">इयत्ता, सत्र व घटकांनुसार नोंदी भरा · १-क्लिक जलद सूचना व ऑटो-फिल</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= APP_URL ?>/cce/narrative_report.php?std=<?= $std ?>&all=1" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3">
            <i class="bi bi-printer"></i> नोंदी प्रिंट करा
        </a>
        <a href="<?= APP_URL ?>/cce/index.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
            <i class="bi bi-arrow-left"></i> मागे
        </a>
    </div>
</div>

<!-- STANDARD CHOICE CHIPS (AURA UI 3.0) -->
<div class="mb-3">
    <div class="small fw-bold text-muted mb-1 text-uppercase" style="letter-spacing:0.5px; font-size:0.75rem;">इयत्ता निवडा (Standard)</div>
    <div class="aura-chip-group">
        <?php foreach ($standards as $i => $lbl): ?>
            <a href="?std=<?= $i ?>&semester=<?= $semester ?>&slot=<?= $selected_slot ?>" 
               class="aura-chip <?= $i === $std ? 'active' : '' ?>">
                <?= std_label($i) ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- FILTER CARD -->
<form method="GET" class="card card-body mb-3 border-0 shadow-sm" style="border-radius:14px; background: #ffffff;">
    <input type="hidden" name="std" value="<?= $std ?>">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small fw-semibold">सत्र निवडा</label>
            <div class="d-flex gap-2">
                <a href="?std=<?= $std ?>&semester=1&slot=<?= $selected_slot ?>&student_id=<?= $student_id ?>" 
                   class="btn btn-sm flex-fill fw-semibold <?= $semester === 1 ? 'btn-primary' : 'btn-outline-secondary' ?>" style="border-radius:10px;">
                   प्रथम सत्र (सत्र १)
                </a>
                <a href="?std=<?= $std ?>&semester=2&slot=<?= $selected_slot ?>&student_id=<?= $student_id ?>" 
                   class="btn btn-sm flex-fill fw-semibold <?= $semester === 2 ? 'btn-primary' : 'btn-outline-secondary' ?>" style="border-radius:10px;">
                   द्वितीय सत्र (सत्र २)
                </a>
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label small fw-semibold" style="color: var(--aura-primary, #4F46E5);">घटक / विषय निवडा</label>
            <select name="slot" class="form-select" style="border-radius:10px;" onchange="this.form.submit()">
                <option value="0" <?= $selected_slot === 0 ? 'selected' : '' ?>>— सर्व निवडक विषय / घटक (<?= to_mr_digits(count($all_areas)) ?>) —</option>
                <?php foreach ($all_areas as $sl => $lb): ?>
                    <option value="<?= $sl ?>" <?= $selected_slot === $sl ? 'selected' : '' ?>><?= to_mr_digits($sl) ?>. <?= sanitize($lb) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-5">
            <label class="form-label small fw-semibold">विद्यार्थी निवडा</label>
            <select name="student_id" class="form-select" style="border-radius:10px;" onchange="this.form.submit()">
                <?php foreach ($students as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= (int)$s['id'] === $student_id ? 'selected' : '' ?>>
                        हजेरी <?= sanitize($s['roll_no']) ?> — <?= sanitize($s['name_mr'] ?: $s['name']) ?> (<?= cce_is_female_student($s) ? 'मुलगी' : 'मुलगा' ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</form>

<?php if ($students && count($students) > 1): ?>
<!-- QUICK TOUCH STUDENT CAROUSEL CHIPS -->
<div class="mb-3 d-flex align-items-center gap-2 overflow-auto py-1" style="white-space: nowrap; -webkit-overflow-scrolling: touch;">
    <span class="small fw-bold text-muted me-1 flex-shrink-0" style="font-size:0.75rem;">विद्यार्थी:</span>
    <?php foreach ($students as $s): 
        $is_curr = (int)$s['id'] === $student_id;
        $is_g = cce_is_female_student($s);
    ?>
        <a href="?std=<?= $std ?>&semester=<?= $semester ?>&student_id=<?= $s['id'] ?>&slot=<?= $selected_slot ?>" 
           class="btn btn-sm flex-shrink-0 <?= $is_curr ? 'btn-primary shadow-sm' : 'btn-light border' ?>"
           style="border-radius: 999px; font-size: 0.82rem; padding: 4px 12px;">
            <span><?= $is_g ? '👧' : '👦' ?></span> <?= sanitize($s['roll_no']) ?>. <?= sanitize(mb_substr($s['name_mr'] ?: $s['name'], 0, 15)) ?>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!$student): ?>
    <div class="alert alert-warning" style="border-radius:12px;">या इयत्तेत विद्यार्थी नाहीत.</div>
<?php else: ?>

<!-- STUDENT INFO & AUTO-FILL TOOLBAR -->
<div class="card mb-3 border-0 shadow-sm" style="background: <?= $is_girl ? '#FFF5F7' : '#F0FDF4' ?>; border-left: 5px solid <?= $is_girl ? '#EC4899' : '#10B981' ?> !important; border-radius:14px;">
    <div class="card-body py-2 px-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex align-items-center gap-2">
            <span class="fs-4"><?= $is_girl ? '👧' : '👦' ?></span>
            <div>
                <strong class="fs-6" style="color: var(--aura-slate-900, #0f172a);"><?= sanitize($student['name_mr'] ?: $student['name']) ?></strong>
                <span class="text-muted small ms-1">(हजेरी क्र. <?= to_mr_digits($student['roll_no']) ?> | इयत्ता <?= std_label($std) ?>)</span>
            </div>
            <?php if ($is_girl): ?>
                <span class="badge text-white px-2 py-1 ms-1" style="background: #EC4899; border-radius: 999px;">
                    <i class="bi bi-gender-female"></i> मुलगी
                </span>
            <?php else: ?>
                <span class="badge text-white px-2 py-1 ms-1" style="background: #3B82F6; border-radius: 999px;">
                    <i class="bi bi-gender-male"></i> मुलगा
                </span>
            <?php endif; ?>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <button type="button" class="btn btn-sm btn-success rounded-pill px-3" onclick="autoFillRemarks(false)">
                <i class="bi bi-lightning-charge-fill"></i> रिकाम्या नोंदी ऑटो-फिल करा
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" onclick="autoFillRemarks(true)" title="नवीन सुचवलेल्या नोंदींनी बदला">
                <i class="bi bi-arrow-repeat"></i> पुन्हा भरा
            </button>
        </div>
    </div>
</div>

<form method="POST">
    <?= cce_csrf_field() ?>
    <input type="hidden" name="std" value="<?= $std ?>">
    <input type="hidden" name="semester" value="<?= $semester ?>">
    <input type="hidden" name="student_id" value="<?= $student_id ?>">
    <input type="hidden" name="slot" value="<?= $selected_slot ?>">
    
    <div class="row g-3">
    <?php 
    foreach ($areas as $slot => $label):
        $suggestions = $slot_suggestions[$slot] ?? []; ?>
        <div class="<?= $selected_slot > 0 ? 'col-12' : 'col-md-6' ?>">
            <div class="card h-100 border-0 shadow-sm" style="border-radius:12px;">
                <div class="card-header py-2 small fw-bold bg-light d-flex justify-content-between align-items-center" style="border-radius:12px 12px 0 0;">
                    <span><i class="bi bi-tag text-primary"></i> <?= to_mr_digits($slot) ?>. <?= sanitize($label) ?></span>
                    <span class="badge bg-secondary text-white" style="font-size:0.75rem;"><?= sanitize($student['name_mr'] ?: $student['name']) ?></span>
                </div>
                <div class="card-body">
                    <?php if ($suggestions): ?>
                    <div class="mb-2">
                        <label class="form-label small text-muted mb-1"><i class="bi bi-lightbulb"></i> तयार नोंदींमधून निवडा:</label>
                        <select class="form-select" onchange="onSelectSuggestion(<?= $slot ?>, this.value)">
                            <option value="">-- तयार नोंद निवडा (<?= to_mr_digits(count($suggestions)) ?> नोंदी उपलब्ध) --</option>
                            <?php foreach ($suggestions as $sg): ?>
                                <option value="<?= sanitize($sg) ?>"><?= sanitize(mb_substr($sg, 0, 100)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php else: ?>
                        <div class="text-muted small mb-2"><i class="bi bi-info-circle"></i> थेट नोंद खाली लिहा.</div>
                    <?php endif; ?>
                    <label class="form-label small fw-semibold mb-1">वर्णनात्मक नोंद मजकूर:</label>
                    <textarea class="form-control" rows="<?= $selected_slot > 0 ? '4' : '3' ?>" name="note[<?= $slot ?>]" id="note<?= $slot ?>" placeholder="येथे वर्णनात्मक नोंद लिहा किंवा वरील यादीतून निवडा..." onblur="if(isGirl){inflectTextarea(<?= $slot ?>);}"><?= sanitize($saved[$slot] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
    
    <div class="aura-sticky-action-bar">
        <div>
            <?php if ($prev_student): ?>
                <a href="<?= APP_URL ?>/cce/remarks_entry.php?std=<?= $std ?>&semester=<?= $semester ?>&student_id=<?= $prev_student['id'] ?>&slot=<?= $selected_slot ?>" class="btn btn-outline-secondary rounded-pill px-3">
                    <i class="bi bi-chevron-left"></i> मागील (<?= sanitize($prev_student['roll_no']) ?>)
                </a>
            <?php endif; ?>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" name="action" value="save" class="btn btn-primary rounded-pill px-4 shadow-sm fw-bold">
                <i class="bi bi-save"></i> नोंदी जतन करा
            </button>
            <?php if ($next_student): ?>
                <button type="submit" name="action" value="save_and_next" class="btn btn-success rounded-pill px-4 shadow-sm fw-bold">
                    <i class="bi bi-arrow-right-circle"></i> जतन करा व पुढील → (<?= sanitize($next_student['roll_no']) ?>)
                </button>
                <a href="<?= APP_URL ?>/cce/remarks_entry.php?std=<?= $std ?>&semester=<?= $semester ?>&student_id=<?= $next_student['id'] ?>&slot=<?= $selected_slot ?>" class="btn btn-outline-secondary rounded-pill px-3">
                    पुढील <i class="bi bi-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
</form>
<?php endif; ?>

<script>
const defaultNotes = <?= json_encode($default_notes, JSON_UNESCAPED_UNICODE) ?>;
const isGirl = <?= $is_girl ? 'true' : 'false' ?>;

function onSelectSuggestion(slot, val) {
    if (!val) return;
    const el = document.getElementById('note' + slot);
    if (el) {
        el.value = val;
        if (isGirl) inflectTextarea(slot);
    }
}

function autoFillRemarks(overwrite) {
    let filled = 0;
    for (const [slot, text] of Object.entries(defaultNotes)) {
        const el = document.getElementById('note' + slot);
        if (!el || !text) continue;
        if (overwrite || !el.value.trim()) {
            el.value = text;
            if (isGirl) inflectTextarea(slot);
            filled++;
        }
    }
    if (filled > 0) {
        alert(filled + ' विषयांच्या नोंदी स्वयंचलित भरल्या आहेत.');
    } else {
        alert('सर्व नोंदी आधीच भरलेल्या आहेत.');
    }
}

function inflectTextarea(slot) {
    const el = document.getElementById('note' + slot);
    if (!el || !el.value) return;
    let t = el.value;
    
    // English inflection
    if (slot === 3 || /\b(He|he|His|his|Him|him|boy)\b/.test(t)) {
        t = t.replace(/\bHe\b/g, 'She')
             .replace(/\bhe\b/g, 'she')
             .replace(/\bHis\b/g, 'Her')
             .replace(/\bhis\b/g, 'her')
             .replace(/\bHim\b/g, 'Her')
             .replace(/\bhim\b/g, 'her')
             .replace(/\bboy\b/gi, 'girl');
        el.value = t;
        return;
    }
    
    // Pronouns
    const pronouns = {
        'त्याच्या': 'तिच्या', 'त्याचा': 'तिचा', 'त्याची': 'तिची', 'त्याचे': 'तिचे',
        'त्याला': 'तिला', 'त्याने': 'तिने', 'त्यास': 'तिस', 'तो': 'ती', 'हा': 'ही',
        'हुशार विद्यार्थी': 'हुशार विद्यार्थिनी'
    };
    for (const [m, f] of Object.entries(pronouns)) {
        const re = new RegExp('([\\s,।\\.]|^)' + m + '([\\s,।\\.]|$)', 'gu');
        t = t.replace(re, '$1' + f + '$2');
    }

    // Specific irregular phrases
    const phrases = {
        'करू लागतो': 'करू लागते',
        'सहभागी होतो': 'सहभागी होते',
        'मदत करतो': 'मदत करते',
        'लक्ष देतो': 'लक्ष देते',
        'दिसून येतो': 'दिसून येते'
    };
    for (const [m, f] of Object.entries(phrases)) {
        t = t.replaceAll(m, f);
    }
    
    // Suffix Marathi verbs: any word ending with 'तो' before punctuation or end of line
    // e.g. करतो. -> करते., सांगतो. -> सांगते., काढतो -> काढते
    t = t.replace(/([\u0900-\u097F]{2,})तो([\.।!,\s]|$)/gu, '$1ते$2');
    
    el.value = t;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
