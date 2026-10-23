<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/answer_space.php';

/**
 * Premium Word (.docx) Exporter ONLY for Aakarik and Sankalit assessment papers.
 * Creates clean, native Microsoft Word (.docx) files without requiring external libraries.
 */
function ep_export_paper_docx(int $paperId, string $set = 'A'): void
{
    $paper = ep_paper($paperId);
    if (!$paper) {
        http_response_code(404);
        die('Paper not found.');
    }

    // STRICT CHECK: Only allowed for Aakarik and Sankalit papers!
    $pType = $paper['paper_type'] ?? 'regular';
    $meta = $paper['meta'] ?? [];
    $examType = $meta['exam_type'] ?? '';
    $isAssessment = in_array($pType, ['assessment', 'daily_study'], true) || in_array($examType, ['sankalit', 'aakarik', 'daily_study'], true);

    if (!$isAssessment) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;padding:30px;text-align:center;">
            <h2 style="color:#d9534f;">हे वैशिष्ट्य केवळ आकारिक व संकलित मूल्यमापन चाचण्यांसाठी आहे</h2>
            <p>Premium Word (.docx) Export is available exclusively for Aakarik and Sankalit evaluation papers.</p>
            <a href="index.php">परत जा</a></div>');
    }

    if ($set !== 'A') {
        $paper = ep_scramble_paper($paper, $set);
    }

    $schoolName = ep_setting('school_name', 'माझी शाळा');
    $schoolAddr = ep_setting('school_address', 'महाराष्ट्र राज्य');
    $hub = ep_get_hub_school();
    if ($hub) {
        $schoolName = $hub['name_mr'] ?: ($hub['name'] ?: $schoolName);
        if (!empty($hub['formatted_address'])) {
            $schoolAddr = $hub['formatted_address'];
        }
    }

    $std = (int)($meta['standard'] ?? 0);
    $stdLabel = $paper['std_label'] ?: ($std ? "इयत्ता - {$std} वी" : '');
    $subject = $meta['subject'] ?? ($paper['subject_name'] ?? '');
    $english = ($meta['medium'] ?? '') === 'English';
    $totalMarks = (float)$paper['total_marks'];

    // Helper to escape XML
    $x = fn($str) => htmlspecialchars((string)$str, ENT_QUOTES | ENT_XML1, 'UTF-8');

    // Build document.xml body
    $bodyXml = '';

    // 1. School Header
    $bodyXml .= '<w:p><w:pPr><w:jc w:val="center"/><w:spacing w:after="60"/></w:pPr>
        <w:r><w:rPr><w:rFonts w:ascii="Nirmala UI" w:hAnsi="Nirmala UI" w:cs="Nirmala UI"/><w:b/><w:sz w:val="34"/><w:szCs w:val="34"/><w:color w:val="0F172A"/></w:rPr>
        <w:t>' . $x($schoolName) . '</w:t></w:r></w:p>';

    if ($schoolAddr) {
        $bodyXml .= '<w:p><w:pPr><w:jc w:val="center"/><w:spacing w:after="100"/></w:pPr>
            <w:r><w:rPr><w:rFonts w:ascii="Nirmala UI" w:hAnsi="Nirmala UI" w:cs="Nirmala UI"/><w:sz w:val="20"/><w:szCs w:val="20"/><w:color w:val="475569"/></w:rPr>
            <w:t>' . $x($schoolAddr) . '</w:t></w:r></w:p>';
    }

    // Exam Title & Set Badge
    $examTitle = $paper['title'];
    if ($set && $set !== 'A') {
        $examTitle .= " (संच {$set})";
    }
    $bodyXml .= '<w:p><w:pPr><w:jc w:val="center"/><w:spacing w:after="140"/><w:pBdr><w:bottom w:val="single" w:sz="12" w:space="6" w:color="000000"/></w:pBdr></w:pPr>
        <w:r><w:rPr><w:rFonts w:ascii="Nirmala UI" w:hAnsi="Nirmala UI" w:cs="Nirmala UI"/><w:b/><w:sz w:val="26"/><w:szCs w:val="26"/><w:color w:val="000000"/></w:rPr>
        <w:t>' . $x($examTitle) . '</w:t></w:r></w:p>';

    // Meta Table (Std, Subject, Date, Time, Marks)
    $examDate = $paper['exam_date'] ? date('d/m/Y', strtotime($paper['exam_date'])) : '............';
    $duration = $paper['duration'] ?: ($english ? '1 Hr' : '१ तास');

    $bodyXml .= '<w:tbl>
      <w:tblPr><w:tblW w:w="5000" w:type="pct"/><w:tblBorders><w:top w:val="none"/><w:left w:val="none"/><w:bottom w:val="none"/><w:right w:val="none"/><w:insideH w:val="none"/><w:insideV w:val="none"/></w:tblBorders></w:tblPr>
      <w:tr>
        <w:tc><w:tcPr><w:tcW w:w="1600" w:type="pct"/></w:tcPr><w:p><w:r><w:rPr><w:rFonts w:ascii="Nirmala UI" w:hAnsi="Nirmala UI" w:cs="Nirmala UI"/><w:b/><w:sz w:val="21"/></w:rPr><w:t>' . ($english ? 'Std: ' : 'इयत्ता: ') . $x($stdLabel) . '</w:t></w:r></w:p></w:tc>
        <w:tc><w:tcPr><w:tcW w:w="1800" w:type="pct"/></w:tcPr><w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:rFonts w:ascii="Nirmala UI" w:hAnsi="Nirmala UI" w:cs="Nirmala UI"/><w:b/><w:sz w:val="21"/></w:rPr><w:t>' . ($english ? 'Subject: ' : 'विषय: ') . $x($subject) . '</w:t></w:r></w:p></w:tc>
        <w:tc><w:tcPr><w:tcW w:w="1600" w:type="pct"/></w:tcPr><w:p><w:pPr><w:jc w:val="right"/></w:pPr><w:r><w:rPr><w:rFonts w:ascii="Nirmala UI" w:hAnsi="Nirmala UI" w:cs="Nirmala UI"/><w:b/><w:sz w:val="21"/></w:rPr><w:t>' . ($english ? 'Total Marks: ' : 'एकूण गुण: ') . $totalMarks . '</w:t></w:r></w:p></w:tc>
      </w:tr>
      <w:tr>
        <w:tc><w:tcPr><w:tcW w:w="1600" w:type="pct"/></w:tcPr><w:p><w:r><w:rPr><w:rFonts w:ascii="Nirmala UI" w:hAnsi="Nirmala UI" w:cs="Nirmala UI"/><w:sz w:val="20"/></w:rPr><w:t>' . ($english ? 'Date: ' : 'दिनांक: ') . $examDate . '</w:t></w:r></w:p></w:tc>
        <w:tc><w:tcPr><w:tcW w:w="1800" w:type="pct"/></w:tcPr><w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:rFonts w:ascii="Nirmala UI" w:hAnsi="Nirmala UI" w:cs="Nirmala UI"/><w:sz w:val="20"/></w:rPr><w:t>' . ($english ? 'Time: ' : 'वेळ: ') . $x($duration) . '</w:t></w:r></w:p></w:tc>
        <w:tc><w:tcPr><w:tcW w:w="1600" w:type="pct"/></w:tcPr><w:p><w:pPr><w:jc w:val="right"/></w:pPr><w:r><w:rPr><w:rFonts w:ascii="Nirmala UI" w:hAnsi="Nirmala UI" w:cs="Nirmala UI"/><w:b/><w:sz w:val="20"/></w:rPr><w:t>' . ($english ? 'Set: ' : 'संच: ') . $set . '</w:t></w:r></w:p></w:tc>
      </w:tr>
    </w:tbl>';

    // Student Info line
    $bodyXml .= '<w:p><w:pPr><w:spacing w:before="120" w:after="160"/><w:pBdr><w:bottom w:val="single" w:sz="6" w:space="6" w:color="777777"/></w:pBdr></w:pPr>
        <w:r><w:rPr><w:rFonts w:ascii="Nirmala UI" w:hAnsi="Nirmala UI" w:cs="Nirmala UI"/><w:b/><w:sz w:val="21"/></w:rPr><w:t>' . ($english ? 'Student Name: ' : 'विद्यार्थ्याचे नाव: ') . '___________________________________________  ' . ($english ? 'Roll No: ' : 'हजेरी क्र.: ') . '________  ' . ($english ? 'Marks: ' : 'मिळालेले गुण: ') . '____/' . $totalMarks . '</w:t></w:r></w:p>';

    // Instructions
    if (trim($paper['instructions'])) {
        $bodyXml .= '<w:p><w:pPr><w:spacing w:before="80" w:after="120"/></w:pPr>
            <w:r><w:rPr><w:rFonts w:ascii="Nirmala UI" w:hAnsi="Nirmala UI" w:cs="Nirmala UI"/><w:b/><w:i/><w:sz w:val="20"/></w:rPr><w:t>' . ($english ? 'Instructions: ' : 'सूचना: ') . $x(trim($paper['instructions'])) . '</w:t></w:r></w:p>';
    }

    // Sections and Questions
    foreach ($paper['sections'] as $sec) {
        $qNo = $sec['q_no'] ?? 1;
        $sub = $sec['sub'] ?? '';
        $qLabel = ($english ? 'Q.' : 'प्र.') . $qNo . ($sub !== '' ? " ({$sub})" : '');
        $instr = trim($sec['instruction'] ?? '');
        $secMarks = (float)($sec['marks'] ?? 0);

        // Section Title Paragraph
        $bodyXml .= '<w:p><w:pPr><w:spacing w:before="220" w:after="80"/><w:pBdr><w:bottom w:val="dotted" w:sz="6" w:space="3" w:color="AAAAAA"/></w:pBdr></w:pPr>
            <w:r><w:rPr><w:rFonts w:ascii="Nirmala UI" w:hAnsi="Nirmala UI" w:cs="Nirmala UI"/><w:b/><w:sz w:val="23"/><w:color w:val="0F172A"/></w:rPr><w:t>' . $x($qLabel) . ' ' . $x($instr) . '</w:t></w:r>
            <w:r><w:rPr><w:rFonts w:ascii="Nirmala UI" w:hAnsi="Nirmala UI" w:cs="Nirmala UI"/><w:b/><w:sz w:val="22"/><w:color w:val="2563EB"/></w:rPr><w:t>  (' . $secMarks . ' ' . ($english ? 'Marks' : 'गुण') . ')</w:t></w:r>
            </w:p>';

        $items = $sec['items'] ?? [];
        foreach ($items as $idx => $it) {
            $itemNum = ($idx + 1) . ')';
            $text = trim($it['text'] ?? '');
            $pairs = ep_item_pairs($it);
            $itemSpace = ep_answer_space($sec, $std, $text);
            $mode = $pairs ? 'none' : $itemSpace['mode'];

            // Question Text Paragraph
            $bodyXml .= '<w:p><w:pPr><w:spacing w:before="80" w:after="60"/><w:ind w:left="360" w:hanging="360"/></w:pPr>
                <w:r><w:rPr><w:rFonts w:ascii="Nirmala UI" w:hAnsi="Nirmala UI" w:cs="Nirmala UI"/><w:b/><w:sz w:val="22"/></w:rPr><w:t>' . $itemNum . ' </w:t></w:r>
                <w:r><w:rPr><w:rFonts w:ascii="Nirmala UI" w:hAnsi="Nirmala UI" w:cs="Nirmala UI"/><w:sz w:val="22"/></w:rPr><w:t>' . $x($text) . '</w:t></w:r></w:p>';

            // If Match pairs: render 2-column Word Table
            if ($pairs && !empty($pairs['a']) && !empty($pairs['b'])) {
                $bodyXml .= '<w:tbl>
                  <w:tblPr><w:tblW w:w="4500" w:type="pct"/><w:tblBorders><w:top w:val="single" w:sz="6" w:color="888888"/><w:left w:val="single" w:sz="6" w:color="888888"/><w:bottom w:val="single" w:sz="6" w:color="888888"/><w:right w:val="single" w:sz="6" w:color="888888"/><w:insideH w:val="single" w:sz="4" w:color="CCCCCC"/><w:insideV w:val="single" w:sz="4" w:color="CCCCCC"/></w:tblBorders></w:tblPr>
                  <w:tr>
                    <w:tc><w:tcPr><w:tcW w:w="2500" w:type="pct"/><w:shd w:val="clear" w:color="auto" w:fill="F1F5F9"/></w:tcPr><w:p><w:r><w:rPr><w:rFonts w:ascii="Nirmala UI" w:hAnsi="Nirmala UI" w:cs="Nirmala UI"/><w:b/><w:sz w:val="20"/></w:rPr><w:t>' . ($english ? 'Group A' : 'अ गट') . '</w:t></w:r></w:p></w:tc>
                    <w:tc><w:tcPr><w:tcW w:w="2500" w:type="pct"/><w:shd w:val="clear" w:color="auto" w:fill="F1F5F9"/></w:tcPr><w:p><w:r><w:rPr><w:rFonts w:ascii="Nirmala UI" w:hAnsi="Nirmala UI" w:cs="Nirmala UI"/><w:b/><w:sz w:val="20"/></w:rPr><w:t>' . ($english ? 'Group B' : 'ब गट') . '</w:t></w:r></w:p></w:tc>
                  </w:tr>';
                $pCount = max(count($pairs['a']), count($pairs['b']));
                for ($pi = 0; $pi < $pCount; $pi++) {
                    $sideA = $pairs['a'][$pi] ?? '';
                    $sideB = $pairs['b'][$pi] ?? '';
                    $bodyXml .= '<w:tr>
                      <w:tc><w:tcPr><w:tcW w:w="2500" w:type="pct"/></w:tcPr><w:p><w:r><w:rPr><w:rFonts w:ascii="Nirmala UI" w:hAnsi="Nirmala UI" w:cs="Nirmala UI"/><w:sz w:val="20"/></w:rPr><w:t>' . $x($sideA) . '</w:t></w:r></w:p></w:tc>
                      <w:tc><w:tcPr><w:tcW w:w="2500" w:type="pct"/></w:tcPr><w:p><w:r><w:rPr><w:rFonts w:ascii="Nirmala UI" w:hAnsi="Nirmala UI" w:cs="Nirmala UI"/><w:sz w:val="20"/></w:rPr><w:t>' . $x($sideB) . '</w:t></w:r></w:p></w:tc>
                    </w:tr>';
                }
                $bodyXml .= '</w:tbl>';
            }

            // Answer writing lines
            if ($mode === 'lines') {
                $lineCount = max(1, (int)($itemSpace['lines'] ?? 2));
                for ($li = 0; $li < $lineCount; $li++) {
                    $bodyXml .= '<w:p><w:pPr><w:spacing w:before="60" w:after="80"/><w:pBdr><w:bottom w:val="dotted" w:sz="6" w:space="3" w:color="555555"/></w:pBdr></w:pPr><w:r><w:t> </w:t></w:r></w:p>';
                }
            } elseif ($mode === 'blank' || $mode === 'box') {
                $bodyXml .= '<w:p><w:pPr><w:spacing w:before="100" w:after="160"/><w:pBdr><w:top w:val="single" w:sz="4" w:space="6" w:color="888888"/><w:left w:val="single" w:sz="4" w:space="6" w:color="888888"/><w:bottom w:val="single" w:sz="4" w:space="6" w:color="888888"/><w:right w:val="single" w:sz="4" w:space="6" w:color="888888"/></w:pBdr></w:pPr><w:r><w:t>                                                                                                                </w:t></w:r></w:p>';
            }
        }
    }

    // Signatures footer block
    $bodyXml .= '<w:p><w:pPr><w:spacing w:before="360" w:after="120"/><w:pBdr><w:top w:val="dashed" w:sz="6" w:space="6" w:color="666666"/></w:pBdr></w:pPr>
        <w:r><w:rPr><w:rFonts w:ascii="Nirmala UI" w:hAnsi="Nirmala UI" w:cs="Nirmala UI"/><w:b/><w:sz w:val="20"/></w:rPr>
        <w:t>' . ($english ? 'Marks Obtained: _______ / ' . $totalMarks . '        Teacher Sign: _________________        Principal Sign & Stamp' : 'प्राप्त गुण: _______ / ' . $totalMarks . '        परीक्षक स्वाक्षरी: _________________        मुख्याध्यापक स्वाक्षरी व शिक्का') . '</w:t></w:r></w:p>';

    // Security Footer Note
    $sigCode = ep_paper_signature($paper);
    $bodyXml .= '<w:p><w:pPr><w:jc w:val="center"/><w:spacing w:before="120" w:after="0"/></w:pPr>
        <w:r><w:rPr><w:rFonts w:ascii="Nirmala UI" w:hAnsi="Nirmala UI" w:cs="Nirmala UI"/><w:sz w:val="16"/><w:color w:val="64748B"/></w:rPr>
        <w:t>Verified Exam Document • ' . $sigCode . ' • All the Best • शुभेच्छा</w:t></w:r></w:p>';

    // Section Page properties (A4)
    $sectPr = '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134" w:header="720" w:footer="720" w:gutter="0"/></w:sectPr>';

    $fullDocXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
    <w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
      <w:body>' . $bodyXml . $sectPr . '</w:body>
    </w:document>';

    // Create zip archive
    $zipFile = tempnam(sys_get_temp_dir(), 'docx_');
    $zip = new ZipArchive();
    if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        die('Failed to create docx zip.');
    }

    // 1. [Content_Types].xml
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
</Types>');

    // 2. _rels/.rels
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>');

    // 3. word/_rels/document.xml.rels
    $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>');

    // 4. word/styles.xml
    $zip->addFromString('word/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:docDefaults>
    <w:rPrDefault>
      <w:rPr>
        <w:rFonts w:ascii="Nirmala UI" w:eastAsia="Nirmala UI" w:hAnsi="Nirmala UI" w:cs="Nirmala UI"/>
        <w:sz w:val="22"/>
        <w:szCs w:val="22"/>
        <w:lang w:val="mr-IN"/>
      </w:rPr>
    </w:rPrDefault>
    <w:pPrDefault>
      <w:pPr>
        <w:spacing w:line="276" w:lineRule="auto"/>
      </w:pPr>
    </w:pPrDefault>
  </w:docDefaults>
</w:styles>');

    // 5. word/document.xml
    $zip->addFromString('word/document.xml', $fullDocXml);
    $zip->close();

    $cleanTitle = preg_replace('/[^a-zA-Z0-9_\-\x{0900}-\x{097F}]/u', '_', $paper['title']);
    $filename = ($cleanTitle ?: 'Exam_Paper') . '_Set' . $set . '.docx';

    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($zipFile));
    header('Cache-Control: max-age=0');
    readfile($zipFile);
    @unlink($zipFile);
    exit;
}
