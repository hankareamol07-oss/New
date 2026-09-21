# Competitive exam question bank (downloadpapers.com objective questions)

Files:
- `questions.json` – all questions with answer key
- `images/` – every question / option / solution image referenced from the JSON

Coverage (only these categories have objective questions on downloadpapers.com):

| standard              | questions |
|-----------------------|-----------|
| 4th शिष्यवृत्ती        | 10,544    |
| 7th शिष्यवृत्ती        | 7,743     |
| 8) NMMS               | 14,148    |

`questions.json` structure:

```json
{
  "source": "https://downloadpapers.com",
  "count": 32435,
  "questions": [
    {
      "standard_id": 72, "standard": "4th शिष्यवृत्ती", "medium": "Semi-English",
      "subject_id": 256, "subject": "पेपर क्र. १) (मराठी व गणित)",
      "chapter_id": 3104, "chapter": "मराठी - 1) आकलन",
      "topic_id": 2743, "topic": "1.1) उतारा व त्यावर आधारित प्रश्न",
      "is_pyq": 0,
      "question_id": 134647,
      "parent_id": 134646,          // set when the question belongs to a passage / group question
      "is_group": false,            // true = this record is the passage / common text of a group
      "difficulty": "Simple",       // Simple | Medium | Hard
      "marks": 2.0,
      "text": "पूर्ण नसलेल्या किल्ल्यास काय म्हणतात?",
      "images": [],                 // paths relative to this folder, e.g. "images/61164054815211865"
      "options": [ {"text": "डोंगरी किल्ला", "images": []}, ... ],   // 4 options, index 0..3
      "correct_option": 2,          // ANSWER KEY: 0-based index into options
      "correct_option_label": "C",  // A/B/C/D
      "solution": "c) गढी",         // explanation text (may be empty)
      "solution_images": []
    }
  ]
}
```

Image files have no extension; they are PNG. Serve them with `Content-Type: image/png`.
