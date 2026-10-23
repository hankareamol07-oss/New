<?php
/**
 * Applies AI-rewritten topic-quiz questions (data/quiz_rewrites.json) to ep_topic_packs.
 * Each entry: {ch, old, q, options[4], answer} or {ch, old, drop: true} (question that cannot be asked
 * without the textbook page, e.g. "What does the text ask the teacher to do?" — removed). A question is replaced only when its current text
 * still equals "old", so it is safe to run more than once. Run tools/clean_topic_packs.php --apply first.
 * A JSON backup of every changed pack is written to data/backup_quiz_rewrites_<time>.json.
 *
 *   php tools/apply_quiz_rewrites.php          (report only)
 *   php tools/apply_quiz_rewrites.php --apply  (backup + save)
 */
require_once __DIR__ . '/../includes/functions.php';

$apply = in_array('--apply', $argv, true);
$dir = __DIR__ . '/../data/';
$fixes = json_decode((string)@file_get_contents($dir . 'quiz_rewrites.json'), true);
if (!is_array($fixes)) exit("data/quiz_rewrites.json not found\n");
$byCh = [];
foreach ($fixes as $f) $byCh[(int)$f['ch']][trim((string)$f['old'])] = $f;
$db = ep_db();
$get = $db->prepare('SELECT quiz_json FROM ep_topic_packs WHERE chapter_id = ?');
$changed = [];
$backup = [];
$n = 0;
$dropped = 0;
foreach ($byCh as $ch => $list) {
    $get->execute([$ch]);
    $json = $get->fetchColumn();
    if ($json === false) continue;
    $quiz = json_decode((string)$json, true) ?: [];
    $hit = false;
    $before = count($quiz);
    $quiz = array_values(array_filter($quiz, fn($q) => empty($list[trim((string)($q['q'] ?? ''))]['drop'])));
    $dropped += $before - count($quiz);
    $hit = $before !== count($quiz);
    foreach ($quiz as &$q) {
        $f = $list[trim((string)($q['q'] ?? ''))] ?? null;
        if (!$f) continue;
        $q['q'] = $f['q'];
        $q['options'] = array_values($f['options']);
        $q['answer'] = (int)$f['answer'];
        $q['ai_fixed'] = 1;
        $hit = true;
        $n++;
    }
    unset($q);
    if ($hit) { $changed[$ch] = json_encode($quiz, JSON_UNESCAPED_UNICODE); $backup[$ch] = $json; }
}
echo "rewrites in file: " . count($fixes) . "\nquestions rewritten: $n\nquestions removed: $dropped\npacks changed: " . count($changed) . "\n";
if (!$apply) { echo "Report only. Run with --apply to save.\n"; exit; }
file_put_contents($dir . 'backup_quiz_rewrites_' . date('Ymd_His') . '.json', json_encode($backup, JSON_UNESCAPED_UNICODE));
$up = $db->prepare('UPDATE ep_topic_packs SET quiz_json = ? WHERE chapter_id = ?');
$db->beginTransaction();
foreach ($changed as $ch => $j) $up->execute([$j, $ch]);
$db->commit();
echo "Saved.\n";
