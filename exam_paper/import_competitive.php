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
 * - MathML in stems/options/solutions is converted to plain text (mfrac -> (a)/(b), msup -> a^b, msqrt -> √(x) ...)
 * - the first question image is copied to data/images/<question_id>.png (has_image = 1); for a child of a group whose
 *   passage has an image, the passage image takes that slot and the child's own image goes to <question_id>_2.png
 *   (has_image2); questions whose options are images only are skipped (cannot be rendered as text MCQ)
 * - blank options are dropped and correct_option re-indexed accordingly (rows whose keyed option is blank are skipped)
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
/**
 * MathML -> plain text without ext-dom: repeatedly collapse innermost elements (mfrac -> (a)/(b), msup -> a^b,
 * msub -> a_b, msqrt -> √(x), mroot -> n√(x), mover/munder -> a¯ ...) until only text is left.
 */
function cq_mathml_to_text(string $s): string
{
    if (stripos($s, '<math') === false) {
        return $s;
    }
    return preg_replace_callback('~<math\b[^>]*>.*?</math>~is', function ($m) {
        $x = preg_replace('~<annotation(-xml)?\b[^>]*>.*?</annotation(-xml)?>~is', '', $m[0]);   // LaTeX duplicate
        $x = preg_replace('~<(mspace|mprescripts|none)\b[^>]*/?>~i', ' ', $x);
        $x = preg_replace('~<(\w+)\b[^>]*/>~', '', $x);                       // other self-closing tags
        $wrap = fn(string $t) => preg_match('/^[\p{L}\p{N}.]+$/u', $t) ? $t : '(' . $t . ')';
        $join = function (array $parts): string {                             // "2" + "1/2" -> "2 1/2", "b" + "×" -> "b×"
            $out = '';
            foreach ($parts as $t) {
                if ($out !== '' && preg_match('/[\p{L}\p{N})]$/u', $out) && preg_match('/^[\p{L}\p{N}(√]/u', $t)) {
                    $out .= ' ';
                }
                $out .= $t;
            }
            return $out;
        };
        for ($i = 0; $i < 200 && preg_match('~<(m\w+)\b[^>]*>((?:(?!<m\w+\b)(?!</m\w+>).)*)</\1>~su', $x, $mm, PREG_OFFSET_CAPTURE); $i++) {
            [$full, $off] = $mm[0];
            $tag = strtolower($mm[1][0]);
            $inner = trim($mm[2][0]);
            $parts = array_values(array_filter(array_map(fn($t) => preg_replace('/\s+/u', ' ', trim($t)), explode("\x1f", $inner)), fn($t) => $t !== ''));
            switch ($tag) {
                case 'mfrac':   $r = count($parts) === 2 ? $wrap($parts[0]) . '/' . $wrap($parts[1]) : $join($parts); break;
                case 'msup':    $r = count($parts) === 2 ? $wrap($parts[0]) . '^' . $wrap($parts[1]) : implode('', $parts); break;
                case 'msub':    $r = count($parts) === 2 ? $wrap($parts[0]) . '_' . $wrap($parts[1]) : implode('', $parts); break;
                case 'msubsup': $r = count($parts) === 3 ? $wrap($parts[0]) . '_' . $wrap($parts[1]) . '^' . $wrap($parts[2]) : implode('', $parts); break;
                case 'msqrt':   $r = '√(' . implode('', $parts) . ')'; break;
                case 'mroot':   $r = count($parts) === 2 ? $parts[1] . '√(' . $parts[0] . ')' : '√(' . implode('', $parts) . ')'; break;
                case 'mover':
                case 'munder':  $r = count($parts) === 2 ? $wrap($parts[0]) . (in_array($parts[1], ['¯', '‾', '_', '-'], true) ? "\u{0304}" : $parts[1]) : implode('', $parts); break;
                case 'mtd':     $r = implode('', $parts) . ' '; break;
                case 'mtr':     $r = trim(implode('', $parts)) . '; '; break;
                case 'mi': case 'mn': case 'mo': case 'mtext': case 'mtex': case 'ms':
                                $r = implode('', $parts); break;
                default:        $r = $join($parts);                      // mrow, mstyle, mtable, menclose, mphantom, math ...
            }
            // child boundary marker so the parent can split its operands (mrow/mi/mn... are single operands)
            $x = substr($x, 0, $off) . "\x1f" . $r . "\x1f" . substr($x, $off + strlen($full));
        }
        $x = str_replace("\x1f", '', strip_tags($x));
        $x = preg_replace('/\s*\(\s*\)/u', '', $x);                          // empty groups
        return ' ' . trim(cq_plain_letters($x)) . ' ';
    }, $s);
}

/** 𝑥, 𝐴 (U+1D400 mathematical alphanumerics, 4-byte) -> x, A so they survive 3-byte utf8 columns and print as text. */
function cq_plain_letters(string $s): string
{
    if (class_exists('Normalizer')) {
        return Normalizer::normalize($s, Normalizer::NFKC) ?: $s;
    }
    return preg_replace_callback('/[\x{1D400}-\x{1D7FF}]/u', function ($m) {
        $cp = mb_ord($m[0], 'UTF-8') - 0x1D400;
        if ($cp >= 0x3CE) {                                                     // 𝟎-𝟗 (5 styles)
            return (string)(($cp - 0x3CE) % 10);
        }
        if ($cp >= 0x2A4) {                                                     // greek: keep
            return $m[0];
        }
        $i = $cp % 52;                                                          // 13 styles × 52 latin letters
        return $i < 26 ? chr(65 + $i) : chr(97 + $i - 26);
    }, str_replace("\u{210E}", 'h', $s));
}

$clean = fn(?string $s) => trim(preg_replace('/\s+/u', ' ', strip_tags(str_replace(['<br>', '<br/>', '<br />', '</div>', '</p>'], "\n", cq_mathml_to_text((string)$s)))));
$passages = $passageImgs = [];
foreach ($data['questions'] as $q) {
    if (!empty($q['is_group'])) {
        $passages[$q['question_id']] = $clean($q['text']);
        if (!empty($q['images'])) {
            $passageImgs[$q['question_id']] = $q['images'][0];
        }
    }
}
$copyImg = function (string $rel, string $dst): bool {
    $f = __DIR__ . '/data/competitive/' . $rel;
    if (!is_file($f)) {
        return false;
    }
    if (!is_file($dst)) {
        if (mime_content_type($f) === 'image/png') {
            copy($f, $dst);
        } elseif (function_exists('imagecreatefromstring') && ($im = @imagecreatefromstring((string)file_get_contents($f)))) {
            imagepng($im, $dst);
            imagedestroy($im);
        }
    }
    return is_file($dst);
};

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
    $stem = $clean($q['text']);
    $ci = isset($q['correct_option']) ? (int)$q['correct_option'] : -1;
    if ($hasOptImg || $stem === '' || $ci < 0 || $ci >= count($opts) || $opts[$ci] === '') {
        $skipped++;
        continue;
    }
    $ci -= count(array_filter(array_slice($opts, 0, $ci), fn($o) => $o === ''));   // blanks before the key shift its index
    $opts = array_values(array_filter($opts, fn($o) => $o !== ''));
    if (count($opts) < 3) {
        $skipped++;
        continue;
    }
    $q['correct_option'] = $ci;
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
    $hasImg = $hasImg2 = 0;
    $imgs = [];
    if (isset($passageImgs[$q['parent_id'] ?? 0])) {
        $imgs[] = $passageImgs[$q['parent_id']];
    }
    if (!empty($q['images'])) {
        $imgs[] = $q['images'][0];
    }
    if (isset($imgs[0]) && $copyImg($imgs[0], EP_IMAGE_DIR . '/' . $qid . '.png')) {
        $hasImg = 1;
        $img++;
    }
    if (isset($imgs[1]) && $copyImg($imgs[1], EP_IMAGE_DIR . '/' . $qid . '_2.png')) {
        $hasImg2 = 1;
        $img++;
    }
    $source = trim(($q['is_pyq'] ? 'PYQ · ' : '') . ($q['topic'] ?: $q['chapter']));
    $ins->execute([$qid, (int)$q['chapter_id'], 'mcq', '1', max(1, (int)round((float)($q['marks'] ?? 1))), $difficulty[$q['difficulty'] ?? ''] ?? 2,
        mb_substr($source, 0, 50), $passages[$q['parent_id'] ?? 0] ?? '', $markup, '', '', $ans, '', $hasImg, $hasImg2, 0, null]);
    $n++;
    $perStd[$q['standard']] = ($perStd[$q['standard']] ?? 0) + 1;
}
$db->commit();
echo "imported=$n skipped=$skipped images=$img\n";
foreach ($perStd as $s => $c) {
    echo "  $s: $c\n";
}
