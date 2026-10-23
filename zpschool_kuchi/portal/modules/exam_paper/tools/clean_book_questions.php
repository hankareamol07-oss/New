<?php
/**
 * Checks and cleans the textbook exercise bank (ep_book_questions) and its picture questions.
 *  - repeated questions inside the same book (same text + type)      -> removed (first copy kept)
 *  - "जोड्या लावा" rows that have no pairs and only OCR noise          -> removed
 *  - figure image taken from a different book                         -> figure link cleared
 *  - page / figure image file not present on this server              -> link cleared
 *  - "needs figure" but no image at all                               -> needs_figure = 0
 * Every changed or removed row is saved to data/backup_book_questions_<time>.json first.
 *
 *   php tools/clean_book_questions.php          (report only)
 *   php tools/clean_book_questions.php --apply  (backup + save)
 */
require_once __DIR__ . '/../includes/functions.php';

$apply = in_array('--apply', $argv, true);
$db = ep_db();
$dir = __DIR__ . '/../data/';
$rows = $db->query('SELECT * FROM ep_book_questions ORDER BY bq_id')->fetchAll(PDO::FETCH_ASSOC);
$seen = [];
$delete = [];
$update = [];
$st = ['questions' => count($rows), 'duplicate' => 0, 'match_no_pairs_junk' => 0, 'figure_other_book' => 0,
       'page_image_missing' => 0, 'figure_missing' => 0, 'needs_figure_no_image' => 0, 'no_answer' => 0, 'too_short' => 0];
foreach ($rows as $r) {
    $id = (int)$r['bq_id'];
    $text = trim((string)$r['text']);
    $key = $r['book_id'] . '|' . $r['qtype'] . '|' . mb_strtolower(preg_replace('/\s+/u', ' ', $text));
    if (isset($seen[$key])) { $st['duplicate']++; $delete[$id] = $r; continue; }
    $seen[$key] = 1;
    if ($r['qtype'] === 'match' && empty($r['pairs_json']) && !preg_match('/\p{L}{3,}.*\p{L}{3,}/u', $text)) {
        $st['match_no_pairs_junk']++; $delete[$id] = $r; continue;
    }
    if (mb_strlen($text) < 8) $st['too_short']++;
    if (trim((string)$r['answer']) === '') $st['no_answer']++;
    $set = [];
    $fig = (string)($r['figure_image'] ?? '');
    if ($fig !== '' && (int)strtok($fig, '/') !== (int)$r['book_id']) { $st['figure_other_book']++; $set['figure_image'] = null; $fig = ''; }
    if ($fig !== '' && !is_file($dir . 'book_figures/' . $fig)) { $st['figure_missing']++; $set['figure_image'] = null; $fig = ''; }
    $page = (string)($r['page_image'] ?? '');
    if ($page !== '' && !is_file($dir . 'book_pages/' . $page)) { $st['page_image_missing']++; $set['page_image'] = null; $page = ''; }
    if ((int)$r['needs_figure'] === 1 && $fig === '' && $page === '') { $st['needs_figure_no_image']++; $set['needs_figure'] = 0; }
    if ($set) $update[$id] = [$set, $r];
}
foreach ($st as $k => $v) echo str_pad($k, 22) . ": $v\n";
echo 'rows to remove: ' . count($delete) . ', rows to fix: ' . count($update) . "\n";
if (!$apply) { echo "Report only. Run with --apply to save.\n"; exit; }
$file = $dir . 'backup_book_questions_' . date('Ymd_His') . '.json';
file_put_contents($file, json_encode(['removed' => array_values($delete), 'changed' => array_map(fn($u) => $u[1], array_values($update))], JSON_UNESCAPED_UNICODE));
$db->beginTransaction();
$del = $db->prepare('DELETE FROM ep_book_questions WHERE bq_id = ?');
foreach (array_keys($delete) as $id) $del->execute([$id]);
foreach ($update as $id => [$set, $r]) {
    $cols = implode(', ', array_map(fn($c) => "$c = ?", array_keys($set)));
    $db->prepare("UPDATE ep_book_questions SET $cols WHERE bq_id = ?")->execute([...array_values($set), $id]);
}
$db->commit();
echo "Saved. Backup: $file\n";
