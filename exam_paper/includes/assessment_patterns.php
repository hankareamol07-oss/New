<?php
/**
 * Default section layouts for संकलित / आकारिक मूल्यमापन चाचणी papers.
 *
 * A layout is a list of sections: q_no, sub (अ/ब/क), instruction (printed heading),
 * count (questions to auto-fill from the textbook exercise bank), qtype (bank type
 * to draw from) and marks for the whole section. Layouts are chosen by subject
 * group + medium; real papers stored in ep_paper_models can be loaded instead.
 */

/** Maps a textbook subject name to a layout group. */
function ep_assessment_group(string $subject, string $medium): string
{
    $s = strtolower($subject);
    if (str_contains($s, 'math')) return 'maths';
    if (str_contains($s, 'english')) return 'english';
    if (str_contains($s, 'evs') || str_contains($s, 'science') || str_contains($s, 'geograph') || str_contains($s, 'history')) return 'science';
    if (str_contains($s, 'hindi')) return 'hindi';
    return 'language';
}

/** Marathi-medium instruction text for each layout, with English / Hindi variants. */
function ep_assessment_layouts(): array
{
    $mr = [
        'fill' => 'रिकाम्या जागी योग्य शब्द लिहा.', 'match' => 'जोड्या जुळवा.', 'tf' => 'चूक की बरोबर ते लिहा.',
        'one_word' => 'एका शब्दात उत्तरे लिहा.', 'one_sent' => 'खालील प्रश्नांची एका वाक्यात उत्तरे लिहा.',
        'short' => 'खालील प्रश्नांची दोन-तीन वाक्यांत उत्तरे लिहा.', 'long' => 'खालील प्रश्नांची सविस्तर उत्तरे लिहा.',
        'reason' => 'शास्त्रीय कारणे लिहा.', 'syn' => 'समानार्थी शब्द लिहा.', 'ant' => 'विरुद्धार्थी शब्द लिहा.',
        'mcq' => 'कंसातील योग्य पर्याय निवडून लिहा.', 'solve' => 'खालील उदाहरणे सोडवा.', 'draw' => 'आकृती काढा.',
        'who' => 'कोण ते लिहा.', 'idiom' => 'वाक्प्रचारांचा अर्थ सांगून वाक्यात उपयोग करा.', 'essay' => 'खालील विषयावर आठ-दहा ओळी लिहा.',
    ];
    $en = [
        'fill' => 'Fill in the blanks.', 'match' => 'Match the following.', 'tf' => 'Say whether true or false.',
        'one_word' => 'Answer in one word.', 'one_sent' => 'Answer the following questions in one sentence.',
        'short' => 'Answer the following questions in two or three sentences.', 'long' => 'Answer the following questions in detail.',
        'reason' => 'Give scientific reasons.', 'syn' => 'Write the synonyms.', 'ant' => 'Write the opposite words.',
        'mcq' => 'Choose the correct alternative.', 'solve' => 'Solve the following.', 'draw' => 'Draw the figure.',
        'who' => 'Who said to whom?', 'idiom' => 'Use the following phrases in your own sentences.', 'essay' => 'Write eight to ten lines on the following topic.',
    ];
    $hi = [
        'fill' => 'रिक्त स्थानों की पूर्ति करो।', 'match' => 'जोड़ियाँ मिलाओ।', 'tf' => 'सही या गलत लिखो।',
        'one_word' => 'एक शब्द में उत्तर लिखो।', 'one_sent' => 'निम्नलिखित प्रश्नों के उत्तर एक वाक्य में लिखो।',
        'short' => 'निम्नलिखित प्रश्नों के उत्तर दो-तीन वाक्यों में लिखो।', 'long' => 'निम्नलिखित प्रश्नों के उत्तर विस्तार से लिखो।',
        'reason' => 'कारण लिखो।', 'syn' => 'समानार्थी शब्द लिखो।', 'ant' => 'विलोम शब्द लिखो।',
        'mcq' => 'सही विकल्प चुनकर लिखो।', 'solve' => 'हल करो।', 'draw' => 'आकृति बनाओ।',
        'who' => 'कौन ते लिखो।', 'idiom' => 'मुहावरों का अर्थ लिखकर वाक्य में प्रयोग करो।', 'essay' => 'निम्नलिखित विषय पर आठ-दस पंक्तियाँ लिखो।',
    ];
    $sec = fn(int $q, string $sub, string $key, int $count, string $qtype, int $marks) =>
        ['q_no' => $q, 'sub' => $sub, 'key' => $key, 'count' => $count, 'qtype' => $qtype, 'marks' => $marks];

    return [
        // ---- संकलित (40 marks, std 3-8) ----
        'sankalit' => [
            'language' => [
                $sec(1, 'अ', 'fill', 4, 'fill_blank', 4), $sec(1, 'ब', 'match', 4, 'match', 4),
                $sec(2, 'अ', 'one_word', 4, 'one_word', 4), $sec(2, 'ब', 'one_sent', 5, 'one_sentence', 10),
                $sec(3, 'अ', 'syn', 4, 'vocabulary', 4), $sec(3, 'ब', 'ant', 4, 'vocabulary', 4),
                $sec(4, '', 'short', 3, 'short_answer', 6), $sec(5, '', 'essay', 1, 'descriptive', 4),
            ],
            'hindi' => [
                $sec(1, 'अ', 'fill', 4, 'fill_blank', 4), $sec(1, 'ब', 'match', 4, 'match', 4),
                $sec(2, 'अ', 'one_word', 4, 'one_word', 4), $sec(2, 'ब', 'one_sent', 5, 'one_sentence', 10),
                $sec(3, 'अ', 'syn', 4, 'vocabulary', 4), $sec(3, 'ब', 'ant', 4, 'vocabulary', 4),
                $sec(4, '', 'short', 3, 'short_answer', 6), $sec(5, '', 'essay', 1, 'descriptive', 4),
            ],
            'english' => [
                $sec(1, 'A', 'fill', 4, 'fill_blank', 4), $sec(1, 'B', 'match', 4, 'match', 4),
                $sec(2, 'A', 'one_word', 5, 'one_word', 5), $sec(2, 'B', 'one_sent', 5, 'one_sentence', 10),
                $sec(3, 'A', 'syn', 4, 'vocabulary', 4), $sec(3, 'B', 'ant', 4, 'vocabulary', 4),
                $sec(4, '', 'short', 3, 'short_answer', 6), $sec(5, '', 'essay', 1, 'descriptive', 3),
            ],
            'maths' => [
                $sec(1, 'अ', 'mcq', 5, 'mcq', 5), $sec(1, 'ब', 'fill', 5, 'fill_blank', 5),
                $sec(2, '', 'match', 4, 'match', 4), $sec(3, '', 'solve', 6, 'solve', 12),
                $sec(4, '', 'solve', 4, 'solve', 8), $sec(5, '', 'draw', 2, 'draw', 6),
            ],
            'science' => [
                $sec(1, 'अ', 'fill', 5, 'fill_blank', 5), $sec(1, 'ब', 'tf', 5, 'true_false', 5),
                $sec(2, 'अ', 'match', 4, 'match', 4), $sec(2, 'ब', 'one_word', 4, 'one_word', 4),
                $sec(3, '', 'one_sent', 4, 'one_sentence', 8), $sec(4, '', 'reason', 2, 'reason', 4),
                $sec(5, '', 'short', 2, 'short_answer', 6), $sec(6, '', 'draw', 1, 'draw', 4),
            ],
        ],
        // ---- आकारिक (20 marks) ----
        'aakarik' => [
            'language' => [
                $sec(1, 'अ', 'fill', 2, 'fill_blank', 2), $sec(1, 'ब', 'who', 4, 'one_word', 4),
                $sec(2, '', 'one_sent', 5, 'one_sentence', 5), $sec(3, 'अ', 'syn', 2, 'vocabulary', 2),
                $sec(3, 'ब', 'ant', 2, 'vocabulary', 2), $sec(4, '', 'short', 2, 'short_answer', 4), $sec(5, '', 'idiom', 1, 'vocabulary', 1),
            ],
            'hindi' => [
                $sec(1, 'अ', 'fill', 2, 'fill_blank', 2), $sec(1, 'ब', 'who', 4, 'one_word', 4),
                $sec(2, '', 'one_sent', 5, 'one_sentence', 5), $sec(3, 'अ', 'syn', 2, 'vocabulary', 2),
                $sec(3, 'ब', 'ant', 2, 'vocabulary', 2), $sec(4, '', 'short', 2, 'short_answer', 4), $sec(5, '', 'idiom', 1, 'vocabulary', 1),
            ],
            'english' => [
                $sec(1, '', 'fill', 4, 'fill_blank', 4), $sec(2, '', 'one_word', 6, 'one_word', 6),
                $sec(3, 'A', 'syn', 2, 'vocabulary', 2), $sec(3, 'B', 'ant', 2, 'vocabulary', 2),
                $sec(4, '', 'one_sent', 3, 'one_sentence', 3), $sec(5, '', 'short', 1, 'short_answer', 3),
            ],
            'maths' => [
                $sec(1, 'अ', 'mcq', 3, 'mcq', 3), $sec(1, 'ब', 'fill', 4, 'fill_blank', 4),
                $sec(2, '', 'match', 4, 'match', 4), $sec(3, '', 'solve', 3, 'solve', 6), $sec(4, '', 'solve', 1, 'solve', 3),
            ],
            'science' => [
                $sec(1, 'अ', 'fill', 4, 'fill_blank', 4), $sec(1, 'ब', 'tf', 3, 'true_false', 3),
                $sec(2, '', 'one_sent', 5, 'one_sentence', 5), $sec(3, 'अ', 'match', 4, 'match', 4), $sec(3, 'ब', 'short', 2, 'short_answer', 4),
            ],
        ],
        'texts' => ['Marathi' => $mr, 'English' => $en, 'Hindi' => $hi],
    ];
}

/** Resolved layout (instruction text filled in) for exam type + subject + medium. */
function ep_assessment_layout(string $examType, string $subject, string $medium): array
{
    $all = ep_assessment_layouts();
    $group = ep_assessment_group($subject, $medium);
    $lang = $group === 'english' ? 'English' : ($group === 'hindi' ? 'Hindi' : ($medium === 'English' ? 'English' : 'Marathi'));
    $texts = $all['texts'][$lang];
    $sections = $all[$examType][$group] ?? $all[$examType]['language'];
    foreach ($sections as &$s) {
        $s['instruction'] = $texts[$s['key']];
        if ($lang === 'English') {
            $s['sub'] = ['अ' => 'A', 'ब' => 'B', 'क' => 'C'][$s['sub']] ?? $s['sub'];
        }
        unset($s['key']);
    }
    return ['group' => $group, 'lang' => $lang, 'sections' => $sections];
}
