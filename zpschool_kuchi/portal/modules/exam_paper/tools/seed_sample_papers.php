<?php
/**
 * Creates sample संकलित / आकारिक papers in the उत्तर-लेखन (answer-lines) format for several classes and
 * subjects, using the default layout of each subject and textbook exercise questions.
 *
 *   cd exam_paper && php tools/seed_sample_papers.php [http://localhost/exam_paper]
 *
 * Prints one paper_view URL per paper (add &key=1 for the teacher copy). Used to produce the sample PDFs
 * in docs/samples and handy after a fresh install to see the format with real data.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/assessment_patterns.php';

$base = rtrim($argv[1] ?? 'http://localhost:8088', '/');
$wanted = [
    // [std, subject, medium, exam_type, test_no, oral_marks]
    [1, 'Marathi', 'Marathi', 'aakarik', 1, 10],
    [2, 'Maths', 'Marathi', 'sankalit', 1, 0],
    [3, 'EVS', 'Marathi', 'sankalit', 1, 0],
    [4, 'Marathi', 'Marathi', 'sankalit', 1, 10],
    [5, 'English', 'English', 'sankalit', 1, 10],
    [5, 'Hindi', 'Hindi', 'aakarik', 1, 5],
    [6, 'Maths', 'Marathi', 'sankalit', 2, 0],
    [7, 'Science', 'Marathi', 'sankalit', 1, 0],
    [8, 'Geography', 'Marathi', 'sankalit', 2, 0],
    [8, 'English', 'English', 'aakarik', 2, 5],
];
$tree = [];
foreach (ep_book_tree() as $s) {
    foreach ($s['subjects'] as $sub) {
        $tree[$s['standard'] . '|' . $sub['subject'] . '|' . $sub['medium']] = $sub;
    }
}
$stdMr = ['', 'पहिली', 'दुसरी', 'तिसरी', 'चौथी', 'पाचवी', 'सहावी', 'सातवी', 'आठवी'];
$mr = fn($n) => strtr((string)$n, ['0' => '०', '1' => '१', '2' => '२', '3' => '३', '4' => '४', '5' => '५', '6' => '६', '7' => '७', '8' => '८', '9' => '९']);

foreach ($wanted as [$std, $subject, $medium, $type, $testNo, $oral]) {
    $sub = $tree["$std|$subject|$medium"] ?? null;
    if (!$sub) {
        fwrite(STDERR, "skip std $std $subject ($medium): no textbook questions\n");
        continue;
    }
    $chapters = array_merge(...array_map(fn($b) => array_column($b['chapters'], 'chapter_id'), $sub['books']));
    $half = (int)ceil(count($chapters) / 2);
    $chapterIds = $type === 'sankalit' ? ($testNo === 1 ? array_slice($chapters, 0, $half) : array_slice($chapters, $half)) : $chapters;
    $layout = ep_assessment_layout($type, $subject, $medium);
    $used = [];
    $sections = [];
    foreach ($layout['sections'] as $sec) {
        $rows = ep_book_random($chapterIds, $sec['qtype'], (int)$sec['count'], $used);
        if (!$rows) {                                    // fall back to any type from the same chapters
            $rows = ep_book_random($chapterIds, '', (int)$sec['count'], $used);
        }
        $items = [];
        foreach ($rows as $q) {
            $used[] = $q['bq_id'];
            $text = $q['text'];
            if (!empty($q['pairs'])) {
                $text .= "\n" . implode("\n", array_map(fn($p) => $p[0] . ' | ' . $p[1], $q['pairs']));
            }
            $items[] = ['bq_id' => $q['bq_id'], 'text' => $text, 'page_image' => $q['page_image'], 'show_image' => false, 'qtype' => $q['qtype'] ?? ''];
        }
        $sections[] = ['q_no' => $sec['q_no'], 'sub' => $sec['sub'], 'instruction' => $sec['instruction'], 'marks' => $sec['marks'],
            'qtype' => $sec['qtype'], 'answer_space' => '', 'oral' => false, 'chapter_ids' => $chapterIds, 'items' => $items];
    }
    if ($oral > 0) {
        $last = end($sections);
        $sections[] = ['q_no' => $last['q_no'] + 1, 'sub' => '', 'instruction' => $medium === 'English' ? 'Read the passage aloud / recite a poem.' : ($medium === 'Hindi' ? 'कविता सुनाओ / गद्यांश का वाचन करो।' : 'कविता म्हणून दाखवा / उतारा वाचा.'),
            'marks' => $oral, 'qtype' => '', 'answer_space' => 'none', 'oral' => true, 'chapter_ids' => $chapterIds, 'items' => []];
    }
    $en = $medium === 'English';
    $body = [
        'paper_id' => null, 'exam_type' => $type, 'test_no' => $testNo, 'standard' => $std, 'subject' => $subject, 'medium' => $medium,
        'title' => $en ? ($type === 'sankalit' ? 'Summative' : 'Formative') . " Evaluation Test - $testNo" : ($type === 'sankalit' ? 'संकलित' : 'आकारिक') . ' मूल्यमापन चाचणी - ' . $mr($testNo),
        'std_label' => $en ? "Std. $std" : 'इयत्ता - ' . $stdMr[$std], 'exam_name' => $type === 'sankalit' ? 'संकलित मूल्यमापन' : 'आकारिक मूल्यमापन',
        'duration' => $type === 'sankalit' ? ($en ? '2 Hrs' : '२ तास') : ($en ? '1 Hr' : '१ तास'), 'exam_date' => date('Y-m-d'),
        'total_marks' => array_sum(array_column($sections, 'marks')), 'instructions' => '', 'student_fields' => true,
        'format' => 'lines', 'oral_marks' => $oral, 'chapter_ids' => $chapterIds, 'sections' => $sections,
    ];
    $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: application/json\r\n", 'content' => json_encode($body, JSON_UNESCAPED_UNICODE), 'ignore_errors' => true]]);
    $r = json_decode((string)@file_get_contents("$base/api.php?action=save_assessment", false, $ctx), true) ?: [];
    if (($r['status'] ?? '') !== 'success') {
        fwrite(STDERR, "std $std $subject: " . ($r['message'] ?? 'save failed') . "\n");
        continue;
    }
    echo "std$std\t$subject\t$type$testNo\t$base/paper_view.php?id={$r['paper_id']}\n";
}
