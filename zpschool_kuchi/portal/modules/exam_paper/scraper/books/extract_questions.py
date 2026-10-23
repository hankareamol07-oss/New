#!/usr/bin/env python3
"""Extract chapter-wise exercise (स्वाध्याय / सराव संच / Exercise) questions from the OCR'd Balbharati textbooks.

Input : books/ocr/<book>/<page>.txt  (from ocr_all.py) + INDEX.csv
Output: books/book_questions.json  = {"books":[...], "questions":[...]}
        books/pages/<book_id>/<page>.jpg  (page image for questions that refer to a figure)
"""
import csv, json, os, re, sys, unicodedata
from difflib import SequenceMatcher

ROOT = os.path.dirname(os.path.abspath(__file__))
OCR = os.path.join(ROOT, 'ocr')
PAGES_OUT = os.path.join(ROOT, 'pages')
DEV = str.maketrans('०१२३४५६७८९', '0123456789')

TOC_RE = re.compile(r'(अनुक्रमणिका|अनुक्रमाणिका|अनुक्रम|विषय.?सूची|विषय.?सुची|पाठाचे नाव|पाठ का नाम|लेखक/कवी|Contents|CONTENTS|Lesson\s*Name|Name of the (?:Chapter|Lesson)|Title of the (?:lesson|unit)|Page\s*No)', re.U | re.I)
TOC_HEADER = re.compile(r'(अनुक्रम|पाठाचे नाव|पाठ का नाम|लेखक|पृष्ठ|पान|क्रमांक|अ\.\s*क्र|Contents|Page|Sr\.|Lesson|Title|No\.|Unit|Name of the|Expected|Periods)', re.U | re.I)
# "१२. title ...... ४५"  |  "« title | ४५"  |  "२. title" (page unreadable)
TOC_LINE = re.compile(r'^(?P<pre>\W{0,4}(?:[0-9०-९]{1,2}\s*[.,।)\]|]?|[a-zA-Z«*=]{1,2}\s*[.,।)\]|])?\s*[|।\]\[]?\s*)?(?P<title>[^\d०-९\W][^|]{2,80}?)\s*(?:[|।]\s*)?(?:[.…_\-|।\s]*?(?P<page>[0-9०-९]{1,3}))?\W{0,3}$', re.U)
NUM_LINE = re.compile(r'^\s*[|।(\[]?\s*([0-9०-९]{1,2})\s*[.)\]।|]\s*(?P<title>\S.{2,70})$', re.U)

INSTR_KW = ('उत्तरे लिहा', 'उत्तर लिहा', 'उत्तरे द्या', 'उत्तर द्या', 'उत्तरे सांगा', 'उत्तरे शोधा', 'रिकाम्या जाग', 'रिक्त स्थान', 'जोड्या',
            'चूक की बरोबर', 'चूक कि बरोबर', 'बरोबर की चूक', 'योग्य पर्याय', 'योग्य शब्द', 'कंसात', 'कोण ते', 'थोडक्यात', 'स्पष्ट करा',
            'कारणे लिहा', 'कारण लिहा', 'कारणे द्या', 'फरक', 'व्याख्या', 'सोडवा', 'काढा', 'लिहा', 'सांगा', 'शोधा', 'करा', 'ओळखा', 'का ते',
            'गाळलेल', 'भरा', 'जागा', 'निवडा', 'लावा', 'सांगा पाहू', 'किती', 'कोणत', 'कशा', 'कसे', 'काय', 'कुठे', 'कोठे', 'का ?', 'का?', 'मोजा', 'रंगवा', 'जुळवा', 'वर्तुळ करा', 'खूण करा',
            'वाक्यात', 'गोळा करा', 'पूर्ण करा', 'वर्णन', 'टीपा', 'माहिती', 'गटात न बसणारा', 'शब्दांचे अर्थ', 'समानार्थी', 'विरुद्धार्थी', 'वाक्प्रचार',
            'answer', 'fill in', 'match', 'write', 'complete', 'choose', 'find', 'solve', 'say whether', 'true or false', 'give reason',
            'explain', 'name the', 'read', 'make', 'discuss', 'describe', 'list', 'draw', 'identify', 'pick out', 'rewrite', 'add',
            'subtract', 'multiply', 'divide', 'compare', 'convert', 'arrange', 'count', 'state', 'distinguish', 'define', 'what', 'why', 'how',
            'उत्तर लिखो', 'लिखिए', 'लिखो', 'बताओ', 'करो', 'कीजिए', 'चुनो', 'मिलाओ', 'बनाओ', 'पढ़ो', 'दीजिए', 'दो', 'भरो', 'पूर्ति', 'खोजो', 'ढूँढो')
EXERCISE_HEAD = re.compile(r'^\W{0,3}(स्वाध्याय|सराव\s*संच|सराव|अभ्यास|उदाहरण\s*संग्रह|उदाहरणे|प्रश्नसंग्रह|Exercise|Practice\s*[Ss]et|Let.?s (?:do|practise|try)|Activity|कृती|उपक्रम|प्रकल्प|चला सराव करू)\b\s*[:\-]?\s*([0-9०-९]{1,2}(?:\s*[.(]\s*[0-9०-९]{1,2}\s*\)?)?)?\W{0,3}$', re.U | re.I)
LABEL = re.compile(r'^\W{0,2}(?:प्र(?:श्न)?\.?\s*[0-9०-९]{1,2}\s*(?:ला|रा|ली|था|वा|वी|सा)?|Q\.?\s*[0-9०-९]{1,2}|[0-9०-९]{1,2}|\(?\s*[अआइईउएबकडफABCDEFGH]\s*\))\s*[.)]?\s*(?P<t>\S.*)$', re.U)
ITEM_RE = re.compile(r'^\s*\(?\s*(?P<n>[0-9०-९]{1,2}|[ivx]{1,4}|[a-h]|[अआइईउऊएऐओऔबकडफ])\s*[\)\.।]\s*(?P<t>\S.*)$', re.U)
BULLET_RE = re.compile(r'^\s*[७●•*e★☆»>+\-–]\s+(?P<t>\S.{4,})$', re.U)
INLINE_SPLIT = re.compile(r'\s+(?=\(?[0-9०-९]{1,2}\s*[\)\.]\s*\S)', re.U)
FIGURE_KW = re.compile(r'(चित्र|आकृती|आकृति|नकाशा|तक्ता|तक्त्या|आलेख|कोष्टक|शब्दकोड|figure|picture|diagram|map|graph|table|chart|puzzle|grid|shown|given below|खालील (?:आकृती|चित्र)|दिलेल्या (?:आकृती|चित्र)|पुढील (?:आकृती|चित्र))', re.U | re.I)
NOISE = re.compile(r'^[\W\d_ ]*$', re.U)
OUTCOMES_PAGE = re.compile(r'(Learning Outcomes|Curricular|Carricular|Curriculum Objective|अध्ययन निष्पत्ती)', re.I)
SKIP_PAGE = re.compile(r'(राष्ट्रगीत|प्रतिज्ञा|जनगणमन|National Anthem|Pledge|Preamble|संविधान|Learning Outcomes|Curricular|Carricular|Competenc|Curriculum Objective|अध्ययन निष्पत्ती|समिती|सदस्य|प्रकाशक|Committee|Publisher|Reprint|मुद्रक)', re.I)
TEACHER = re.compile(r'(शिक्षकांसाठी|For the teacher|शिक्षकों के लिए|अध्ययन निष्पत्ती|Learning Outcomes)', re.U | re.I)

QTYPES = [
    ('fill_blank', r'रिकाम्या जाग|रिक्त स्थान|fill in|गाळलेल|blank'),
    ('true_false', r'चूक की बरोबर|चूक कि बरोबर|बरोबर की चूक|true or false|say whether|सही या गलत|सत्य या असत्य'),
    ('match', r'जोड्या|match|मिलाओ|जोड़ी'),
    ('mcq', r'योग्य पर्याय|पर्याय निवडा|choose the (?:correct|right)|tick|✓|option|सही विकल्प'),
    ('odd_one', r'गटात न बसणारा|odd one|odd man'),
    ('one_word', r'कोण ते|एका शब्दात|one word|कौन|नावे लिहा|नावे सांगा|name the'),
    ('one_sentence', r'एका वाक्यात|एक वाक्यात|in one sentence|in a sentence|एक वाक्य में'),
    ('short_answer', r'थोडक्यात|दोन-तीन वाक्य|२-३ वाक्य|short|briefly|in brief|संक्षेप|थोडक्यात उत्तरे'),
    ('reason', r'कारण|why|give reason|शास्त्रीय कारण'),
    ('difference', r'फरक|difference|अंतर'),
    ('define', r'व्याख्या|define|definition'),
    ('explain', r'स्पष्ट करा|explain|समजावून|टीपा|note|describe|वर्णन'),
    ('solve', r'सोडवा|solve|काढा|find|बेरीज|वजाबाकी|गुणाकार|भागाकार|add|subtract|multiply|divide|calculate|किंमत|value|convert|रूपांतर'),
    ('draw', r'काढा|draw|रेखाटा|आकृती'),
    ('vocabulary', r'समानार्थी|विरुद्धार्थी|वाक्प्रचार|शब्दांचे अर्थ|synonym|antonym|opposite|meaning|rhyming'),
    ('grammar', r'व्याकरण|वाक्य|लिंग|वचन|काळ|grammar|tense|noun|verb|adjective|punctuat'),
    ('activity', r'उपक्रम|प्रकल्प|activity|project|करून पहा|कृती|गोळा करा|collect|discuss|चर्चा'),
]


def d2i(s):
    try:
        return int(str(s).translate(DEV))
    except (TypeError, ValueError):
        return None


def clean(s):
    s = unicodedata.normalize('NFC', s)
    s = re.sub(r'[|।॥_\-–—=~.]{3,}', '______', s)
    s = re.sub(r'[|]', ' ', s)
    s = re.sub(r'\s{2,}', ' ', s).strip(' \t.:;,-|')
    if re.search(r'[\u0900-\u097F]', s):
        # Devanagari text: trailing 1-3 letter latin tokens are OCR garbage ("ale ale ale", "oP OP Oe")
        s = re.sub(r'(\s+[A-Za-z]{1,3}\b)+\W*$', '', s)
    if s.endswith(')') and s.count('(') < s.count(')'):
        s = s[:-1].rstrip(' .')
    return s.strip()


def similar(a, b):
    a = re.sub(r'\W', '', a)
    b = re.sub(r'\W', '', b)
    if not a or not b:
        return 0
    return SequenceMatcher(None, a, b).ratio()


def is_instruction(line):
    low = line.lower()
    return any(k in low for k in INSTR_KW)


def qtype_for(instr, text=''):
    s = (instr + ' ' + text).lower()
    for t, pat in QTYPES:
        if re.search(pat, s, re.U | re.I):
            return t
    return 'descriptive'


def load_pages(book_dir):
    pages = {}
    for f in sorted(os.listdir(book_dir)):
        if f.endswith('.txt'):
            pages[int(f[:3])] = open(os.path.join(book_dir, f), encoding='utf-8', errors='ignore').read()
    return pages


def letters(s):
    return len(re.findall(r'[\u0900-\u097FA-Za-z]', s))


def parse_toc(pages):
    """Return list of (chapter_no, title, printed_page|None) from the contents page(s).
    Chapter numbers are assigned sequentially (OCR of the printed numbers is unreliable)."""
    toc_pages = [p for p in sorted(pages) if p < 20 and TOC_RE.search(pages[p])]
    # a contents page has many lines ending in a page number
    def score(p):
        if len(re.findall(r'\d{2}\.\d{2}\.\d{2}', pages[p])) >= 3 or OUTCOMES_PAGE.search(pages[p]):
            return 0      # learning-outcomes table, not the contents page
        return sum(1 for l in pages[p].splitlines() if re.search(r'[\u0900-\u097FA-Za-z]{3,}.*\s[0-9०-९]{1,3}\W{0,3}$', l.strip()))
    if not toc_pages:
        # heading missed by OCR: look for a page full of "<no> | <title> <page>" lines
        def numbered(p):
            return sum(1 for l in pages[p].splitlines()
                       if re.match(r'^\W{0,3}[0-9०-९]{1,2}\s*[.।|)\]]\s*[\u0900-\u097FA-Za-z][^0-9०-९]{2,45}\s[0-9०-९]{1,3}\W{0,3}$', l.strip()))
        cands = [p for p in sorted(pages) if 2 <= p < 20 and numbered(p) >= 5]
        if not cands:
            return []
        best = max(cands, key=numbered)
        toc_pages = [best]
    toc_pages = sorted(set(toc_pages + [p + d for p in toc_pages for d in (-1, 1) if p + d in pages and score(p + d) >= 4]))
    scored = [p for p in toc_pages if score(p) >= 3]
    if not scored:
        # page numbers were in a separate column the OCR dropped: take the title lines only
        heads = [p for p in toc_pages
                 if any(len(l.strip()) <= 40 and re.search(r'(अनुक्रमणिका|अनुक्रम|Contents|CONTENTS|पाठाचे नाव|पाठ का नाम)', l, re.I) for l in pages[p].splitlines())
                 and len(re.findall(r'\d{2}\.\d{2}\.\d{2}', pages[p])) < 3 and not SKIP_PAGE.search(pages[p])]
        def title_line(l):
            l = re.sub(r'[\s.…_\-–]*[0-9०-९]{1,3}(?:\s*(?:ते|to|से|[-–])\s*[0-9०-९]{1,3})?\W{0,3}$', '', l.strip())
            return (3 <= len(l) <= 45 and letters(l) >= 3 and len(l.split()) <= 7 and not NOISE.match(l)
                    and not re.search(r'[.,;:!?]$', l) and not re.search(r'\d{2,}', l)
                    and max(l.lower().count(c) for c in set(l.lower()) if c.isalpha()) <= max(3, len(l) // 3))
        if not heads:
            # heading lost by OCR: a page that is mostly short title lines (English readers list lessons this way)
            def ratio(p):
                ls = [l for l in pages[p].splitlines() if l.strip()]
                n = sum(1 for l in ls if title_line(l))
                return n if ls and n >= 6 and n / len(ls) >= 0.7 else 0
            cands = [p for p in sorted(pages) if 3 <= p < 20 and ratio(p) and not SKIP_PAGE.search(pages[p])]
            if not cands:
                return []
            heads = [max(cands, key=ratio)]
        entries = []
        for raw in pages[heads[0]].splitlines():
            if re.match(r'^\W{0,3}(Unit|Part|Section|विभाग|भाग|इकाई)\s+\S{1,6}\W{0,3}$', raw.strip(), re.I):
                continue
            line = clean(re.sub(r'^\W{0,4}(?:[0-9०-९]{1,2})?\s*[.।)|\]]?\s*', '', raw.strip()))
            if letters(line) < 3 or TOC_HEADER.search(line) or len(line) > 45 or NOISE.match(line) or not title_line(line):
                continue
            if re.search(r'(?:प्रा\.|श्री|डॉ\.|पान|पृष्ठ|सूचना|प्रस्तावना|मुखपृष्ठ)', line) or re.search(r'[.,;:]$', line) or len(line.split()) > 7:
                continue
            entries.append([line, None])
        return [(i + 1, t, pg) for i, (t, pg) in enumerate(entries)] if len(entries) >= 3 else []
    toc_pages = sorted(set(scored + [p + d for p in scored for d in (-1, 1) if p + d in pages and score(p + d) >= 4]))
    entries = []
    for p in toc_pages:
        for raw in pages[p].splitlines():
            line = raw.strip()
            if len(line) < 4 or NOISE.match(line):
                continue
            line = re.sub(r'([0-9०-९]{1,3})\s*(?:ते|to|से|[-–])\s*[0-9०-९]{1,3}\W*$', r'\1', line)   # page range -> first page
            m = TOC_LINE.match(line)
            if not m:
                continue
            title = clean(m.group('title'))
            pg = d2i(m.group('page'))
            pre = (m.group('pre') or '').strip()
            if letters(title) < 3 or TOC_HEADER.search(title) or len(title) > 80:
                continue
            if pg is None and not pre:
                continue           # plain prose line
            if pg is None:
                m2 = re.search(r'\s([0-9०-९]{1,3})\s*(?:ते|to|से|[-–])\s*[0-9०-९]{1,3}\W*$', title)
                if m2:
                    pg = d2i(m2.group(1))
                    title = clean(title[:m2.start()])
            if pg is not None and pg > 400:
                continue
            entries.append([title, pg])
    # printed pages must increase; drop unreadable ones
    last = 0
    for e in entries:
        if e[1] is not None:
            if e[1] < last or e[1] > last + 60:
                e[1] = None
            else:
                last = e[1]
    if len(entries) < 3:
        return []
    return [(i + 1, t, pg) for i, (t, pg) in enumerate(entries)]


def find_offset(toc, pages):
    """pdf_index = printed_page + offset. Pick the offset for which chapter titles are found near their predicted page."""
    scores = {}
    for off in range(0, 25):
        score = 0
        for no, title, pg in toc:
            if pg is None:
                continue
            for d in (0, 1, -1):
                idx = pg + off + d
                txt = pages.get(idx, '')
                head = '\n'.join(txt.splitlines()[:12])
                if title[:12] in head or any(similar(title, l) > 0.75 for l in head.splitlines() if l.strip()):
                    score += 1 if d == 0 else 0.5
                    break
        scores[off] = score
    best = max(scores, key=lambda k: (scores[k], -k))
    return best if scores[best] >= 2 else None


def chapter_ranges(toc, offset, pages, first_content):
    """Map every chapter to a (start_pdf, end_pdf) range."""
    last = max(pages)
    ranges = []
    starts = []
    for i, (no, title, pg) in enumerate(toc):
        if pg is not None and offset is not None:
            starts.append(pg + offset)
        else:
            starts.append(None)
    # locate chapters whose printed page is unknown by finding their title as a heading
    pos = first_content
    for i, (no, title, pg) in enumerate(toc):
        if starts[i] is not None:
            pos = max(pos, starts[i])
            continue
        nxt = next((starts[j] for j in range(i + 1, len(starts)) if starts[j] is not None), last)
        for p in range(pos, min(nxt, last) + 1):
            head = [l.strip() for l in pages.get(p, '').splitlines()[:8] if l.strip()]
            if any(title[:10] in l or similar(title, l) > 0.7 for l in head):
                starts[i] = p
                pos = p + 1
                break
    # interpolate missing starts
    for i, s in enumerate(starts):
        if s is None:
            prev = next((starts[j] for j in range(i - 1, -1, -1) if starts[j] is not None), first_content)
            nxt = next((starts[j] for j in range(i + 1, len(starts)) if starts[j] is not None), last)
            starts[i] = (prev + nxt) // 2 if nxt > prev else prev
    for i, (no, title, pg) in enumerate(toc):
        start = max(first_content, starts[i])
        end = (starts[i + 1] - 1) if i + 1 < len(starts) else last
        ranges.append({'no': no, 'title': title, 'start': start, 'end': max(start, end)})
    return ranges


def extract_from_page(text, page_no):
    """Yield sections [{instruction, marks, items:[(text)], page}] found in a page."""
    sections = []
    cur = None
    in_exercise = False
    lines = [l.rstrip() for l in text.splitlines()]
    for i, raw in enumerate(lines):
        line = raw.strip()
        if not line or NOISE.match(line):
            continue
        if TEACHER.search(line):
            cur = None
            continue
        if EXERCISE_HEAD.match(line):
            in_exercise = True
            cur = {'instruction': clean(line), 'block': clean(line), 'items': [], 'page': page_no, 'kind': 'head'}
            sections.append(cur)
            continue
        lm = LABEL.match(line)
        body = lm.group('t') if lm else line
        upcoming = [l.strip() for l in lines[i + 1:i + 7] if l.strip() and not NOISE.match(l.strip())]
        n_items_follow = sum(1 for l in upcoming[:4] if ITEM_RE.match(l))
        first_item = next((ITEM_RE.match(l) for l in upcoming[:2] if ITEM_RE.match(l)), None)
        starts_list = first_item is not None and (first_item.group('n').translate(DEV) in ('1', 'a', 'i', 'अ'))
        item_m = ITEM_RE.match(line)
        instr = len(body) <= 140 and is_instruction(body)
        if item_m and item_m.group('n').translate(DEV).isdigit():
            # numbered line: a heading only if it opens a fresh numbered list ("१. उत्तरे लिहा." then "(१) ...")
            is_head = instr and starts_list and n_items_follow >= 1
        elif item_m:
            # letter label (अ) / (a): heading when instruction-like and something numbered follows
            is_head = instr and (n_items_follow >= 1 or body.rstrip().endswith((':', '.', '?', ')')) and len(body) < 90)
        else:
            is_head = instr and (n_items_follow >= 2 or (in_exercise and n_items_follow >= 1) or
                                 (bool(lm) and n_items_follow >= 1))
        if is_head:
            cur = {'instruction': clean(body), 'block': sections[-1]['block'] if sections and sections[-1].get('kind') == 'head' else '',
                   'items': [], 'page': page_no, 'kind': 'section'}
            sections.append(cur)
            continue
        bm = BULLET_RE.match(line)
        if bm and len(line) <= 200 and ('?' in line or (is_instruction(bm.group('t')) and n_items_follow == 0)):
            # "* त्रिकोणाला कडा किती ?" / "+ खालील तक्ता पूर्ण करा." - a self-contained activity question
            t = clean(bm.group('t'))
            if n_items_follow >= 1 and is_instruction(t):
                cur = {'instruction': t, 'block': '', 'items': [], 'page': page_no, 'kind': 'section'}
                sections.append(cur)
            elif letters(t) >= 4:
                sections.append({'instruction': '', 'block': '', 'items': [t], 'page': page_no, 'kind': 'bullet'})
                cur = None
            continue
        if cur is None:
            continue
        parts = INLINE_SPLIT.split(line) if ITEM_RE.match(line) else [line]
        for p in parts:
            im = ITEM_RE.match(p)
            if im:
                t = clean(im.group('t'))
                if len(t) >= 2:
                    cur['items'].append(t)
            elif BULLET_RE.match(p):
                t = clean(BULLET_RE.match(p).group('t'))
                if len(t) >= 3:
                    cur['items'].append(t)
            elif cur['items'] and len(p) > 2 and len(cur['items'][-1]) < 400:
                cur['items'][-1] = clean(cur['items'][-1] + ' ' + p)
            elif not cur['items'] and len(p) > 3 and len(cur['instruction']) < 300:
                cur['instruction'] = clean(cur['instruction'] + ' ' + p)
    return [s for s in sections if s['items'] or (s['kind'] == 'section' and '?' in s['instruction'])]


CHAPTER_END = re.compile(r'^(?:[^\n]{0,12}\W)?(स्वाध्याय|Exercises?|अभ्यास|Let.?s (?:do|practise))\b(?:[^\w\n]{0,6}\w{0,6}){0,3}\W*$', re.U | re.I | re.M)
LESSON_WORD = {'mr': 'पाठ', 'hi': 'पाठ', 'en': 'Lesson'}
PAGE_WORD = {'mr': 'पृ.', 'hi': 'पृ.', 'en': 'pp.'}


def pseudo_chapters(pages, lang, first_content, min_span=4, block=12):
    """No readable contents page: split the book at the end-of-chapter exercise headings
    (a chapter runs from the page after the previous स्वाध्याय/Exercise up to and including its own).
    Falls back to fixed page blocks when no such headings are found."""
    last = max(pages)
    ends = []
    for p in sorted(pages):
        if p < first_content or not CHAPTER_END.search(pages[p]):
            continue
        if ends and p - ends[-1] <= 1:      # exercise continues on the next page
            ends[-1] = p
        elif ends and p - ends[-1] < min_span:
            ends[-1] = p
        else:
            ends.append(p)
    if len(ends) < 2:
        ends = list(range(first_content + block - 1, last, block))
    if not ends or ends[-1] < last:
        ends.append(last)
    chapters, start = [], 0
    for i, e in enumerate(ends, 1):
        label = f"{LESSON_WORD[lang]} {i}"
        rng = f"({PAGE_WORD[lang]} {start + 1}-{e + 1})"
        chapters.append({'no': i, 'title': f"{label} {rng}", 'start': start, 'end': e})
        start = e + 1
    return chapters


def render_page(pdf, page_no, out_jpg):
    if os.path.exists(out_jpg):
        return
    import pymupdf
    doc = pymupdf.open(pdf)
    os.makedirs(os.path.dirname(out_jpg), exist_ok=True)
    doc[page_no].get_pixmap(dpi=110).save(out_jpg, jpg_quality=70)
    doc.close()


def find_pdf(name):
    for d, _, fs in os.walk(ROOT):
        if name in fs:
            return os.path.join(d, name)
    return None


def main():
    rows = list(csv.DictReader(open(os.path.join(ROOT, 'INDEX.csv'))))
    books, questions = [], []
    qid = 0
    for bid, r in enumerate(rows, 1):
        book = os.path.splitext(r['File'])[0]
        bdir = os.path.join(OCR, book)
        if not os.path.isdir(bdir):
            continue
        pages = load_pages(bdir)
        if len(pages) < int(r['Pages']) - 2:
            print(f"{book}: OCR incomplete ({len(pages)}/{r['Pages']}) - skipped", flush=True)
            continue
        std = int(re.sub(r'\D', '', r['Standard']))
        lang = 'hi' if 'Hindi' in book else 'mr' if 'Marathi' in book else 'en'
        subject = r['Subject']
        if 'Geography' in book:
            subject = 'Geography'
        elif 'History' in book:
            subject = 'History & Civics'
        elif 'Part_1' in book:
            subject += ' Part 1'
        elif 'Part_2' in book:
            subject += ' Part 2'
        r['Subject'] = subject
        toc = parse_toc(pages)
        offset = find_offset(toc, pages) if toc else None
        toc_hits = [p for p in pages if p < 20 and TOC_RE.search(pages[p])] or [p for p in pages if p < 20 and any(t[:12] in pages[p] for _, t, _ in toc[:3])]
        first_content = (max(toc_hits) + 1) if toc and toc_hits else 4
        if toc and offset is None:
            # chapter headings are usually decorative art (not OCR'd): assume printed page 1 follows the front matter
            first_pg = next((pg for _, _, pg in toc if pg is not None), 1)
            offset = first_content - first_pg
        if offset is not None and not 0 <= offset <= 30:
            offset = first_content - 1
        chapters = chapter_ranges(toc, offset, pages, first_content) if toc else pseudo_chapters(pages, lang, first_content)
        pdf = find_pdf(r['File'])
        nq = 0
        for ch in chapters:
            for p in range(ch['start'], ch['end'] + 1):
                if p not in pages:
                    continue
                for sec in extract_from_page(pages[p], p):
                    items = sec['items'] or [sec['instruction']]
                    for k, t in enumerate(items):
                        if len(t) < 3 or NOISE.match(t):
                            continue
                        instr = sec['instruction'] if sec['items'] else ''
                        fig = bool(FIGURE_KW.search(t) or FIGURE_KW.search(instr))
                        qid += 1
                        nq += 1
                        img = None
                        if fig and pdf:
                            img = f'{bid}/{p:03d}.jpg'
                            try:
                                render_page(pdf, p, os.path.join(PAGES_OUT, img))
                            except Exception as e:  # noqa
                                print('render fail', book, p, e, file=sys.stderr)
                                img = None
                        questions.append({
                            'id': qid, 'book_id': bid, 'std': std, 'subject': r['Subject'], 'lang': lang,
                            'chapter_no': ch['no'], 'chapter': ch['title'], 'page': p + 1,
                            'block': sec['block'], 'instruction': instr, 'qtype': qtype_for(instr, t),
                            'text': t, 'item_no': k + 1 if sec['items'] else None,
                            'needs_figure': fig, 'page_image': img,
                        })
        books.append({'book_id': bid, 'std': std, 'subject': r['Subject'], 'lang': lang, 'file': r['File'], 'title': book.replace('_', ' '),
                      'pages': len(pages), 'toc_found': bool(toc), 'page_offset': offset,
                      'chapters': [{'no': c['no'], 'title': c['title'], 'start_page': c['start'] + 1, 'end_page': c['end'] + 1} for c in chapters],
                      'questions': nq})
        print(f"{book}: pages={len(pages)} toc={len(toc)} offset={offset} questions={nq}", flush=True)
    json.dump({'books': books, 'questions': questions}, open(os.path.join(ROOT, 'book_questions.json'), 'w'), ensure_ascii=False, indent=0)
    print('TOTAL books', len(books), 'questions', len(questions))


if __name__ == '__main__':
    main()
