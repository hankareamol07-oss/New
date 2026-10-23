<?php
declare(strict_types=1);

/**
 * Timetable Helper — Integrates directly with Tachan (दैनिक टाचण) Module
 * Retrieves class timetable configured by the assigned class teacher.
 */

if (!function_exists('getClassTimetableFromTachan')) {
    function getClassTimetableFromTachan(int $std, ?int $schoolId = 2): array {
        $tenantDb = null;
        $udise = '27350302306';
        $paths = [
            __DIR__ . "/../../data/schools/{$udise}.sqlite",
            dirname(__DIR__, 2) . "/data/schools/{$udise}.sqlite",
            dirname(__DIR__, 1) . "/data/schools/{$udise}.sqlite",
            "/var/www/html/zpschool2kuchi/data/schools/{$udise}.sqlite",
            "/var/www/html/zpschool2kuchi/portal/data/schools/{$udise}.sqlite",
        ];

        foreach ($paths as $p) {
            if (file_exists($p)) {
                try {
                    $tenantDb = new PDO('sqlite:' . $p, null, null, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ]);
                    break;
                } catch (Throwable $e) {}
            }
        }

        if (!$tenantDb && function_exists('getSchoolTenantDB')) {
            try {
                $tenantDb = getSchoolTenantDB($schoolId);
            } catch (Throwable $e) {}
        }

        $teacherName = 'वर्गशिक्षक';
        $className = "इयत्ता {$std} वी";
        $classId = 0;

        $daysMap = [
            1 => 'सोमवार',
            2 => 'मंगळवार',
            3 => 'बुधवार',
            4 => 'गुरुवार',
            5 => 'शुक्रवार',
            6 => 'शनिवार'
        ];

        $defaultTimes = [
            1 => '१०:०० - १०:४०',
            2 => '१०:४० - ११:२०',
            3 => '११:२० - १२:००',
            4 => '१२:०० - १२:४०',
            5 => '०१:१५ - ०१:५५',
            6 => '०१:५५ - ०२:३५',
            7 => '०२:३५ - ०३:१५',
            8 => '०३:१५ - ०३:५५',
            9 => '०३:५५ - ०४:३०'
        ];

        if ($tenantDb) {
            try {
                $st = $tenantDb->prepare("
                    SELECT c.id, c.name, c.std, c.section, c.class_teacher_id, 
                           t.name as t_name, t.name_mr as t_name_mr 
                    FROM classes c 
                    LEFT JOIN teachers t ON c.class_teacher_id = t.id 
                    WHERE c.std = ? 
                    LIMIT 1
                ");
                $st->execute([$std]);
                $cls = $st->fetch();

                if ($cls) {
                    $classId = (int)$cls['id'];
                    $className = $cls['name'] ?: $className;
                    $teacherName = $cls['t_name_mr'] ?: ($cls['t_name'] ?: $teacherName);

                    $ttStmt = $tenantDb->prepare("
                        SELECT day, period, subject 
                        FROM timetable 
                        WHERE class_id = ? 
                        ORDER BY day, period
                    ");
                    $ttStmt->execute([$classId]);
                    $ttRows = $ttStmt->fetchAll();

                    if (!empty($ttRows)) {
                        $schedule = [];
                        foreach ($daysMap as $dNum => $dName) {
                            $schedule[$dName] = [];
                        }

                        foreach ($ttRows as $r) {
                            $d = (int)$r['day'];
                            $p = (int)$r['period'];
                            $sub = trim((string)$r['subject']);
                            $dayName = $daysMap[$d] ?? 'सोमवार';
                            $time = $defaultTimes[$p] ?? "तासिका {$p}";

                            $schedule[$dayName][] = [
                                'period' => $p,
                                'time' => $time,
                                'subject' => $sub
                            ];
                        }

                        $formattedTable = [];
                        foreach ($daysMap as $dNum => $dName) {
                            $pMap = [];
                            foreach ($schedule[$dName] as $pSlot) {
                                $pMap[$pSlot['period']] = $pSlot['subject'];
                            }

                            $formattedTable[] = [
                                'day' => $dName,
                                'p1' => $pMap[1] ?? '-',
                                'p2' => $pMap[2] ?? '-',
                                'p3' => $pMap[3] ?? '-',
                                'p4' => $pMap[4] ?? '-',
                                'p5' => $pMap[5] ?? '-',
                                'p6' => $pMap[6] ?? '-',
                                'p7' => $pMap[7] ?? '-',
                                'p8' => $pMap[8] ?? '-',
                                'p9' => $pMap[9] ?? '-',
                            ];
                        }

                        return [
                            'success' => true,
                            'std' => $std,
                            'class_name' => $className,
                            'teacher_name' => $teacherName,
                            'timetable' => $schedule,
                            'data' => $formattedTable,
                            'source' => 'tachan_module'
                        ];
                    }
                }
            } catch (Throwable $e) {}
        }

        // Fallback standard schedule if not configured in tachan module yet
        $schedule = [
            'सोमवार' => [
                ['period' => 1, 'time' => '१०:०० - १०:३०', 'subject' => 'प्रार्थना व परिपाठ'],
                ['period' => 2, 'time' => '१०:३० - ११:१५', 'subject' => 'मराठी बालभारती'],
                ['period' => 3, 'time' => '११:१५ - १२:००', 'subject' => 'गणित'],
                ['period' => 4, 'time' => '१२:०० - १२:४५', 'subject' => 'इंग्रजी'],
                ['period' => 5, 'time' => '०१:१५ - ०२:००', 'subject' => ($std <= 5 ? 'परिसर अभ्यास' : 'सामान्य विज्ञान')],
                ['period' => 6, 'time' => '०२:०० - ०२:४५', 'subject' => ($std <= 5 ? 'परिसर अभ्यास भाग २' : 'इतिहास व नागरिकशास्त्र')],
                ['period' => 7, 'time' => '०२:४५ - ०३:३०', 'subject' => 'शारीरिक शिक्षण व खेळ'],
            ],
            'मंगळवार' => [
                ['period' => 1, 'time' => '१०:०० - १०:३०', 'subject' => 'प्रार्थना व परिपाठ'],
                ['period' => 2, 'time' => '१०:३० - ११:१५', 'subject' => 'गणित'],
                ['period' => 3, 'time' => '११:१५ - १२:००', 'subject' => ($std <= 5 ? 'परिसर अभ्यास' : 'सामान्य विज्ञान')],
                ['period' => 4, 'time' => '१२:०० - १२:४५', 'subject' => 'मराठी बालभारती'],
                ['period' => 5, 'time' => '०१:१५ - ०२:००', 'subject' => 'इंग्रजी'],
                ['period' => 6, 'time' => '०२:०० - ०२:४५', 'subject' => ($std <= 5 ? 'कला शिक्षण' : 'भूगोल')],
                ['period' => 7, 'time' => '०२:४५ - ०३:३०', 'subject' => 'चित्रकला'],
            ],
            'बुधवार' => [
                ['period' => 1, 'time' => '१०:०० - १०:३०', 'subject' => 'प्रार्थना व परिपाठ'],
                ['period' => 2, 'time' => '१०:३० - ११:१५', 'subject' => 'इंग्रजी'],
                ['period' => 3, 'time' => '११:१५ - १२:००', 'subject' => 'मराठी बालभारती'],
                ['period' => 4, 'time' => '१२:०० - १२:४५', 'subject' => 'गणित'],
                ['period' => 5, 'time' => '०१:१५ - ०२:००', 'subject' => ($std <= 5 ? 'परिसर अभ्यास' : 'सामान्य विज्ञान')],
                ['period' => 6, 'time' => '०२:०० - ०२:४५', 'subject' => ($std <= 5 ? 'कार्यानुभव' : 'हिंदी सुलभभारती')],
                ['period' => 7, 'time' => '०२:४५ - ०३:३०', 'subject' => 'कार्यानुभव'],
            ],
            'गुरुवार' => [
                ['period' => 1, 'time' => '१०:०० - १०:३०', 'subject' => 'प्रार्थना व परिपाठ'],
                ['period' => 2, 'time' => '१०:३० - ११:१५', 'subject' => ($std <= 5 ? 'परिसर अभ्यास' : 'सामान्य विज्ञान')],
                ['period' => 3, 'time' => '११:१५ - १२:००', 'subject' => 'गणित'],
                ['period' => 4, 'time' => '१२:०० - १२:४५', 'subject' => 'इंग्रजी'],
                ['period' => 5, 'time' => '०१:१५ - ०२:००', 'subject' => 'मराठी बालभारती'],
                ['period' => 6, 'time' => '०२:०० - ०२:४५', 'subject' => ($std <= 5 ? 'कार्यानुभव' : 'इतिहास व नागरिकशास्त्र')],
                ['period' => 7, 'time' => '०२:४५ - ०३:३०', 'subject' => 'संगीत व गायन'],
            ],
            'शुक्रवार' => [
                ['period' => 1, 'time' => '१०:०० - १०:३०', 'subject' => 'प्रार्थना व परिपाठ'],
                ['period' => 2, 'time' => '१०:३० - ११:१५', 'subject' => 'मराठी बालभारती'],
                ['period' => 3, 'time' => '११:१५ - १२:००', 'subject' => 'इंग्रजी'],
                ['period' => 4, 'time' => '१२:०० - १२:४५', 'subject' => 'गणित'],
                ['period' => 5, 'time' => '०१:१५ - ०२:००', 'subject' => ($std <= 5 ? 'परिसर अभ्यास' : 'सामान्य विज्ञान')],
                ['period' => 6, 'time' => '०२:०० - ०२:४५', 'subject' => ($std <= 5 ? 'सामान्य ज्ञान' : 'भूगोल')],
                ['period' => 7, 'time' => '०२:४५ - ०३:३०', 'subject' => 'वाचन व मूल्यशिक्षण'],
            ],
            'शनिवार' => [
                ['period' => 1, 'time' => '०८:०० - ०८:३०', 'subject' => 'प्रार्थना व परिपाठ'],
                ['period' => 2, 'time' => '०८:३० - ०९:१५', 'subject' => 'कार्यानुभव व हस्तकला'],
                ['period' => 3, 'time' => '०९:१५ - १०:००', 'subject' => 'चित्रकला व रांगोळी'],
                ['period' => 4, 'time' => '१०:०० - ११:००', 'subject' => 'क्रीडा व मैदानी खेळ'],
            ]
        ];

        $formattedTable = [];
        foreach ($schedule as $dayName => $periods) {
            $formattedTable[] = [
                'day' => $dayName,
                'p1' => $periods[1]['subject'] ?? '-',
                'p2' => $periods[2]['subject'] ?? '-',
                'p3' => $periods[3]['subject'] ?? '-',
                'p4' => $periods[4]['subject'] ?? '-',
                'p5' => $periods[5]['subject'] ?? '-',
                'p6' => $periods[6]['subject'] ?? '-',
                'p7' => '-',
                'p8' => '-',
                'p9' => '-',
            ];
        }

        return [
            'success' => true,
            'std' => $std,
            'class_name' => $className,
            'teacher_name' => $teacherName,
            'timetable' => $schedule,
            'data' => $formattedTable,
            'source' => 'default_schedule'
        ];
    }
}
