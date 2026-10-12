# Reference style (given to the script LLM)

Derived from public listings/thumbnails of https://www.youtube.com/@saicoachingclasses-Raj
(chapter-wise Maharashtra Board std 5–8 solution videos) and the NotebookLM "video overview"
format the user likes (https://www.youtube.com/watch?v=zAJ1hydSWDM).

## What works on that channel
- One video = one chapter (or one practice set / one स्वाध्याय). Title always carries
  `इयत्ता <N>वी | <विषय> | <पाठ क्रमांक>. <पाठाचे नाव> | स्वाध्याय / प्रश्नोत्तरे | Maharashtra Board`.
- Video shows ONE question at a time on a bright slide, then the answer appears under it.
  Maths: the working is shown step by step (one step per line). Languages: question → 1–3 line answer.
- Narrator reads the question, pauses, reads the answer and adds one sentence of "why".
  Simple classroom Marathi/Hindi/English, warm and unhurried, addresses students as "विद्यार्थी मित्रांनो".
- Length 5–20 min. Shorts (≤ 60 s): one tricky question + answer.
- Thumbnail: big class number + subject, chapter name in 2 lines, textbook-style illustration, high-contrast
  yellow/blue, no more than 8 words.
- Description: chapter summary in 2 lines, question list with timestamps (chapters), playlist link,
  10–15 keywords (Marathi + English transliteration: "6vi vidyan", "std 6 science swadhyay").

## NotebookLM overview format (mixed in)
- Opens with a 20–30 s "what you will learn" overview slide (3–4 bullets).
- Closes with a 20–30 s recap of key points and a call to action (like, subscribe, next chapter).

## Voice/pacing rules for our pipeline
- ≈ 140 words/min; 2–4 s pause after a question before the answer (we insert a short beat).
- Never read option letters as "bracket a"; say "पर्याय अ".
- Numbers in Marathi words for std 1–4 (एकोणतीस), digits read as-is for std 5–8.
