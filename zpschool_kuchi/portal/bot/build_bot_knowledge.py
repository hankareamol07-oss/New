import os
import sys
import re
import sqlite3
import json

sys.stdout.reconfigure(encoding='utf-8')

SOURCES_DIR = r"D:\YT STUDIO\yt_explain_full_v2\yt_explain\sources"
DB_PATH = r"c:\xampp\htdocs\hpc\zpschool2kuchi\portal\bot\data\bot_knowledge.sqlite"

if os.path.exists(DB_PATH):
    try:
        os.remove(DB_PATH)
    except Exception:
        pass

conn = sqlite3.connect(DB_PATH)
cur = conn.cursor()

# Enable fast writes
cur.execute("PRAGMA synchronous = OFF")
cur.execute("PRAGMA journal_mode = MEMORY")

# Create tables
cur.execute("""
CREATE TABLE chapters (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    std INTEGER,
    subject TEXT,
    chapter_folder TEXT,
    chapter_num INTEGER,
    title TEXT,
    summary TEXT,
    textbook_text TEXT
)
""")

cur.execute("""
CREATE TABLE questions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    chapter_id INTEGER,
    std INTEGER,
    subject TEXT,
    chapter_title TEXT,
    source_type TEXT,
    q_type TEXT,
    question TEXT,
    answer TEXT
)
""")

cur.execute("""
CREATE TABLE scholarship (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    std INTEGER,
    subject TEXT,
    topic_name TEXT,
    description TEXT,
    formulas_or_tricks TEXT,
    sample_questions TEXT
)
""")

cur.execute("CREATE INDEX idx_ch_std_subj ON chapters(std, subject)")
cur.execute("CREATE INDEX idx_q_std_subj ON questions(std, subject)")
cur.execute("CREATE INDEX idx_q_type ON questions(q_type)")

print("=== Indexing Chapters and Q&A from Sources ===")

q_regex = re.compile(r"^-\s*\(([^)]+)\)\s*(.*?)(?:→\s*(.*))?$", re.DOTALL)

total_chapters = 0
total_questions = 0

for std_entry in sorted(os.listdir(SOURCES_DIR), key=lambda x: int(x) if x.isdigit() else 99):
    std_path = os.path.join(SOURCES_DIR, std_entry)
    if not os.path.isdir(std_path) or not std_entry.isdigit():
        continue
    std = int(std_entry)

    for subj_entry in sorted(os.listdir(std_path)):
        subj_path = os.path.join(std_path, subj_entry)
        if not os.path.isdir(subj_path):
            continue
        subject = subj_entry

        for ch_entry in sorted(os.listdir(subj_path)):
            ch_path = os.path.join(subj_path, ch_entry)
            if not os.path.isdir(ch_path):
                continue

            # Extract chapter number and clean title
            # e.g. "01_1_सजीव_सृष्टी_अनुकूलन_व_वर्गीकरण" -> num: 1, title: "सजीव सृष्टी : अनुकूलन व वर्गीकरण"
            m = re.match(r"^(\d+)_(\d+)_(.*)$", ch_entry)
            if m:
                ch_num = int(m.group(2))
                raw_title = m.group(3).replace("_", " ")
            else:
                ch_num = total_chapters + 1
                raw_title = ch_entry.replace("_", " ")

            # Read textbook summary
            textbook_path = os.path.join(ch_path, "01_textbook.txt")
            summary = ""
            full_text = ""
            if os.path.exists(textbook_path):
                try:
                    with open(textbook_path, "r", encoding="utf-8", errors="ignore") as f:
                        full_text = f.read()
                        summary = full_text[:1200]
                except Exception:
                    pass

            # Read Title from Swadhyay or Textbook if available
            swadhyay_path = os.path.join(ch_path, "02_swadhyay.md")
            practice_path = os.path.join(ch_path, "03_practice.md")

            ch_title = raw_title
            if os.path.exists(swadhyay_path):
                try:
                    with open(swadhyay_path, "r", encoding="utf-8", errors="ignore") as f:
                        for line in f:
                            if line.startswith("#"):
                                clean = line.lstrip("#").strip()
                                clean = re.sub(r"^[0-9.]+\s*", "", clean)
                                clean = re.sub(r"—\s*स्वाध्याय.*$", "", clean).strip()
                                if clean:
                                    ch_title = clean
                                break
                except Exception:
                    pass

            # Insert Chapter
            cur.execute(
                "INSERT INTO chapters (std, subject, chapter_folder, chapter_num, title, summary, textbook_text) VALUES (?,?,?,?,?,?,?)",
                (std, subject, ch_entry, ch_num, ch_title, summary, full_text[:15000]) # Cap text to reasonable size
            )
            ch_id = cur.lastrowid
            total_chapters += 1

            # Parse 03_practice.md questions
            if os.path.exists(practice_path):
                try:
                    with open(practice_path, "r", encoding="utf-8", errors="ignore") as f:
                        for line in f:
                            line = line.strip()
                            if not line or not line.startswith("-"):
                                continue
                            qm = q_regex.match(line)
                            if qm:
                                q_type = qm.group(1).strip()
                                q_text = qm.group(2).strip()
                                ans = (qm.group(3) or "").strip()
                                if q_text:
                                    cur.execute(
                                        "INSERT INTO questions (chapter_id, std, subject, chapter_title, source_type, q_type, question, answer) VALUES (?,?,?,?,?,?,?,?)",
                                        (ch_id, std, subject, ch_title, "practice", q_type, q_text, ans)
                                    )
                                    total_questions += 1
                except Exception:
                    pass

            # Parse 02_swadhyay.md questions
            if os.path.exists(swadhyay_path):
                try:
                    with open(swadhyay_path, "r", encoding="utf-8", errors="ignore") as f:
                        for line in f:
                            line = line.strip()
                            if not line or not line.startswith("-"):
                                continue
                            qm = q_regex.match(line)
                            if qm:
                                q_type = qm.group(1).strip()
                                q_text = qm.group(2).strip()
                                ans = (qm.group(3) or "").strip()
                                if q_text:
                                    cur.execute(
                                        "INSERT INTO questions (chapter_id, std, subject, chapter_title, source_type, q_type, question, answer) VALUES (?,?,?,?,?,?,?,?)",
                                        (ch_id, std, subject, ch_title, "swadhyay", q_type, q_text, ans)
                                    )
                                    total_questions += 1
                            else:
                                # Regular bullet question in swadhyay
                                clean_q = line.lstrip("- ").strip()
                                if len(clean_q) > 5 and not clean_q.startswith("#"):
                                    cur.execute(
                                        "INSERT INTO questions (chapter_id, std, subject, chapter_title, source_type, q_type, question, answer) VALUES (?,?,?,?,?,?,?,?)",
                                        (ch_id, std, subject, ch_title, "swadhyay", "descriptive", clean_q, "")
                                    )
                                    total_questions += 1
                except Exception:
                    pass

print(f"Chapters Indexed: {total_chapters}")
print(f"Questions Indexed: {total_questions}")

# Now Add Scholarship Topics & Sample Questions for Class 4 & Class 7
print("=== Adding Scholarship Modules for Class 4 & Class 7 ===")

scholarship_data = [
    # Class 4 Scholarship (PUP 5th Prep)
    (4, "भाषा (मराठी)", "वर्णमाला व जोडाक्षरे", "स्वर, व्यंजन, अनुस्वार, विसर्ग, जोडाक्षरे व उच्चारस्थान ओळख", "मराठीत १२ स्वर, २ स्वरादी (अं, अ:) आणि ३४ व्यंजने मिळून ४८ मूळ वर्ण आहेत.", json.dumps([
        {"q": "खालीलपैकी कोणता स्वर आहे?", "a": "अ", "opts": ["क", "ख", "अ", "म"], "exp": "'अ' हा मराठीतील पहिला स्वर आहे."},
        {"q": "खालीलपैकी जोडाक्षर ओळखा:", "a": "प्रज्ञा", "opts": ["घर", "झाड", "प्रज्ञा", "नदी"], "exp": "'प्र' आणि 'ज्ञ' ही जोडाक्षरे आहेत."}
    ], ensure_ascii=False)),
    (4, "भाषा (मराठी)", "समानार्थी व विरुद्धार्थी शब्द", "महत्त्वाचे समानार्थी शब्द (अमृत-सुधा, अरण्य-वन) व विरुद्धार्थी शब्द", "नियमित वाचन व शब्दसंग्रह सराव आवश्यक.", json.dumps([
        {"q": "'अमृत' या शब्दाचा समानार्थी शब्द कोणता?", "a": "सुधा", "opts": ["विष", "सुधा", "गरल", "जल"], "exp": "अमृत = सुधा = पियूष."},
        {"q": "'उंच' या शब्दाचा विरुद्धार्थी शब्द कोणता?", "a": "ठेंगू", "opts": ["लांब", "मोठा", "ठेंगू", "खोल"], "exp": "उंच चा विरुद्धार्थी ठेंगू / बुटका."}
    ], ensure_ascii=False)),
    (4, "गणित (Maths)", "स्थानिक किंमत व दर्शनी किंमत", "अंकांची स्थानिक किंमत (Place value) व दर्शनी किंमत (Face value)", "स्थानिक किंमत = अंक × स्थानाचे मूल्य (एकक=1, दशक=10, शतक=100, हजार=1000).", json.dumps([
        {"q": "५७८४ या संख्येतील '७' ची स्थानिक किंमत किती?", "a": "७००", "opts": ["७", "७०", "७००", "७०००"], "exp": "७ हा शतक स्थानी असल्याने ७ × १०० = ७००."},
        {"q": "८४२६ या संख्येतील ४ ची दर्शनी किंमत किती?", "a": "४", "opts": ["४", "४०", "४००", "४०००"], "exp": "दर्शनी किंमत नेहमी तोच अंक असतो."}
    ], ensure_ascii=False)),
    (4, "गणित (Maths)", "अपूर्णांक (Fractions)", "अर्धा (१/२), पाव (१/४), पाऊण (३/४), सव्वा, दीड, अडीच", "१ = पूर्ण, १/२ = अर्धा, १/४ = पाव, ३/४ = पाऊण, १ + १/४ = सव्वा, १ + १/२ = दीड, २ + १/२ = अडीच.", json.dumps([
        {"q": "एका चपातीचे समान ४ भाग केले, त्यातील १ भाग म्हणजे किती?", "a": "पाव (१/४)", "opts": ["अर्धा", "पाव (१/४)", "पाऊण", "पूर्ण"], "exp": "४ पैकी १ भाग = १/४ = पाव."}
    ], ensure_ascii=False)),
    (4, "बुद्धिमत्ता चाचणी (Intelligence)", "संख्यामालिका (Number Series)", "संख्यांमधील फरक, बेरीज, दुप्पट, सम/विषम क्रम ओळखणे", "लगतच्या पदांमधील संबंध (उदा. +२, +३, +४ किंवा ×२) शोधा.", json.dumps([
        {"q": "२, ४, ६, ८, ?", "a": "१०", "opts": ["९", "१०", "११", "१२"], "exp": "प्रत्येक संख्येत २ मिळवले आहेत, ८ + २ = १०."},
        {"q": "३, ६, ९, १२, ?", "a": "१५", "opts": ["१३", "१४", "१५", "१६"], "exp": "३ चा पाढा आहे, पुढील संख्या १५ येईल."}
    ], ensure_ascii=False)),
    (4, "बुद्धिमत्ता चाचणी (Intelligence)", "आरशातील प्रतिमा (Mirror Image)", "उजवी बाजू डावीकडे आणि डावी बाजू उजवीकडे दिसते", "आरशात वरची आणि खालची बाजू बदलत नाही, फक्त डावी-उजवी बाजू बदलते.", json.dumps([
        {"q": "आरशात पाहिल्यास घड्याळातील ३:०० ची वेळ किती दिसेल?", "a": "९:००", "opts": ["६:००", "९:००", "१२:००", "३:००"], "exp": "११:६० मधून ३:०० वजा केल्यास ९:०० मिळते."}
    ], ensure_ascii=False)),
    (4, "इंग्रजी (English)", "Vocabulary & Opposites", "Rhyming words, Animals, Opposites, Singular/Plural", "Read vocabulary lists daily.", json.dumps([
        {"q": "What is the opposite of 'Hot'?", "a": "Cold", "opts": ["Warm", "Cold", "Cool", "Dry"], "exp": "Hot opposite is Cold."},
        {"q": "Plural of 'Child' is:", "a": "Children", "opts": ["Childs", "Childrens", "Children", "Childes"], "exp": "Child -> Children."}
    ], ensure_ascii=False)),

    # Class 7 Scholarship (PSS 8th Prep)
    (7, "भाषा (मराठी)", "संधी व संधीचे प्रकार", "स्वरसंधी, व्यंजनसंधी, विसर्ग संधी", "पहिल्या शब्दाचा शेवटचा वर्ण + दुसऱ्या शब्दाचा पहिला वर्ण = संधी.", json.dumps([
        {"q": "'सूर्योदय' या शब्दाची संधी कशी होईल?", "a": "सूर्य + उदय", "opts": ["सूर्य + उदय", "सूर्यो + दय", "सूर्य + दय", "सूर्या + उदय"], "exp": "सूर्य (अ) + उदय (उ) = ओ (सूर्योदय)."},
        {"q": "'विद्यार्थी' या शब्दाची संधी कोणती?", "a": "विद्या + अर्थी", "opts": ["विद्य + अर्थी", "विद्या + अर्थी", "विद्या + र्थी", "विधी + अर्थी"], "exp": "विद्या (आ) + अर्थी (अ) = आ (विद्यार्थी - दीर्घत्व स्वरसंधी)."}
    ], ensure_ascii=False)),
    (7, "भाषा (मराठी)", "समास व समासाचे प्रकार", "अव्ययीभाव, तत्पुरुष, द्वंद्व, बहुव्रीही समास", "समासात दोन शब्द एकत्र येऊन नवा सामासिक शब्द बनतो.", json.dumps([
        {"q": "'आई-वडील' हा कोणत्या प्रकारचा समास आहे?", "a": "द्वंद्व समास", "opts": ["तत्पुरुष", "द्वंद्व समास", "अव्ययीभाव", "बहुव्रीही"], "exp": "आई आणि वडील - दोन्ही पदे महत्त्वाची असल्याने द्वंद्व समास."}
    ], ensure_ascii=False)),
    (7, "गणित (Maths)", "लसावि व मसावि (LCM & HCF)", "लसावि = लघुत्तम सामाईक विभाज्य, मसावि = महत्तम सामाईक विभाजक", "दोन संख्यांचा गुणाकार = लसावि × मसावि.", json.dumps([
        {"q": "१२ आणि १८ यांचा मसावि (HCF) किती?", "a": "६", "opts": ["२", "३", "६", "३६"], "exp": "१२ = २ × २ × ३, १८ = २ × ३ × ३. सामाईक = २ × ३ = ६."},
        {"q": "१२ आणि १८ यांचा लसावि (LCM) किती?", "a": "३६", "opts": ["१८", "२४", "३६", "७२"], "exp": "सामाईक × असामाईक = ६ × २ × ३ = ३६."}
    ], ensure_ascii=False)),
    (7, "गणित (Maths)", "शेकडेवारी व नफा-तोटा", "शेकडा नफा = (नफा / खरेदी किंमत) × १००, शेकडा तोटा = (तोटा / खरेदी किंमत) × १००", "नफा = विक्री - खरेदी. तोटा = खरेदी - विक्री.", json.dumps([
        {"q": "एका वस्तूची खरेदी किंमत ₹५०० व विक्री ₹६०० असल्यास शेकडा नफा किती?", "a": "२०%", "opts": ["१०%", "१५%", "२०%", "२५%"], "exp": "नफा = ६०० - ५०० = १००. शेकडा नफा = (१०० / ५००) × १०० = २०%."}
    ], ensure_ascii=False)),
    (7, "बुद्धिमत्ता चाचणी (Intelligence)", "वेन आकृत्या (Venn Diagrams)", "घटकांचे परस्परसंबंध दर्शवण्यासाठी वर्तुळांचा वापर", "सर्व (All), काही (Some), एकही नाही (None) संबंध ओळखा.", json.dumps([
        {"q": "महाराष्ट्र, भारत, आशिया यांच्यातील संबंध दर्शवणारी आकृती कोणती?", "a": "तीन एकाआत एक वर्तुळे", "opts": ["तीन वेगळी वर्तुळे", "दोन छेदणारी वर्तुळे", "तीन एकाआत एक वर्तुळे", "दोन आत एक बाहेर"], "exp": "महाराष्ट्र हा भारतामध्ये आहे आणि भारत हा आशिया खंडात आहे."}
    ], ensure_ascii=False)),
    (7, "बुद्धिमत्ता चाचणी (Intelligence)", "दिनदर्शिका (Calendar)", "वार काढणे, लीप वर्ष (४ ने भाग जाणारे वर्ष), वाढीव दिवस (Odd days)", "सामान्य वर्षात ३६५ दिवस (१ वाढीव दिवस), लीप वर्षात ३६६ दिवस (२ वाढीव दिवस).", json.dumps([
        {"q": "१ जानेवारी २०२४ ला सोमवार असेल तर ३१ डिसेंबर २०२४ ला कोणता वार असेल?", "a": "मंगळवार", "opts": ["सोमवार", "मंगळवार", "बुधवार", "रविवार"], "exp": "२०२४ हे लीप वर्ष आहे. लीप वर्षाची सुरुवात ज्या वाराने होते त्याच्या पुढील वाराने वर्ष संपते."}
    ], ensure_ascii=False)),
    (7, "इंग्रजी (English)", "Tenses & Modals", "Present, Past, Future, Continuous, Perfect; can, must, should", "Subject + Verb agreement and tense forms (V1, V2, V3).", json.dumps([
        {"q": "Choose the correct past continuous form: 'He _______ a book.'", "a": "was reading", "opts": ["is reading", "was reading", "has read", "will read"], "exp": "'was reading' is past continuous for singular 'He'."}
    ], ensure_ascii=False)),
]

for item in scholarship_data:
    cur.execute(
        "INSERT INTO scholarship (std, subject, topic_name, description, formulas_or_tricks, sample_questions) VALUES (?,?,?,?,?,?)",
        item
    )

conn.commit()
conn.close()

db_size = os.path.getsize(DB_PATH) // (1024 * 1024)
print(f"✅ Bot Knowledge Database successfully created at: {DB_PATH}")
print(f"Database size: {db_size} MB")
