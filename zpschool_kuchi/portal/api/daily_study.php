<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/common/response.php';
require_once __DIR__ . '/common/auth_guard.php';

header('Content-Type: application/json; charset=UTF-8');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$db = null;
try {
    $db = getDB();
} catch (Throwable $dbEx) {
    // MySQL may be offline in dev/CLI; actions needing DB will check $db
}

try {
    switch ($action) {
        case 'tachan_topics':
            $std = (int)($_GET['std'] ?? 0);
            $subject = trim($_GET['subject'] ?? '');
            if (!$std || !$subject) {
                echo json_encode(['success' => false, 'error' => 'इयत्ता आणि विषय आवश्यक आहे.']);
                exit;
            }
            // Fetch topics from tachan_bank
            $st = $db->prepare("SELECT id, topic, learning_outcome, homework, tl_activity FROM tachan_bank WHERE std = ? AND subject LIKE ? ORDER BY id ASC LIMIT 50");
            $st->execute([$std, "%$subject%"]);
            $topics = $st->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'topics' => $topics]);
            exit;

        case 'publish':
            $std = (int)($_POST['std'] ?? 0);
            $section = trim($_POST['section'] ?? 'A');
            $study_date = trim($_POST['study_date'] ?? date('Y-m-d'));
            $subject = trim($_POST['subject'] ?? '');
            $topic = trim($_POST['topic'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $instructions = trim($_POST['instructions'] ?? '');
            $tachan_id = !empty($_POST['tachan_id']) ? (int)$_POST['tachan_id'] : null;
            $youtube_url = trim($_POST['youtube_url'] ?? '');

            if (!$std || !$subject || !$topic || !$content) {
                echo json_encode(['success' => false, 'error' => 'कृपया इयत्ता, विषय, घटक आणि स्वाध्याय भरा.']);
                exit;
            }

            $teacher_id = $_SESSION['teacher_id'] ?? $_SESSION['user_id'] ?? null;
            $teacher_name = $_SESSION['name_mr'] ?? $_SESSION['name'] ?? 'शिक्षक';

            $ins = $db->prepare("
                INSERT INTO daily_study (school_id, std, section, study_date, subject, topic, tachan_id, content, instructions, youtube_url, teacher_id, teacher_name, is_published)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            $ins->execute([SCHOOL_ID, $std, $section, $study_date, $subject, $topic, $tachan_id, $content, $instructions, $youtube_url, $teacher_id, $teacher_name]);
            $newId = (int)$db->lastInsertId();

            echo json_encode(['success' => true, 'id' => $newId, 'message' => 'दैनिक अभ्यास यशस्वीरीत्या प्रकाशित केला!']);
            exit;

        case 'complete':
            $study_id = (int)($_POST['study_id'] ?? 0);
            $student_id = (int)($_POST['student_id'] ?? ($_SESSION['student_id'] ?? 0));
            if (!$study_id || !$student_id) {
                echo json_encode(['success' => false, 'error' => 'अवैध विनंती.']);
                exit;
            }

            $st = $db->prepare("INSERT INTO student_study_submissions (study_id, student_id, status) VALUES (?, ?, 'completed') ON DUPLICATE KEY UPDATE status='completed', completed_at=NOW()");
            $st->execute([$study_id, $student_id]);
            echo json_encode(['success' => true, 'message' => 'अभ्यास पूर्ण झाला म्हणून नोंद झाली! अभिनंदन! 🎉']);
            exit;

        case 'list_today':
            $std = (int)($_GET['std'] ?? 0);
            $date = trim($_GET['date'] ?? date('Y-m-d'));
            $student_id = (int)($_GET['student_id'] ?? ($_SESSION['student_id'] ?? 0));

            $st = $db->prepare("
                SELECT ds.*, 
                       (CASE WHEN sub.id IS NOT NULL THEN 1 ELSE 0 END) as is_completed,
                       sub.completed_at
                FROM daily_study ds
                LEFT JOIN student_study_submissions sub ON ds.id = sub.study_id AND sub.student_id = ?
                WHERE ds.school_id = ? AND ds.std = ? AND ds.study_date = ? AND ds.is_published = 1
                ORDER BY ds.id DESC
            ");
            $st->execute([$student_id, SCHOOL_ID, $std, $date]);
            $list = $st->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'data' => $list]);
            exit;

        case 'save_personal_plan':
            $student_id = (int)($_SESSION['student_id'] ?? $_POST['student_id'] ?? 0);
            $plan_date = trim($_POST['plan_date'] ?? date('Y-m-d'));
            $subject = trim($_POST['subject'] ?? 'सामान्य');
            $goal_text = trim($_POST['goal_text'] ?? '');
            $target_minutes = (int)($_POST['target_minutes'] ?? 30);

            if (!$student_id || empty($goal_text)) {
                echo json_encode(['success' => false, 'error' => 'अभ्यास ध्येय आवश्यक आहे.']);
                exit;
            }

            $st = $db->prepare("
                INSERT INTO student_study_plans (school_id, student_id, plan_date, subject, goal_text, target_minutes, is_completed)
                VALUES (?, ?, ?, ?, ?, ?, 0)
            ");
            $st->execute([SCHOOL_ID, $student_id, $plan_date, $subject, $goal_text, $target_minutes]);
            $newId = (int)$db->lastInsertId();

            echo json_encode([
                'success' => true,
                'id' => $newId,
                'message' => 'तुमचे वैयक्तिक अभ्यास नियोजन जतन झाले! 🎯'
            ]);
            exit;

        case 'toggle_personal_plan':
            $plan_id = (int)($_POST['plan_id'] ?? 0);
            $student_id = (int)($_SESSION['student_id'] ?? $_POST['student_id'] ?? 0);

            if (!$plan_id || !$student_id) {
                echo json_encode(['success' => false, 'error' => 'अवैध विनंती.']);
                exit;
            }

            $st = $db->prepare("
                UPDATE student_study_plans 
                SET is_completed = IF(is_completed=1, 0, 1) 
                WHERE id = ? AND student_id = ? AND school_id = ?
            ");
            $st->execute([$plan_id, $student_id, SCHOOL_ID]);

            $check = $db->prepare("SELECT is_completed FROM student_study_plans WHERE id = ?");
            $check->execute([$plan_id]);
            $isDone = (int)$check->fetchColumn();

            echo json_encode([
                'success' => true,
                'is_completed' => $isDone,
                'message' => $isDone ? 'अभ्यास ध्येय पूर्ण झाले! अभिनंदन! 🌟' : 'ध्येय अपूर्ण म्हणून चिन्हांकित केले.'
            ]);
            exit;

        case 'get_personal_plans':
            $student_id = (int)($_SESSION['student_id'] ?? $_GET['student_id'] ?? 0);
            $plan_date = trim($_GET['plan_date'] ?? date('Y-m-d'));

            $st = $db->prepare("
                SELECT * FROM student_study_plans 
                WHERE school_id = ? AND student_id = ? AND plan_date = ? 
                ORDER BY id ASC
            ");
            $st->execute([SCHOOL_ID, $student_id, $plan_date]);
            $plans = $st->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'plans' => $plans]);
            exit;

        case 'get_class_timetable':
            $std = (int)($_GET['std'] ?? ($_SESSION['std'] ?? 6));
            if ($std < 1 || $std > 8) $std = 6;

            // Load tachan planner data helper if present
            $plannerDataFile = __DIR__ . '/../modules/data/planner_data.php';
            if (file_exists($plannerDataFile)) {
                require_once $plannerDataFile;
            }

            $db = getDB();
            
            // 1. Find class_id for this standard
            $classStmt = $db->prepare("SELECT id, name FROM classes WHERE school_id = ? AND (std = ? OR name LIKE ?) LIMIT 1");
            $classStmt->execute([SCHOOL_ID, $std, "%{$std}%"]);
            $classRow = $classStmt->fetch(PDO::FETCH_ASSOC);
            $classId = (int)($classRow['id'] ?? 0);

            $grid = [];
            
            // 2. Try loading from timetable table (class custom timetable saved in tachan)
            if ($classId > 0) {
                try {
                    $ttStmt = $db->prepare("SELECT day, period, subject FROM timetable WHERE school_id = ? AND class_id = ? ORDER BY day, period");
                    $ttStmt->execute([SCHOOL_ID, $classId]);
                    $rows = $ttStmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($rows as $r) {
                        $grid[(int)$r['day']][(int)$r['period']] = trim($r['subject']);
                    }
                } catch (Throwable $e) {}
            }

            // 3. Try loading from teacher_timetable if class timetable is empty
            if (empty($grid)) {
                try {
                    $tWhere = ["school_id = ?", "(std = ? OR std LIKE ? OR class_id = ?)"];
                    $tParams = [SCHOOL_ID, (string)$std, "%{$std}%", $classId];
                    $ttStmt = $db->prepare("SELECT day, period, subject FROM teacher_timetable WHERE " . implode(' AND ', $tWhere) . " ORDER BY day, period");
                    $ttStmt->execute($tParams);
                    $rows = $ttStmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($rows as $r) {
                        $grid[(int)$r['day']][(int)$r['period']] = trim($r['subject']);
                    }
                } catch (Throwable $e) {}
            }

            // 4. Fallback to official Maharashtra ZP planner template from tachan module
            if (empty($grid) && function_exists('planner_timetable_for_std')) {
                $grid = planner_timetable_for_std($std);
            }

            // Marathi day names and period structures
            $dayNames = [
                1 => 'सोमवार',
                2 => 'मंगळवार',
                3 => 'बुधवार',
                4 => 'गुरुवार',
                5 => 'शुक्रवार',
                6 => 'शनिवार'
            ];

            $stdLabels = [
                1 => 'इयत्ता १ ली',
                2 => 'इयत्ता २ री',
                3 => 'इयत्ता ३ री',
                4 => 'इयत्ता ४ थी',
                5 => 'इयत्ता ५ वी',
                6 => 'इयत्ता ६ वी',
                7 => 'इयत्ता ७ वी',
                8 => 'इयत्ता ८ वी'
            ];

            $formattedTable = [];
            foreach ($dayNames as $dNum => $dName) {
                $row = [
                    'day_num'  => $dNum,
                    'day'      => $dName,
                    'paripath' => ($dNum === 6 ? 'परिपाठ (०७:०० - ०७:३०)' : 'परिपाठ (१०:३० - १०:५५)'),
                    'p1'       => $grid[$dNum][1] ?? '-',
                    'p2'       => $grid[$dNum][2] ?? '-',
                    'p3'       => $grid[$dNum][3] ?? '-',
                    'p4'       => $grid[$dNum][4] ?? '-',
                    'p5'       => $grid[$dNum][5] ?? '-',
                    'p6'       => $grid[$dNum][6] ?? '-',
                    'p7'       => ($dNum === 6 ? '-' : ($grid[$dNum][7] ?? '-')),
                    'p8'       => ($dNum === 6 ? '-' : ($grid[$dNum][8] ?? '-')),
                ];
                $formattedTable[] = $row;
            }

            echo json_encode([
                'success' => true,
                'std' => $std,
                'std_label' => $stdLabels[$std] ?? "इयत्ता {$std} वी",
                'source' => (!empty($classId) && !empty($grid) ? 'tachan_module' : 'curriculum_template'),
                'data' => $formattedTable
            ]);
            exit;

        case 'get_paripath_today':
            $marathiDays = ['Sunday' => 'रविवार', 'Monday' => 'सोमवार', 'Tuesday' => 'मंगळवार', 'Wednesday' => 'बुधवार', 'Thursday' => 'गुरुवार', 'Friday' => 'शुक्रवार', 'Saturday' => 'शनिवार'];
            $todayEnDay = date('l');
            $todayMrDay = $marathiDays[$todayEnDay] ?? 'सोमवार';

            // High-quality thought and importance of the day
            $thoughts = [
                "ज्ञानाने मनुष्य नम्र होतो आणि नम्रतेने तो आदरणीय होतो.",
                "कष्ट आणि प्रामाणिकपणा हीच यशाची खरी गुरुकिल्ली आहे.",
                "निसर्गाचे रक्षण करा, निसर्ग आपले रक्षण करेल.",
                "वाचाल तर वाचाल — वाचनाने जीवनाची दिशा बदलते.",
                "वेळेचे नियोजन म्हणजेच भविष्यातील यशाची खात्री."
            ];
            $tIdx = (int)date('z') % count($thoughts);

            $pData = [
                'date' => date('d-m-Y'),
                'day' => $todayMrDay,
                'suvichar' => $thoughts[$tIdx],
                'dinvishesh' => 'महाराष्ट्रातील थोर विचारवंत व शिक्षणतज्ज्ञ यांच्या शैक्षणिक विचारांचे स्मरण.',
                'subhashit' => 'विद्या ददाति विनयं विनयाद्याति पात्रताम्।'
            ];

            echo json_encode([
                'success' => true,
                'data' => $pData,
                'date' => $pData['date'],
                'day' => $pData['day'],
                'suvichar' => $pData['suvichar'],
                'dinvishesh' => $pData['dinvishesh'],
            ]);
            exit;

        case 'get_slider_feed':
            $reqDate = trim($_GET['date'] ?? date('Y-m-d'));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $reqDate)) {
                $reqDate = date('Y-m-d');
            }

            // Load Paripath and Planner libraries
            if (file_exists(__DIR__ . '/../modules/lib/paripath_engine.php')) {
                require_once __DIR__ . '/../modules/lib/paripath_engine.php';
            }
            if (file_exists(__DIR__ . '/../modules/data/planner_data.php')) {
                require_once __DIR__ . '/../modules/data/planner_data.php';
            }

            $marathiDate = function_exists('paripath_marathi_date') ? paripath_marathi_date($reqDate) : $reqDate;
            $marathiDay = function_exists('paripath_marathi_day') ? paripath_marathi_day($reqDate) : date('l');

            // 1. Paripath data
            $assembly = function_exists('paripath_get_full_assembly') ? paripath_get_full_assembly($reqDate) : [];
            $suvichar = $assembly['suvichar'] ?? [];
            $subhashit = $assembly['subhashit'] ?? [];
            $panchang = $assembly['panchang'] ?? [];
            $dinvisheshList = $assembly['dinvishesh'] ?? [];

            // 2. Academic Calendar (शैक्षणिक दिनदर्शिका)
            $curMonth = (int)date('n', strtotime($reqDate));
            $allMonths = function_exists('planner_months') ? planner_months() : [];
            $monthName = $allMonths[$curMonth] ?? 'चालू महिना';
            $calActivities = function_exists('planner_calendar') ? (planner_calendar()[$curMonth] ?? []) : [];

            // Upakram matching today or upcoming in this month
            $todayMD = date('m-d', strtotime($reqDate));
            $upakramToday = [];
            $upakramMonth = [];
            if (function_exists('planner_upakram')) {
                foreach (planner_upakram() as [$md, $title]) {
                    if ($md === $todayMD) {
                        $upakramToday[] = $title;
                    }
                    if (substr($md, 0, 2) === sprintf('%02d', $curMonth)) {
                        $upakramMonth[] = ['date' => $md, 'title' => $title];
                    }
                }
            }

            // 3. Holidays (सुट्ट्या)
            $upcomingHolidays = [];
            $todayTs = strtotime($reqDate);
            if (function_exists('planner_holidays')) {
                foreach (planner_holidays() as [$hDate, $hName, $hKind]) {
                    $hTs = strtotime($hDate);
                    $diffDays = (int)round(($hTs - $todayTs) / 86400);
                    if ($diffDays >= 0 && $diffDays <= 45) {
                        $upcomingHolidays[] = [
                            'date' => $hDate,
                            'name' => $hName,
                            'days_left' => $diffDays,
                            'is_today' => ($diffDays === 0)
                        ];
                    }
                }
            }
            if (function_exists('planner_holiday_ranges')) {
                foreach (planner_holiday_ranges() as [$from, $to, $rName]) {
                    $fromTs = strtotime($from);
                    $toTs = strtotime($to);
                    if ($todayTs >= $fromTs && $todayTs <= $toTs) {
                        $upcomingHolidays[] = [
                            'date' => "$from ते $to",
                            'name' => "$rName (सुरू आहे)",
                            'days_left' => 0,
                            'is_today' => true
                        ];
                    } elseif ($fromTs > $todayTs && ($fromTs - $todayTs) <= 45 * 86400) {
                        $diffDays = (int)round(($fromTs - $todayTs) / 86400);
                        $upcomingHolidays[] = [
                            'date' => "$from ते $to",
                            'name' => $rName,
                            'days_left' => $diffDays,
                            'is_today' => false
                        ];
                    }
                }
            }
            // Sort upcoming holidays by days_left
            usort($upcomingHolidays, fn($a, $b) => $a['days_left'] <=> $b['days_left']);

            // Build slides collection
            $slides = [];

            // Slide 1: दैनिक परिपाठ व सुविचार
            $suvicharText = !empty($suvichar['text_mr']) ? $suvichar['text_mr'] : 'ज्ञानाने मनुष्य नम्र होतो आणि नम्रतेने तो आदरणीय होतो.';
            $suvicharMeaning = !empty($suvichar['meaning_mr']) ? $suvichar['meaning_mr'] : ($suvichar['author'] ?? 'सुविचार');
            $subhashitText = !empty($subhashit['text_mr']) ? $subhashit['text_mr'] : (!empty($subhashit['text_sa']) ? $subhashit['text_sa'] : 'विद्या ददाति विनयं विनयाद्याति पात्रताम्।');

            $slides[] = [
                'type' => 'paripath',
                'badge' => 'दैनिक परिपाठ',
                'badge_color' => 'warning',
                'gradient' => 'linear-gradient(135deg, #1e3c72 0%, #2a5298 100%)',
                'icon' => 'bi-sun-fill text-warning',
                'title' => "आजचा परिपाठ — $marathiDate ($marathiDay)",
                'content_heading' => '💡 आजचा सुविचार',
                'main_text' => $suvicharText,
                'sub_text' => $suvicharMeaning ? "अर्थ/विचार: " . $suvicharMeaning : '',
                'extra_label' => '🌸 सुभाषित',
                'extra_text' => $subhashitText
            ];

            // Slide 2: आजचे दिनविशेष
            $dvItems = [];
            if (!empty($dinvisheshList)) {
                $count = 0;
                foreach ($dinvisheshList as $dv) {
                    if ($count++ >= 3) break;
                    $yr = !empty($dv['event_year']) ? (function_exists('paripath_marathi_digits') ? paripath_marathi_digits($dv['event_year']) : $dv['event_year']) : '';
                    $title = $dv['title_mr'] ?? $dv['title_en'] ?? '';
                    $info = $dv['short_info_mr'] ?? $dv['description_mr'] ?? '';
                    $dvItems[] = ($yr ? "($yr) " : "") . "<strong>" . htmlspecialchars($title) . "</strong>: " . htmlspecialchars($info);
                }
            }
            if (empty($dvItems)) {
                $dvItems[] = "महाराष्ट्रातील थोर विचारवंत, समाजसुधारक व शिक्षणतज्ज्ञ यांच्या पवित्र स्मृतीस विनम्र अभिवादन.";
            }

            $slides[] = [
                'type' => 'dinvishesh',
                'badge' => 'आजचे दिनविशेष',
                'badge_color' => 'danger',
                'gradient' => 'linear-gradient(135deg, #8E0E00 0%, #1F1C18 100%)',
                'icon' => 'bi-star-fill text-warning',
                'title' => "दिनविशेष गौरवगाथा — $marathiDate",
                'content_heading' => '📜 ऐतिहासिक घटना व स्मृती',
                'items' => $dvItems
            ];

            // Slide 3: शैक्षणिक दिनदर्शिका व मासिक नियोजन
            $calItems = [];
            if (!empty($upakramToday)) {
                foreach ($upakramToday as $ut) {
                    $calItems[] = "⭐ <strong>आजचा विशेष दिन:</strong> " . htmlspecialchars($ut);
                }
            }
            if (!empty($calActivities)) {
                foreach ($calActivities as $ca) {
                    $calItems[] = "📌 <strong>शालेय उपक्रम:</strong> " . htmlspecialchars($ca);
                }
            }
            $slides[] = [
                'type' => 'academic_calendar',
                'badge' => 'शैक्षणिक दिनदर्शिका',
                'badge_color' => 'primary',
                'gradient' => 'linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%)',
                'icon' => 'bi-calendar3-event-fill text-info',
                'title' => "शैक्षणिक दिनदर्शिका — $monthName २०२६",
                'content_heading' => '🎯 चालू महिन्यातील प्रमुख शालेय उपक्रम',
                'items' => $calItems
            ];

            // Slide 4: आगामी शासकीय व शाळा सुट्ट्या
            $holItems = [];
            if (!empty($upcomingHolidays)) {
                $hCount = 0;
                foreach ($upcomingHolidays as $h) {
                    if ($hCount++ >= 4) break;
                    $leftStr = $h['is_today'] ? "<span class='badge bg-danger'>आज सुट्टी</span>" : "<span class='badge bg-success'>" . ($h['days_left'] == 1 ? 'उद्या' : $h['days_left'] . ' दिवसांनी') . "</span>";
                    $holItems[] = "🚩 <strong>" . htmlspecialchars($h['name']) . "</strong> (" . htmlspecialchars($h['date']) . ") — " . $leftStr;
                }
            } else {
                $holItems[] = "चालू महिन्यात आगामी सार्वजनिक सुट्टी नाही (नियमित शालेय कामकाज चालू राहील).";
            }
            $slides[] = [
                'type' => 'holidays',
                'badge' => 'शाळा सुट्ट्या कॅलेंडर',
                'badge_color' => 'success',
                'gradient' => 'linear-gradient(135deg, #134e5e 0%, #71b280 100%)',
                'icon' => 'bi-sun-fill text-warning',
                'title' => "आगामी शासकीय व शाळा सुट्ट्या",
                'content_heading' => '🗓️ सुट्ट्या व सुट्टी कालावधी',
                'items' => $holItems
            ];

            // Slide 5: पंचांग व निसर्ग दर्शन
            if (!empty($panchang)) {
                $pItems = [];
                if (!empty($panchang['tithi'])) $pItems[] = "<strong>तिथी:</strong> " . htmlspecialchars($panchang['tithi']);
                if (!empty($panchang['nakshatra'])) $pItems[] = "<strong>नक्षत्र:</strong> " . htmlspecialchars($panchang['nakshatra']);
                if (!empty($panchang['sunrise']) && !empty($panchang['sunset'])) {
                    $pItems[] = "<strong>सूर्योदय:</strong> " . htmlspecialchars($panchang['sunrise']) . " | <strong>सूर्यास्त:</strong> " . htmlspecialchars($panchang['sunset']);
                }
                if (!empty($panchang['festivals'])) $pItems[] = "<strong>सण / उत्सव:</strong> " . htmlspecialchars($panchang['festivals']);

                if (!empty($pItems)) {
                    $slides[] = [
                        'type' => 'panchang',
                        'badge' => 'पंचांग दर्शन',
                        'badge_color' => 'info',
                        'gradient' => 'linear-gradient(135deg, #2b5876 0%, #4e4376 100%)',
                        'icon' => 'bi-moon-stars-fill text-warning',
                        'title' => "दैनिक पंचांग व निसर्ग — $marathiDate",
                        'content_heading' => '✨ तिथी, नक्षत्र व सूर्योदय/सूर्यास्त',
                        'items' => $pItems
                    ];
                }
            }

            echo json_encode([
                'success' => true,
                'date' => $reqDate,
                'marathi_date' => $marathiDate,
                'marathi_day' => $marathiDay,
                'total_slides' => count($slides),
                'slides' => $slides
            ], JSON_UNESCAPED_UNICODE);
            exit;

        default:
            echo json_encode(['success' => false, 'error' => 'अवैध क्रिया.']);
            exit;
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
