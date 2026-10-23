<?php
declare(strict_types=1);
/**
 * टाचण इंजिन — तासिकानिहाय दैनिक टाचण.
 *
 * वेळापत्रक (तासिका -> विषय) + टाचण कंटेंट बँक (अधिकृत घटक + ५ स्तंभी मजकूर)
 * यांतून प्रत्येक कामकाजाच्या दिवसासाठी प्रत्येक तासिकेचे संपूर्ण टाचण आपोआप तयार करते.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/planner_seed.php';
require_once __DIR__ . '/../data/planner_data.php';

/** बँक आवृत्ती — बँक बदलली की वाढवा; जुन्या आवृत्तीच्या bank नोंदी आपोआप पुन्हा तयार होतात. */
const TACHAN_BANK_VERSION = 2026004;

/** lesson_plans मध्ये bank_ver स्तंभ आहे का (जुन्या इन्स्टॉलसाठी सुरक्षित). */
function tachan_has_bank_ver(PDO $pdo): bool
{
    static $has = null;
    if ($has !== null) return $has;
    try {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $cols = $pdo->query("PRAGMA table_info(lesson_plans)")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cols as $c) {
                if (($c['name'] ?? '') === 'bank_ver') return $has = true;
            }
            return $has = false;
        }
        $has = (bool) $pdo->query("SHOW COLUMNS FROM lesson_plans LIKE 'bank_ver'")->fetch();
    } catch (Throwable $e) {
        $has = false;
    }
    return $has;
}

/** माध्यम सामान्यीकरण : कोणतेही रूप -> 'semi_english' किंवा 'marathi'. */
function tachan_norm_medium(?string $medium): string
{
    $m = mb_strtolower(trim((string) $medium));
    if ($m === '') return 'marathi';
    if (mb_strpos($m, 'सेमी') !== false || strpos($m, 'semi') !== false) return 'semi_english';
    return 'marathi';
}

/** पोर्टलचा विषय -> बँकेतील विषयनाव (इयत्ता + माध्यमानुसार). */
function tachan_bank_subject(int $std, string $subject, string $medium = 'marathi'): string
{
    $s = trim($subject);
    $has = static fn(string $needle): bool => mb_strpos($s, $needle) !== false;
    $semi = tachan_norm_medium($medium) === 'semi_english' || stripos($s, 'semi') !== false || $has('सेमी') || $has('इंग्रजी माध्यम');

    if ($has('इंग्रजी') || $has('इंग्लिश') || stripos($s, 'english') !== false) return 'इंग्लिश';
    if ($has('गणित')) return $semi ? 'Semi-Maths' : 'गणित';
    if ($has('हिंदी')) return 'हिंदी';
    if ($has('मराठी') || $has('वाचन') || $has('ग्रंथालय')) return 'मराठी';
    if (mb_strpos($s, 'खेळू,करू,शिकू') === 0) return $s;
    if ($has('कार्यानुभव') || $has('कार्यशिक्षण') || $has('व्यावसायिक') || $has('करू')) {
        return 'खेळू,करू,शिकू -कार्यानुभव';
    }
    if ($has('कला') || $has('चित्रकला') || $has('संगीत') || $has('शिकू')) return 'खेळू,करू,शिकू -कला';
    if ($has('शारीरिक') || $has('शारिरीक') || $has('शाशि') || $has('क्रीडा') || $has('खेळ')) return 'खेळू,करू,शिकू -शारिरीक शिक्षण';
    if ($has('संगणक')) return 'संगणक';
    if ($has('विज्ञान')) return $semi ? 'Semi-Science' : 'सामान्य विज्ञान';
    if ($has('इतिहास')) return 'इतिहास';
    if ($has('भूगोल')) return 'भूगोल';
    if ($has('समाज') || $has('सामाजिक') || $has('नागरिक')) return $std >= 7 ? 'समाजशास्त्र' : 'इतिहास';
    if ($has('परिसर')) return 'परिसर अभ्यास';
    return $s;
}

/** बँकेतील (इयत्ता,विषय) च्या क्रमवार नोंदी — कॅशसह. */
function tachan_bank_list(?PDO $pdo, int $std, string $bankSubject): array
{
    static $cache = [];
    $key = $std . '|' . $bankSubject;
    if (isset($cache[$key])) return $cache[$key];
    try {
        $mysql = getMainDB();
        if ($mysql) {
            $q = $mysql->prepare('SELECT topic,tl_activity,eval_tool,materials,learning_outcome,homework,topic_status
                                FROM tachan_bank WHERE std=? AND subject=? ORDER BY seq');
            $q->execute([$std, $bankSubject]);
            $res = $q->fetchAll(PDO::FETCH_ASSOC);
            if ($res) return $cache[$key] = $res;
        }
    } catch (Throwable $e) {}
    if ($pdo) {
        try {
            $q = $pdo->prepare('SELECT topic,tl_activity,eval_tool,materials,learning_outcome,homework,topic_status
                                FROM tachan_bank WHERE std=? AND subject=? ORDER BY seq');
            $q->execute([$std, $bankSubject]);
            $res = $q->fetchAll(PDO::FETCH_ASSOC);
            if ($res) return $cache[$key] = $res;
        } catch (Throwable $e) {}
    }
    return $cache[$key] = [];
}

/** नसलेल्या विषयासाठी साचा-आधारित पर्यायी नोंद. */
function tachan_fallback_row(string $subject, int $std, int $month): array
{
    $tpl = planner_tachan_templates();
    $topics = planner_annual_topics();
    $cSub = function_exists('planner_canonical_subject') ? planner_canonical_subject($subject) : $subject;
    $t = $tpl[$subject] ?? $tpl[$cSub] ?? ['objectives' => '', 'activities' => 'चर्चा, कृती व सराव.', 'homework' => 'सरावासाठी दिलेले काम पूर्ण करा.'];
    $topic = $topics[$subject][$month] ?? $topics[$cSub][$month] ?? ($topics[$subject][6] ?? ($topics[$cSub][6] ?? $subject));
    return [
        'topic' => mb_substr((string) $topic, 0, 200),
        'tl_activity' => $t['activities'] ?? '',
        'eval_tool' => 'निरीक्षण, प्रश्नोत्तरे',
        'materials' => 'पाठ्यपुस्तक, फळा, तक्ते',
        'learning_outcome' => $t['objectives'] ?? '',
        'homework' => $t['homework'] ?? '',
        'topic_status' => 'template_fallback',
    ];
}

/** शाळेच्या वर्गाचे वेळापत्रक (दिवस -> तासिका -> विषय). DB मध्ये नसल्यास साचा. */
function tachan_timetable(PDO $pdo, int $sid, array $class): array
{
    $q = $pdo->prepare('SELECT day,period,subject FROM timetable WHERE school_id=? AND class_id=?');
    $q->execute([$sid, (int) $class['id']]);
    $grid = [];
    foreach ($q as $r) {
        $sub = trim((string) $r['subject']);
        if ($sub !== '') $grid[(int) $r['day']][(int) $r['period']] = $sub;
    }
    if (!$grid) $grid = planner_timetable_for_std(planner_class_std($class));
    return $grid;
}

/** शाळेच्या सुट्ट्या (Y-m-d => true). */
function tachan_holiday_set(PDO $pdo, int $sid): array
{
    $q = $pdo->prepare('SELECT date FROM holidays WHERE school_id=?');
    $q->execute([$sid]);
    $h = [];
    foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $d) $h[substr((string) $d, 0, 10)] = true;
    return $h;
}

/**
 * एका वर्गासाठी एका महिन्याचे तासिकानिहाय दैनिक टाचण तयार करा.
 * प्रत्येक विषयाचे घटक बँकेतील क्रमाने पुढे सरकतात (मागील दिनांकांनुसार सातत्य).
 * @return int नव्याने तयार झालेल्या नोंदी.
 */
function tachan_generate_month(PDO $pdo, int $sid, int $teacherId, array $class, int $year, int $month, ?string $medium = null): int
{
    $std = planner_class_std($class);
    $cid = (int) $class['id'];
    $medium = tachan_norm_medium($medium ?? ($class['medium'] ?? 'marathi'));
    $grid = tachan_timetable($pdo, $sid, $class);
    $hol = tachan_holiday_set($pdo, $sid);

    // प्रत्येक विषयासाठी बँकेतील क्रमांक कुठवर आला — मागील दिनांकांच्या नोंदींवरून.
    $firstDay = sprintf('%04d-%02d-01', $year, $month);
    $cur = $pdo->prepare('SELECT subject,COUNT(*) c FROM lesson_plans
                          WHERE school_id=? AND class_id=? AND date<? AND src=\'bank\' GROUP BY subject');
    $cur->execute([$sid, $cid, $firstDay]);
    $cursor = [];
    foreach ($cur as $r) $cursor[(string) $r['subject']] = (int) $r['c'];

    $have = $pdo->prepare('SELECT COUNT(*) FROM lesson_plans WHERE school_id=? AND class_id=? AND date=? AND period=?');
    $withVer = tachan_has_bank_ver($pdo);
    $ins = $pdo->prepare('INSERT INTO lesson_plans
        (school_id,teacher_id,date,period,class_id,subject,topic,objectives,activities,eval_tool,materials,learning_outcome,homework,notes,src,medium,topic_status' . ($withVer ? ',bank_ver' : '') . ')
        VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?' . ($withVer ? ',?' : '') . ')');

    $days = (int) date('t', (int) mktime(0, 0, 0, $month, 1, $year));
    $n = 0;
    for ($d = 1; $d <= $days; $d++) {
        $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
        $dow = (int) date('N', strtotime($date));
        if ($dow === 7 || isset($hol[$date])) continue;      // रविवार + सुट्ट्या वगळा
        $periods = $grid[$dow] ?? [];
        if (!$periods) continue;
        ksort($periods);
        foreach ($periods as $p => $subject) {
            $subject = trim((string) $subject);
            if ($subject === '') continue;
            $bankSub = tachan_bank_subject($std, $subject, $medium);
            $list = tachan_bank_list($pdo, $std, $bankSub);
            if ($list) {
                $idx = ($cursor[$subject] ?? 0);
                $row = $list[$idx % count($list)];
            } else {
                $row = tachan_fallback_row($subject, $std, $month);
            }
            $cursor[$subject] = ($cursor[$subject] ?? 0) + 1;

            $have->execute([$sid, $cid, $date, (int) $p]);
            if ((int) $have->fetchColumn() > 0) continue;
            $vals = [
                $sid, $teacherId, $date, (int) $p, $cid, $subject,
                mb_substr((string) $row['topic'], 0, 200),
                (string) ($row['learning_outcome'] ?? ''),   // objectives = अध्ययन निष्पत्ती संक्षिप्त
                (string) ($row['tl_activity'] ?? ''),          // activities = अध्ययन-अध्यापन कृती
                (string) ($row['eval_tool'] ?? ''),            // मूल्यमापन तंत्र
                (string) ($row['materials'] ?? ''),            // शैक्षणिक साहित्य
                (string) ($row['learning_outcome'] ?? ''),     // अध्ययन निष्पत्ती
                (string) ($row['homework'] ?? ''),             // गृहपाठ
                'नियोजनानुसार', 'bank', $medium, (string) ($row['topic_status'] ?? ''),
            ];
            if ($withVer) $vals[] = TACHAN_BANK_VERSION;
            $ins->execute($vals);
            $n++;

            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                $recData = [
                    'school_id' => $sid,
                    'teacher_id' => $teacherId,
                    'date' => $date,
                    'period' => (int) $p,
                    'class_id' => $cid,
                    'subject' => $subject,
                    'topic' => mb_substr((string) $row['topic'], 0, 200),
                    'objectives' => (string) ($row['learning_outcome'] ?? ''),
                    'activities' => (string) ($row['tl_activity'] ?? ''),
                    'eval_tool' => (string) ($row['eval_tool'] ?? ''),
                    'materials' => (string) ($row['materials'] ?? ''),
                    'learning_outcome' => (string) ($row['learning_outcome'] ?? ''),
                    'homework' => (string) ($row['homework'] ?? ''),
                    'notes' => 'नियोजनानुसार',
                    'src' => 'bank',
                    'medium' => $medium,
                    'topic_status' => (string) ($row['topic_status'] ?? ''),
                ];
                if ($withVer) $recData['bank_ver'] = TACHAN_BANK_VERSION;
                syncTenantRecordToMySQL('lesson_plans', $recData, ['school_id', 'class_id', 'date', 'period']);
            }
        }
    }
    return $n;
}

/** एका वर्गासाठी एका दिवसाच्या तासिकानिहाय नोंदी (नसल्यास तो महिना तयार करून). */
function tachan_day_rows(PDO $pdo, int $sid, int $teacherId, array $class, string $date, bool $autogen = true, ?string $medium = null): array
{
    $cid = (int) $class['id'];
    $want = $medium !== null ? tachan_norm_medium($medium) : null;
    $q = $pdo->prepare('SELECT * FROM lesson_plans WHERE school_id=? AND class_id=? AND date=? ORDER BY period');
    $q->execute([$sid, $cid, $date]);
    $rows = $q->fetchAll();
    // माध्यम बदलले असल्यास (त्या दिवसाच्या bank नोंदी वेगळ्या माध्यमाच्या) — तो महिना पुन्हा तयार करा.
    $mismatch = false;
    if ($want !== null && $rows) {
        foreach ($rows as $r) {
            if (($r['src'] ?? '') === 'bank' && tachan_norm_medium((string) ($r['medium'] ?? 'marathi')) !== $want) { $mismatch = true; break; }
        }
    }
    // बँक आवृत्ती बदलली असल्यास (जुन्या अभ्यासक्रमाच्या bank नोंदी) — तो महिना पुन्हा तयार करा.
    if ($autogen && $rows && !$mismatch && tachan_has_bank_ver($pdo)) {
        foreach ($rows as $r) {
            if (($r['src'] ?? '') === 'bank' && (int) ($r['bank_ver'] ?? 0) !== TACHAN_BANK_VERSION) { $mismatch = true; break; }
        }
    }
    // वेळापत्रक बदलले असल्यास (bank नोंदींचा विषय/तासिका आजच्या वेळापत्रकाशी जुळत नाही) — पुन्हा तयार करा.
    if ($autogen && $rows && !$mismatch) {
        $dow = (int) date('N', strtotime($date));
        if ($dow !== 7) {
            $tt = tachan_timetable($pdo, $sid, $class)[$dow] ?? [];
            $byP = [];
            foreach ($rows as $r) $byP[(int) $r['period']] = $r;
            foreach ($tt as $p => $sub) {
                $sub = trim((string) $sub);
                if ($sub === '') continue;
                $r = $byP[(int) $p] ?? null;
                if ($r === null || (($r['src'] ?? '') === 'bank' && trim((string) $r['subject']) !== $sub)) { $mismatch = true; break; }
            }
            if (!$mismatch) {
                foreach ($byP as $p => $r) {
                    if (($r['src'] ?? '') === 'bank' && trim((string) ($tt[$p] ?? '')) === '') { $mismatch = true; break; }
                }
            }
        }
    }
    if ($autogen && (!$rows || $mismatch)) {
        $t = strtotime($date);
        if ($mismatch) {
            tachan_regenerate_month($pdo, $sid, $teacherId, $class, (int) date('Y', $t), (int) date('n', $t), $want);
        } else {
            tachan_generate_month($pdo, $sid, $teacherId, $class, (int) date('Y', $t), (int) date('n', $t), $want);
        }
        $q->execute([$sid, $cid, $date]);
        $rows = $q->fetchAll();
    }
    return $rows;
}

/** निवडलेल्या माध्यमाने एका महिन्याचे bank टाचण पुन्हा तयार करा (जुन्या bank नोंदी वगळून). */
function tachan_regenerate_month(PDO $pdo, int $sid, int $teacherId, array $class, int $year, int $month, ?string $medium = null): int
{
    $cid = (int) $class['id'];
    $mPad = sprintf('%02d', $month);
    $yPad = sprintf('%04d', $year);
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $del = $pdo->prepare("DELETE FROM lesson_plans WHERE school_id=? AND class_id=? AND strftime('%m', date)=? AND strftime('%Y', date)=? AND src='bank'");
        $del->execute([$sid, $cid, $mPad, $yPad]);
        try {
            $mysql = getMainDB();
            $delM = $mysql->prepare("DELETE FROM lesson_plans WHERE school_id=? AND class_id=? AND MONTH(date)=? AND YEAR(date)=? AND src='bank'");
            $delM->execute([$sid, $cid, $month, $year]);
        } catch (Throwable $e) {}
    } else {
        $del = $pdo->prepare("DELETE FROM lesson_plans WHERE school_id=? AND class_id=? AND MONTH(date)=? AND YEAR(date)=? AND src='bank'");
        $del->execute([$sid, $cid, $month, $year]);
    }
    return tachan_generate_month($pdo, $sid, $teacherId, $class, $year, $month, $medium);
}

/**
 * वर्गाचे सर्व bank टाचण नव्या बँकेतून पुन्हा तयार करा (शैक्षणिक वर्षाच्या जूनपासून पुढील महिन्यापर्यंत, क्रमाने —
 * म्हणजे घटकांचे सातत्य टिकते). शिक्षकाने स्वतः लिहिलेल्या (src<>'bank') नोंदी तशाच राहतात.
 * @return int नव्याने तयार झालेल्या नोंदी.
 */
function tachan_refresh_class(PDO $pdo, int $sid, int $teacherId, array $class, ?string $medium = null): int
{
    $cid = (int) $class['id'];
    $del = $pdo->prepare("DELETE FROM lesson_plans WHERE school_id=? AND class_id=? AND src='bank'");
    $del->execute([$sid, $cid]);
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        try {
            $mysql = getMainDB();
            $delM = $mysql->prepare("DELETE FROM lesson_plans WHERE school_id=? AND class_id=? AND src='bank'");
            $delM->execute([$sid, $cid]);
        } catch (Throwable $e) {}
    }
    $y = (int) date('Y'); $m = (int) date('n');
    $startY = $m >= 6 ? $y : $y - 1;                 // शैक्षणिक वर्ष जूनपासून
    $endTs = mktime(0, 0, 0, $m + 1, 1, $y);         // पुढील महिन्यापर्यंत
    $n = 0;
    for ($ts = mktime(0, 0, 0, 6, 1, $startY); $ts <= $endTs; $ts = mktime(0, 0, 0, (int) date('n', $ts) + 1, 1, (int) date('Y', $ts))) {
        $n += tachan_generate_month($pdo, $sid, $teacherId, $class, (int) date('Y', $ts), (int) date('n', $ts), $medium);
    }
    return $n;
}

/** शिक्षकाच्या टाचण सेटिंग्ज (नसल्यास सुरक्षित डीफॉल्ट). */
function tachan_prefs_get(PDO $pdo, int $sid, int $teacherId): array
{
    $def = ['teach_type' => 'single', 'class_id' => null, 'jod_class_id' => null,
            'medium' => 'marathi', 'day_length' => 'full', 'upto_period' => 8];
    try {
        $q = $pdo->prepare('SELECT teach_type,class_id,jod_class_id,medium,day_length,upto_period FROM tachan_prefs WHERE school_id=? AND teacher_id=?');
        $q->execute([$sid, $teacherId]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $row = false;
    }
    if (!$row) return $def;
    $rawUpto = (int) ($row['upto_period'] ?? 8);
    $upto = $rawUpto >= 4 ? min(9, $rawUpto) : 8;
    return [
        'teach_type' => (string) ($row['teach_type'] ?: 'single'),
        'class_id' => $row['class_id'] !== null ? (int) $row['class_id'] : null,
        'jod_class_id' => $row['jod_class_id'] !== null ? (int) $row['jod_class_id'] : null,
        'medium' => tachan_norm_medium((string) $row['medium']),
        'day_length' => (string) ($row['day_length'] ?: 'full'),
        'upto_period' => $upto,
    ];
}

/** शिक्षकाच्या टाचण सेटिंग्ज जतन करा (upsert). */
function tachan_prefs_save(PDO $pdo, int $sid, int $teacherId, array $p): void
{
    $type = in_array($p['teach_type'] ?? 'single', ['single', 'jod', 'periodwise'], true) ? $p['teach_type'] : 'single';
    $len = in_array($p['day_length'] ?? 'full', ['full', 'half', 'upto'], true) ? $p['day_length'] : 'full';
    $rawUpto = (int) ($p['upto_period'] ?? 8);
    $upto = $rawUpto >= 4 ? min(9, $rawUpto) : 8;
    $cid = !empty($p['class_id']) ? (int) $p['class_id'] : null;
    $jid = !empty($p['jod_class_id']) ? (int) $p['jod_class_id'] : null;
    $med = tachan_norm_medium((string) ($p['medium'] ?? 'marathi'));

    $data = [
        'school_id' => $sid,
        'teacher_id' => $teacherId,
        'teach_type' => $type,
        'class_id' => $cid,
        'jod_class_id' => $jid,
        'medium' => $med,
        'day_length' => $len,
        'upto_period' => $upto,
    ];

    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $st = $pdo->prepare('INSERT OR REPLACE INTO tachan_prefs(school_id,teacher_id,teach_type,class_id,jod_class_id,medium,day_length,upto_period)
            VALUES(?,?,?,?,?,?,?,?)');
        $st->execute([$sid, $teacherId, $type, $cid, $jid, $med, $len, $upto]);
        syncTenantRecordToMySQL('tachan_prefs', $data, ['school_id', 'teacher_id']);
    } else {
        $st = $pdo->prepare('INSERT INTO tachan_prefs(school_id,teacher_id,teach_type,class_id,jod_class_id,medium,day_length,upto_period)
            VALUES(?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE teach_type=VALUES(teach_type),class_id=VALUES(class_id),jod_class_id=VALUES(jod_class_id),
            medium=VALUES(medium),day_length=VALUES(day_length),upto_period=VALUES(upto_period)');
        $st->execute([$sid, $teacherId, $type, $cid, $jid, $med, $len, $upto]);
    }
}

/** दिवसाच्या लांबीनुसार शेवटची तासिका क्रमांक (full=9, half=4, upto=दिलेली). */
function tachan_last_period(string $dayLength, int $uptoPeriod): int
{
    if ($dayLength === 'half') return 4;
    if ($dayLength === 'upto') return max(4, min(9, $uptoPeriod));
    return 9;
}

/** दिवसाच्या लांबीनुसार तासिका-ओळी छाटा. */
function tachan_apply_day_length(array $rows, string $dayLength, int $uptoPeriod): array
{
    $last = tachan_last_period($dayLength, $uptoPeriod);
    if ($last >= 9) return $rows;
    return array_values(array_filter($rows, static fn(array $r): bool => (int) $r['period'] <= $last));
}

/** शिक्षक वेळापत्रक व पाठ टाचण अतिरिक्त टेबल्स/कॉलम अस्तित्त्वात असल्याची खात्री करा */
function tachan_ensure_teacher_timetable_tables(PDO $pdo): void
{
    static $done = false;
    if ($done) return;

    $isSqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    if ($isSqlite) {
        $pdo->exec('CREATE TABLE IF NOT EXISTS teacher_timetable (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            school_id INTEGER NOT NULL,
            teacher_id INTEGER NOT NULL,
            day INTEGER NOT NULL,
            period INTEGER NOT NULL,
            class_id INTEGER DEFAULT 0,
            class_name TEXT DEFAULT "",
            std TEXT DEFAULT "",
            div TEXT DEFAULT "",
            subject TEXT NOT NULL,
            medium TEXT DEFAULT "marathi",
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(school_id, teacher_id, day, period)
        )');

        $cols = [];
        foreach ($pdo->query("PRAGMA table_info(lesson_plans)") as $r) {
            $cols[strtolower((string)$r['name'])] = true;
        }
        if (!isset($cols['class_name'])) {
            @$pdo->exec('ALTER TABLE lesson_plans ADD COLUMN class_name TEXT NULL');
        }
        if (!isset($cols['suvichar'])) {
            @$pdo->exec('ALTER TABLE lesson_plans ADD COLUMN suvichar TEXT NULL');
        }
        if (!isset($cols['upakram'])) {
            @$pdo->exec('ALTER TABLE lesson_plans ADD COLUMN upakram TEXT NULL');
        }
    }

    try {
        $mysql = getMainDB();
        $mysql->exec("CREATE TABLE IF NOT EXISTS `teacher_timetable` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `school_id` INT NOT NULL,
            `teacher_id` INT NOT NULL,
            `day` INT NOT NULL,
            `period` INT NOT NULL,
            `class_id` INT DEFAULT 0,
            `class_name` VARCHAR(100) DEFAULT '',
            `std` VARCHAR(50) DEFAULT '',
            `div` VARCHAR(10) DEFAULT '',
            `subject` VARCHAR(255) NOT NULL,
            `medium` VARCHAR(50) DEFAULT 'marathi',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uniq_teacher_slot` (`school_id`, `teacher_id`, `day`, `period`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $mcols = [];
        foreach ($mysql->query("SHOW COLUMNS FROM lesson_plans") as $r) {
            $mcols[strtolower((string)$r['Field'])] = true;
        }
        if (!isset($mcols['class_name'])) {
            @$mysql->exec("ALTER TABLE lesson_plans ADD COLUMN class_name VARCHAR(100) NULL");
        }
        if (!isset($mcols['suvichar'])) {
            @$mysql->exec("ALTER TABLE lesson_plans ADD COLUMN suvichar TEXT NULL");
        }
        if (!isset($mcols['upakram'])) {
            @$mysql->exec("ALTER TABLE lesson_plans ADD COLUMN upakram TEXT NULL");
        }
    } catch (Throwable $e) {}

    $done = true;
}

/** शिक्षकाचे तासिका वेळापत्रक मिळवा */
function tachan_teacher_timetable_get(PDO $pdo, int $sid, int $teacherId): array
{
    tachan_ensure_teacher_timetable_tables($pdo);
    $q = $pdo->prepare('SELECT `day`, `period`, `class_id`, `class_name`, `std`, `div`, `subject`, `medium` 
                        FROM `teacher_timetable` 
                        WHERE `school_id`=? AND `teacher_id`=? ORDER BY `day`, `period`');
    $q->execute([$sid, $teacherId]);
    $grid = [];
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $d = (int)$r['day'];
        $p = (int)$r['period'];
        $grid[$d][$p] = [
            'class_id' => (int)($r['class_id'] ?? 0),
            'class_name' => trim((string)($r['class_name'] ?? '')),
            'std' => trim((string)($r['std'] ?? '')),
            'div' => trim((string)($r['div'] ?? '-')),
            'subject' => trim((string)($r['subject'] ?? '')),
            'medium' => trim((string)($r['medium'] ?? 'marathi')),
        ];
    }
    return $grid;
}

/** शिक्षकाचे तासिका वेळापत्रक जतन करा */
function tachan_teacher_timetable_save(PDO $pdo, int $sid, int $teacherId, array $cells, string $medium = 'marathi'): int
{
    tachan_ensure_teacher_timetable_tables($pdo);
    $del = $pdo->prepare('DELETE FROM `teacher_timetable` WHERE `school_id`=? AND `teacher_id`=? AND `day`=? AND `period`=?');
    $isSqlite = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite');
    $insSql = $isSqlite 
        ? 'INSERT OR REPLACE INTO `teacher_timetable`(`school_id`, `teacher_id`, `day`, `period`, `class_id`, `class_name`, `std`, `div`, `subject`, `medium`) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        : 'REPLACE INTO `teacher_timetable`(`school_id`, `teacher_id`, `day`, `period`, `class_id`, `class_name`, `std`, `div`, `subject`, `medium`) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
    $ins = $pdo->prepare($insSql);
    $n = 0;
    foreach ($cells as $d => $ps) {
        $d = (int)$d;
        if ($d < 1 || $d > 6 || !is_array($ps)) continue;
        foreach ($ps as $p => $slot) {
            $p = (int)$p;
            if ($p < 1 || $p > 9) continue;
            $sub = trim((string)($slot['subject'] ?? ''));
            if ($sub === '') {
                $del->execute([$sid, $teacherId, $d, $p]);
                syncTenantDeleteToMySQL('teacher_timetable', ['school_id' => $sid, 'teacher_id' => $teacherId, 'day' => $d, 'period' => $p]);
            } else {
                $std = trim((string)($slot['std'] ?? ''));
                $className = trim((string)($slot['class_name'] ?? ''));
                $div = trim((string)($slot['div'] ?? '-'));
                $cid = (int)($slot['class_id'] ?? 0);
                $ins->execute([$sid, $teacherId, $d, $p, $cid, $className, $std, $div, $sub, $medium]);
                syncTenantRecordToMySQL('teacher_timetable', [
                    'school_id' => $sid,
                    'teacher_id' => $teacherId,
                    'day' => $d,
                    'period' => $p,
                    'class_id' => $cid,
                    'class_name' => $className,
                    'std' => $std,
                    'div' => $div,
                    'subject' => $sub,
                    'medium' => $medium,
                ], ['school_id', 'teacher_id', 'day', 'period']);
                $n++;
            }
        }
    }
    return $n;
}

/** आजचा सुविचार — परिपाठ डेटाबेसवरून आपोआप मिळवा */
function tachan_fetch_suvichar(string $date): array
{
    try {
        require_once __DIR__ . '/paripath_db.php';
        $db = getParipathDB();

        // 1. थेट दिनांकाची अचूक नोंद
        $q = $db->prepare('SELECT text_mr, author FROM suvichar WHERE display_date = ? LIMIT 1');
        $q->execute([$date]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty(trim((string)$row['text_mr']))) {
            return [
                'text' => trim((string)$row['text_mr']),
                'author' => trim((string)($row['author'] ?? ''))
            ];
        }

        // 2. महिना-दिनांक जुळवणी (%-MM-DD)
        $md = substr($date, 5);
        $q = $db->prepare('SELECT text_mr, author FROM suvichar WHERE display_date LIKE ? ORDER BY display_date DESC LIMIT 1');
        $q->execute(['%' . $md]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty(trim((string)$row['text_mr']))) {
            return [
                'text' => trim((string)$row['text_mr']),
                'author' => trim((string)($row['author'] ?? ''))
            ];
        }

        // 3. परिपाठातील ५१६ सुविचारांमधून चक्राकार सुविचार
        $total = (int) $db->query('SELECT COUNT(*) FROM suvichar')->fetchColumn();
        if ($total > 0) {
            $offset = (int) date('z', strtotime($date)) % $total;
            $q = $db->query("SELECT text_mr, author FROM suvichar ORDER BY id LIMIT 1 OFFSET $offset");
            $row = $q->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty(trim((string)$row['text_mr']))) {
                return [
                    'text' => trim((string)$row['text_mr']),
                    'author' => trim((string)($row['author'] ?? ''))
                ];
            }
        }
    } catch (Throwable $e) {}

    return [
        'text' => 'संकटाशी लढणे हाच जगण्याचा मार्ग आहे.',
        'author' => 'डॉ. बाबासाहेब आंबेडकर'
    ];
}

/** आजचा उपक्रम व दिनविशेष — शैक्षणिक दिनदर्शिका व परिपाठ वरून */
function tachan_fetch_upakram(PDO $pdo, int $sid, string $date): array
{
    $items = [];
    // १. शाळा दिनदर्शिका नोंदी (calendar_events)
    try {
        $q = $pdo->prepare('SELECT title FROM calendar_events WHERE school_id=? AND date=? ORDER BY id');
        $q->execute([$sid, $date]);
        foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $t) {
            $t = trim((string)$t);
            if ($t !== '' && !in_array($t, $items, true)) $items[] = $t;
        }
    } catch (Throwable $e) {}

    // २. शासकीय शैक्षणिक दिनदर्शिका (planner_upakram)
    $md = substr($date, 5);
    foreach (planner_upakram() as [$uMd, $uTitle]) {
        if ($uMd === $md && !in_array($uTitle, $items, true)) {
            $items[] = $uTitle;
        }
    }

    // ३. परिपाठ दिनविशेष
    try {
        require_once __DIR__ . '/paripath_db.php';
        $pdb = getParipathDB();
        $pq = $pdb->prepare('SELECT title_mr FROM dinvishesh WHERE event_date=? OR (month=? AND day=?) ORDER BY is_featured DESC LIMIT 3');
        $m = (int)date('n', strtotime($date));
        $d = (int)date('j', strtotime($date));
        $pq->execute([$date, $m, $d]);
        foreach ($pq->fetchAll(PDO::FETCH_COLUMN) as $dt) {
            $dt = trim((string)$dt);
            if ($dt !== '' && !in_array($dt, $items, true)) {
                $items[] = $dt;
            }
        }
    } catch (Throwable $e) {}

    return $items;
}

/** शिक्षकाच्या स्वतःच्या वेळापत्रकानुसार दिवसाचे संपूर्ण तासिकानिहाय पाठ टाचण तयार करा / मिळवा */
function tachan_teacher_day_rows(PDO $pdo, int $sid, int $teacherId, string $date, bool $autogen = true, ?string $medium = null): array
{
    tachan_ensure_teacher_timetable_tables($pdo);
    $dow = (int) date('N', strtotime($date));
    if ($dow === 7) return []; // रविवार सुट्टी

    $tt = tachan_teacher_timetable_get($pdo, $sid, $teacherId);
    $todaySlots = $tt[$dow] ?? [];

    // १. शिक्षकाच्या स्वतःच्या वेळापत्रकानुसार आधीच्या नोंदी शोधा
    $q = $pdo->prepare('SELECT * FROM lesson_plans 
                        WHERE school_id=? AND teacher_id=? AND date=? 
                          AND (src IN ("teacher_tt", "manual") OR (class_name IS NOT NULL AND class_name != ""))
                        ORDER BY period');
    $q->execute([$sid, $teacherId, $date]);
    $rows = $q->fetchAll(PDO::FETCH_ASSOC);

    // जर वेळापत्रक भरलेले नसेल, तर साध्या वर्ग नोंदी परत करा
    if (!$todaySlots) {
        if ($rows) return $rows;
        $fallbackQ = $pdo->prepare('SELECT * FROM lesson_plans WHERE school_id=? AND teacher_id=? AND date=? ORDER BY period');
        $fallbackQ->execute([$sid, $teacherId, $date]);
        $fb = $fallbackQ->fetchAll(PDO::FETCH_ASSOC);
        if ($fb) return $fb;
        // जर कोणतीही नोंद नसेल, तर शिक्षकाच्या पसंतीचा/पहिल्या वर्गाचे टाचण तयार करून परत करा
        $classes = school_classes($sid);
        if ($classes) {
            $pref = tachan_prefs_get($pdo, $sid, $teacherId);
            $byId = [];
            foreach ($classes as $c) $byId[(int) $c['id']] = $c;
            $selClass = isset($byId[$pref['class_id']]) ? $byId[$pref['class_id']] : $classes[0];
            return tachan_day_rows($pdo, $sid, $teacherId, $selClass, $date, $autogen, $medium);
        }
        return [];
    }

    $existingByPeriod = [];
    foreach ($rows as $r) {
        $existingByPeriod[(int)$r['period']] = $r;
    }

    // सर्व तासिका उपलब्ध आहेत का ते तपासा
    $needGen = false;
    foreach ($todaySlots as $p => $slot) {
        $sub = trim((string)($slot['subject'] ?? ''));
        if ($sub === '') continue;
        if (!isset($existingByPeriod[$p])) {
            $needGen = true;
            break;
        }
    }

    if (!$needGen || !$autogen) {
        // आधीच बरोबर भरलेले आहे — १ तासिका १ ओळ परत करा
        return array_values($existingByPeriod);
    }

    $withVer = tachan_has_bank_ver($pdo);
    $ins = $pdo->prepare('INSERT INTO lesson_plans
        (school_id, teacher_id, date, period, class_id, class_name, subject, topic, objectives, activities, eval_tool, materials, learning_outcome, homework, notes, src, medium, topic_status' . ($withVer ? ',bank_ver' : '') . ')
        VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?' . ($withVer ? ',?' : '') . ')');

    $month = (int) date('n', strtotime($date));
    $stdLabels = [1 => 'इ. १ ली', 2 => 'इ. २ री', 3 => 'इ. ३ री', 4 => 'इ. ४ थी', 5 => 'इ. ५ वी', 6 => 'इ. ६ वी', 7 => 'इ. ७ वी', 8 => 'इ. ८ वी'];

    $curStmt = $pdo->prepare('SELECT subject, COUNT(*) c FROM lesson_plans WHERE school_id=? AND teacher_id=? AND date<? AND src IN ("bank","teacher_tt") GROUP BY subject');
    $curStmt->execute([$sid, $teacherId, $date]);
    $cursor = [];
    foreach ($curStmt->fetchAll(PDO::FETCH_ASSOC) as $cr) {
        $cursor[$cr['subject']] = (int)$cr['c'];
    }

    foreach ($todaySlots as $p => $slot) {
        $p = (int)$p;
        $sub = trim((string)($slot['subject'] ?? ''));
        if ($sub === '') continue;

        if (isset($existingByPeriod[$p])) {
            // शिक्षकाने स्वतः लिहिलेली (src='manual') नोंद तशीच ठेवा
            if (($existingByPeriod[$p]['src'] ?? '') === 'manual') {
                continue;
            }
            $pdo->prepare('DELETE FROM lesson_plans WHERE id=?')->execute([(int)$existingByPeriod[$p]['id']]);
        }

        $stdRaw = trim((string)($slot['std'] ?? ''));
        $std = 6;
        if (preg_match('/(\d+)/', $stdRaw, $sm)) {
            $std = (int)$sm[1];
        }
        $clsName = trim((string)($slot['class_name'] ?? ''));
        if ($clsName === '') {
            $clsName = $stdLabels[$std] ?? "इ. {$std} वी";
        }
        $slotMed = !empty($slot['medium']) ? tachan_norm_medium($slot['medium']) : ($medium ? tachan_norm_medium($medium) : 'marathi');

        $bankSub = tachan_bank_subject($std, $sub, $slotMed);
        $list = tachan_bank_list($pdo, $std, $bankSub);

        if ($list) {
            $idx = $cursor[$sub] ?? 0;
            $row = $list[$idx % count($list)];
        } else {
            $row = tachan_fallback_row($sub, $std, $month);
        }
        $cursor[$sub] = ($cursor[$sub] ?? 0) + 1;

        $cid = (int)($slot['class_id'] ?? 0);
        $vals = [
            $sid, $teacherId, $date, $p, $cid, $clsName, $sub,
            mb_substr((string)$row['topic'], 0, 200),
            (string)($row['learning_outcome'] ?? ''),
            (string)($row['tl_activity'] ?? ''),
            (string)($row['eval_tool'] ?? 'निरीक्षण, प्रश्नोत्तरे'),
            (string)($row['materials'] ?? 'पाठ्यपुस्तक, फळा'),
            (string)($row['learning_outcome'] ?? ''),
            (string)($row['homework'] ?? ''),
            'नियोजनानुसार', 'teacher_tt', $slotMed, (string)($row['topic_status'] ?? '')
        ];
        if ($withVer) $vals[] = TACHAN_BANK_VERSION;
        $ins->execute($vals);
        $newId = (int)$pdo->lastInsertId();

        $recData = [
            'id' => $newId,
            'school_id' => $sid,
            'teacher_id' => $teacherId,
            'date' => $date,
            'period' => $p,
            'class_id' => $cid,
            'class_name' => $clsName,
            'subject' => $sub,
            'topic' => mb_substr((string)$row['topic'], 0, 200),
            'objectives' => (string)($row['learning_outcome'] ?? ''),
            'activities' => (string)($row['tl_activity'] ?? ''),
            'eval_tool' => (string)($row['eval_tool'] ?? ''),
            'materials' => (string)($row['materials'] ?? ''),
            'learning_outcome' => (string)($row['learning_outcome'] ?? ''),
            'homework' => (string)($row['homework'] ?? ''),
            'notes' => 'नियोजनानुसार',
            'src' => 'teacher_tt',
            'medium' => $slotMed,
            'topic_status' => (string)($row['topic_status'] ?? ''),
        ];
        if ($withVer) $recData['bank_ver'] = TACHAN_BANK_VERSION;
        syncTenantRecordToMySQL('lesson_plans', $recData, ['id']);
    }

    $q->execute([$sid, $teacherId, $date]);
    return $q->fetchAll(PDO::FETCH_ASSOC);
}

