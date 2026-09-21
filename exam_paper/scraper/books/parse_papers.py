#!/usr/bin/env python3
"""Parse OCR text of minishala संकलित / आकारिक model papers into structured sections/questions.

Output: /home/ubuntu/papers/model_papers.json  (list of papers)
  {exam_type, test_no, std, subject, file, file_id, pages, total_marks,
   sections:[{q_no, sub, instruction, marks, items:[...] }]}
"""
import glob, json, os, re, unicodedata

ROOT = '/home/ubuntu/papers'
manifest = json.load(open(f'{ROOT}/manifest.json'))

DEV = str.maketrans('०१२३४५६७८९', '0123456789')
STD_WORDS = {'पहिली': 1, 'दुसरी': 2, 'तिसरी': 3, 'चौथी': 4, 'चोथी': 4, 'पाचवी': 5, 'सहावी': 6, 'सातवी': 7, 'आठवी': 8,
             'पहली': 1, 'दूसरी': 2, 'तीसरी': 3, 'चौथी': 4, 'पाँचवी': 5, 'पांचवी': 5, 'छठी': 6, 'सातवीं': 7, 'आठवीं': 8}
SUB_LETTERS = 'अआबकडइईएफ' 
ORD = r'(?:ला|रा|ली|था|वा|वी|सा|रे|ले)?'

# "प्रश्न १ ला अ) रिकाम्या जागी ... (४ गुण)"  /  "प्रश्न.1(अ) ... (गुण 5 )" / "Q 4 A) Write ... (2 mark)" / "Q. 3) Answer ... (6 mark)"
SEC_RE = re.compile(
    r'^\s*(?:प्रश्न|प्रथ्न|प्र\.|प्र|Q\.?|Que\.?)\s*\.?\s*(?P<q>[0-9०-९]{1,2})?\s*' + ORD + r'\s*'
    r'(?:\(?\s*(?P<sub>[' + SUB_LETTERS + r'A-Ha-h])\s*\)|\)|\.)?\s*'
    r'(?P<instr>.+?)\s*'
    r'(?:\(\s*(?:गुण|marks?|Marks?)?\s*[:\-]?\s*(?P<m1>[0-9०-९]{1,2})\s*(?:गुण|marks?|Marks?|गु\.?)?\s*\)|(?P<m2>[0-9०-९]{1,2})\s*(?:गुण|marks?))\s*\.?\s*$',
    re.U)
# section with sub-letter only: "(अ) खालील ... (३ गुण)" / "अ) ... (२ गुण)" / "ब) ..."
SUB_RE = re.compile(
    r'^\s*\(?\s*(?P<sub>[' + SUB_LETTERS + r'A-Ha-h])\s*\)\s*(?P<instr>.+?)\s*'
    r'\(\s*(?:गुण|marks?|Marks?)?\s*[:\-]?\s*(?P<m1>[0-9०-९]{1,2})\s*(?:गुण|marks?|Marks?|गु\.?)?\s*\)\s*\.?\s*$', re.U)
ITEM_RE = re.compile(r'^\s*\(?\s*([0-9०-९]{1,2}|[ivx]{1,4}|[a-h]|[अआबकडइई])\s*[\)\.]\s*(?P<t>\S.*)$', re.U)
INLINE_SPLIT = re.compile(r'\s+(?=\(?[0-9०-९]{1,2}\s*\)\s*\S)', re.U)
TOTAL_RE = re.compile(r'(?:एकूण\s*गुण|एकुण\s*गुण|कुल\s*अंक|Total\s*Marks?)\D{0,6}([0-9०-९]{1,3})', re.U | re.I)
STD_RE = re.compile(r'(?:इयत्ता|ड्यत्ता|डइयत्ता|कक्षा|Std\.?|Class)\s*[:\-]?\s*([^\s:]+)', re.U | re.I)


def d2i(s):
    return int(s.translate(DEV)) if s else None


def clean(s):
    s = unicodedata.normalize('NFC', s)
    s = re.sub(r'[|।॥_\-–—=]{3,}', '______', s)      # blank lines drawn as rules
    s = re.sub(r'\s{2,}', ' ', s).strip(' \t.:')
    return s


def parse_text(text):
    """Return (std, total_marks, sections)."""
    std = None
    total = None
    sections = []
    cur = None
    q_counter = 0
    for raw in text.splitlines():
        line = raw.strip()
        if not line or len(line) < 2:
            continue
        if std is None:
            m = STD_RE.search(line)
            if m:
                w = m.group(1).strip('.,;')
                std = STD_WORDS.get(w) or (d2i(re.sub(r'\D', '', w)) if re.search(r'[0-9०-९]', w) else None)
        if total is None:
            m = TOTAL_RE.search(line)
            if m:
                total = d2i(m.group(1))
        m = SEC_RE.match(line) or (SUB_RE.match(line) if cur else None)
        if m and len(m.group('instr')) >= 4:
            q = d2i(m.groupdict().get('q'))
            if q:
                q_counter = q
            elif m.groupdict().get('sub') is None or not cur:
                q_counter += 1
            cur = {'q_no': q_counter, 'sub': (m.groupdict().get('sub') or '').strip(),
                   'instruction': clean(m.group('instr')),
                   'marks': d2i(m.groupdict().get('m1') or m.groupdict().get('m2')), 'items': []}
            sections.append(cur)
            continue
        if cur is None:
            continue
        if re.search(r'minishala|दर्जेदार इ-साहित्य|www\.', line, re.I):
            continue
        # several short items on one line: "१) अ २) ब"
        parts = INLINE_SPLIT.split(line) if ITEM_RE.match(line) else [line]
        for p in parts:
            im = ITEM_RE.match(p)
            if im:
                t = clean(im.group('t'))
                if len(t) >= 2:
                    cur['items'].append(t)
            elif cur['items'] and not re.match(r'^[\W\d]+$', p):
                # continuation of previous item (wrapped line)
                if len(p) > 3 and not SEC_RE.match(p):
                    cur['items'][-1] = clean(cur['items'][-1] + ' ' + p)
            elif not cur['items'] and len(p) > 3 and not re.match(r'^[\W\d]+$', p):
                # extra instruction text under the heading (e.g. word bank in brackets)
                cur['instruction'] = clean(cur['instruction'] + ' ' + p)
    # drop junk
    for s in sections:
        s['items'] = [t for t in s['items'] if re.search(r'\w', t, re.U)]
    return std, total, sections


def main():
    out = []
    last_std = {}
    for m in manifest:
        base = os.path.splitext(m['file'])[0]
        pages = sorted(glob.glob(f'{ROOT}/ocr/{base}__*.txt'))
        if not pages or not os.path.exists(f'{ROOT}/pdf/{m["file"]}'):
            continue
        text = '\n'.join(open(p).read() for p in pages)
        std, total, sections = parse_text(text)
        exam = m['exam']
        if std:
            last_std[exam] = std
        else:
            # English papers OCR'd with eng only - use std of the preceding paper in the same list
            std = last_std.get(exam, m['std'])
        exam_type = 'sankalit' if exam.startswith('sankalit') else 'aakarik'
        test_no = int(exam[-1])
        out.append({
            'exam_type': exam_type, 'test_no': test_no, 'std': std, 'subject': m['subject'].strip(),
            'file': m['file'], 'file_id': m['file_id'], 'pages': len(pages), 'total_marks': total,
            'sections': sections,
        })
    json.dump(out, open(f'{ROOT}/model_papers.json', 'w'), ensure_ascii=False, indent=1)
    nsec = sum(len(p['sections']) for p in out)
    nit = sum(len(s['items']) for p in out for s in p['sections'])
    print(f'{len(out)} papers, {nsec} sections, {nit} items')
    for p in out:
        print(p['exam_type'], p['test_no'], 'std', p['std'], p['subject'], '| sections', len(p['sections']),
              'items', sum(len(s['items']) for s in p['sections']), '| total', p['total_marks'])


if __name__ == '__main__':
    main()
