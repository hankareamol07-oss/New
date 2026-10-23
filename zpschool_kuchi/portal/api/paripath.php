<?php
/**
 * GET/POST /api/paripath.php
 * Daily school assembly (Panchang, Suvichar, Subhashit, Dinvishesh, Blackboard, Prayers).
 * Fully synced with modules/lib/paripath_engine.php
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../modules/lib/paripath_engine.php';

$date = $_GET['date'] ?? $_POST['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'get';

$isSync = ($action === 'sync' || isset($_GET['sync']) || isset($_POST['sync']));
if ($isSync) {
    paripath_sync_date($date);
}


// 2. Action: save_notes (Blackboard custom announcement, word of day, GK)
if ($action === 'save_notes' || ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_notes']))) {
    $rawInput = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $notesData = [
        'announcement' => trim($rawInput['announcement'] ?? ''),
        'word_of_day' => trim($rawInput['word_of_day'] ?? ''),
        'word_meaning' => trim($rawInput['word_meaning'] ?? ''),
        'gk_question' => trim($rawInput['gk_question'] ?? ''),
        'gk_answer' => trim($rawInput['gk_answer'] ?? '')
    ];
    paripath_save_custom_notes($date, $notesData);
    $assembly = paripath_get_full_assembly($date);
    apiSuccess([
        'message' => 'शालेय फळा नोंद व सामान्य ज्ञान सेव्ह झाले!',
        'custom_notes' => $assembly['custom_notes']
    ]);
}

// 3. Action: prayers
if ($action === 'prayers') {
    $prayers = paripath_get_prayers();
    apiSuccess([
        'prayers' => $prayers
    ]);
}

// 4. Default: Full assembly
$assembly = paripath_get_full_assembly($date);
$panchang = $assembly['panchang'];
$suvichar = $assembly['suvichar'];
$subhashit = $assembly['subhashit'];
$dinvishesh = $assembly['dinvishesh'];
$notes = $assembly['custom_notes'];
$prayers = $assembly['prayers'] ?? paripath_get_prayers();

// Determine school name if user is logged in
$schoolName = 'शालेय परिपाठ केंद्र';
try {
    if (function_exists('isLoggedIn') && isLoggedIn()) {
        $school = getSchool();
        $schoolName = (string)(($school['name_mr'] ?? '') ?: ($school['name'] ?? '') ?: $schoolName);
    }
} catch (Throwable $e) {}

// Build flat dinvishesh summary for backward compatibility
$dinvishesh_text = '';
if (!empty($dinvishesh)) {
    $firstFew = array_slice($dinvishesh, 0, 5);
    $lines = [];
    foreach ($firstFew as $d) {
        $yr = !empty($d['event_year']) ? " (" . paripath_marathi_digits($d['event_year']) . ")" : "";
        $lines[] = "• " . $d['title_mr'] . $yr . (!empty($d['short_info_mr']) ? " - " . $d['short_info_mr'] : "");
    }
    $dinvishesh_text = implode("\n\n", $lines);
}

// National Anthem & Pledge
$nationalAnthem = "जन गण मन अधिनायक जय हे\nभारत भाग्य विधाता।\nपंजाब सिंधु गुजरात मराठा\nद्राविड उत्कल वंग।\nविंध्य हिमाचल यमुना गंगा\nउच्छल जलधि तरंग।\nतव शुभ नामे जागे, तव शुभ आशिष मांगे\nगाहे तव जय गाथा।\nजन गण मंगलदायक जय हे\nभारत भाग्य विधाता।\nजय हे, जय हे, जय हे\nजय जय जय जय हे।।";

$pledge = "भारत माझा देश आहे.\nसारे भारतीय माझे बांधव आहेत.\nमी माझ्या देशावर प्रेम करतो.\nत्याच्या समृद्ध आणि विविध परंपरांचा मला अभिमान आहे.\nत्यास योग्य असे बनण्याचा मी नेहमी प्रयत्न करेन.\nमाझ्या माता-पित्यांचा, गुरूजनांचा आणि थोरामोठ्यांचा मी आदर करेन.\nआणि प्रत्येकाशी नम्रतेने वागेन.\nमाझा देश आणि माझे देशबांधव यांच्याप्रती मी निष्ठावंत राहीन.\nत्यांच्या कल्याणात आणि समृद्धीत माझे सौख्य आहे.\n    जय हिंद!";

$payload = [
    'date' => $date,
    'marathi_date' => $assembly['marathi_date'],
    'marathi_day' => $assembly['marathi_day'],
    'day_name' => $assembly['marathi_day'],
    'school_name' => $schoolName,
    'panchang' => $panchang,
    'suvichar' => $suvichar['text_mr'] ?? '',
    'suvichar_source' => $suvichar['author'] ?? '',
    'suvichar_meaning' => $suvichar['meaning_mr'] ?? '',
    'suvichar_data' => $suvichar,
    'subhashit_text' => $subhashit['text_sa'] ?? '',
    'subhashit_meaning' => $subhashit['meaning_mr'] ?? '',
    'subhashit_data' => $subhashit,
    'dinvishesh' => $dinvishesh_text,
    'dinvishesh_list' => $dinvishesh,
    'custom_notes' => $notes,
    'prayers' => $prayers,
    'prayer_text' => "हे ईश्वरा, आम्हाला सद्बुद्धी दे, उत्तम ज्ञान दे, आमच्या मनाला शांती दे.\nसर्व प्राणिमात्रांवर तुझी कृपा असू दे. आमेन / ॐ शांती शांती शांती।।",
    'national_anthem' => $nationalAnthem,
    'pledge' => $pledge,
];
if ($isSync) {
    $payload['message'] = 'परिपाठ डेटा यशस्वीरीत्या सिंक झाला!';
}
apiSuccess($payload);
