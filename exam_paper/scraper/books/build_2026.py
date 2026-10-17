#!/usr/bin/env python3
"""Rebuild the textbook book/chapter registry on the 2026 syllabus.

 * chapter lists come from the tachan bank (tachan_bank_2026.sql = contents pages of the current Balbharati books);
 * books whose 2026 edition differs from our old PDF are replaced by the official eBalbharati PDF (books/eb2026/pdf/<id>.pdf,
   OCR in books/ocr/<id>/), keeping the same book_id so downstream keys stay stable;
 * chapter start pages are located in the OCR text (heading search, then the printed contents page, then interpolation);
 * exercise questions are re-extracted (raw) for every chapter -> books/book_questions.json (raw extractor output);
 * existing AI packs / typed sets (books/packs, books/typed) are carried over to books/packs2026, books/typed2026 for chapters that
   did not change (same page range + title); the rest are listed in books/regen_chapters.json for gemini_packs / typed_questions.
 * books/chapter_map_2026.json : per book, chapter -> tachan (std, subject, seq, topic) link + old chapter no.
"""
import json, os, re, sys, glob, shutil, difflib, unicodedata
from collections import OrderedDict

sys.path.insert(0, '/home/ubuntu/books')
import extract_questions as ex

ROOT = '/home/ubuntu/books'
EB = f'{ROOT}/eb2026'
REPO_BQ = '/home/ubuntu/repos/New/exam_paper/data/book_questions.json'
TACHAN_SQL = '/home/ubuntu/tachan/pkg/sql/tachan_bank_2026.sql'

# tachan subject -> (our subject, lang)
TSUB = {
    'मराठी': ('Marathi', 'mr'), 'इंग्लिश': ('English', 'en'), 'गणित': ('Maths', 'mr'), 'Semi-Maths': ('Maths', 'en'),
    'सामान्य विज्ञान': ('Science', 'mr'), 'Semi-Science': ('Science', 'en'), 'इतिहास': ('History & Civics', 'mr'),
    'भूगोल': ('Geography', 'mr'), 'हिंदी': ('Hindi', 'hi'), 'परिसर अभ्यास 1': ('EVS Part 1', 'mr'), 'परिसर अभ्यास 2': ('EVS Part 2', 'mr'),
}
# English-medium translations of Marathi-titled tachan subjects (chapters aligned by order)
TSUB_EN = {'इतिहास': ('History & Civics', 'en'), 'भूगोल': ('Geography', 'en'), 'परिसर अभ्यास 1': ('EVS Part 1', 'en'),
           'परिसर अभ्यास 2': ('EVS Part 2', 'en'), 'परिसर अभ्यास': ('EVS', 'en')}
# 2026 books replacing our old PDFs: (std, subject, lang) -> eBalbharati id
NEW_PDF = {
    (2, 'Marathi', 'mr'): '201060001', (2, 'Maths', 'mr'): '201060004', (2, 'Maths', 'en'): '203060004',
    (3, 'EVS', 'mr'): '301000869', (3, 'EVS', 'en'): '303000869', (3, 'Marathi', 'mr'): '301060001',
    (3, 'Maths', 'mr'): '301060004', (3, 'Maths', 'en'): '303060004',
    (4, 'EVS Part 1', 'mr'): '401000868', (4, 'EVS Part 1', 'en'): '403000868', (4, 'Marathi', 'mr'): '401060001',
    (4, 'Maths', 'mr'): '401060004', (4, 'Maths', 'en'): '403060004',
    (5, 'English', 'en'): '501020035',
    (6, 'Hindi', 'hi'): '602020020',
    (6, 'Geography', 'mr'): '601050011', (6, 'Geography', 'en'): '603050011',
}
NEW_TITLE = {'201060001': 'Balbharati 2nd Std Marathi (2026)', '201060004': 'Balbharati 2nd Std Ganit (2026)', '203060004': 'Balbharati 2nd Std Mathematics (2026)',
             '301000869': 'Balbharati 3rd Std Aaplya Sabhovatalche Jag Bhag 2 (2026)', '303000869': 'Balbharati 3rd Std The World Around Us Part 2 (2026)',
             '301060001': 'Balbharati 3rd Std Marathi (2026)', '301060004': 'Balbharati 3rd Std Ganit (2026)', '303060004': 'Balbharati 3rd Std Mathematics (2026)',
             '401000868': 'Balbharati 4th Std Aaplya Sabhovatalche Jag Bhag 1 (2026)', '403000868': 'Balbharati 4th Std The World Around Us Part 1 (2026)',
             '401060001': 'Balbharati 4th Std Marathi (2026)', '401060004': 'Balbharati 4th Std Ganit (2026)', '403060004': 'Balbharati 4th Std Mathematics (2026)',
             '501020035': 'Balbharati 5th Std My English Book Five', '602020020': 'Balbharati 6th Std Hindi Sulabhbharati', '601050011': 'Balbharati 6th Std Bhugol (2026)', '603050011': 'Balbharati 6th Std Geography (2026)'}

# books with no tachan list and a two-column contents page: (title, printed start page); 'first' = 1-based PDF page of printed page 1
FIXED_CHAPTERS = {
    (6, 'Hindi', 'hi'): {'first': 9, 'chapters': [
        ('सैर', 2), ('बसंती हवा', 4), ('उपहार', 7), ('जोकर', 11), ('आओ, आयु बताना सीखो / महाराष्ट्र की बेटी', 13), ('मेरा अहोभाग्य', 14),
        ('नदी कंधे पर', 18), ('जन्मदिन', 19), ('सोई मेरी छौना रे!', 23), ('उपयोग हमारे', 28), ('तूफानों से क्या डरना', 30), ('कठपुतली', 33),
        ('सोना और लोहा', 37), ('क्या तुम जानते हो? / पहेलियाँ', 40), ('स्वास्थ्य संपदा', 41), ('कागज की थैली', 45), ('टीटू और चिंकी', 46),
        ('वह देश कौन-सा है?', 50)]},
}
# std 3 tachan 'परिसर अभ्यास' is the single book (भाग २ is the state-wide part; भाग १ is district-specific)
ALIAS = {(3, 'परिसर अभ्यास'): ('EVS', 'mr')}
EN_TITLES = json.load(open(f'{ROOT}/en_titles.json', encoding='utf-8')) if os.path.exists(f'{ROOT}/en_titles.json') else {}


def norm(s):
    s = unicodedata.normalize('NFC', s).lower()
    s = re.sub(r'\([^)]*\)', ' ', s)                 # (कविता) (प्रार्थना) (चित्रकथा) tags
    s = re.sub(r'^\W*[0-9०-९]{1,2}(\.[0-9०-९]{1,2})?\W*', ' ', s)   # leading chapter number
    s = re.sub(r'[^a-z0-9\u0900-\u097F]+', ' ', s)
    return re.sub(r'\s+', ' ', s).strip()


def sim(a, b):
    a, b = norm(a), norm(b)
    if not a or not b:
        return 0
    if a in b or b in a:
        return 1.0
    return difflib.SequenceMatcher(None, a, b).ratio()


def load_tachan():
    s = open(TACHAN_SQL, encoding='utf-8').read()
    rows = re.findall(r"\((\d+),'((?:[^'\\]|\\.)*)',(\d+),(\d+),(\d+),'((?:[^'\\]|\\.)*)'", s)
    d = OrderedDict()
    for std, sub, dn, m, day, topic in rows:
        topic = topic.replace("\\'", "'").strip()
        lst = d.setdefault((int(std), sub), [])
        if topic not in lst:
            lst.append(topic)
    return d


def ocr_pages(dirname):
    d = os.path.join(ROOT, 'ocr', dirname)
    pages = {}
    for f in glob.glob(d + '/*.txt'):
        pages[int(os.path.basename(f)[:3]) + 1] = open(f, encoding='utf-8', errors='ignore').read()   # 1-based pdf page
    return pages


def heading_score(title, text, nlines=14):
    t = norm(title)
    if len(t) < 3:
        return 0
    best = 0
    for line in text.splitlines()[:nlines]:
        l = norm(line)
        if len(l) < 3:
            continue
        if t in l:
            return 1.0
        # title may be split across OCR lines -> compare with a sliding window of the line
        r = difflib.SequenceMatcher(None, t, l[:len(t) + 4]).ratio()
        best = max(best, r)
    return best


def first_content_page(pages):
    """1-based pdf page after the front matter (contents / outcomes / prelims)."""
    last_front = 0
    for p in sorted(pages):
        if p > 24:
            break
        if ex.TOC_RE.search(pages[p]) or ex.SKIP_PAGE.search(pages[p]) or ex.OUTCOMES_PAGE.search(pages[p]):
            last_front = p
    return last_front + 1


def toc_titles(pages, n, front=20):
    """chapter_no -> title from numbered contents-page lines ('3. World of Coordinates 15') in the front matter."""
    out = {}
    for p in sorted(pages)[:front]:
        for line in pages[p].splitlines():
            m = re.match(r'^\W*([1-9][0-9]?)\s*[.,]?\s+([A-Za-z][A-Za-z ,\'\-!?:/()]{2,60}?)\s+[0-9]{1,3}\s*$', line.strip())
            if m and 1 <= int(m.group(1)) <= n and int(m.group(1)) not in out:
                out[int(m.group(1))] = m.group(2).strip()
    return out if len(out) >= n * 0.5 else {}


EN_HEAD = re.compile(r"^\W*(?:[1-9][0-9]?\s*[.)]\s*)?([A-Z][A-Za-z][A-Za-z ,'\-!?:()]{2,60})\W*$")


def page_heading_en(pages, p):
    """English chapter heading printed at the top of page p (or the next page)."""
    for q in (p, p + 1):
        for line in pages.get(q, '').splitlines()[:8]:
            m = EN_HEAD.match(line.strip())
            if m and len(m.group(1).split()) <= 8 and not re.search(r'\b(Std|Standard|Page|Textbook|Bureau|Pune|Learning|Outcomes?)\b', m.group(1)):
                return m.group(1).strip()
    return None


def locate(titles, pages, npages, old_ch=()):
    """Return list of 1-based start pages for the ordered titles (None where not found), plus how many were found."""
    first = first_content_page(pages)
    starts = [None] * len(titles)
    # pass 0: same title already located in the previous chapter list (same PDF)
    used = set()
    for i, t in enumerate(titles):
        best = max(((sim(t, oc['title']), oc) for oc in old_ch if oc['no'] not in used), key=lambda x: x[0], default=(0, None))
        if best[0] >= 0.8:
            starts[i] = best[1]['start_page']
            used.add(best[1]['no'])
    for i in range(1, len(starts)):      # drop non-monotonic hits
        if starts[i] and any(s and s >= starts[i] for s in starts[:i]):
            starts[i] = None
    # pass 1: greedy monotonic heading search for the rest
    pos = first
    for i, t in enumerate(titles):
        if starts[i]:
            pos = starts[i] + 1
            continue
        hi = min([s for s in starts[i + 1:] if s] or [npages + 1])
        best, bp = 0, None
        for p in range(pos, hi):
            sc = heading_score(t, pages.get(p, ''))
            if sc >= 0.82:
                best, bp = sc, p
                break
        if bp:
            starts[i] = bp
            pos = bp + 1
    found = sum(1 for s in starts if s)
    # pass 2: printed contents page (title -> printed page) + offset
    toc = ex.parse_toc({p - 1: t for p, t in pages.items()})
    if toc:
        off = ex.find_offset(toc, {p - 1: t for p, t in pages.items()})
        if off is not None:
            for i, t in enumerate(titles):
                if starts[i]:
                    continue
                m = max(toc, key=lambda e: sim(t, e[1]))
                if sim(t, m[1]) >= 0.7 and m[2] is not None:
                    cand = m[2] + off + 1
                    lo = max([s for s in starts[:i] if s] or [first])
                    hi = min([s for s in starts[i + 1:] if s] or [npages + 1])
                    if lo < cand < hi:
                        starts[i] = cand
    # pass 3: interpolate
    known = [(i, s) for i, s in enumerate(starts) if s]
    if not known:
        return None, 0
    for i, s in enumerate(starts):
        if s:
            continue
        before = [k for k in known if k[0] < i]
        after = [k for k in known if k[0] > i]
        lo = before[-1][1] if before else first
        hi = after[0][1] if after else npages + 1
        span = (after[0][0] if after else len(starts)) - (before[-1][0] if before else -1)
        pos = i - (before[-1][0] if before else -1)
        starts[i] = lo + (hi - lo) * pos // span
    for i in range(1, len(starts)):
        if starts[i] <= starts[i - 1]:
            starts[i] = min(starts[i - 1] + 1, npages)
    return starts, found


def extract(book_pages, chapters, bid, std, subject, lang, qid_start):
    qs = []
    qid = qid_start
    for ch in chapters:
        for p, sec in ex.extract_chapter(book_pages, ch['start_page'], ch['end_page'], lambda p: p - 1):
            items = sec['items'] or [sec['instruction']]
            for k, t in enumerate(items):
                if len(t) < 3 or ex.NOISE.match(t):
                    continue
                instr = sec['instruction'] if sec['items'] else ''
                fig = bool(ex.FIGURE_KW.search(t) or ex.FIGURE_KW.search(instr))
                qid += 1
                qs.append({'id': qid, 'book_id': bid, 'std': std, 'subject': subject, 'lang': lang,
                           'chapter_no': ch['no'], 'chapter': ch['title'], 'page': p, 'block': sec['block'], 'instruction': instr,
                           'qtype': ex.qtype_for(instr, t), 'text': t, 'item_no': k + 1 if sec['items'] else None,
                           'needs_figure': fig, 'page_image': f'{bid}/{p - 1:03d}.jpg' if fig else None})
    return qs, qid


def main():
    tachan = load_tachan()
    repo = json.load(open(REPO_BQ, encoding='utf-8'))
    books = {b['book_id']: dict(b) for b in repo['books']}
    by_key = {(b['std'], b['subject'], b['lang']): b['book_id'] for b in repo['books']}
    next_bid = max(books) + 1

    # book target list: our books + brand new subjects present in the 2026 set
    for key, pid in NEW_PDF.items():
        if key not in by_key:
            std, subject, lang = key
            books[next_bid] = {'book_id': next_bid, 'std': std, 'subject': subject, 'lang': lang, 'file': f'{pid}.pdf', 'title': NEW_TITLE[pid],
                               'pages': 0, 'chapters': [], 'questions': 0}
            by_key[key] = next_bid
            next_bid += 1

    # tachan topic list per (std, subject, lang)
    canon = {}
    for (std, tsub), topics in tachan.items():
        key = ALIAS.get((std, tsub)) or TSUB.get(tsub)
        if key:
            canon[(std,) + key] = (tsub, topics)
        if tsub in TSUB_EN:
            canon.setdefault((std,) + TSUB_EN[tsub], (tsub, topics))   # by-order alignment for English versions
        if (std, tsub) in ALIAS:
            canon.setdefault((std, ALIAS[(std, tsub)][0], 'en'), (tsub, topics))

    out_books, out_q, chapter_map, regen, report = [], [], {}, [], []
    qid = 0
    os.makedirs(f'{ROOT}/packs2026', exist_ok=True)
    os.makedirs(f'{ROOT}/typed2026', exist_ok=True)
    def is_translated(bid):
        b = books[bid]
        tsub = canon.get((b['std'], b['subject'], b['lang']), (None,))[0]
        return tsub in TSUB_EN and b['lang'] == 'en'

    done = {}   # (std, subject, lang) -> aligned book (Marathi originals first, so English translations can copy their page spans)
    for bid in sorted(books, key=lambda i: (is_translated(i), i)):
        b = books[bid]
        key = (b['std'], b['subject'], b['lang'])
        replaced = key in NEW_PDF
        ocr_dir = NEW_PDF[key] if replaced else b['file'][:-4]
        pages = ocr_pages(ocr_dir)
        if not pages:
            report.append(f'!! {bid} {key}: no OCR ({ocr_dir})')
            continue
        npages = max(pages)
        nb = dict(b, file=(NEW_PDF[key] + '.pdf') if replaced else b['file'], title=NEW_TITLE.get(NEW_PDF.get(key), b['title']),
                  pages=npages, source='ebalbharati_2026' if replaced else 'legacy_pdf', edition=2026 if replaced else None)
        old_ch = b['chapters']
        tsub, topics = canon.get(key, (None, None))
        translated = tsub in TSUB_EN and b['lang'] == 'en'   # Marathi tachan titles, English book -> align by order only
        chapters = None
        mode = 'keep'
        if topics and not translated:
            titles = [re.sub(r'^\s*[0-9०-९]+\.\s*', '', t) for t in topics]
            starts, found = locate(titles, pages, npages, () if replaced else old_ch)
            ratio = found / len(titles) if titles else 0
            # accept the tachan list when we can see enough headings, or when the book is the new 2026 PDF (its contents = tachan)
            if starts and (ratio >= 0.33 or replaced or len(old_ch) < len(titles) * 0.6):
                chapters = []
                for i, (t, s) in enumerate(zip(titles, starts)):
                    e = (starts[i + 1] - 1) if i + 1 < len(starts) else npages
                    chapters.append({'no': i + 1, 'title': t, 'start_page': s, 'end_page': max(s, e), 'tachan_seq': i + 1})
                mode = f'tachan ({found}/{len(titles)} headings found)'
            else:
                mode = f'keep (tachan headings {found}/{len(titles)} too few)'
        if chapters is None and topics and translated and len(old_ch) == len(topics) and not replaced:
            chapters = [dict(c, tachan_seq=i + 1) for i, c in enumerate(old_ch)]
            mode = 'keep + tachan by order'
        sib = done.get((b['std'], b['subject'], 'mr'))
        if chapters is None and topics and translated and sib and len(sib['chapters']) == len(topics):
            # English edition of a Marathi 2026 book: same layout -> copy the sibling's page spans (scaled),
            # English titles from this book's own numbered contents lines where readable
            en_titles = {int(k): v for k, v in EN_TITLES.get(str(bid), {}).items()}   # LLM-read titles (en_titles.py)
            en_titles.update(toc_titles(pages, len(topics)))                            # printed contents page wins
            f = npages / max(1, sib['pages'])
            chapters = []
            for i, sc in enumerate(sib['chapters']):
                sp = int(round(sc['start_page'] * f))
                chapters.append({'no': i + 1, 'title': en_titles.get(i + 1) or page_heading_en(pages, sp) or re.sub(r'^\s*[0-9०-९]+\.\s*', '', topics[i]),
                                 'start_page': sp, 'end_page': int(round(sc['end_page'] * f)), 'tachan_seq': i + 1, 'title_mr': topics[i]})
            mode = f'sibling pages + tachan by order ({len(en_titles)}/{len(topics)} en titles)'
        if chapters is None and key in FIXED_CHAPTERS:
            fx = FIXED_CHAPTERS[key]
            st = [fx['first'] + pg - 1 for _, pg in fx['chapters']]
            chapters = [{'no': i + 1, 'title': t, 'start_page': s0, 'end_page': (st[i + 1] - 1) if i + 1 < len(st) else npages - 2}
                        for i, ((t, _), s0) in enumerate(zip(fx['chapters'], st))]
            mode = 'fixed contents'
        if chapters is None and replaced:
            # new PDF without tachan list: contents page
            toc = ex.parse_toc({p - 1: t for p, t in pages.items()})
            titles = [t for _, t, _ in toc]
            starts, found = locate(titles, pages, npages) if titles else (None, 0)
            if starts and (not topics or abs(len(titles) - len(topics)) <= 2):
                chapters = [{'no': i + 1, 'title': t, 'start_page': s, 'end_page': (starts[i + 1] - 1) if i + 1 < len(starts) else npages}
                            for i, (t, s) in enumerate(zip(titles, starts))]
                mode = f'new pdf contents ({found}/{len(titles)})'
        if chapters is None:
            chapters = [dict(c) for c in old_ch]
            if topics and translated:
                # best-effort by-order link when counts differ
                for i, c in enumerate(chapters):
                    if i < len(topics):
                        c['tachan_seq'] = i + 1
        for c in chapters:
            c['start_page'] = max(1, min(c['start_page'], npages))
            c['end_page'] = max(c['start_page'], min(c['end_page'], npages))
        nb['chapters'] = chapters
        nb['tachan_subject'] = tsub
        done[key] = nb
        nb['chapter_mode'] = mode
        # questions
        qs, qid = extract(pages, chapters, bid, b['std'], b['subject'], b['lang'], qid)
        nb['questions'] = len(qs)
        out_q += qs
        out_books.append(nb)
        # carry over packs / typed for unchanged chapters
        kept = 0
        for c in chapters:
            match = None
            if not replaced:
                for oc in old_ch:
                    if abs(oc['start_page'] - c['start_page']) <= 1 and abs(oc['end_page'] - c['end_page']) <= 1 and sim(oc['title'], c['title']) >= 0.6:
                        match = oc
                        break
            chapter_map.setdefault(str(bid), []).append({'no': c['no'], 'title': c['title'], 'start_page': c['start_page'], 'end_page': c['end_page'],
                                                          'tachan_subject': tsub, 'tachan_seq': c.get('tachan_seq'), 'old_no': match['no'] if match else None,
                                                          'old_title': match['title'] if match else None})
            c['old_no'] = match['no'] if match else None
            if match:
                for src, dst in (('packs', 'packs2026'), ('typed', 'typed2026')):
                    f = f'{ROOT}/{src}/{bid}_{match["no"]}.json'
                    if os.path.exists(f):
                        p = json.load(open(f, encoding='utf-8'))
                        p['chapter_no'] = c['no']
                        p['chapter'] = c['title']
                        for q in p.get('questions', []):
                            q['chapter_no'] = c['no']
                            q['chapter'] = c['title']
                        json.dump(p, open(f'{ROOT}/{dst}/{bid}_{c["no"]}.json', 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
                kept += 1
            else:
                regen.append({'book_id': bid, 'chapter_no': c['no'], 'title': c['title'], 'std': b['std'], 'subject': b['subject'], 'lang': b['lang']})
        report.append(f'{bid:3d} std{b["std"]} {b["subject"]:16s} {b["lang"]} {"NEW-PDF" if replaced else "same   "} ch={len(chapters):2d} (old {len(old_ch):2d}) '
                      f'kept={kept:2d} q={len(qs):4d} [{mode}]')

    json.dump({'books': out_books, 'questions': out_q}, open(f'{ROOT}/book_questions.json', 'w', encoding='utf-8'), ensure_ascii=False, indent=0)
    json.dump(chapter_map, open(f'{ROOT}/chapter_map_2026.json', 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
    json.dump(regen, open(f'{ROOT}/regen_chapters.json', 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
    open(f'{ROOT}/BUILD_2026_REPORT.txt', 'w').write('\n'.join(report) + f'\n\nbooks={len(out_books)} chapters={sum(len(b["chapters"]) for b in out_books)} '
                                                      f'questions={len(out_q)} regen={len(regen)}\n')
    print('\n'.join(report))
    print('books', len(out_books), 'chapters', sum(len(b['chapters']) for b in out_books), 'questions', len(out_q), 'regen', len(regen))


if __name__ == '__main__':
    main()
