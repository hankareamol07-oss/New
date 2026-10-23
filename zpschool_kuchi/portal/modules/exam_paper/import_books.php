<?php
/**
 * Importer for the textbook exercise bank and the संकलित / आकारिक reference paper models.
 *
 *   mysql -u root -p school < db/schema_books.sql
 *   php import_books.php
 *
 * Reads data/book_questions.json ({books:[...], questions:[...]}) and data/model_papers.json.
 * Re-runnable: rows are replaced by id, chapters of each book are rebuilt.
 */
require_once __DIR__ . '/includes/functions.php';

if (PHP_SAPI !== 'cli') {
    die("Run from the command line: php import_books.php\n");
}

$dataDir = EP_ROOT . '/data';
$db = ep_db();

$books = json_decode((string)@file_get_contents($dataDir . '/book_questions.json'), true);
if (!$books || empty($books['books'])) {
    die("data/book_questions.json missing or invalid\n");
}

$db->beginTransaction();
$insBook = $db->prepare('REPLACE INTO ep_books (book_id, standard, subject, medium, title, file, pages) VALUES (?,?,?,?,?,?,?)');
$delCh = $db->prepare('DELETE FROM ep_book_chapters WHERE book_id = ?');
$insCh = $db->prepare('INSERT INTO ep_book_chapters (book_id, chapter_no, title, start_page, end_page) VALUES (?,?,?,?,?)');
$delQ = $db->prepare('DELETE FROM ep_book_questions WHERE book_id = ?');
$insQ = $db->prepare('INSERT INTO ep_book_questions (bq_id, book_id, chapter_id, standard, subject, lang, page, block, instruction, qtype, text, item_no, needs_figure, page_image)
                      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');

$medium = fn(string $lang) => ['mr' => 'Marathi', 'hi' => 'Hindi', 'en' => 'English'][$lang] ?? $lang;
$chapterIds = [];   // book_id => [chapter_no => chapter_id]
$nBooks = $nCh = 0;
foreach ($books['books'] as $b) {
    $insBook->execute([$b['book_id'], $b['std'], $b['subject'], $medium($b['lang']), $b['title'], $b['file'], $b['pages']]);
    $delCh->execute([$b['book_id']]);
    $delQ->execute([$b['book_id']]);
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
    ]);
    $nQ++;
}
$db->commit();
echo "books=$nBooks chapters=$nCh questions=$nQ\n";

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
