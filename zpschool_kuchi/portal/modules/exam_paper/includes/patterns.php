<?php
/**
 * Competitive exam paper patterns (Scholarship / Navodaya / NMMS).
 *
 * Each section is pre-configured to select from the dedicated competitive question bank:
 * 4th शिष्यवृत्ती, 7th शिष्यवृत्ती, and 8) NMMS.
 * Regular textbook classes are never mixed into competitive exams.
 */
function ep_exam_patterns(): array
{
    return [
        'jnv5_practice' => [
            'name' => '5th Navodaya (JNVST) - Chapterwise Practice Paper (40 Q / 50 Marks)',
            'group' => 'Navodaya (JNVST)', 'std_label' => '5th Navodaya', 'total_marks' => 50, 'duration' => '1:30 Hrs',
            'marks_per_q' => 1.25, 'negative' => 0,
            'sections' => [
                ['title' => 'विभाग १', 'subtitle' => 'मानसिक क्षमता चाचणी (Mental Ability)', 'count' => 20, 'source_std' => ['4th शिष्यवृत्ती', '72'], 'source_subject' => ['बुद्धिमत्ता', 'Intelligence', 'Mental']],
                ['title' => 'विभाग २', 'subtitle' => 'अंकगणित चाचणी (Arithmetic)', 'count' => 10, 'source_std' => ['4th शिष्यवृत्ती', '72'], 'source_subject' => ['गणित', 'Math']],
                ['title' => 'विभाग ३', 'subtitle' => 'भाषा चाचणी (उतारे व व्याकरण)', 'count' => 10, 'source_std' => ['4th शिष्यवृत्ती', '72'], 'source_subject' => ['मराठी', 'Marathi']],
            ],
        ],
        'jnv5_full' => [
            'name' => '5th Navodaya (JNVST) - Full Paper (80 Q / 100 Marks)',
            'group' => 'Navodaya (JNVST)', 'std_label' => '5th Navodaya', 'total_marks' => 100, 'duration' => '2:00 Hrs',
            'marks_per_q' => 1.25, 'negative' => 0,
            'sections' => [
                ['title' => 'विभाग १', 'subtitle' => 'मानसिक क्षमता चाचणी (Mental Ability)', 'count' => 40, 'source_std' => ['4th शिष्यवृत्ती', '72'], 'source_subject' => ['बुद्धिमत्ता', 'Intelligence', 'Mental']],
                ['title' => 'विभाग २', 'subtitle' => 'अंकगणित चाचणी (Arithmetic)', 'count' => 20, 'source_std' => ['4th शिष्यवृत्ती', '72'], 'source_subject' => ['गणित', 'Math']],
                ['title' => 'विभाग ३', 'subtitle' => 'भाषा चाचणी (उतारे व व्याकरण)', 'count' => 20, 'source_std' => ['4th शिष्यवृत्ती', '72'], 'source_subject' => ['मराठी', 'Marathi']],
            ],
        ],
        'sch4_p1' => [
            'name' => '४ थी शिष्यवृत्ती (4th Scholarship) - Paper 1: प्रथम भाषा (मराठी) व गणित (75 Q / 150 Marks)',
            'group' => 'Scholarship (शिष्यवृत्ती)', 'std_label' => '४ थी शिष्यवृत्ती (4th Scholarship)', 'total_marks' => 150, 'duration' => '1:30 Hrs',
            'marks_per_q' => 2, 'negative' => 0,
            'sections' => [
                ['title' => 'विभाग १', 'subtitle' => 'प्रथम भाषा (मराठी)', 'count' => 25, 'source_std' => ['4th शिष्यवृत्ती', '72'], 'source_subject' => ['मराठी व गणित', 'मराठी']],
                ['title' => 'विभाग २', 'subtitle' => 'गणित', 'count' => 50, 'source_std' => ['4th शिष्यवृत्ती', '72'], 'source_subject' => ['मराठी व गणित', 'गणित']],
            ],
        ],
        'sch4_p2' => [
            'name' => '४ थी शिष्यवृत्ती (4th Scholarship) - Paper 2: तृतीय भाषा (इंग्रजी) व बुद्धिमत्ता चाचणी (75 Q / 150 Marks)',
            'group' => 'Scholarship (शिष्यवृत्ती)', 'std_label' => '४ थी शिष्यवृत्ती (4th Scholarship)', 'total_marks' => 150, 'duration' => '1:30 Hrs',
            'marks_per_q' => 2, 'negative' => 0,
            'sections' => [
                ['title' => 'विभाग १', 'subtitle' => 'तृतीय भाषा (इंग्रजी)', 'count' => 25, 'source_std' => ['4th शिष्यवृत्ती', '72'], 'source_subject' => ['इंग्रजी व बुद्धिमत्ता', 'इंग्रजी']],
                ['title' => 'विभाग २', 'subtitle' => 'बुद्धिमत्ता चाचणी', 'count' => 50, 'source_std' => ['4th शिष्यवृत्ती', '72'], 'source_subject' => ['इंग्रजी व बुद्धिमत्ता', 'बुद्धिमत्ता']],
            ],
        ],
        'sch7_p1' => [
            'name' => '७ वी शिष्यवृत्ती (7th Scholarship) - Paper 1: प्रथम भाषा (मराठी) व गणित (75 Q / 150 Marks)',
            'group' => 'Scholarship (शिष्यवृत्ती)', 'std_label' => '७ वी शिष्यवृत्ती (7th Scholarship)', 'total_marks' => 150, 'duration' => '1:30 Hrs',
            'marks_per_q' => 2, 'negative' => 0,
            'sections' => [
                ['title' => 'विभाग १', 'subtitle' => 'प्रथम भाषा (मराठी)', 'count' => 25, 'source_std' => ['7th शिष्यवृत्ती', '71'], 'source_subject' => ['मराठी व गणित', 'मराठी']],
                ['title' => 'विभाग २', 'subtitle' => 'गणित', 'count' => 50, 'source_std' => ['7th शिष्यवृत्ती', '71'], 'source_subject' => ['मराठी व गणित', 'गणित']],
            ],
        ],
        'sch7_p2' => [
            'name' => '७ वी शिष्यवृत्ती (7th Scholarship) - Paper 2: तृतीय भाषा (इंग्रजी) व बुद्धिमत्ता चाचणी (75 Q / 150 Marks)',
            'group' => 'Scholarship (शिष्यवृत्ती)', 'std_label' => '७ वी शिष्यवृत्ती (7th Scholarship)', 'total_marks' => 150, 'duration' => '1:30 Hrs',
            'marks_per_q' => 2, 'negative' => 0,
            'sections' => [
                ['title' => 'विभाग १', 'subtitle' => 'तृतीय भाषा (इंग्रजी)', 'count' => 25, 'source_std' => ['7th शिष्यवृत्ती', '71'], 'source_subject' => ['इंग्रजी व बुद्धिमत्ता', 'इंग्रजी']],
                ['title' => 'विभाग २', 'subtitle' => 'बुद्धिमत्ता चाचणी', 'count' => 50, 'source_std' => ['7th शिष्यवृत्ती', '71'], 'source_subject' => ['इंग्रजी व बुद्धिमत्ता', 'बुद्धिमत्ता']],
            ],
        ],
        'nmms_mat' => [
            'name' => '8th NMMS - Paper 1: Mental Ability Test (MAT) (90 Q / 90 Marks)',
            'group' => 'NMMS (८ वी)', 'std_label' => '8th NMMS', 'total_marks' => 90, 'duration' => '1:30 Hrs',
            'marks_per_q' => 1, 'negative' => 0,
            'sections' => [
                ['title' => 'मानसिक क्षमता चाचणी', 'subtitle' => 'Mental Ability Test (MAT)', 'count' => 90, 'source_std' => ['8) NMMS', '46'], 'source_subject' => ['MAT', 'Paper - 1']],
            ],
        ],
        'nmms_sat' => [
            'name' => '8th NMMS - Paper 2: Scholastic Aptitude Test (SAT) (90 Q / 90 Marks)',
            'group' => 'NMMS (८ वी)', 'std_label' => '8th NMMS', 'total_marks' => 90, 'duration' => '1:30 Hrs',
            'marks_per_q' => 1, 'negative' => 0,
            'sections' => [
                ['title' => 'विभाग अ: सामान्य विज्ञान', 'subtitle' => 'General Science (35 Q)', 'count' => 35, 'source_std' => ['8) NMMS', '46'], 'source_subject' => ['SAT', 'Paper - 2']],
                ['title' => 'विभाग ब: सामाजिक शास्त्रे', 'subtitle' => 'Social Science (35 Q)', 'count' => 35, 'source_std' => ['8) NMMS', '46'], 'source_subject' => ['SAT', 'Paper - 2']],
                ['title' => 'विभाग क: गणित', 'subtitle' => 'Mathematics (20 Q)', 'count' => 20, 'source_std' => ['8) NMMS', '46'], 'source_subject' => ['SAT', 'Paper - 2']],
            ],
        ],
        'topic_sch4_20' => [
            'name' => '४ थी शिष्यवृत्ती - एका घटकाची सराव चाचणी (२० प्रश्न / ४० गुण)',
            'group' => 'घटकनिहाय सराव (Single Topic)', 'std_label' => '४ थी शिष्यवृत्ती (4th Scholarship)', 'total_marks' => 40, 'duration' => '30 Min',
            'marks_per_q' => 2, 'negative' => 0,
            'sections' => [
                ['title' => 'घटक चाचणी', 'subtitle' => 'वस्तुनिष्ठ बहुपर्यायी प्रश्न (MCQ)', 'count' => 20, 'source_std' => ['4th शिष्यवृत्ती', '72'], 'source_subject' => []],
            ],
        ],
        'topic_sch7_20' => [
            'name' => '७ वी शिष्यवृत्ती - एका घटकाची सराव चाचणी (२० प्रश्न / ४० गुण)',
            'group' => 'घटकनिहाय सराव (Single Topic)', 'std_label' => '७ वी शिष्यवृत्ती (7th Scholarship)', 'total_marks' => 40, 'duration' => '30 Min',
            'marks_per_q' => 2, 'negative' => 0,
            'sections' => [
                ['title' => 'घटक चाचणी', 'subtitle' => 'वस्तुनिष्ठ बहुपर्यायी प्रश्न (MCQ)', 'count' => 20, 'source_std' => ['7th शिष्यवृत्ती', '71'], 'source_subject' => []],
            ],
        ],
        'topic_nmms8_25' => [
            'name' => '८ वी NMMS - एका घटकाची सराव चाचणी (२५ प्रश्न / २५ गुण)',
            'group' => 'घटकनिहाय सराव (Single Topic)', 'std_label' => '8th NMMS', 'total_marks' => 25, 'duration' => '35 Min',
            'marks_per_q' => 1, 'negative' => 0,
            'sections' => [
                ['title' => 'घटक चाचणी', 'subtitle' => 'वस्तुनिष्ठ बहुपर्यायी प्रश्न (MCQ)', 'count' => 25, 'source_std' => ['8) NMMS', '46'], 'source_subject' => []],
            ],
        ],
        'topicwise' => [
            'name' => 'इतर कोणत्याही स्पर्धा परीक्षेची सराव चाचणी (Custom Single Topic Test)',
            'group' => 'घटकनिहाय सराव (Single Topic)', 'std_label' => 'Competitive', 'total_marks' => 20, 'duration' => '30 Min',
            'marks_per_q' => 1, 'negative' => 0,
            'sections' => [
                ['title' => 'घटक चाचणी', 'subtitle' => 'सराव प्रश्न', 'count' => 20, 'source_std' => [], 'source_subject' => []],
            ],
        ],
    ];
}
