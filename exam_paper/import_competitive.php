<?php
/**
 * Import the competitive objective bank (data/competitive/questions.json: 4th/7th शिष्यवृत्ती + 8th NMMS MAT/SAT,
 * scraped from downloadpapers.com) into ep_questions so the competitive paper builder / quiz can pick them per chapter.
 *
 *   php import_competitive.php
 *
 * - question_id = 5000000 + source id (the site reuses ids across categories, so the plain id may collide)
 * - markup = stem + "(A) .. (B) .. (C) .. (D) .." in the form ep_parse_mcq() understands; answer_markup = "(B) text" + solution
 * - passage of a group question is copied to its child questions
 * - the first question image is copied to data/images/<question_id>.png (has_image = 1); questions whose options are
 *   images only are skipped (cannot be rendered as text MCQ)
 * Requires ep_chapters of the competitive categories (php import_data.php) — chapters missing there are skipped.
 */
require_once __DIR__ . '/config.php';

const CQ_OFFSET = 5000000;
$src = __DIR__ . '/data/competitive/questions.json';
$data = json_decode((string)@file_get_contents($src), true);
if (!$data || empty($data['questions'])) {
    die("data/competitive/questions.json missing or invalid\n");
}
$db = ep_db();
$chapters = array_fill_keys(array_map('intval', $db->query('SELECT chapter_id FROM ep_chapters')->fetchAll(PDO::FETCH_COLUMN)), true);
$labels = ['A', 'B', 'C', 'D'];
$difficulty = ['Simple' => 1, 'Easy' => 1, 'Medium' => 2, 'Moderate' => 2, 'Hard' => 3, 'Difficult' => 3];
$clean = fn(?string $s) => trim(preg_replace('/\s+/u', ' ', strip_tags(str_replace(['<br>', '<br/>', '<br />', '</div>', '</p>'], "\n", (string)$s))));
$passages = [];
foreach ($data['questions'] as $q) {
    if (!empty($q['is_group'])) {
        $passages[$q['question_id']] = $clean($q['text']);
    }
}

$ins = $db->prepare('REPLACE INTO ep_questions (question_id, chapter_id, question_type, question_number, marks, difficulty, source, passage, markup, markup2, markup3,
                                                answer_markup, answer_markup2, has_image, has_image2, has_answer_image, sub_questions)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
@mkdir(EP_IMAGE_DIR, 0775, true);
$n = $skipped = $img = 0;
$perStd = [];
$db->beginTransaction();
foreach ($data['questions'] as $q) {
    if (!empty($q['is_group']) || !isset($chapters[(int)$q['chapter_id']])) {
        $skipped++;
        continue;
    }
    $opts = array_map(fn($o) => $clean($o['text'] ?? ''), $q['options'] ?? []);
    $hasOptImg = (bool)array_filter($q['options'] ?? [], fn($o) => !empty($o['images']));
    $opts = array_values(array_filter($opts, fn($o) => $o !== ''));
    $stem = $clean($q['text']);
    if ($hasOptImg || count($opts) < 3 || $stem === '' || !isset($q['correct_option']) || $q['correct_option'] >= count($opts)) {
        $skipped++;
        continue;
    }
    $markup = $stem;
    foreach ($opts as $i => $o) {
        $markup .= "\n(" . $labels[$i] . ') ' . $o;
    }
    $ans = '(' . $labels[(int)$q['correct_option']] . ') ' . $opts[(int)$q['correct_option']];
    $sol = $clean($q['solution'] ?? '');
    if ($sol !== '' && $sol !== $opts[(int)$q['correct_option']]) {
        $ans .= "\n" . $sol;
    }
    $qid = CQ_OFFSET + (int)$q['question_id'];
    $hasImg = 0;
    if (!empty($q['images'])) {
        $f = __DIR__ . '/data/competitive/' . $q['images'][0];
        if (is_file($f)) {
            $dst = EP_IMAGE_DIR . '/' . $qid . '.png';
            if (!is_file($dst)) {
                if (mime_content_type($f) === 'image/png') {
                    copy($f, $dst);
                } elseif (function_exists('imagecreatefromstring') && ($im = @imagecreatefromstring((string)file_get_contents($f)))) {
                    imagepng($im, $dst);
                    imagedestroy($im);
                }
            }
            $hasImg = is_file($dst) ? 1 : 0;
            $img += $hasImg;
        }
    }
    $source = trim(($q['is_pyq'] ? 'PYQ · ' : '') . ($q['topic'] ?: $q['chapter']));
    $ins->execute([$qid, (int)$q['chapter_id'], 'mcq', '1', max(1, (int)round((float)($q['marks'] ?? 1))), $difficulty[$q['difficulty'] ?? ''] ?? 2,
        mb_substr($source, 0, 50), $passages[$q['parent_id'] ?? 0] ?? '', $markup, '', '', $ans, '', $hasImg, 0, 0, null]);
    $n++;
    $perStd[$q['standard']] = ($perStd[$q['standard']] ?? 0) + 1;
}
$db->commit();
echo "imported=$n skipped=$skipped images=$img\n";
foreach ($perStd as $s => $c) {
    echo "  $s: $c\n";
}
