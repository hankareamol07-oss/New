<?php
/**
 * Answer-writing space for संकलित / आकारिक papers ("उत्तर-लेखन" format).
 *
 * Modelled on the 151 minishala.com sample papers (std 1-8, सत्र 1/2, 2021-22):
 * every question gets a place to write the answer on the paper itself -
 * dotted lines, a short line beside the question, a blank working area for
 * sums, or a box for figures. The rule for a section is chosen in this order:
 *   1. section['answer_space'] set by the teacher in the builder,
 *   2. section['qtype'] (textbook question type),
 *   3. keywords in the section instruction (Marathi / Hindi / English),
 *   4. default: one line per question.
 * data/answer_space_rules.json is generated from ep_answer_space_rules().
 */

/** Answer-space modes and their default sizes. */
function ep_answer_space_modes(): array
{
    return [
        'none'   => ['label' => 'जागा नाही (जोड्या / तक्ता)',        'en' => 'No space (match table / table)'],
        'inline' => ['label' => 'ओळीतच रिकामी जागा',                 'en' => 'Blank inside the sentence'],
        'tail'   => ['label' => 'प्रश्नापुढे ओळ पूर्ण करा',          'en' => 'Dotted line continues after the text'],
        'short'  => ['label' => 'उजवीकडे छोटी ओळ',                  'en' => 'Short line at the right'],
        'grid2'  => ['label' => 'दोन स्तंभ, प्रत्येकापुढे छोटी ओळ',   'en' => 'Two columns, short line each'],
        'lines'  => ['label' => 'उत्तरासाठी ठिपक्यांच्या ओळी',        'en' => 'Dotted answer lines'],
        'blank'  => ['label' => 'मोकळी जागा (उदाहरण सोडवा)',          'en' => 'Blank working space'],
        'box'    => ['label' => 'चौकट (आकृती / चित्र)',              'en' => 'Box for figure / drawing'],
    ];
}

/**
 * Rules per textbook question type: mode + number of dotted lines (mode=lines)
 * or height in mm (blank/box). `std12` overrides apply to इयत्ता 1-2 where the
 * sample papers use larger handwriting space.
 */
function ep_answer_space_rules(): array
{
    return [
        'fill_blank'   => ['mode' => 'inline'],
        'match'        => ['mode' => 'none'],
        'true_false'   => ['mode' => 'short'],
        'odd_one'      => ['mode' => 'short'],
        'mcq'          => ['mode' => 'short'],
        'one_word'     => ['mode' => 'grid2'],
        'vocabulary'   => ['mode' => 'grid2'],
        'grammar'      => ['mode' => 'short'],
        'one_sentence' => ['mode' => 'lines', 'lines' => 1, 'std12' => ['lines' => 2]],
        'short_answer' => ['mode' => 'lines', 'lines' => 3, 'std12' => ['lines' => 2]],
        'reason'       => ['mode' => 'lines', 'lines' => 3],
        'define'       => ['mode' => 'lines', 'lines' => 2],
        'difference'   => ['mode' => 'lines', 'lines' => 4],
        'explain'      => ['mode' => 'lines', 'lines' => 4],
        'activity'     => ['mode' => 'lines', 'lines' => 3],
        'descriptive'  => ['mode' => 'lines', 'lines' => 6, 'std12' => ['lines' => 4]],
        'solve'        => ['mode' => 'blank', 'mm' => 30, 'std12' => ['mm' => 25]],
        'draw'         => ['mode' => 'box', 'mm' => 45],
        ''             => ['mode' => 'lines', 'lines' => 1],
    ];
}

/**
 * Instruction keyword → qtype, first match wins (order matters: जोड्या before
 * पर्याय, टीपा before लिहा, ...). Same table is exported to JSON for Antigravity.
 */
function ep_answer_space_keywords(): array
{
    return [
        ['match',        '/जोड्या|जोड़ी|जोड़िय|match/iu'],
        ['fill_blank',   '/रिकाम्या|रिक्त|गाळलेल|blank|ओळी पूर्ण|पूर्ण करा|complete the/iu'],
        ['true_false',   '/चूक|बरोबर|सही या गलत|true|false/iu'],
        ['odd_one',      '/विसंगत|गटात न|odd one|odd man/iu'],
        ['mcq',          '/पर्याय|विकल्प|option|alternative|correct/iu'],
        ['vocabulary',   '/समानार्थी|विरुद्धार्थी|विलोम|अर्थ लिहा|प्रत्यय|अनेकवचन|एकवचन|लिंग|वाक्प्रचार|मुहावर|synonym|opposite|antonym|meaning|rhym|plural|singular/iu'],
        ['one_word',     '/एका शब्दात|एक शब्द|one word|कोण ते|कोणाला|who said/iu'],
        ['draw',         '/आकृती काढा|चित्र काढा|आकृति बनाओ|draw/iu'],
        ['solve',        '/सोडवा|हल करो|बेरीज|वजाबाकी|गुणाकार|भागाकार|किंमत काढा|solve|simplify|find the|calculate|add|subtract|multiply|divide/iu'],
        ['one_sentence', '/एका वाक्यात|एक वाक्य|one sentence|उत्तरे लिहा|उत्तर लिखो/iu'],
        ['reason',       '/कारणे|कारण|असे का|why|reason/iu'],
        ['difference',   '/फरक|अंतर|difference|distinguish/iu'],
        ['define',       '/व्याख्या|म्हणजे काय|define|what is meant/iu'],
        ['short_answer', '/थोडक्यात|टीपा|टिपा|दोन-तीन|दो-तीन|two or three|briefly|short note|notes on/iu'],
        ['descriptive',  '/सविस्तर|निबंध|पत्र|आठ-दहा|आठ ते दहा|उतारा|कथा|वर्णन|essay|letter|paragraph|detail|eight to ten|describe/iu'],
        ['explain',      '/स्पष्ट करा|समजावून|explain/iu'],
    ];
}

/** Guess the textbook question type from a section instruction. */
function ep_answer_space_guess_qtype(string $instruction): string
{
    foreach (ep_answer_space_keywords() as [$qtype, $re]) {
        if (preg_match($re, $instruction)) {
            return $qtype;
        }
    }
    return '';
}

/**
 * Resolve the answer space for one section.
 * Returns ['mode' => ..., 'lines' => n, 'mm' => h, 'source' => 'teacher|qtype|instruction|default'].
 */
function ep_answer_space(array $section, int $std = 0): array
{
    $rules = ep_answer_space_rules();
    $modes = ep_answer_space_modes();
    $custom = $section['answer_space'] ?? null;
    if (is_string($custom) && $custom !== '' && $custom !== 'auto') {
        // "lines:3", "blank:40", "short", ...
        [$mode, $n] = array_pad(explode(':', $custom, 2), 2, null);
        if (isset($modes[$mode])) {
            $spec = ['mode' => $mode, 'source' => 'teacher'];
            if ($mode === 'lines') $spec['lines'] = max(1, (int)($n ?? 1));
            if ($mode === 'blank' || $mode === 'box') $spec['mm'] = max(10, (int)($n ?? ($mode === 'box' ? 45 : 30)));
            return $spec;
        }
    }
    $qtype = trim((string)($section['qtype'] ?? ''));
    $source = 'qtype';
    if ($qtype === '' || !isset($rules[$qtype])) {
        $qtype = ep_answer_space_guess_qtype((string)($section['instruction'] ?? ''));
        $source = $qtype === '' ? 'default' : 'instruction';
    }
    $rule = $rules[$qtype] ?? $rules[''];
    if ($std >= 1 && $std <= 2 && isset($rule['std12'])) {
        $rule = array_merge($rule, $rule['std12']);
    }
    unset($rule['std12']);
    $rule['source'] = $source;
    return $rule;
}

/** HTML printed under / beside a question for the resolved answer space. */
function ep_answer_space_html(array $spec): string
{
    switch ($spec['mode']) {
        case 'lines':
            return '<div class="ans ans-lines">' . str_repeat('<div class="ans-line"></div>', (int)($spec['lines'] ?? 1)) . '</div>';
        case 'blank':
            return '<div class="ans ans-blank" style="height:' . (int)($spec['mm'] ?? 30) . 'mm"></div>';
        case 'box':
            return '<div class="ans ans-box" style="height:' . (int)($spec['mm'] ?? 45) . 'mm"></div>';
        case 'tail':
            return '<span class="ans ans-tail"></span>';
        case 'short':
        case 'grid2':
            return '<span class="ans ans-short"></span>';
        default:
            return '';
    }
}

/** Serialisable copy of modes, rules and keyword table (used to write data/answer_space_rules.json). */
function ep_answer_space_export(): array
{
    return [
        'modes' => ep_answer_space_modes(),
        'rules' => ep_answer_space_rules(),
        'instruction_keywords' => array_map(fn($r) => ['qtype' => $r[0], 'regex' => $r[1]], ep_answer_space_keywords()),
        'line_pitch_mm' => ['default' => 9, 'std12' => 11],
    ];
}
