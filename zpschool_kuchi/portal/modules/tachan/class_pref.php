<?php
declare(strict_types=1);

/**
 * Smart School Hub — Teacher Lesson Planner Settings (टाचण सेटिंग्ज व प्राधान्ये)
 * Supports:
 * 1. एक वर्ग (Single Class)
 * 2. जोडवर्ग (Jodvarg / Combined Multi-grade)
 * 3. तासवार (Period-wise / Multi-class Teacher Timetable)
 * Separate tenant SQLite storage with dual-sync to MySQL.
 */
require_once __DIR__ . '/../lib/tachan_ui.php';
require_once __DIR__ . '/../lib/module_helpers.php';
require_once __DIR__ . '/../lib/tachan_engine.php';
require_once __DIR__ . '/../data/planner_data.php';
require_once __DIR__ . '/../lib/planner_seed.php';

$user = tachan_auth_check();
$sid = (int)$_SESSION['school_id'];
$teacherId = (int)$user['id'];
$pdo = db();
$classes = school_classes($sid);

if (!$classes) {
    flash_set('error', 'आधी वर्ग व्यवस्थापनातून वर्ग तयार करा.');
    header('Location: ' . APP_URL . '/modules/tachan/index.php');
    exit;
}

$byId = [];
foreach ($classes as $c) {
    $byId[(int)$c['id']] = $c;
}

$mediums = ['marathi' => 'मराठी माध्यम', 'semi_english' => 'सेमी-इंग्रजी'];
$types = [
    'single' => [
        'title' => 'एक वर्ग (वर्गशिक्षक)',
        'desc'  => 'एकाच इयत्तेचे सर्व किंवा बहुतांश विषय तुम्ही स्वतः शिकवता.',
        'icon'  => 'bi-person-badge',
    ],
    'jod' => [
        'title' => 'जोडवर्ग (द्विशिक्षकी / बहुवर्ग)',
        'desc'  => 'दोन इयत्ता एकाच वर्गात एकत्र शिकवता (उदा. १ली + २री किंवा ६वी + ७वी).',
        'icon'  => 'bi-people-fill',
    ],
    'periodwise' => [
        'title' => 'तासवार (विषय शिक्षक)',
        'desc'  => 'वेगवेगळ्या वर्गांना तुमचे विषय तासिकांनुसार शिकवता (उदा. ६वी ला गणित, ७वी ला विज्ञान).',
        'icon'  => 'bi-clock-history',
    ],
];

$lengths = [
    'full' => 'पूर्ण दिवस (सर्व तासिका १–८)',
    'half' => 'अर्धा दिवस (तासिका १–४)',
    'upto' => 'तासिका क्रमांकापर्यंत…',
];
$periods = planner_periods(1);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && tachan_verify_csrf()) {
    $tt = (string)($_POST['teach_type'] ?? 'single');
    if (!isset($types[$tt])) $tt = 'single';

    $cid = (int)($_POST['class_id'] ?? 0);
    if (!isset($byId[$cid])) $cid = (int)$classes[0]['id'];

    $jid = ($tt === 'jod') ? (int)($_POST['jod_class_id'] ?? 0) : 0;
    if ($jid && (!isset($byId[$jid]) || $jid === $cid)) $jid = 0;

    $dayLength = (string)($_POST['day_length'] ?? 'full');
    $uptoPeriod = max(1, min(9, (int)($_POST['upto_period'] ?? 8)));
    $medium = (string)($_POST['medium'] ?? 'marathi');

    // Save preferences in SQLite and dual-sync to MySQL
    tachan_prefs_save($pdo, $sid, $teacherId, [
        'teach_type'   => $tt,
        'class_id'     => $cid,
        'jod_class_id' => $jid,
        'medium'       => $medium,
        'day_length'   => $dayLength,
        'upto_period'  => $uptoPeriod,
    ]);

    // If teacher selected Jodvarg or Periodwise and auto_tt is checked
    if (!empty($_POST['auto_tt'])) {
        if ($tt === 'jod') {
            $stdA = planner_class_std($byId[$cid]);
            $stdB = $jid ? planner_class_std($byId[$jid]) : ($stdA === 8 ? 7 : $stdA + 1);
            $tplKey = "jod_{$stdA}_{$stdB}";
            $tpls = planner_teacher_timetable_templates();
            if (!isset($tpls[$tplKey])) {
                $tplKey = "jod_{$stdB}_{$stdA}";
            }
            if (!isset($tpls[$tplKey])) {
                $tplKey = 'jod_6_7';
            }
            $tgrid = planner_get_teacher_template_grid($tplKey, $medium);
            tachan_teacher_timetable_save($pdo, $sid, $teacherId, $tgrid, $medium);
        } elseif ($tt === 'periodwise') {
            $teacherTT = tachan_teacher_timetable_get($pdo, $sid, $teacherId);
            if (empty($teacherTT)) {
                $stdA = planner_class_std($byId[$cid]);
                $tgrid = planner_get_teacher_template_grid((string)$stdA, $medium);
                tachan_teacher_timetable_save($pdo, $sid, $teacherId, $tgrid, $medium);
            }
        }
    }

    if ($tt === 'periodwise') {
        flash_set('success', 'तासवार प्राधान्य जतन झाले. आता तुमचे तासिका वेळापत्रक तपासा व जतन करा.');
        header('Location: ' . APP_URL . '/modules/tachan/timetable.php?mode=teacher');
        exit;
    }

    flash_set('success', 'टाचण प्राधान्ये यशस्वीरीत्या जतन झाली.');
    header('Location: ' . APP_URL . '/modules/tachan/day.php');
    exit;
}

$p = tachan_prefs_get($pdo, $sid, $teacherId);
if (!$p['class_id']) {
    $p['class_id'] = (int)$classes[0]['id'];
}

tachan_layout_start('टाचण सेटिंग्ज व अध्यापन पद्धती', 'settings');
?>

<style>
.pref-type-card {
    border: 2px solid #E2E8F0;
    border-radius: 14px;
    padding: 18px;
    cursor: pointer;
    transition: all 0.2s ease;
    background: #ffffff;
}
.pref-type-card:hover {
    border-color: #CBD5E1;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}
.pref-type-card.selected {
    border-color: #167A5B;
    background: #FFF8E1;
    box-shadow: 0 4px 14px rgba(22,122,91, 0.15);
}
.wizard-step {
    display: none;
}
.wizard-step.active {
    display: block;
}
.step-dot {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    background: #E2E8F0;
    color: #475569;
    transition: all 0.2s ease;
}
.step-dot.active {
    background: #167A5B;
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(22,122,91, 0.35);
}
</style>

<div class="row justify-content-center">
    <div class="col-12 col-lg-9 col-xl-8">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
            <!-- Header with Step Indicator -->
            <div class="card-header py-3 px-4 text-white d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%);">
                <div>
                    <h5 class="mb-0 fw-bold">
                        <i class="bi bi-sliders text-warning me-2"></i> टाचण वही प्राधान्ये व अध्यापन पद्धत
                    </h5>
                    <div class="small text-white-50 mt-1">एक वर्ग, जोडवर्ग किंवा तासवार पद्धतीनुसार टाचण सेट करा</div>
                </div>

                <div class="d-flex gap-2">
                    <span id="dot1" class="step-dot active">1</span>
                    <span id="dot2" class="step-dot">2</span>
                    <span id="dot3" class="step-dot">3</span>
                </div>
            </div>

            <form method="post" id="prefForm" class="p-3 p-md-4">
                <?= tachan_csrf_field() ?>
                <input type="hidden" name="teach_type" id="teach_type" value="<?= e($p['teach_type']) ?>">

                <!-- Step 1: Teaching Type -->
                <div class="wizard-step active" id="step1">
                    <h6 class="fw-bold text-dark mb-1">
                        <span class="badge bg-primary rounded-circle me-1">१</span> तुमची अध्यापन पद्धत निवडा:
                    </h6>
                    <p class="small text-muted mb-4">खालीलपैकी तुमच्या शाळेतील कामकाजाशी जुळणारा योग्य पर्याय निवडा:</p>

                    <div class="row g-3 mb-4">
                        <?php foreach ($types as $tv => $ti): ?>
                            <div class="col-12 col-md-4">
                                <div class="pref-type-card h-100 <?= $p['teach_type'] === $tv ? 'selected' : '' ?>" data-type="<?= $tv ?>" onclick="selectTeachType('<?= $tv ?>', this)">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <i class="bi <?= $ti['icon'] ?> fs-4 <?= $p['teach_type'] === $tv ? 'text-warning' : 'text-primary' ?>"></i>
                                        <strong class="text-dark fs-6"><?= e($ti['title']) ?></strong>
                                    </div>
                                    <p class="small text-muted mb-0"><?= e($ti['desc']) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="d-flex justify-content-end pt-3 border-top">
                        <button type="button" onclick="goToStep(2)" class="btn btn-primary px-4 fw-bold rounded-pill" style="background:#167A5B; border-color:#167A5B;">
                            पुढे जा <i class="bi bi-arrow-right ms-1"></i>
                        </button>
                    </div>
                </div>

                <!-- Step 2: Class, Jodvarg & Medium -->
                <div class="wizard-step" id="step2">
                    <h6 class="fw-bold text-dark mb-1">
                        <span class="badge bg-primary rounded-circle me-1">२</span> वर्ग व अध्यापनाचे माध्यम निवडा
                    </h6>
                    <p class="small text-muted mb-4">तुमचा वर्ग आणि माध्यम निवडा:</p>

                    <div class="row g-3 mb-4">
                        <!-- Primary Class -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold text-dark mb-1">मुख्य वर्ग / इयत्ता</label>
                            <select name="class_id" id="class_id" class="form-select rounded-3">
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= (int)$p['class_id'] === (int)$c['id'] ? 'selected' : '' ?>>
                                        <?= e($c['name']) ?> (<?= e(class_label(planner_class_std($c))) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Medium -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold text-dark mb-1">अध्यापनाचे माध्यम</label>
                            <select name="medium" class="form-select rounded-3">
                                <?php foreach ($mediums as $mv => $ml): ?>
                                    <option value="<?= $mv ?>" <?= $p['medium'] === $mv ? 'selected' : '' ?>><?= e($ml) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Jod Class (Visible if jod selected) -->
                        <div class="col-12" id="jodClassRow" style="<?= $p['teach_type'] === 'jod' ? '' : 'display:none;' ?>">
                            <div class="p-3 bg-light rounded-3 border">
                                <label class="form-label fw-bold text-dark mb-1">
                                    <i class="bi bi-people-fill text-primary me-1"></i> जोडवर्ग (दुसरी इयत्ता)
                                </label>
                                <select name="jod_class_id" class="form-select rounded-3">
                                    <option value="0">— दुसरी इयत्ता निवडा —</option>
                                    <?php foreach ($classes as $c): ?>
                                        <option value="<?= $c['id'] ?>" <?= (int)$p['jod_class_id'] === (int)$c['id'] ? 'selected' : '' ?>>
                                            <?= e($c['name']) ?> (<?= e(class_label(planner_class_std($c))) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="small text-muted mt-1">दैनिक टाचण पृष्ठावर दोन्ही वर्गांचे तासिका नियोजन एकत्र जोडलेले दिसेल.</div>
                            </div>
                        </div>

                        <!-- Period-wise Notice (Visible if periodwise selected) -->
                        <div class="col-12" id="periodwiseNotice" style="<?= $p['teach_type'] === 'periodwise' ? '' : 'display:none;' ?>">
                            <div class="p-3 bg-primary-subtle text-primary-emphasis rounded-3 border border-primary-subtle">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <i class="bi bi-calendar3-range-fill fs-4 text-primary"></i>
                                    <strong>तासवार अध्यापन पद्धत</strong>
                                </div>
                                <p class="small mb-2">
                                    तुम्ही वेगवेगळ्या वर्गांना तासवार शिकवत असल्यास (उदा. ६ वी ला गणित, ७ वी ला विज्ञान), तुम्ही <strong>"माझे वेळापत्रक"</strong> मध्ये तुमची संपूर्ण आठवड्याची तासिका रचना सहज तयार करू शकता.
                                </p>
                                <a href="<?= APP_URL ?>/modules/tachan/timetable.php?mode=teacher" class="btn btn-primary btn-sm rounded-pill fw-bold">
                                    <i class="bi bi-pencil-square me-1"></i> माझे वेळापत्रक आताच तयार करा →
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between pt-3 border-top">
                        <button type="button" onclick="goToStep(1)" class="btn btn-outline-secondary px-4 rounded-pill">
                            <i class="bi bi-arrow-left me-1"></i> मागे
                        </button>
                        <button type="button" onclick="goToStep(3)" class="btn btn-primary px-4 fw-bold rounded-pill" style="background:#167A5B; border-color:#167A5B;">
                            पुढे जा <i class="bi bi-arrow-right ms-1"></i>
                        </button>
                    </div>
                </div>

                <!-- Step 3: Day Length & Auto Templates -->
                <div class="wizard-step" id="step3">
                    <h6 class="fw-bold text-dark mb-1">
                        <span class="badge bg-primary rounded-circle me-1">३</span> तासिका व्याप्ती व सुविधा
                    </h6>
                    <p class="small text-muted mb-4">टाचणामध्ये दररोज किती तासिकांचा समावेश करायचा ते ठरवा:</p>

                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold text-dark mb-1">दिवस लांबी</label>
                            <select name="day_length" id="day_length" class="form-select rounded-3" onchange="toggleUptoRow()">
                                <?php foreach ($lengths as $lv => $ll): ?>
                                    <option value="<?= $lv ?>" <?= $p['day_length'] === $lv ? 'selected' : '' ?>><?= e($ll) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12 col-md-6" id="uptoPeriodRow" style="<?= $p['day_length'] === 'upto' ? '' : 'display:none;' ?>">
                            <label class="form-label fw-bold text-dark mb-1">या तासिका क्रमांकापर्यंत</label>
                            <select name="upto_period" class="form-select rounded-3">
                                <?php for ($i = 1; $i <= 8; $i++): ?>
                                    <option value="<?= $i ?>" <?= (int)$p['upto_period'] === $i ? 'selected' : '' ?>>तासिका <?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <div class="form-check p-3 bg-light rounded-3 border">
                                <input class="form-check-input ms-0 me-2" type="checkbox" name="auto_tt" value="1" id="auto_tt" checked>
                                <label class="form-check-label fw-bold text-dark" for="auto_tt">
                                    निवडलेल्या पद्धतीनुसार शासकीय तयार वेळापत्रक साचा आपोआप लागू करा
                                </label>
                                <div class="small text-muted mt-1 ps-4">
                                    जोडवर्ग किंवा तासवार पद्धतीसाठी शासकीय अधिकृत साचा आपोआप तयार होईल.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between pt-3 border-top">
                        <button type="button" onclick="goToStep(2)" class="btn btn-outline-secondary px-4 rounded-pill">
                            <i class="bi bi-arrow-left me-1"></i> मागे
                        </button>
                        <button type="submit" class="btn btn-success px-5 py-2 fw-bold rounded-pill shadow-sm">
                            <i class="bi bi-check-circle-fill me-2"></i> सेटिंग्ज जतन करा
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function selectTeachType(type, element) {
    document.getElementById('teach_type').value = type;
    document.querySelectorAll('.pref-type-card').forEach(function(el) {
        el.classList.remove('selected');
    });
    element.classList.add('selected');

    const jodRow = document.getElementById('jodClassRow');
    const periodwiseNotice = document.getElementById('periodwiseNotice');
    if (type === 'jod') {
        jodRow.style.display = 'block';
        periodwiseNotice.style.display = 'none';
    } else if (type === 'periodwise') {
        jodRow.style.display = 'none';
        periodwiseNotice.style.display = 'block';
    } else {
        jodRow.style.display = 'none';
        periodwiseNotice.style.display = 'none';
    }
}

function toggleUptoRow() {
    const dl = document.getElementById('day_length').value;
    const upto = document.getElementById('uptoPeriodRow');
    if (dl === 'upto') {
        upto.style.display = 'block';
    } else {
        upto.style.display = 'none';
    }
}

function goToStep(step) {
    document.querySelectorAll('.wizard-step').forEach(function(el) {
        el.classList.remove('active');
    });
    document.querySelectorAll('.step-dot').forEach(function(el) {
        el.classList.remove('active');
    });

    document.getElementById('step' + step).classList.add('active');
    for (let i = 1; i <= step; i++) {
        document.getElementById('dot' + i).classList.add('active');
    }
}
</script>

<?php tachan_layout_end(); ?>
