<?php
/**
 * Competitive exam paper patterns (Scholarship / Navodaya / NMMS).
 *
 * Each section is filled from the regular class question bank: `source_std` and
 * `source_subject` are keyword hints used to pre-select a class + subject in the
 * builder; the user can change them and pick chapters (topics) per section.
 * `marks_per_q` may be fractional (JNVST uses 1.25).
 */
function ep_exam_patterns(): array
{
    return [
        'jnv5_practice' => [
            'name' => '5th Navodaya (JNVST) - Chapterwise Practice Paper',
            'group' => 'Navodaya', 'std_label' => '5th', 'total_marks' => 50, 'duration' => '1:30 Hrs',
            'marks_per_q' => 1.25, 'negative' => 0,
            'sections' => [
                ['title' => 'विभाग अ', 'subtitle' => 'मानसिक क्षमता परीक्षण', 'count' => 20, 'source_std' => ['5'], 'source_subject' => ['बुद्धिमत्ता', 'Intelligence', 'Mental', 'Reasoning', 'गणित', 'Math']],
                ['title' => 'विभाग ब', 'subtitle' => 'अंकगणित परीक्षण', 'count' => 10, 'source_std' => ['5'], 'source_subject' => ['गणित', 'Math']],
                ['title' => 'विभाग क', 'subtitle' => 'भाषा परीक्षण', 'count' => 10, 'source_std' => ['5'], 'source_subject' => ['मराठी', 'Marathi', 'English']],
            ],
        ],
        'jnv5_full' => [
            'name' => '5th Navodaya (JNVST) - Full Paper (80 Q / 100 Marks)',
            'group' => 'Navodaya', 'std_label' => '5th', 'total_marks' => 100, 'duration' => '2:00 Hrs',
            'marks_per_q' => 1.25, 'negative' => 0,
            'sections' => [
                ['title' => 'विभाग अ', 'subtitle' => 'मानसिक क्षमता परीक्षण', 'count' => 40, 'source_std' => ['5'], 'source_subject' => ['बुद्धिमत्ता', 'Intelligence', 'Mental', 'Reasoning', 'गणित', 'Math']],
                ['title' => 'विभाग ब', 'subtitle' => 'अंकगणित परीक्षण', 'count' => 20, 'source_std' => ['5'], 'source_subject' => ['गणित', 'Math']],
                ['title' => 'विभाग क', 'subtitle' => 'भाषा परीक्षण', 'count' => 20, 'source_std' => ['5'], 'source_subject' => ['मराठी', 'Marathi', 'English']],
            ],
        ],
        'sch4_p1' => [
            'name' => '4th Scholarship (5th Std Pre-Upper Primary) - Paper 1: प्रथम भाषा व गणित',
            'group' => 'Scholarship', 'std_label' => '4th / 5th', 'total_marks' => 150, 'duration' => '1:30 Hrs',
            'marks_per_q' => 2, 'negative' => 0,
            'sections' => [
                ['title' => 'विभाग १', 'subtitle' => 'प्रथम भाषा (मराठी)', 'count' => 25, 'source_std' => ['4', '5'], 'source_subject' => ['मराठी', 'Marathi']],
                ['title' => 'विभाग २', 'subtitle' => 'गणित', 'count' => 50, 'source_std' => ['4', '5'], 'source_subject' => ['गणित', 'Math']],
            ],
        ],
        'sch4_p2' => [
            'name' => '4th Scholarship (5th Std Pre-Upper Primary) - Paper 2: तृतीय भाषा व बुद्धिमत्ता चाचणी',
            'group' => 'Scholarship', 'std_label' => '4th / 5th', 'total_marks' => 150, 'duration' => '1:30 Hrs',
            'marks_per_q' => 2, 'negative' => 0,
            'sections' => [
                ['title' => 'विभाग १', 'subtitle' => 'तृतीय भाषा (इंग्रजी)', 'count' => 25, 'source_std' => ['4', '5'], 'source_subject' => ['English', 'इंग्रजी']],
                ['title' => 'विभाग २', 'subtitle' => 'बुद्धिमत्ता चाचणी', 'count' => 50, 'source_std' => ['4', '5'], 'source_subject' => ['बुद्धिमत्ता', 'Intelligence', 'Mental', 'गणित']],
            ],
        ],
        'sch7_p1' => [
            'name' => '7th Scholarship (8th Std Pre-Secondary) - Paper 1: प्रथम भाषा व गणित',
            'group' => 'Scholarship', 'std_label' => '7th / 8th', 'total_marks' => 150, 'duration' => '1:30 Hrs',
            'marks_per_q' => 2, 'negative' => 0,
            'sections' => [
                ['title' => 'विभाग १', 'subtitle' => 'प्रथम भाषा (मराठी)', 'count' => 25, 'source_std' => ['7', '8'], 'source_subject' => ['मराठी', 'Marathi']],
                ['title' => 'विभाग २', 'subtitle' => 'गणित', 'count' => 50, 'source_std' => ['7', '8'], 'source_subject' => ['गणित', 'Math']],
            ],
        ],
        'sch7_p2' => [
            'name' => '7th Scholarship (8th Std Pre-Secondary) - Paper 2: तृतीय भाषा व बुद्धिमत्ता चाचणी',
            'group' => 'Scholarship', 'std_label' => '7th / 8th', 'total_marks' => 150, 'duration' => '1:30 Hrs',
            'marks_per_q' => 2, 'negative' => 0,
            'sections' => [
                ['title' => 'विभाग १', 'subtitle' => 'तृतीय भाषा (इंग्रजी)', 'count' => 25, 'source_std' => ['7', '8'], 'source_subject' => ['English', 'इंग्रजी']],
                ['title' => 'विभाग २', 'subtitle' => 'बुद्धिमत्ता चाचणी', 'count' => 50, 'source_std' => ['7', '8'], 'source_subject' => ['बुद्धिमत्ता', 'Intelligence', 'Mental', 'गणित']],
            ],
        ],
        'nmms_mat' => [
            'name' => '8th NMMS - Paper 1: Mental Ability Test (MAT)',
            'group' => 'NMMS', 'std_label' => '8th', 'total_marks' => 90, 'duration' => '1:30 Hrs',
            'marks_per_q' => 1, 'negative' => 0,
            'sections' => [
                ['title' => 'Mental Ability Test', 'subtitle' => '', 'count' => 90, 'source_std' => ['8'], 'source_subject' => ['बुद्धिमत्ता', 'Intelligence', 'Mental', 'Reasoning', 'गणित', 'Math']],
            ],
        ],
        'nmms_sat' => [
            'name' => '8th NMMS - Paper 2: Scholastic Aptitude Test (SAT)',
            'group' => 'NMMS', 'std_label' => '8th', 'total_marks' => 90, 'duration' => '1:30 Hrs',
            'marks_per_q' => 1, 'negative' => 0,
            'sections' => [
                ['title' => 'Section A', 'subtitle' => 'Science', 'count' => 35, 'source_std' => ['8'], 'source_subject' => ['Science', 'विज्ञान']],
                ['title' => 'Section B', 'subtitle' => 'Social Science', 'count' => 35, 'source_std' => ['8'], 'source_subject' => ['History', 'Geography', 'इतिहास', 'भूगोल', 'Social']],
                ['title' => 'Section C', 'subtitle' => 'Mathematics', 'count' => 20, 'source_std' => ['8'], 'source_subject' => ['गणित', 'Math']],
            ],
        ],
        'topicwise' => [
            'name' => 'Topic-wise Practice Test (any exam) - single topic, MCQ only',
            'group' => 'Practice', 'std_label' => '', 'total_marks' => 0, 'duration' => '30 Min',
            'marks_per_q' => 1, 'negative' => 0,
            'sections' => [
                ['title' => 'Topic Test', 'subtitle' => '', 'count' => 20, 'source_std' => [], 'source_subject' => []],
            ],
        ],
    ];
}
