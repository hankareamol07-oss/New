<?php
/**
 * Topic notes (ep_topic_packs.notes_json):
 *  - removes repeated points in every chapter's notes
 *  - applies regenerated notes from data/topic_notes.json ({ch, book_id, standard, subject, lang, title, notes[], model})
 *    for chapters whose notes were empty, too short or referred to "the text/song"; chapters without a
 *    topic pack get a new pack (notes only, quiz empty)
 * A JSON backup of changed packs is written to data/backup_topic_notes_<time>.json.
 *
 *   php tools/apply_topic_notes.php          (report only)
 *   php tools/apply_topic_notes.php --apply  (backup + save)
 */
require_once __DIR__ . '/../includes/functions.php';

$apply = in_array('--apply', $argv, true);
$dir = __DIR__ . '/../data/';
$new = json_decode((string)@file_get_contents($dir . 'topic_notes.json'), true) ?: [];
$byCh = [];
foreach ($new as $n) $byCh[(int)$n['ch']] = $n;
$db = ep_db();
$rows = $db->query('SELECT chapter_id, notes_json FROM ep_topic_packs')->fetchAll(PDO::FETCH_KEY_PAIR);
$update = [];
$insert = [];
$dedup = 0;
foreach ($rows as $ch => $json) {
    if (isset($byCh[$ch])) { $update[$ch] = json_encode(array_values($byCh[$ch]['notes']), JSON_UNESCAPED_UNICODE); continue; }
    $notes = json_decode((string)$json, true);
    if (!is_array($notes)) continue;
    $u = array_values(array_unique(array_map(fn($x) => trim((string)$x), $notes)));
    if (count($u) !== count($notes)) { $update[$ch] = json_encode($u, JSON_UNESCAPED_UNICODE); $dedup++; }
}
foreach ($byCh as $ch => $n) if (!array_key_exists($ch, $rows)) $insert[$ch] = $n;
echo 'regenerated notes : ' . (count($update) - $dedup) . "\nrepeated points removed in : $dedup packs\nnew packs (notes only) : " . count($insert) . "\n";
if (!$apply) { echo "Report only. Run with --apply to save.\n"; exit; }
file_put_contents($dir . 'backup_topic_notes_' . date('Ymd_His') . '.json', json_encode(array_intersect_key($rows, $update), JSON_UNESCAPED_UNICODE));
$db->beginTransaction();
$up = $db->prepare('UPDATE ep_topic_packs SET notes_json = ? WHERE chapter_id = ?');
foreach ($update as $ch => $j) $up->execute([$j, $ch]);
$ins = $db->prepare('INSERT INTO ep_topic_packs (chapter_id, book_id, standard, subject, lang, title, notes_json, quiz_json, model, created_at) VALUES (?,?,?,?,?,?,?,?,?,?)');
foreach ($insert as $ch => $n) $ins->execute([$ch, $n['book_id'], $n['standard'], $n['subject'], $n['lang'], $n['title'], json_encode($n['notes'], JSON_UNESCAPED_UNICODE), '[]', $n['model'], date('Y-m-d H:i:s')]);
$db->commit();
echo "Saved.\n";
