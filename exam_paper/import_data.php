<?php
/**
 * One-time importer: loads data/hierarchy.json and data/questions/*.json into the ep_* tables.
 *
 *   mysql -u root -p school < db/schema.sql
 *   php import_data.php
 */
require_once __DIR__ . '/includes/functions.php';

if (PHP_SAPI !== 'cli') {
    die("Run from the command line: php import_data.php\n");
}

$dataDir = EP_ROOT . '/data';
$hierarchy = json_decode(file_get_contents($dataDir . '/hierarchy.json'), true);
if (!$hierarchy) {
    die("data/hierarchy.json missing or invalid\n");
}

$imageIndex = [];
if (is_file($dataDir . '/images_index.json')) {
    $imageIndex = json_decode(file_get_contents($dataDir . '/images_index.json'), true) ?: [];
}
$img = function ($qid, string $key) use ($imageIndex): int {
    return !empty($imageIndex[(string)$qid][$key]) ? 1 : 0;
};

$db = ep_db();
$db->beginTransaction();

$insStd = $db->prepare('REPLACE INTO ep_standards (standard_id, name, medium, board, sort_order) VALUES (?,?,?,?,?)');
$insSub = $db->prepare('REPLACE INTO ep_subjects (subject_id, standard_id, name) VALUES (?,?,?)');
$insCh = $db->prepare('REPLACE INTO ep_chapters (chapter_id, subject_id, name, sort_order) VALUES (?,?,?,?)');
$insTpl = $db->prepare('REPLACE INTO ep_templates (template_id, subject_id, title, total_marks, description) VALUES (?,?,?,?,?)');
$insTq = $db->prepare('REPLACE INTO ep_template_questions (tq_id, template_id, question_title, marks, question_type, question_number, no_of_questions, sort_order) VALUES (?,?,?,?,?,?,?,?)');
$insQ = $db->prepare('REPLACE INTO ep_questions (question_id, chapter_id, question_type, question_number, marks, difficulty, source, passage, markup, markup2, markup3, answer_markup, answer_markup2, has_image, has_image2, has_answer_image, sub_questions)
                      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');

$counts = ['standards' => 0, 'subjects' => 0, 'chapters' => 0, 'templates' => 0, 'questions' => 0];
$chapterIds = [];

foreach ($hierarchy as $std) {
    $order = (int)preg_replace('/\D.*/', '', trim($std['name'])) ?: 99;
    $insStd->execute([$std['standardid'], trim($std['name']), trim($std['medium'] ?? ''), $std['board'] ?? null, $order]);
    $counts['standards']++;
    foreach ($std['subjects'] as $sub) {
        if (empty($sub['chapters'])) {
            continue; // subject without any accessible content
        }
        $insSub->execute([$sub['subjectid'], $std['standardid'], trim($sub['name'])]);
        $counts['subjects']++;
        foreach ($sub['chapters'] as $ch) {
            $insCh->execute([$ch['chapterid'], $sub['subjectid'], trim($ch['name']), (int)($ch['sortorder'] ?? 0)]);
            $chapterIds[$ch['chapterid']] = true;
            $counts['chapters']++;
        }
        foreach ($sub['templates'] ?? [] as $tpl) {
            $insTpl->execute([$tpl['tid'], $sub['subjectid'], trim($tpl['title']), (int)$tpl['marks'], $tpl['description'] ?? null]);
            $counts['templates']++;
            foreach ($tpl['templatequestionList'] ?? [] as $i => $tq) {
                if (trim($tq['questiontype']) === '' || trim($tq['questionnumber']) === '') {
                    continue;
                }
                $insTq->execute([$tq['tqid'], $tpl['tid'], trim($tq['questiontitle']), (int)$tq['marks'],
                    ep_normalize_type($tq['questiontype']), trim($tq['questionnumber']), (int)$tq['noofquestions'], $i]);
            }
        }
    }
}

foreach (glob($dataDir . '/questions/*.json') as $file) {
    $questions = json_decode(file_get_contents($file), true) ?: [];
    foreach ($questions as $q) {
        if (!isset($chapterIds[$q['chapterid']])) {
            continue;
        }
        $subs = [];
        foreach ($q['questionList'] ?? [] as $sq) {
            $subs[] = [
                'markup' => $sq['markup'] ?? '', 'answer_markup' => $sq['answermarkup'] ?? '',
                'marks' => $sq['marks'] ?? null, 'question_id' => $sq['questionid'] ?? null,
                'has_image' => (bool)$img($sq['questionid'] ?? 0, 'image'),
                'has_answer_image' => (bool)$img($sq['questionid'] ?? 0, 'answer'),
            ];
        }
        $insQ->execute([
            $q['questionid'], $q['chapterid'], ep_normalize_type($q['type']), trim($q['questionno']),
            (int)($q['marks'] ?? 1), (int)($q['difficulty'] ?? 2), $q['source'] ?? null,
            $q['passage'] ?? '', $q['markup'] ?? '', $q['markup2'] ?? '', $q['markup3'] ?? '',
            $q['answermarkup'] ?? '', $q['answermarkup2'] ?? '',
            $img($q['questionid'], 'image'), $img($q['questionid'], 'image2'), $img($q['questionid'], 'answer'),
            $subs ? json_encode($subs, JSON_UNESCAPED_UNICODE) : null,
        ]);
        $counts['questions']++;
    }
    echo basename($file), ': ', count($questions), " questions\n";
}

$db->commit();
echo "Imported: ", json_encode($counts), "\n";
