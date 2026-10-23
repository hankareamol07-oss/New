<?php
/**
 * GET & POST /api/mdm.php
 * Native Mid-Day Meal (पोषण आहार) API for Android app.
 *
 * GET: ?date=YYYY-MM-DD
 * Returns: daily meal entry status, beneficiaries, cooked, remarks, menus list
 *
 * POST: Save daily meal record
 * Body: {
 *   date: "YYYY-MM-DD",
 *   group_1_5: { beneficiaries: 45, menu_id: 1, cooked: true, remarks: "" },
 *   group_6_8: { beneficiaries: 30, menu_id: 1, cooked: true, remarks: "" }
 * }
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../includes/tenant_db.php';

$schoolId = getAuthSchoolId();
$tdb = getTenantDB($schoolId);

if (!$tdb) {
    apiError('School database not available', 500);
}

// ─── GET: Fetch MDM Data ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $date = trim($_GET['date'] ?? date('Y-m-d'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = date('Y-m-d');
    }

    try {
        $tdb->exec("CREATE TABLE IF NOT EXISTS mdm_daily (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            school_id INTEGER,
            date TEXT NOT NULL,
            class_group TEXT NOT NULL,
            menu_id INTEGER DEFAULT 0,
            menu_name TEXT DEFAULT '',
            beneficiaries INTEGER DEFAULT 0,
            cooked INTEGER DEFAULT 1,
            holiday INTEGER DEFAULT 0,
            rice_kg REAL DEFAULT 0,
            dal_kg REAL DEFAULT 0,
            remarks TEXT,
            UNIQUE(date, class_group)
        )");

        $stmt = $tdb->prepare("SELECT * FROM mdm_daily WHERE date = ?");
        $stmt->execute([$date]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $entries = [];
        $totalBeneficiaries = 0;
        $totalRice = 0.0;
        $totalDal = 0.0;

        foreach ($rows as $r) {
            $group = $r['class_group'];
            $b = (int)($r['beneficiaries'] ?? 0);
            $totalBeneficiaries += $b;
            $ck = (int)($r['cooked'] ?? 1) === 1 && (int)($r['holiday'] ?? 0) === 0;
            if (!array_key_exists('rice_kg', $r) || $r['rice_kg'] === null) {
                $r['rice_kg'] = $ck ? round($b * ($group === '1-5' ? 0.100 : 0.150), 3) : 0;
                $r['dal_kg'] = $ck ? round($b * ($group === '1-5' ? 0.020 : 0.030), 3) : 0;
            }
            $totalRice += (float)($r['rice_kg'] ?? 0);
            $totalDal += (float)($r['dal_kg'] ?? 0);

            $entries[$group] = [
                'class_group' => $group,
                'beneficiaries' => $b,
                'menu_id' => (int)($r['menu_id'] ?? 0),
                'menu_name' => $r['menu_name'] ?? '',
                'cooked' => (bool)($r['cooked'] ?? 1),
                'holiday' => (bool)($r['holiday'] ?? 0),
                'rice_kg' => (float)($r['rice_kg'] ?? 0),
                'dal_kg' => (float)($r['dal_kg'] ?? 0),
                'remarks' => $r['remarks'] ?? ''
            ];
        }

        // Standard government menus for Maharashtra schools
        $menus = [
            ['id' => 1, 'name' => 'मुगडाळ खिचडी (सोमवार)'],
            ['id' => 2, 'name' => 'मटकी उसळ + भात (मंगळवार)'],
            ['id' => 3, 'name' => 'चना पुलाव (बुधवार)'],
            ['id' => 4, 'name' => 'तूरडाळ वरण भात (गुरुवार)'],
            ['id' => 5, 'name' => 'सोयाबीन पुलाव (शुक्रवार)'],
            ['id' => 6, 'name' => 'गोड खिचडी / खीर (शनिवार)']
        ];

        apiSuccess([
            'date' => $date,
            'summary' => [
                'total_beneficiaries' => $totalBeneficiaries,
                'total_rice_kg' => round($totalRice, 2),
                'total_dal_kg' => round($totalDal, 2),
                'is_completed' => count($entries) > 0
            ],
            'entries' => $entries,
            'entries_map' => $entries,
            'entries_list' => array_values($entries),
            'menus' => $menus
        ]);

    } catch (Exception $e) {
        apiError('MDM माहिती मिळवताना त्रुटी: ' . $e->getMessage(), 500);
    }
}

// ─── POST: Save MDM Entry ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = getRequestBody();
    $date = trim($body['date'] ?? date('Y-m-d'));

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        apiError('अवैध तारीख');
    }

    $groups = [
        '1-5' => $body['group_1_5'] ?? null,
        '6-8' => $body['group_6_8'] ?? null
    ];

    try {
        $meta = $tdb->query("SELECT * FROM mdm_daily LIMIT 0");
        $cols = [];
        for ($i = 0; $i < $meta->columnCount(); $i++) {
            $cols[$meta->getColumnMeta($i)['name']] = true;
        }
        $find = $tdb->prepare("SELECT * FROM mdm_daily WHERE school_id = ? AND date = ? AND class_group = ? LIMIT 1");

        foreach ($groups as $groupKey => $data) {
            if (!$data) continue;

            $benef = max(0, (int)($data['beneficiaries'] ?? 0));
            $cooked = !empty($data['cooked']) ? 1 : 0;
            $holiday = !empty($data['holiday']) ? 1 : 0;
            $menuId = (int)($data['menu_id'] ?? 1);
            $menuName = trim($data['menu_name'] ?? '');
            $remarks = trim($data['remarks'] ?? '');

            // Norms:
            // 1-5 std: 100g rice, 20g dal per beneficiary
            // 6-8 std: 150g rice, 30g dal per beneficiary
            $ricePerStudent = ($groupKey === '1-5') ? 0.100 : 0.150;
            $dalPerStudent  = ($groupKey === '1-5') ? 0.020 : 0.030;

            $riceKg = $cooked ? round($benef * $ricePerStudent, 3) : 0.0;
            $dalKg  = $cooked ? round($benef * $dalPerStudent, 3) : 0.0;

            $row = [
                'menu_id'       => $menuId,
                'menu_name'     => $menuName,
                'beneficiaries' => $benef,
                'cooked'        => $cooked,
                'holiday'       => $holiday,
                'rice_kg'       => $riceKg,
                'dal_kg'        => $dalKg,
                'remarks'       => $remarks,
            ];
            $saveRow = array_intersect_key($row, $cols);

            $find->execute([$schoolId, $date, $groupKey]);
            $old = $find->fetch(PDO::FETCH_ASSOC);
            $find->closeCursor();
            if ($old && !empty($old['locked'])) {
                apiError('या तारखेची नोंद lock केलेली आहे (इ. ' . $groupKey . '). बदल करता येणार नाही.', 423);
            }
            if ($old) {
                $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($saveRow)));
                $tdb->prepare("UPDATE mdm_daily SET $set WHERE id = ?")->execute(array_merge(array_values($saveRow), [$old['id']]));
            } else {
                $saveRow = array_merge(['school_id' => $schoolId, 'date' => $date, 'class_group' => $groupKey], $saveRow);
                $ph = implode(', ', array_fill(0, count($saveRow), '?'));
                $tdb->prepare('INSERT INTO mdm_daily (' . implode(', ', array_keys($saveRow)) . ") VALUES ($ph)")->execute(array_values($saveRow));
            }

            if (function_exists('syncTenantRecordToMySQL')) {
                syncTenantRecordToMySQL('mdm_daily', [
                    'school_id' => $schoolId,
                    'date' => $date,
                    'class_group' => $groupKey,
                    'menu_id' => $menuId,
                    'menu_name' => $menuName,
                    'beneficiaries' => $benef,
                    'cooked' => $cooked,
                    'holiday' => $holiday,
                    'rice_kg' => $riceKg,
                    'dal_kg' => $dalKg,
                    'remarks' => $remarks
                ], ['school_id', 'date', 'class_group']);
            }
        }

        apiSuccess(['date' => $date], 'पोषण आहार नोंद यशस्वीरित्या जतन झाली!');

    } catch (Exception $e) {
        apiError('MDM जतन करताना त्रुटी: ' . $e->getMessage(), 500);
    }
}
