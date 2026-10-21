import sqlite3,json,random,sys,html,asyncio,re
from playwright.async_api import async_playwright
DB='/home/ubuntu/papers26/bank.sqlite'
FONT='/home/ubuntu/apk_store/assets/flutter_assets/assets/fonts/Mukta-Bold.ttf'
LOGO='/home/ubuntu/apk_store/kit/app_icon_512_transparent.png'
MR={'Marathi':'मराठी','Maths':'गणित','English':'English','EVS':'परिसर अभ्यास','EVS Part 1':'परिसर अभ्यास भाग १','EVS Part 2':'परिसर अभ्यास भाग २','Science':'सामान्य विज्ञान','Hindi':'हिंदी','Geography':'भूगोल','History & Civics':'इतिहास व नागरिकशास्त्र'}
STD={1:'पहिली',2:'दुसरी',3:'तिसरी',4:'चौथी',5:'पाचवी',6:'सहावी',7:'सातवी',8:'आठवी'}
DEV=str.maketrans('0123456789','०१२३४५६७८९')
INS={'mr':{'fill_blank':'रिकाम्या जागी योग्य शब्द लिहा.','true_false':'चूक की बरोबर ते लिहा.','match':'जोड्या जुळवा.','mcq':'योग्य पर्याय निवडून लिहा.','one_word':'एका शब्दात उत्तरे लिहा.','one_sentence':'एका वाक्यात उत्तरे लिहा.','reason':'कारणे लिहा.','short_answer':'थोडक्यात उत्तरे लिहा.','solve':'सोडवा.','draw':'आकृती काढा.','vocabulary':'शब्दसंपत्ती – योग्य शब्द लिहा.','grammar':'व्याकरण.','odd_one':'गटात न बसणारा शब्द ओळखा.','define':'व्याख्या लिहा.','descriptive':'सविस्तर उत्तरे लिहा.'},
 'hi':{'fill_blank':'रिक्त स्थानों की पूर्ति करो।','true_false':'सही या गलत लिखो।','match':'जोड़ियाँ मिलाओ।','mcq':'सही विकल्प चुनकर लिखो।','one_word':'एक शब्द में उत्तर लिखो।','one_sentence':'एक वाक्य में उत्तर लिखो।','reason':'कारण लिखो।','short_answer':'संक्षेप में उत्तर लिखो।','vocabulary':'शब्द-संपदा – सही शब्द लिखो।','grammar':'व्याकरण।','odd_one':'असंगत शब्द छाँटो।','descriptive':'विस्तार से उत्तर लिखो।'},
 'en':{'fill_blank':'Fill in the blanks.','true_false':'Write True or False.','match':'Match the pairs.','mcq':'Choose the correct option.','one_word':'Answer in one word.','one_sentence':'Answer in one sentence.','reason':'Give reasons.','short_answer':'Answer briefly.','solve':'Solve.','draw':'Draw and label.','vocabulary':'Vocabulary.','grammar':'Grammar.','odd_one':'Circle the odd one out.','descriptive':'Answer in detail.'}}
# plan: (qtype, n items, marks each)
PLANS={20:[('fill_blank',4,1),('mcq',3,1),('true_false',3,1),('one_word',3,1),('match',1,3),('one_sentence',2,2)],
 30:[('fill_blank',5,1),('mcq',4,1),('true_false',4,1),('one_word',4,1),('match',1,4),('one_sentence',3,2),('short_answer',2,2)],
 40:[('fill_blank',5,1),('mcq',5,1),('true_false',5,1),('one_word',5,1),('match',1,4),('one_sentence',4,2),('short_answer',2,3),('reason',1,3)],
 50:[('fill_blank',5,1),('mcq',5,1),('true_false',5,1),('match',1,5),('one_word',5,1),('one_sentence',5,2),('short_answer',3,3),('reason',2,3)]}
ALT={'one_word':['vocabulary','odd_one'],'short_answer':['explain','define','descriptive'],'reason':['short_answer','explain'],'match':['vocabulary'],'one_sentence':['one_word']}
def pick(db,std,sub,med,total,sem_frac=0.5,seed=26):
    random.seed(f'{std}{sub}{med}{seed}')
    chs=db.execute("select c.chapter_id,c.chapter_no,c.title from ep_book_chapters c join ep_books b on b.book_id=c.book_id where b.standard=? and b.subject=? and b.medium=? order by c.chapter_no",(std,sub,med)).fetchall()
    chs=chs[:max(3,round(len(chs)*sem_frac))]; ids=[c[0] for c in chs]
    q=f"select bq_id,qtype,text,options_json,answer,pairs_json from ep_book_questions where chapter_id in ({','.join('?'*len(ids))}) and qtype=? and length(text)>6 and length(text)<220 and text not like '%चित्र%' and text not like '%picture%' and text not like '%देखकर%' and text not like '%पढ़कर%' and text not like '%वाचून%' and text not like '%उतारा%'"
    used=set();secs=[]
    for qt,n,m in PLANS[total]:
        rows=[]
        for t in [qt]+ALT.get(qt,[]):
            rows+= [r for r in db.execute(q,ids+[t]) if r[0] not in used]
            if len(rows)>=n*3: break
        random.shuffle(rows); rows=[r for r in rows if not (qt=='mcq' and not r[3])]
        if qt=='match': rows=[r for r in rows if goodpairs(r[5])]
        rows=rows[:n]
        if not rows: continue
        used.update(r[0] for r in rows); secs.append((qt,m,rows))
    tot=sum(m*len(r) for _,m,r in secs)
    while tot<total:
        need=total-tot; qt,m=('one_sentence',2) if need>=2 else ('one_word',1)
        rows=[r for r in db.execute(q,ids+[qt]) if r[0] not in used]
        if not rows: break
        r=random.choice(rows); used.add(r[0])
        for i,(t,mm,rr) in enumerate(secs):
            if t==qt: rr.append(r); break
        else: secs.append((qt,m,[r]))
        tot+=m
    return chs,secs
def goodpairs(pj):
    try: p=json.loads(pj or 'null')
    except: return False
    return isinstance(p,list) and len(p)>=3 and all(isinstance(x,dict) and (x.get('a') or x.get('left')) and (x.get('b') or x.get('right')) for x in p)
def fmt_item(qt,r,lang):
    t=html.escape(r[2]).replace('______','<span class="bl">&nbsp;</span>').replace('____','<span class="bl">&nbsp;</span>')
    out=f'<div class="qt">{t}</div>'
    if qt=='mcq' and r[3]:
        try: ops=json.loads(r[3])
        except: ops=[]
        ops=[o for o in ops if isinstance(o,str)]
        if len(ops)>=2: out+='<div class="ops">'+''.join(f'<span>{"अ आ इ ई".split()[i] if lang!="en" else "abcd"[i]}) {html.escape(o)}</span>' for i,o in enumerate(ops[:4]))+'</div>'
    elif qt=='match':
        pairs=[]
        try: pairs=json.loads(r[5] or 'null') or []
        except: pass
        if not pairs and r[3]:
            try: pairs=[dict(zip(['a','b'],p.split(': ',1))) for p in json.loads(r[3])[0].split(' | ')]
            except: pass
        if pairs and isinstance(pairs[0],dict):
            A=[html.escape(str(p.get('a') or p.get('left') or '')) for p in pairs]; B=[html.escape(str(p.get('b') or p.get('right') or '')) for p in pairs]; random.shuffle(B)
            out+='<table class="match"><tr><th>\'अ\' गट</th><th>\'ब\' गट</th></tr>'+''.join(f'<tr><td>{i+1}) {a}</td><td>{"अआइईउऊ"[i]}) {b}</td></tr>' for i,(a,b) in enumerate(zip(A,B)))+'</table>'
    elif qt in ('one_sentence','short_answer','reason','descriptive','explain','define'):
        out+='<div class="lines">'+'<div></div>'*(1 if qt=='one_sentence' else 3)+'</div>'
    elif qt in ('one_word','vocabulary','odd_one','true_false'):
        out+='<div class="ans">______________________</div>'
    return out
def build(std,sub,med,total,year='२०२६-२०२७'):
    db=sqlite3.connect(DB); chs,secs=pick(db,std,sub,med,total)
    lang='hi' if sub=='Hindi' else ('en' if med=='English' else 'mr')
    subj=sub if med=='English' and sub!='Marathi' else MR[sub]
    d=lambda n:str(n).translate(DEV) if lang!='en' else str(n)
    title='संकलित मूल्यमापन (प्रथम सत्र)' if lang!='en' else 'Summative Evaluation (First Term)'
    body=''
    for i,(qt,m,rows) in enumerate(secs,1):
        body+=f'<div class="sec"><div class="sh"><span><b>{"प्र." if lang!="en" else "Q."}{d(i)})</b> {INS[lang].get(qt,INS["mr"].get(qt,""))}</span><span class="mk">({"गुण" if lang!="en" else "Marks"} {d(m*len(rows))})</span></div>'
        body+='<ol>'+''.join(f'<li>{fmt_item(qt,r,lang)}</li>' for r in rows)+'</ol></div>'
    tot=sum(m*len(r) for _,m,r in secs)
    chlist=', '.join(html.escape(re.sub(r'^[\d. ]+','',c[2]))[:30] for c in chs)
    H=f'''<!doctype html><html><head><meta charset="utf-8"><style>
@font-face{{font-family:M;src:url(file://{FONT})}} body{{font-family:M,sans-serif;font-size:12.5pt;margin:0;color:#111}}
.page{{padding:10mm 12mm}} .brand{{display:flex;align-items:center;gap:8px;background:#0b3d2e;color:#fff;padding:5px 10px;border-radius:8px}} .brand img{{height:30px}} .brand b{{font-size:13pt}} .brand small{{font-size:9pt;opacity:.9;margin-left:auto}}
.hdr{{border:2px solid #0b3d2e;border-radius:6px;padding:6px 10px;margin-top:6px}} .hdr h1{{margin:0;text-align:center;font-size:15pt}} .hdr .row{{display:flex;justify-content:space-between;margin-top:3px}} .box{{display:inline-block;border:1.5px solid #333;width:46px;height:18px;vertical-align:middle}}
.sec{{margin-top:7px}} .sh{{display:flex;justify-content:space-between;border-bottom:1px solid #999;padding-bottom:2px}} .mk{{white-space:nowrap}} ol{{margin:3px 0 0 0;padding-left:22px}} li{{margin:3px 0;page-break-inside:avoid}}
.bl{{display:inline-block;border-bottom:1.5px solid #333;width:70px}} .ops{{display:flex;flex-wrap:wrap;gap:4px 18px;font-size:11.5pt;margin-top:1px}} .lines div{{border-bottom:1px dotted #777;height:17px}} .ans{{color:#555}}
table.match{{border-collapse:collapse;margin:3px 0;width:70%}} .match td,.match th{{border:1px solid #333;padding:2px 6px;font-size:11.5pt}}

.sig{{display:flex;justify-content:space-between;margin-top:14px;border-top:1px dashed #999;padding-top:6px;font-size:11pt}}
</style></head><body><div class="page">
<div class="brand"><img src="file://{LOGO}"><b>TechGuruji स्मार्ट स्कूल हब</b><small>vijetaacademysangli.in/techguruji</small></div>
<div class="hdr"><h1>{title} {year}</h1>
<div class="row"><span><b>इयत्ता :</b> {STD[std]}</span><span><b>विषय :</b> {subj} (लेखी)</span><span><b>एकूण गुण :</b> {d(tot)}</span></div>
<div class="row"><span><b>विद्यार्थ्याचे नाव :</b> ______________________________________</span><span><b>हजेरी क्र. :</b> <span class="box"></span> <b>तुकडी :</b> ____</span></div>
<div class="row"><span><b>शाळेचे नाव :</b> __________________________________</span><span><b>दिनांक :</b> ___/___/२०२६</span><span><b>प्राप्त गुण :</b> <span class="box"></span></span></div>
<div class="row"><span><b>केंद्र :</b> _____________ <b>ता.</b> _____________ <b>जि.</b> _____________</span><span><b>शिक्षकांची स्वाक्षरी :</b> ____________</span></div>
<div style="font-size:9.5pt;color:#444;margin-top:3px"><b>अभ्यासक्रम (प्रथम सत्र):</b> {chlist}</div></div>
{body}
<div class="sig"><span>परीक्षक स्वाक्षरी: ______________</span><span>मुख्याध्यापक स्वाक्षरी व शिक्का</span></div>
</div></body></html>'''
    return H,tot
async def render(jobs):
    async with async_playwright() as p:
        b=await p.chromium.launch(executable_path='/opt/.devin/google-chrome.sh'); pg=await b.new_page()
        for std,sub,med,total,out in jobs:
            H,tot=build(std,sub,med,total); open(out.replace('.pdf','.html'),'w').write(H)
            await pg.set_content(H); await pg.wait_for_timeout(300)
            await pg.pdf(path=out,format='A4',print_background=True,margin={'top':'0','bottom':'14mm','left':'0','right':'0'},display_header_footer=True,header_template='<span></span>',
              footer_template='<div style="width:100%;margin:0 12mm;display:flex;justify-content:space-between;font-size:8px;color:#0b3d2e;border-top:1px solid #0b3d2e;padding-top:2px;font-family:sans-serif"><span>TechGuruji Smart School Hub - HPC | CCE | प्रश्नपत्रिका | टाचण | परिपाठ</span><span>vijetaacademysangli.in/techguruji &nbsp; पान <span class="pageNumber"></span>/<span class="totalPages"></span></span></div>'); print('ok',out,tot)
        await b.close()
if __name__=='__main__':
    std,sub,med,total=int(sys.argv[1]),sys.argv[2],sys.argv[3],int(sys.argv[4])
    asyncio.run(render([(std,sub,med,total,sys.argv[5])]))
