<?php
declare(strict_types=1);
require_once __DIR__ . '/../data/planner_data.php';

/** शासकीय सुट्ट्या आयात (एकदिवसीय + कालावधी सुट्ट्या) */
function planner_import_holidays(PDO $pdo, int $sid): int
{
    planner_holidays_ensure_columns($pdo);
    $have = $pdo->prepare('SELECT COUNT(*) FROM holidays WHERE school_id=? AND date=?');
    $ins = $pdo->prepare("INSERT INTO holidays(school_id,date,name,kind) VALUES(?,?,?,'govt')");
    $n = 0;
    $add = function (string $date, string $name) use ($pdo, $sid, $have, $ins, &$n): void {
        $have->execute([$sid, $date]);
        if ((int) $have->fetchColumn() > 0) return;
        $ins->execute([$sid, $date, $name]);
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            syncTenantRecordToMySQL('holidays', [
                'school_id' => $sid,
                'date' => $date,
                'name' => $name,
                'kind' => 'govt'
            ], ['school_id', 'date']);
        }
        $n++;
    };
    foreach (planner_holidays() as [$date, $name, $type]) {
        $add($date, $name);
    }
    foreach (planner_holiday_ranges() as [$from, $to, $name]) {
        $d = new DateTime($from);
        $end = new DateTime($to);
        while ($d <= $end) {
            $add($d->format('Y-m-d'), $name);
            $d->modify('+1 day');
        }
    }
    return $n;
}

/** शासकीय दिनविशेष / उपक्रम आयात (शैक्षणिक दिनदर्शिका २०२६-२७) */
function planner_import_upakram(PDO $pdo, int $sid, int $year = 2026): int
{
    $have = $pdo->prepare('SELECT COUNT(*) FROM calendar_events WHERE school_id=? AND date=? AND title=?');
    $ins = $pdo->prepare('INSERT INTO calendar_events(school_id,date,title,event_type,source) VALUES(?,?,?,?,?)');
    $n = 0;
    foreach (planner_upakram() as [$md, $title]) {
        // सुट्टी यादीशी सुसंगत ठेवण्यासाठी संपूर्ण कॅलेंडर वर्ष २०२६
        $date = sprintf('%04d-%s', $year, $md);
        $have->execute([$sid, $date, $title]);
        if ((int) $have->fetchColumn() > 0) continue;
        $ins->execute([$sid, $date, $title, 'दिनविशेष', 'शासकीय दिनदर्शिका']);
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            syncTenantRecordToMySQL('calendar_events', [
                'school_id' => $sid,
                'date' => $date,
                'title' => $title,
                'event_type' => 'दिनविशेष',
                'source' => 'शासकीय दिनदर्शिका'
            ]);
        }
        $n++;
    }
    return $n;
}

function planner_class_std(array $class): int
{
    if (isset($class['std']) && (int) $class['std'] > 0) return (int) $class['std'];
    if (preg_match('/\d+/', (string) ($class['name'] ?? ''), $m)) return (int) $m[0];
    return 1;
}

/** वार्षिक घटक नियोजन आयात — वार्षिक नियोजन (std x महिना x विषय x ४ आठवडे) */
function planner_import_annual(PDO $pdo, int $sid, int $teacherId, array $classes): int
{
    planner_annual_ensure_columns($pdo);
    $weeks = planner_annual_weeks();
    $fallback = planner_annual_topics();
    $have = $pdo->prepare('SELECT COUNT(*) FROM annual_plans WHERE school_id=? AND class_id=? AND subject=? AND month=?');
    $ins = $pdo->prepare('INSERT INTO annual_plans(school_id,teacher_id,class_id,subject,month,topics,week1,week2,week3,week4) VALUES(?,?,?,?,?,?,?,?,?,?)');
    // जुन्या नोंदी (आठवडी स्तंभ रिकामे) असल्यास फक्त आठवडे भरा — शिक्षकाने भरलेले बदलत नाही
    $fill = $pdo->prepare("UPDATE annual_plans SET week1=?,week2=?,week3=?,week4=? WHERE school_id=? AND class_id=? AND subject=? AND month=? AND COALESCE(week1,'')='' AND COALESCE(week2,'')='' AND COALESCE(week3,'')='' AND COALESCE(week4,'')=''");
    $n = 0;
    $isSqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    foreach ($classes as $c) {
        $std = planner_class_std($c);
        $data = $weeks[$std] ?? [];
        if ($data) {
            foreach ($data as $m => $subs) {
                foreach ($subs as $sub => $w) {
                    $w = array_pad(array_values($w), 4, '');
                    $topics = trim(implode(' | ', array_filter($w)));
                    if ($topics === '') $topics = (string) $sub;
                    $have->execute([$sid, (int) $c['id'], (string) $sub, (int) $m]);
                    if ((int) $have->fetchColumn() > 0) {
                        $fill->execute([$w[0], $w[1], $w[2], $w[3], $sid, (int) $c['id'], (string) $sub, (int) $m]);
                        continue;
                    }
                    $ins->execute([$sid, $teacherId, (int) $c['id'], (string) $sub, (int) $m, $topics, $w[0], $w[1], $w[2], $w[3]]);
                    if ($isSqlite) {
                        syncTenantRecordToMySQL('annual_plans', [
                            'school_id' => $sid,
                            'teacher_id' => $teacherId,
                            'class_id' => (int) $c['id'],
                            'subject' => (string) $sub,
                            'month' => (int) $m,
                            'topics' => $topics,
                            'week1' => $w[0],
                            'week2' => $w[1],
                            'week3' => $w[2],
                            'week4' => $w[3]
                        ], ['school_id', 'class_id', 'subject', 'month']);
                    }
                    $n++;
                }
            }
            continue;
        }
        // std ला वार्षिक डेटा नसल्यास सामान्य महिना-सारांश वापरा
        foreach (planner_subjects_for_std($std) as $sub) {
            foreach (planner_months() as $m => $_name) {
                $text = $fallback[$sub][$m] ?? '';
                if ($text === '') continue;
                $have->execute([$sid, (int) $c['id'], $sub, $m]);
                if ((int) $have->fetchColumn() > 0) continue;
                $ins->execute([$sid, $teacherId, (int) $c['id'], $sub, $m, $text, '', '', '', '']);
                if ($isSqlite) {
                    syncTenantRecordToMySQL('annual_plans', [
                        'school_id' => $sid,
                        'teacher_id' => $teacherId,
                        'class_id' => (int) $c['id'],
                        'subject' => (string) $sub,
                        'month' => (int) $m,
                        'topics' => $text,
                        'week1' => '',
                        'week2' => '',
                        'week3' => '',
                        'week4' => ''
                    ], ['school_id', 'class_id', 'subject', 'month']);
                }
                $n++;
            }
        }
    }
    return $n;
}

/** holidays मध्ये kind (govt/user) स्तंभ नसल्यास जोडा (idempotent) */
function planner_holidays_ensure_columns(PDO $pdo): void
{
    $cols = [];
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        foreach ($pdo->query("PRAGMA table_info('holidays')") as $row) {
            $cols[strtolower((string) $row['name'])] = true;
        }
    } else {
        foreach ($pdo->query('SHOW COLUMNS FROM holidays') as $row) {
            $cols[strtolower((string) $row['Field'])] = true;
        }
    }
    if (!isset($cols['kind'])) {
        $pdo->exec("ALTER TABLE holidays ADD COLUMN kind VARCHAR(12) NOT NULL DEFAULT 'govt'");
    }
}

/** annual_plans मध्ये आठवडी स्तंभ (week1..week4) नसल्यास जोडा (idempotent) */
function planner_annual_ensure_columns(PDO $pdo): void
{
    $cols = [];
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        foreach ($pdo->query("PRAGMA table_info('annual_plans')") as $row) {
            $cols[strtolower((string) $row['name'])] = true;
        }
    } else {
        foreach ($pdo->query('SHOW COLUMNS FROM annual_plans') as $row) {
            $cols[strtolower((string) $row['Field'])] = true;
        }
    }
    foreach (['week1', 'week2', 'week3', 'week4'] as $col) {
        if (!isset($cols[$col])) {
            $pdo->exec("ALTER TABLE annual_plans ADD COLUMN {$col} TEXT NULL");
        }
    }
}

/** वेळापत्रक साचा आयात */
function planner_import_timetable(PDO $pdo, int $sid, array $classes): int
{
    $isSqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $sql = $isSqlite ? 'INSERT OR REPLACE INTO timetable(school_id,class_id,day,period,subject) VALUES(?,?,?,?,?)' : 'REPLACE INTO timetable(school_id,class_id,day,period,subject) VALUES(?,?,?,?,?)';
    $ins = $pdo->prepare($sql);
    $clear = $pdo->prepare('DELETE FROM timetable WHERE school_id=? AND class_id=?');
    $n = 0;
    foreach ($classes as $c) {
        $clear->execute([$sid, (int) $c['id']]);
        if ($isSqlite) {
            syncTenantDeleteToMySQL('timetable', ['school_id' => $sid, 'class_id' => (int) $c['id']]);
        }
        foreach (planner_timetable_for_std(planner_class_std($c)) as $day => $periods) {
            foreach ($periods as $p => $sub) {
                $ins->execute([$sid, (int) $c['id'], $day, $p, $sub]);
                if ($isSqlite) {
                    syncTenantRecordToMySQL('timetable', [
                        'school_id' => $sid,
                        'class_id' => (int) $c['id'],
                        'day' => $day,
                        'period' => $p,
                        'subject' => $sub
                    ], ['school_id', 'class_id', 'day', 'period']);
                }
                $n++;
            }
        }
    }
    return $n;
}

/**
 * महिन्याचे तासिकानिहाय दैनिक टाचण आपोआप तयार करा.
 * वेळापत्रक (तासिका -> विषय) + टाचण बँक यांतून प्रत्येक कामकाजाच्या दिवसाच्या
 * प्रत्येक तासिकेचे संपूर्ण ५ स्तंभी टाचण तयार होते.
 */
function planner_import_tachan(PDO $pdo, int $sid, int $teacherId, array $classes, int $year, int $month): int
{
    require_once __DIR__ . '/tachan_engine.php';
    $n = 0;
    foreach ($classes as $c) {
        $n += tachan_generate_month($pdo, $sid, $teacherId, $c, $year, $month);
    }
    return $n;
}

/** उपलब्ध शिक्षक व जोडवर्ग वेळापत्रक साचे यादी */
function planner_teacher_timetable_templates(): array
{
    return [
        '1' => ['name' => 'इयत्ता १ ली', 'type' => 'single', 'stds' => [1]],
        '2' => ['name' => 'इयत्ता २ री', 'type' => 'single', 'stds' => [2]],
        '3' => ['name' => 'इयत्ता ३ री', 'type' => 'single', 'stds' => [3]],
        '4' => ['name' => 'इयत्ता ४ थी', 'type' => 'single', 'stds' => [4]],
        '5' => ['name' => 'इयत्ता ५ वी', 'type' => 'single', 'stds' => [5]],
        '6' => ['name' => 'इयत्ता ६ वी', 'type' => 'single', 'stds' => [6]],
        '7' => ['name' => 'इयत्ता ७ वी', 'type' => 'single', 'stds' => [7]],
        '8' => ['name' => 'इयत्ता ८ वी', 'type' => 'single', 'stds' => [8]],
        'jod_1_2' => ['name' => 'जोडवर्ग १+२ (पहिली व दुसरी)', 'type' => 'jod', 'stds' => [1, 2]],
        'jod_1_3' => ['name' => 'जोडवर्ग १+३ (पहिली व तिसरी)', 'type' => 'jod', 'stds' => [1, 3]],
        'jod_1_4' => ['name' => 'जोडवर्ग १+४ (पहिली व चौथी)', 'type' => 'jod', 'stds' => [1, 4]],
        'jod_2_3' => ['name' => 'जोडवर्ग २+३ (दुसरी व तिसरी)', 'type' => 'jod', 'stds' => [2, 3]],
        'jod_2_4' => ['name' => 'जोडवर्ग २+४ (दुसरी व चौथी)', 'type' => 'jod', 'stds' => [2, 4]],
        'jod_3_4' => ['name' => 'जोडवर्ग ३+४ (तिसरी व चौथी)', 'type' => 'jod', 'stds' => [3, 4]],
        'jod_3_5' => ['name' => 'जोडवर्ग ३+५ (तिसरी व पाचवी)', 'type' => 'jod', 'stds' => [3, 5]],
        'jod_4_5' => ['name' => 'जोडवर्ग ४+५ (चौथी व पाचवी)', 'type' => 'jod', 'stds' => [4, 5]],
        'jod_5_6' => ['name' => 'जोडवर्ग ५+६ (पाचवी व सहावी)', 'type' => 'jod', 'stds' => [5, 6]],
        'jod_6_7' => ['name' => 'जोडवर्ग ६+७ (सहावी व सातवी)', 'type' => 'jod', 'stds' => [6, 7]],
        'jod_6_8' => ['name' => 'जोडवर्ग ६+८ (सहावी व आठवी)', 'type' => 'jod', 'stds' => [6, 8]],
        'jod_7_8' => ['name' => 'जोडवर्ग ७+८ (सातवी व आठवी)', 'type' => 'jod', 'stds' => [7, 8]],
    ];
}

/** निवडलेल्या साच्यानुसार संपूर्ण आठवड्याचे तासिका ग्रीड तयार करा */
function planner_get_teacher_template_grid(string $tplKey, string $medium = 'marathi'): array
{
    $tpls = planner_teacher_timetable_templates();
    $info = $tpls[$tplKey] ?? $tpls['6'] ?? null;
    $grid = [];
    $isSemi = tachan_norm_medium($medium) === 'semi_english';

    $labels = [1 => 'इ. १ ली', 2 => 'इ. २ री', 3 => 'इ. ३ री', 4 => 'इ. ४ थी', 5 => 'इ. ५ वी', 6 => 'इ. ६ वी', 7 => 'इ. ७ वी', 8 => 'इ. ८ वी'];

    if (!$info || $info['type'] === 'single') {
        $std = $info ? (int)$info['stds'][0] : 6;
        $stdLabel = $labels[$std] ?? "इ. {$std} वी";
        $tt = planner_timetable_for_std($std);
        foreach ($tt as $day => $ps) {
            foreach ($ps as $p => $sub) {
                if ($isSemi) {
                    if (mb_strpos($sub, 'गणित') !== false) $sub = 'Maths';
                    elseif (mb_strpos($sub, 'विज्ञान') !== false) $sub = 'Gen Science';
                }
                $grid[$day][$p] = [
                    'std' => (string)$std,
                    'class_name' => $stdLabel,
                    'div' => '-',
                    'subject' => $sub,
                ];
            }
        }
        return $grid;
    }

    // जोडवर्ग (Jodvarg: २ वर्ग एकत्र)
    $stdA = (int)$info['stds'][0];
    $stdB = (int)$info['stds'][1];
    $labelA = $labels[$stdA] ?? "इ. {$stdA} वी";
    $labelB = $labels[$stdB] ?? "इ. {$stdB} वी";
    $jodLabel = "जोडवर्ग {$stdA}+{$stdB}";

    $coreA = planner_subjects_for_std($stdA);
    $coreB = planner_subjects_for_std($stdB);

    for ($d = 1; $d <= 6; $d++) {
        if ($d <= 5) {
            $grid[$d][1] = ['std' => (string)$stdA, 'class_name' => $labelA, 'div' => '-', 'subject' => ($isSemi && $coreA[($d-1)%count($coreA)]==='गणित') ? 'Maths' : $coreA[($d-1)%count($coreA)]];
            $grid[$d][2] = ['std' => (string)$stdB, 'class_name' => $labelB, 'div' => '-', 'subject' => ($isSemi && $coreB[($d-1)%count($coreB)]==='गणित') ? 'Maths' : $coreB[($d-1)%count($coreB)]];
            $grid[$d][3] = ['std' => (string)$stdA, 'class_name' => $labelA, 'div' => '-', 'subject' => ($isSemi && $coreA[$d%count($coreA)]==='गणित') ? 'Maths' : $coreA[$d%count($coreA)]];
            $grid[$d][4] = ['std' => (string)$stdB, 'class_name' => $labelB, 'div' => '-', 'subject' => ($isSemi && $coreB[$d%count($coreB)]==='गणित') ? 'Maths' : $coreB[$d%count($coreB)]];
            $grid[$d][5] = ['std' => (string)$stdA, 'class_name' => $labelA, 'div' => '-', 'subject' => ($isSemi && $coreA[($d+1)%count($coreA)]==='सामान्य विज्ञान') ? 'Gen Science' : $coreA[($d+1)%count($coreA)]];
            $grid[$d][6] = ['std' => (string)$stdB, 'class_name' => $labelB, 'div' => '-', 'subject' => ($isSemi && $coreB[($d+1)%count($coreB)]==='सामान्य विज्ञान') ? 'Gen Science' : $coreB[($d+1)%count($coreB)]];
            $grid[$d][7] = ['std' => (string)$stdA, 'class_name' => $labelA, 'div' => '-', 'subject' => $coreA[($d+2)%count($coreA)]];
            $grid[$d][8] = ['std' => "{$stdA},{$stdB}", 'class_name' => $jodLabel, 'div' => '-', 'subject' => ($d % 2 === 0 ? 'शारीरिक शिक्षण' : 'कला शिक्षण')];
        } else {
            $grid[$d][1] = ['std' => (string)$stdA, 'class_name' => $labelA, 'div' => '-', 'subject' => 'इंग्रजी'];
            $grid[$d][2] = ['std' => (string)$stdB, 'class_name' => $labelB, 'div' => '-', 'subject' => 'इंग्रजी'];
            $grid[$d][3] = ['std' => (string)$stdA, 'class_name' => $labelA, 'div' => '-', 'subject' => $stdA >= 5 ? 'हिंदी' : 'मराठी'];
            $grid[$d][4] = ['std' => (string)$stdB, 'class_name' => $labelB, 'div' => '-', 'subject' => $stdB >= 5 ? 'हिंदी' : 'मराठी'];
            $grid[$d][5] = ['std' => "{$stdA},{$stdB}", 'class_name' => $jodLabel, 'div' => '-', 'subject' => 'कार्यशिक्षण'];
            $grid[$d][6] = ['std' => "{$stdA},{$stdB}", 'class_name' => $jodLabel, 'div' => '-', 'subject' => 'आनंददायी शनिवार उपक्रम'];
        }
    }

    return $grid;
}

