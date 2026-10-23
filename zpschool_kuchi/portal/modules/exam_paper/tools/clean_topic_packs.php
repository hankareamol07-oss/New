<?php
/**
 * Cleans the topic-wise quiz bank (ep_topic_packs.quiz_json):
 *  - removes repeated questions inside a chapter
 *  - removes questions with empty / repeated options or an invalid answer
 *  - balances the correct-answer position (it was mostly option 2 or 3) by moving the correct option
 *    to a stable position derived from the question text
 * A JSON backup of every changed pack is written to data/backup_topic_packs_<time>.json first.
 *
 *   php tools/clean_topic_packs.php          (report only)
 *   php tools/clean_topic_packs.php --apply  (backup + save)
 */
require_once __DIR__ . '/../includes/functions.php';

$apply = in_array('--apply', $argv, true);
$db = ep_db();
$rows = $db->query('SELECT chapter_id, quiz_json FROM ep_topic_packs')->fetchAll(PDO::FETCH_ASSOC);
$st = ['packs' => count($rows), 'questions' => 0, 'dup_question' => 0, 'bad_options' => 0, 'bad_answer' => 0, 'moved_answer' => 0, 'kept' => 0];
$pos = [0, 0, 0, 0];
$changed = [];
$backup = [];
foreach ($rows as $r) {
    $quiz = json_decode((string)$r['quiz_json'], true);
    if (!is_array($quiz)) continue;
    $seen = [];
    $new = [];
    foreach ($quiz as $q) {
        $st['questions']++;
        $text = trim((string)($q['q'] ?? ''));
        $opts = array_map(fn($o) => trim((string)$o), $q['options'] ?? []);
        $key = mb_strtolower(preg_replace('/\s+/u', ' ', $text));
        if ($text === '' || isset($seen[$key])) { $st['dup_question']++; continue; }
        $norm = array_map(fn($o) => mb_strtolower($o), $opts);
        if (count($opts) < 2 || in_array('', $opts, true) || count(array_unique($norm)) !== count($opts)) { $st['bad_options']++; continue; }
        $a = $q['answer'] ?? null;
        if (!is_int($a) || $a < 0 || $a >= count($opts)) { $st['bad_answer']++; continue; }
        $target = crc32($key) % count($opts);
        if ($target !== $a) {
            [$opts[$a], $opts[$target]] = [$opts[$target], $opts[$a]];
            $q['answer'] = $target;
            $st['moved_answer']++;
        }
        $q['options'] = $opts;
        $q['q'] = $text;
        $seen[$key] = 1;
        $pos[$q['answer']] = ($pos[$q['answer']] ?? 0) + 1;
        $new[] = $q;
        $st['kept']++;
    }
    $json = json_encode($new, JSON_UNESCAPED_UNICODE);
    if ($json !== $r['quiz_json']) {
        $changed[$r['chapter_id']] = $json;
        $backup[$r['chapter_id']] = $r['quiz_json'];
    }
}
foreach ($st as $k => $v) echo str_pad($k, 14) . ": $v\n";
echo 'answer position after: ' . json_encode($pos) . "\nchanged packs : " . count($changed) . "\n";
if (!$apply) { echo "Report only. Run with --apply to save.\n"; exit; }
$file = __DIR__ . '/../data/backup_topic_packs_' . date('Ymd_His') . '.json';
file_put_contents($file, json_encode($backup, JSON_UNESCAPED_UNICODE));
$up = $db->prepare('UPDATE ep_topic_packs SET quiz_json = ? WHERE chapter_id = ?');
$db->beginTransaction();
foreach ($changed as $id => $json) $up->execute([$json, $id]);
$db->commit();
echo "Saved. Backup: $file\n";
