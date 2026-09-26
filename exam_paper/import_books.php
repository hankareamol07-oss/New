<?php
/**
 * Importer for the textbook exercise bank and the संकलित / आकारिक reference paper models.
 *
 *   mysql -u root -p school < db/schema_books.sql
 *   php import_books.php
 *
 * Reads data/book_questions.json ({books:[...], questions:[...]}), data/typed_questions.json (AI-generated
 * MiniShala-style typed set, source = 'typed', bq_id >= 1000000) and data/model_papers.json.
 * Re-runnable: rows are replaced by id, chapters of each book are rebuilt.
 */
require_once __DIR__ . '/includes/functions.php';

if (PHP_SAPI !== 'cli') {
    die("Run from the command line: php import_books.php\n");
}

$dataDir = EP_ROOT . '/data';
$db = ep_db();

// older installs: add columns introduced after the first release
foreach (['pairs_json TEXT DEFAULT NULL', "source VARCHAR(10) NOT NULL DEFAULT 'book'", 'marks TINYINT DEFAULT NULL', 'model VARCHAR(80) DEFAULT NULL'] as $col) {
    try {
        $db->exec('ALTER TABLE ep_book_questions ADD COLUMN ' . $col);
    } catch (PDOException $e) {
        // column already present
    }
}

$books = json_decode((string)@file_get_contents($dataDir . '/book_questions.json'), true);
if (!$books || empty($books['books'])) {
    die("data/book_questions.json missing or invalid\n");
}

$db->beginTransaction();
$insBook = $db->prepare('REPLACE INTO ep_books (book_id, standard, subject, medium, title, file, pages) VALUES (?,?,?,?,?,?,?)');
$delCh = $db->prepare('DELETE FROM ep_book_chapters WHERE book_id = ?');
$insCh = $db->prepare('INSERT INTO ep_book_chapters (book_id, chapter_no, title, start_page, end_page) VALUES (?,?,?,?,?)');
$delQ = $db->prepare('DELETE FROM ep_book_questions WHERE book_id = ?');
$insQ = $db->prepare('INSERT INTO ep_book_questions (bq_id, book_id, chapter_id, standard, subject, lang, page, block, instruction, qtype, text, item_no, needs_figure, page_image,
                                                     options_json, pairs_json, answer, ai_cleaned, figure_image, source, marks, model)
                      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
$delPack = $db->prepare('DELETE FROM ep_topic_packs WHERE book_id = ?');
$insPack = $db->prepare('REPLACE INTO ep_topic_packs (chapter_id, book_id, standard, subject, lang, title, notes_json, quiz_json, model) VALUES (?,?,?,?,?,?,?,?,?)');

$medium = fn(string $lang) => ['mr' => 'Marathi', 'hi' => 'Hindi', 'en' => 'English'][$lang] ?? $lang;
$chapterIds = [];   // book_id => [chapter_no => chapter_id]
$nBooks = $nCh = 0;
foreach ($books['books'] as $b) {
    $insBook->execute([$b['book_id'], $b['std'], $b['subject'], $medium($b['lang']), $b['title'], $b['file'], $b['pages']]);
    $delCh->execute([$b['book_id']]);
    $delQ->execute([$b['book_id']]);
    $delPack->execute([$b['book_id']]);
    $nBooks++;
    foreach ($b['chapters'] as $c) {
        if ((int)$c['no'] === 0) {
            continue;
        }
        $insCh->execute([$b['book_id'], $c['no'], $c['title'] ?: ('Chapter ' . $c['no']), $c['start_page'], $c['end_page']]);
        $chapterIds[$b['book_id']][$c['no']] = (int)$db->lastInsertId();
        $nCh++;
    }
}
$nQ = 0;
foreach ($books['questions'] as $q) {
    $insQ->execute([
        $q['id'], $q['book_id'], $chapterIds[$q['book_id']][$q['chapter_no']] ?? null, $q['std'], $q['subject'], $q['lang'], $q['page'],
        mb_substr((string)$q['block'], 0, 100), mb_substr((string)$q['instruction'], 0, 400), $q['qtype'], $q['text'], $q['item_no'],
        $q['needs_figure'] ? 1 : 0, $q['page_image'],
        !empty($q['options']) ? json_encode($q['options'], JSON_UNESCAPED_UNICODE) : null,
        !empty($q['pairs']) ? json_encode($q['pairs'], JSON_UNESCAPED_UNICODE) : null, $q['answer'] ?? null,
        !empty($q['ai_cleaned']) ? 1 : 0, $q['figure_image'] ?? null, 'book', null, null,
    ]);
    $nQ++;
}
// AI-generated typed set: one JSON per chapter merged into data/typed_questions.json by books/merge_typed.py
$nT = 0;
foreach (json_decode((string)@file_get_contents($dataDir . '/typed_questions.json'), true)['questions'] ?? [] as $q) {
    $cid = $chapterIds[$q['book_id']][$q['chapter_no']] ?? null;
    if (!$cid) {
        continue;
    }
    $insQ->execute([
        $q['id'], $q['book_id'], $cid, $q['std'], $q['subject'], $q['lang'], 0, 'सराव प्रश्नसंच (AI)', mb_substr((string)$q['instruction'], 0, 400), $q['qtype'], $q['text'], $q['item_no'] ?? null,
        0, null,
        !empty($q['options']) ? json_encode($q['options'], JSON_UNESCAPED_UNICODE) : null,
        !empty($q['pairs']) ? json_encode($q['pairs'], JSON_UNESCAPED_UNICODE) : null, $q['answer'] ?? null,
        1, null, 'typed', $q['marks'] ?? null, $q['model'] ?? null,
    ]);
    $nT++;
}
$nP = 0;
foreach (json_decode((string)@file_get_contents($dataDir . '/topic_packs.json'), true) ?: [] as $p) {
    $cid = $chapterIds[$p['book_id']][$p['chapter_no']] ?? null;
    if (!$cid || count($p['quiz']) < 5) {
        continue;
    }
    $insPack->execute([$cid, $p['book_id'], $p['std'], $p['subject'], $p['lang'], $p['chapter'],
        json_encode($p['notes'], JSON_UNESCAPED_UNICODE), json_encode($p['quiz'], JSON_UNESCAPED_UNICODE), $p['model'] ?? null]);
    $nP++;
}
$db->commit();
echo "books=$nBooks chapters=$nCh questions=$nQ typed_questions=$nT topic_packs=$nP\n";

$models = json_decode((string)@file_get_contents($dataDir . '/model_papers.json'), true);
if ($models) {
    $db->beginTransaction();
    $db->exec('DELETE FROM ep_paper_models');
    $ins = $db->prepare('INSERT INTO ep_paper_models (exam_type, test_no, standard, subject, total_marks, pages, source_file, sections_json) VALUES (?,?,?,?,?,?,?,?)');
    $n = 0;
    foreach ($models as $m) {
        if (empty($m['sections'])) {
            continue;
        }
        $ins->execute([$m['exam_type'], $m['test_no'], $m['std'], $m['subject'], $m['total_marks'], $m['pages'], $m['file'],
            json_encode($m['sections'], JSON_UNESCAPED_UNICODE)]);
        $n++;
    }
    $db->commit();
    echo "paper models=$n\n";
}
