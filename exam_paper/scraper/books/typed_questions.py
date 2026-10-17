#!/usr/bin/env python3
"""Per-chapter MiniShala-style typed question set (AI generated, with answers).

Output typed/<book_id>_<chapter_no>.json : {"questions":[{qtype, instruction, text, options, pairs, answer}]}
Reuses the API plumbing (backends, retries, quota handling) of gemini_packs.py.
Resumable; BACKEND=gemini|nvidia|groq|cerebras|mistral, WORKERS=n, book ids as argv filter.
"""
import json, os, re, sys
from concurrent.futures import ThreadPoolExecutor
from collections import Counter

os.environ.setdefault('BQ', '/home/ubuntu/repos/New/exam_paper/data/book_questions.json')
import gemini_packs as gp

OUT = os.environ.get('TYPED_OUT', os.path.join(gp.ROOT, 'typed'))

gp.SCHEMA = {
    "type": "OBJECT",
    "properties": {"questions": {"type": "ARRAY", "items": {"type": "OBJECT", "properties": {
        "qtype": {"type": "STRING"},
        "instruction": {"type": "STRING"},
        "text": {"type": "STRING"},
        "options": {"type": "ARRAY", "items": {"type": "STRING"}},
        "pairs": {"type": "ARRAY", "items": {"type": "ARRAY", "items": {"type": "STRING"}}},
        "answer": {"type": "STRING"},
        "marks": {"type": "INTEGER"}}, "required": ["qtype", "text", "answer"]}}},
    "required": ["questions"],
}

# how many of each type per chapter; maths/science add solve+draw, languages add vocabulary/grammar
BASE = [('fill_blank', 5), ('true_false', 4), ('match', 1), ('mcq', 4), ('odd_one', 2), ('one_word', 5),
        ('one_sentence', 5), ('short_answer', 4), ('reason', 3), ('descriptive', 2)]
EXTRA = {
    'Maths': [('solve', 6), ('draw', 1), ('define', 2)],
    'Science': [('define', 3), ('difference', 2), ('draw', 2), ('explain', 2), ('solve', 2)],
    'EVS': [('define', 2), ('difference', 2), ('draw', 2), ('explain', 2)],
    'Geography': [('define', 2), ('difference', 2), ('draw', 2), ('explain', 2)],
    'History': [('define', 2), ('explain', 3)],
    'Civics': [('define', 2), ('explain', 3)],
}
LANG_EXTRA = [('vocabulary', 4), ('grammar', 4), ('explain', 2)]

LOWER = {1: 0.5, 2: 0.6, 3: 0.8}


def plan(book):
    sub = book['subject']
    items = BASE + EXTRA.get(sub, LANG_EXTRA if sub in ('Marathi', 'Hindi', 'English') else [('define', 2), ('explain', 2)])
    f = LOWER.get(book['std'], 1.0)
    return [(t, max(1, round(n * f))) for t, n in items]


def prompt(book, ch, text):
    lang = gp.LANG.get(book['lang'], 'English')
    if book['lang'] == 'hi':
        lang = 'Hindi (हिंदी, मानक हिंदी व्याकरण — NOT Marathi: never use आहे / च्या / म्हणतो / ळ; use है / का / कहता है)'
    want = '\n'.join(f'- {t}: {n}' for t, n in plan(book))
    return f"""You are an experienced Maharashtra State Board (Balbharati) teacher preparing an आकारिक / संकलित चाचणी question bank. Standard {book['std']}, subject: {book['subject']}, language: {lang}.
Below is the OCR text (noisy) of the chapter "{ch['title']}".

Write NEW test questions (not copies of the textbook exercise) covering the whole chapter, strictly in {lang} (English subject stays English; keep numbers/symbols). Difficulty suitable for standard {book['std']}; each question answerable in the space a pupil gets on a printed paper. Required counts by "qtype":
{want}

Rules per qtype:
- fill_blank: one sentence with the blank shown as ______ ; answer = the missing word(s).
- true_false: a statement; answer "बरोबर"/"चूक" (Hindi: "सही"/"गलत", English: "True"/"False").
- match: text = "जोड्या लावा." style stem; "pairs" = 4-5 [left, right] pairs (correctly matched; the paper shuffles the right column); answer = "".
- mcq: 4 "options"; answer = the correct option text.
- odd_one: 4 "options"; answer = the odd item + why (short).
- one_word: answer in one word; one_sentence: answer in one sentence.
- short_answer: 2-3 sentence answer; reason: "…का?" / "Give reason" style, 3-4 sentence answer; difference: "…व … यांतील फरक", 3-4 points; define: definition; explain: 4-5 sentence answer.
- solve: a numerical / worked problem from the chapter with full working in the answer (maths/science only).
- draw: ask to draw a labelled figure/diagram/map/table from the chapter; answer = list of labels expected.
- vocabulary: synonyms/antonyms/word meaning/gender/plural etc.; grammar: grammar item from the chapter.
- descriptive: essay / letter / paragraph / story / summary style, 6-10 lines expected; answer = model points.
- "instruction": the section heading the question belongs under, e.g. "रिकाम्या जागी योग्य शब्द लिहा.", "खालील प्रश्नांची एका वाक्यात उत्तरे लिहा.", "Answer in one sentence.". Use the same instruction wording for all questions of one qtype.
- "marks": typical marks (1 for objective/one_word, 2 one_sentence/vocabulary/grammar, 3 short_answer/reason/define, 4 difference/explain/solve/draw, 5 descriptive).
Clean text, no OCR noise, no numbering inside "text".

Return ONLY a JSON object: {{"questions": [{{"qtype", "instruction", "text", "options": [..], "pairs": [[l, r], ..], "answer", "marks"}}]}}

OCR TEXT:
{text[:gp.TEXT_CAP]}
"""


MARATHI = re.compile(r'आहे|च्या|ळ|म्हण|द्या|लिहा')


def valid(res, book):
    if not isinstance(res, dict) or not isinstance(res.get('questions'), list):
        return False
    if book['lang'] == 'hi':
        qs = [q for q in res['questions'] if isinstance(q, dict)]
        if qs and sum(1 for q in qs if MARATHI.search(str(q.get('text', '')) + str(q.get('answer', '')))) > len(qs) * 0.15:
            return False  # model drifted into Marathi
    good = []
    for q in res['questions']:
        if not isinstance(q, dict) or not str(q.get('text', '')).strip() or not q.get('qtype'):
            continue
        if q['qtype'] == 'match':
            pr = [p for p in (q.get('pairs') or []) if isinstance(p, list) and len(p) == 2 and all(str(x).strip() for x in p)]
            if len(pr) < 3:
                continue
            q['pairs'] = [[str(a).strip(), str(b).strip()] for a, b in pr]
        elif q['qtype'] in ('mcq', 'odd_one'):
            if not isinstance(q.get('options'), list) or len(q['options']) < 3:
                continue
        elif q['qtype'] == 'fill_blank' and '___' not in q['text']:
            continue
        good.append(q)
    need = sum(n for _, n in plan(book))
    res['questions'] = good
    return len(good) >= need * 0.6


def do_chapter(job):
    book, ch = job
    out = os.path.join(OUT, f"{book['book_id']}_{ch['no']}.json")
    if os.path.exists(out):
        return 'skip'
    lock = out + '.lock'
    try:
        os.close(os.open(lock, os.O_CREAT | os.O_EXCL | os.O_WRONLY))
    except FileExistsError:
        return 'skip'
    try:
        text = gp.chapter_text(book, ch)
        if len(text.strip()) < 200:
            return 'empty'
        for attempt in range(3):
            res, usage = gp.call(prompt(book, ch, text))
            if res == 'too_large':
                res, usage = gp.call(prompt(book, ch, text[:14000]))
            if res == 'exhausted':
                return 'exhausted'
            if valid(res, book):
                break
            print('invalid', book['book_id'], ch['no'], type(res).__name__, file=sys.stderr, flush=True)
            res = None
        if not res:
            return 'fail'
        res.update(book_id=book['book_id'], std=book['std'], subject=book['subject'], lang=book['lang'],
                   chapter_no=ch['no'], chapter=ch['title'], usage=usage)
        json.dump(res, open(out + '.tmp', 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
        os.replace(out + '.tmp', out)
        print(book['std'], book['subject'], ch['no'], ch['title'][:40], 'q', len(res['questions']), file=sys.stderr, flush=True)
        return 'ok'
    finally:
        try:
            os.remove(lock)
        except OSError:
            pass


if __name__ == '__main__':
    os.makedirs(OUT, exist_ok=True)
    d = json.load(open(gp.BQ, encoding='utf-8'))
    jobs = [(b, ch) for b in d['books'] for ch in b['chapters'] if int(ch['no']) != 0]
    if len(sys.argv) > 1:
        jobs = [j for j in jobs if str(j[0]['book_id']) in sys.argv[1:]]
    if os.environ.get('REVERSE'):
        jobs.reverse()
    print(len(jobs), 'chapters', file=sys.stderr)
    with ThreadPoolExecutor(int(os.environ.get('WORKERS', '3'))) as ex:
        print(Counter(ex.map(do_chapter, jobs)), file=sys.stderr)
