<?php
$db = new PDO('sqlite:c:/xampp/htdocs/hpc/data/exam_paper.sqlite');

$total = $db->query("SELECT COUNT(*) FROM ep_questions")->fetchColumn();
$hasImg = $db->query("SELECT COUNT(*) FROM ep_questions WHERE has_image = 1")->fetchColumn();
$withImgInMarkup = $db->query("SELECT COUNT(*) FROM ep_questions WHERE markup LIKE '%<img%' OR markup2 LIKE '%<img%' OR passage LIKE '%<img%'")->fetchColumn();
$compTotal = $db->query("SELECT COUNT(*) FROM ep_questions WHERE source = 'Competitive'")->fetchColumn();
$compWithImg = $db->query("SELECT COUNT(*) FROM ep_questions WHERE source = 'Competitive' AND (markup LIKE '%<img%' OR markup2 LIKE '%<img%' OR passage LIKE '%<img%')")->fetchColumn();

echo "ep_questions total: $total\n";
echo "has_image = 1: $hasImg\n";
echo "With <img> in markup/markup2/passage: $withImgInMarkup\n";
echo "Competitive questions total: $compTotal\n";
echo "Competitive with <img>: $compWithImg\n";

// Now check ep_book_questions
$bqTotal = $db->query("SELECT COUNT(*) FROM ep_book_questions")->fetchColumn();
$bqFig = $db->query("SELECT COUNT(*) FROM ep_book_questions WHERE figure_image IS NOT NULL AND figure_image != ''")->fetchColumn();
$bqPage = $db->query("SELECT COUNT(*) FROM ep_book_questions WHERE page_image IS NOT NULL AND page_image != ''")->fetchColumn();

echo "\nep_book_questions total: $bqTotal\n";
echo "With figure_image: $bqFig\n";
echo "With page_image: $bqPage\n";
