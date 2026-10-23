<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../includes/module_access.php';

function school_classes(int $schoolId): array
{
    $school = school_row($schoolId);
    $lower = (int)($school['lower_class'] ?? 1) ?: 1;
    $upper = (int)($school['upper_class'] ?? 8) ?: 8;
    if ($upper < $lower) $upper = 8;
    if ($upper > 12) $upper = 8;

    // 1. Try MySQL Main DB with configured range
    try {
        $s = getMainDB()->prepare('SELECT * FROM classes WHERE school_id=? AND std >= ? AND std <= ? ORDER BY std,section');
        $s->execute([$schoolId, $lower, $upper]);
        $rows = $s->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            return $rows;
        }
    } catch (Throwable $e) {}

    // 2. Try MySQL Main DB without std bounds
    try {
        $s = getMainDB()->prepare('SELECT * FROM classes WHERE school_id=? ORDER BY std,section');
        $s->execute([$schoolId]);
        $rows = $s->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            return $rows;
        }
    } catch (Throwable $e) {}

    // 3. Fallback to SQLite Tenant DB
    try {
        $s = db()->prepare('SELECT * FROM classes WHERE school_id=? AND std >= ? AND std <= ? ORDER BY std,section');
        $s->execute([$schoolId, $lower, $upper]);
        $rows = $s->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            return $rows;
        }
    } catch (Throwable $e) {}

    try {
        $s = db()->prepare('SELECT * FROM classes WHERE school_id=? ORDER BY std,section');
        $s->execute([$schoolId]);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

function school_marathi_name(int $schoolId): string
{
    $school = school_row($schoolId);
    if (!$school) return '';
    return trim((string) (($school['name_mr'] ?? '') ?: ($school['name'] ?? '')));
}

function school_teachers(int $schoolId): array
{
    try {
        $s = getMainDB()->prepare('SELECT id, school_id, name, name_mr, teacher_code, phone, class_assigned, section, role FROM teachers WHERE school_id=? AND (is_active=1 OR is_active IS NULL) ORDER BY id');
        $s->execute([$schoolId]);
        $rows = $s->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) return $rows;
    } catch (Throwable $e) {}

    try {
        $s = db()->prepare('SELECT id, school_id, name, name_mr, teacher_code, phone, class_assigned, section, role FROM teachers WHERE school_id=? AND (is_active=1 OR is_active IS NULL) ORDER BY id');
        $s->execute([$schoolId]);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

function resolve_teacher_name(?array $class, int $schoolId, ?array $user = null): string
{
    $school = school_row($schoolId) ?: [];
    $schoolName = trim((string) (($school['name_mr'] ?? '') ?: ($school['name'] ?? '')));
    $schoolEn = trim((string) ($school['name'] ?? ''));

    $isSchoolName = static function (string $val) use ($schoolName, $schoolEn): bool {
        $v = trim($val);
        if ($v === '') return true;
        if ($schoolName !== '' && (mb_stripos($v, $schoolName) !== false || mb_stripos($schoolName, $v) !== false)) return true;
        if ($schoolEn !== '' && (mb_stripos($v, $schoolEn) !== false || mb_stripos($schoolEn, $v) !== false)) return true;
        if (mb_stripos($v, 'शाळा') !== false || stripos($v, 'school') !== false) return true;
        return false;
    };

    // 1. Explicit teacher name via GET / POST
    if (!empty($_REQUEST['teacher_name'])) {
        $t = trim((string) $_REQUEST['teacher_name']);
        if (!$isSchoolName($t)) return $t;
    }

    // 2. Explicit teacher id via GET / POST
    if (!empty($_REQUEST['teacher_id'])) {
        try {
            $st = getMainDB()->prepare('SELECT name_mr, name FROM teachers WHERE id=? AND school_id=?');
            $st->execute([(int) $_REQUEST['teacher_id'], $schoolId]);
            if ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                $t = trim((string) ($r['name_mr'] ?: $r['name']));
                if (!$isSchoolName($t)) return $t;
            }
        } catch (Throwable $e) {}

        try {
            $st = db()->prepare('SELECT name_mr, name FROM teachers WHERE id=? AND school_id=?');
            $st->execute([(int) $_REQUEST['teacher_id'], $schoolId]);
            if ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                $t = trim((string) ($r['name_mr'] ?: $r['name']));
                if (!$isSchoolName($t)) return $t;
            }
        } catch (Throwable $e) {}
    }

    // 3. Logged in user if teacher role
    if ($user && !empty($user['role']) && $user['role'] === 'teacher') {
        $t = trim((string) ($user['name_mr'] ?? $user['name'] ?? ''));
        if (!$isSchoolName($t)) return $t;
    }

    // 4. Class teacher assigned to this class
    if ($class && !empty($class['class_teacher_id'])) {
        try {
            $st = getMainDB()->prepare('SELECT name_mr, name FROM teachers WHERE id=? AND school_id=?');
            $st->execute([(int) $class['class_teacher_id'], $schoolId]);
            if ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                $t = trim((string) ($r['name_mr'] ?: $r['name']));
                if (!$isSchoolName($t)) return $t;
            }
        } catch (Throwable $e) {}

        try {
            $st = db()->prepare('SELECT name_mr, name FROM teachers WHERE id=? AND school_id=?');
            $st->execute([(int) $class['class_teacher_id'], $schoolId]);
            if ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                $t = trim((string) ($r['name_mr'] ?: $r['name']));
                if (!$isSchoolName($t)) return $t;
            }
        } catch (Throwable $e) {}
    }

    // 5. Teacher assigned by class standard or name in teachers table
    if ($class) {
        $std = (string) ($class['std'] ?? '');
        $cname = (string) ($class['name'] ?? '');
        try {
            $st = getMainDB()->prepare('SELECT name_mr, name FROM teachers WHERE school_id=? AND (is_active=1 OR is_active IS NULL) AND (class_assigned=? OR class_assigned=?) LIMIT 1');
            $st->execute([$schoolId, $std, $cname]);
            if ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                $t = trim((string) ($r['name_mr'] ?: $r['name']));
                if (!$isSchoolName($t)) return $t;
            }
        } catch (Throwable $e) {}

        try {
            $st = db()->prepare('SELECT name_mr, name FROM teachers WHERE school_id=? AND (is_active=1 OR is_active IS NULL) AND (class_assigned=? OR class_assigned=?) LIMIT 1');
            $st->execute([$schoolId, $std, $cname]);
            if ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                $t = trim((string) ($r['name_mr'] ?: $r['name']));
                if (!$isSchoolName($t)) return $t;
            }
        } catch (Throwable $e) {}
    }

    // 6. Intelligently map from active teachers in the school
    $teachers = school_teachers($schoolId);
    if (!empty($teachers)) {
        if ($class && !empty($class['std'])) {
            $idx = max(0, (int) $class['std'] - 1) % count($teachers);
            $t = trim((string) ($teachers[$idx]['name_mr'] ?: $teachers[$idx]['name']));
            if (!$isSchoolName($t)) return $t;
        }
        $t = trim((string) ($teachers[0]['name_mr'] ?: $teachers[0]['name']));
        if (!$isSchoolName($t)) return $t;
    }

    // 7. Fallback to user name only if not school name
    if ($user && !empty($user['name'])) {
        $t = trim((string) $user['name']);
        if (!$isSchoolName($t)) return $t;
    }

    return '____________________';
}

function school_students(int $schoolId): array
{
    $s = db()->prepare('SELECT s.*,c.name class_name,c.std,c.section FROM students s LEFT JOIN classes c ON c.id=s.class_id AND c.school_id=s.school_id WHERE s.school_id=? ORDER BY s.gr_no');
    $s->execute([$schoolId]);
    return $s->fetchAll();
}

function posted_date(string $key = 'date'): string
{
    $value = trim((string) ($_POST[$key] ?? ''));
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : date('Y-m-d');
}

function marathi_number_words(int $number): string
{
    $ones = ['शून्य','एक','दोन','तीन','चार','पाच','सहा','सात','आठ','नऊ','दहा','अकरा','बारा','तेरा','चौदा','पंधरा','सोळा','सतरा','अठरा','एकोणीस','वीस','एकवीस','बावीस','तेवीस','चोवीस','पंचवीस','सव्वीस','सत्तावीस','अठ्ठावीस','एकोणतीस','तीस','एकतीस','बत्तीस','तेहतीस','चौतीस','पस्तीस','छत्तीस','सदतीस','अडतीस','एकोणचाळीस','चाळीस','एक्केचाळीस','बेचाळीस','त्रेचाळीस','चव्वेचाळीस','पंचेचाळीस','सेहेचाळीस','सत्तेचाळीस','अठ्ठेचाळीस','एकोणपन्नास','पन्नास','एकावन्न','बावन्न','त्रेपन्न','चोपन्न','पंचावन्न','छप्पन्न','सत्तावन्न','अठ्ठावन्न','एकोणसाठ','साठ','एकसष्ट','बासष्ट','त्रेसष्ट','चौसष्ट','पासष्ट','सहासष्ट','सदुसष्ट','अडुसष्ट','एकोणसत्तर','सत्तर','एकाहत्तर','बहात्तर','त्र्याहत्तर','चौर्‍याहत्तर','पंच्याहत्तर','शहात्तर','सत्त्याहत्तर','अठ्ठ्याहत्तर','एकोणऐंशी','ऐंशी','एक्याऐंशी','ब्याऐंशी','त्र्याऐंशी','चौर्‍याऐंशी','पंच्याऐंशी','शहाऐंशी','सत्त्याऐंशी','अठ्ठ्याऐंशी','एकोणनव्वद','नव्वद','एक्याण्णव','ब्याण्णव'];
    if ($number < 100) return $ones[$number] ?? (string) $number;
    if ($number < 1000) {
        $hundreds = intdiv($number, 100);
        $remainder = $number % 100;
        return $ones[$hundreds] . 'शे' . ($remainder ? ' ' . marathi_number_words($remainder) : '');
    }
    if ($number < 100000) {
        $thousands = intdiv($number, 1000);
        $remainder = $number % 1000;
        return marathi_number_words($thousands) . ' हजार' . ($remainder ? ' ' . marathi_number_words($remainder) : '');
    }
    return (string) $number;
}

function marathi_date_words(string $date): string
{
    $stamp = DateTimeImmutable::createFromFormat('Y-m-d', $date);
    if (!$stamp) return $date;
    $months = [1=>'जानेवारी',2=>'फेब्रुवारी',3=>'मार्च',4=>'एप्रिल',5=>'मे',6=>'जून',7=>'जुलै',8=>'ऑगस्ट',9=>'सप्टेंबर',10=>'ऑक्टोबर',11=>'नोव्हेंबर',12=>'डिसेंबर'];
    return marathi_number_words((int) $stamp->format('j')) . ' ' . $months[(int) $stamp->format('n')] . ' ' . marathi_number_words((int) $stamp->format('Y'));
}

function class_labels(): array
{
    return [1=>'पहिली',2=>'दुसरी',3=>'तिसरी',4=>'चौथी',5=>'पाचवी',6=>'सहावी',7=>'सातवी',8=>'आठवी',9=>'नववी',10=>'दहावी',11=>'अकरावी',12=>'बारावी'];
}

function class_label(int $std): string
{
    return class_labels()[$std] ?? ('इयत्ता ' . $std);
}

function student_full_name(array $student): string
{
    return trim(implode(' ', array_filter([
        (string) ($student['first_name'] ?? ''),
        (string) ($student['father_name'] ?? ''),
        (string) ($student['last_name'] ?? $student['surname'] ?? ''),
    ])));
}

function gr_navigation(): array
{
    return [
        ['label'=>'मुख्यपृष्ठ','href'=>'/modules/gr/index.php'],
        ['label'=>'मदत','href'=>'/modules/gr/help.php'],
        ['label'=>'जनरल रजिस्टर','href'=>'/modules/gr/students/index.php'],
        ['label'=>'वर्ग यादी','href'=>'/modules/gr/enrollments/class_list.php'],
        ['label'=>'वर्षांती बढती','href'=>'/modules/gr/enrollments/promote.php'],
        ['label'=>'प्रवेश अर्ज','href'=>'/modules/gr/students/applications.php'],
        ['label'=>'निर्गम','href'=>'/modules/gr/certificates/nirgam_index.php'],
        ['label'=>'दाखले','href'=>'/modules/gr/certificates/lc_index.php'],
        ['label'=>'बोनाफाईड','href'=>'/modules/gr/certificates/bonafide_index.php'],
        ['label'=>'डिफॉल्ट माहिती','href'=>'/modules/gr/school/defaults.php'],
        ['label'=>'बिलिंग','href'=>'/modules/gr/billing/subscription.php'],
    ];
}

function medium_options(): array
{
    return ['मराठी'=>'marathi','सेमी-इंग्लिश'=>'semi_english','इंग्रजी'=>'english','उर्दू'=>'urdu'];
}

function student_gender_options(): array
{
    return ['मुलगा'=>'male','मुलगी'=>'female','इतर'=>'other'];
}

function mother_tongue_options(): array
{
    return ['मराठी','हिंदी','English','उर्दू','तेलुगू','कन्नड','तमिळ','गुजराती'];
}

function religion_options(): array
{
    return ['हिंदू','मुस्लिम','बौद्ध','जैन','शीख','ख्रिश्चन','इतर'];
}

function caste_options(): array
{
    return ['खुला'=>'open','OBC'=>'obc','SC'=>'sc','ST'=>'st','NT'=>'nt','SBC'=>'sbc','VJNT'=>'vjnt'];
}

function stream_options(): array
{
    return ['कला'=>'arts','वाणिज्य'=>'commerce','विज्ञान'=>'science'];
}

function next_gr_number(int $schoolId, int $bookNo = 1): int
{
    $stmt = db()->prepare('SELECT COALESCE(MAX(gr_no),0)+1 FROM students WHERE school_id=? AND book_no=?');
    $stmt->execute([$schoolId, $bookNo]);
    return max(1, (int) $stmt->fetchColumn());
}

function current_year_id(int $schoolId): int
{
    return (int) (current_academic_year($schoolId)['id'] ?? 0);
}

function cce_grade(float $percentage): string
{
    $path = __DIR__ . '/../../data/cce_bank/grades.json';
    if (is_file($path)) {
        $grades = json_decode((string) file_get_contents($path), true);
        if (is_array($grades)) {
            usort($grades, static fn(array $a, array $b): int => (float) ($b['marks'] ?? 0) <=> (float) ($a['marks'] ?? 0));
            foreach ($grades as $grade) {
                if ($percentage >= (float) ($grade['marks'] ?? 0)) {
                    return (string) ($grade['gradeText'] ?? 'इ-2');
                }
            }
        }
    }
    return 'इ-2';
}

function cce_statement_bank(): array
{
    return [
        'अभ्यासात सातत्य ठेवून उत्तम प्रगती केली आहे.',
        'वर्गातील उपक्रमांमध्ये उत्स्फूर्त सहभाग घेतो/घेते.',
        'वाचनाची आवड वाढत असून उच्चार स्पष्ट आहेत.',
        'लेखन स्वच्छ, सुवाच्य आणि शिस्तबद्ध आहे.',
        'गणिती संकल्पना समजून घेण्याची क्षमता चांगली आहे.',
        'प्रश्न विचारून शिकण्याची जिज्ञासा दाखवतो/दाखवते.',
        'गटकार्यामध्ये सहकार्याची भावना जपतो/जपते.',
        'नवीन कल्पना मांडण्यास नेहमी पुढाकार घेतो/घेते.',
        'वर्गातील नियमांचे पालन जबाबदारीने करतो/करते.',
        'स्वाध्याय पूर्ण करण्यासाठी नियमित प्रयत्न करतो/करते.',
        'परिसर स्वच्छ ठेवण्याची सवय विकसित झाली आहे.',
        'कला आणि सर्जनशील उपक्रमांमध्ये विशेष रस आहे.',
        'क्रीडा उपक्रमांत उत्साहाने सहभागी होतो/होते.',
        'समवयस्कांना मदत करण्याची सकारात्मक वृत्ती आहे.',
        'आत्मविश्वासात लक्षणीय वाढ दिसून येते.',
        'वेळेचे नियोजन अधिक चांगले करण्याची गरज आहे.',
        'वाचनाचा सराव दररोज केल्यास अधिक प्रगती होईल.',
        'गणितातील मूलभूत उदाहरणांचा अधिक सराव आवश्यक आहे.',
        'शब्दलेखनाकडे लक्ष दिल्यास लेखन अधिक प्रभावी होईल.',
        'ऐकून समजून घेण्याची क्षमता चांगली आहे.',
        'मौखिक उत्तरांमध्ये आत्मविश्वासाने मांडणी करतो/करते.',
        'प्रयोग आणि कृतीतून शिकण्यास उत्सुक असतो/असते.',
        'अडचणींवर मात करण्यासाठी चिकाटी दाखवतो/दाखवते.',
        'नियमित उपस्थितीमुळे शिकण्याचा वेग चांगला राहिला आहे.',
        'स्वतःची कामे स्वतंत्रपणे पूर्ण करण्याचा प्रयत्न करतो/करते.',
        'प्रश्न सोडवताना तर्कशुद्ध विचार करतो/करते.',
        'भाषिक अभिव्यक्ती सुधारत आहे.',
        'चित्रकला व हस्तकलेत कल्पकता दिसते.',
        'संगीत आणि सांस्कृतिक कार्यक्रमांत रस आहे.',
        'शारीरिक तंदुरुस्तीबाबत जागरूक आहे.',
        'नम्र आणि सहकार्यशील वर्तन ठेवतो/ठेवते.',
        'शिक्षकांच्या सूचनांचा सकारात्मक स्वीकार करतो/करते.',
        'अभ्यासातील कमतरता भरून काढण्यासाठी प्रयत्नशील आहे.',
        'आवडीच्या विषयात उल्लेखनीय कामगिरी केली आहे.',
        'नवीन विषय समजून घेण्यासाठी अतिरिक्त वेळ देतो/देते.',
        'वर्ग चर्चेत विचारपूर्वक मत व्यक्त करतो/करते.',
        'सादरीकरण कौशल्यात चांगली प्रगती झाली आहे.',
        'जबाबदारीची कामे विश्वासाने पूर्ण करतो/करते.',
        'शिस्त, स्वच्छता आणि वेळेचे भान चांगले आहे.',
        'पालकांच्या सहकार्याने अधिक उज्ज्वल प्रगती शक्य आहे.',
        'एकूण कामगिरी समाधानकारक असून सातत्य ठेवावे.',
        'एकूण कामगिरी प्रशंसनीय असून पुढील यशासाठी शुभेच्छा आहेत.',
        'अधिक सराव आणि पुनरावृत्ती केल्यास आत्मविश्वास वाढेल.',
        'सर्वांगीण विकासासाठी उपक्रमांमध्ये नियमित सहभाग घ्यावा.',
    ];
}

/** टाचण मॉड्युल आयकॉन (outline SVG) — नाव → path */
function tachan_icon(string $name, string $class = 'w-5 h-5'): string
{
    $paths = [
        'home' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
        'tachan' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
        'weekly' => 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10',
        'timetable' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
        'annual' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253',
        'holidays' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2zM9 16l2 2 4-4',
        'old' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
        'settings' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065zM15 12a3 3 0 11-6 0 3 3 0 016 0z',
        'add' => 'M12 4v16m8-8H4',
        'print' => 'M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m0 0v4h10v-4M7 17V7a2 2 0 012-2h6a2 2 0 012 2v10',
    ];
    $d = $paths[$name] ?? $paths['tachan'];
    return '<svg class="' . e($class) . '" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="' . $d . '"/></svg>';
}
