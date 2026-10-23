import urllib.request, urllib.parse, http.cookiejar, re, sys

sys.stdout.reconfigure(encoding='utf-8')

def test_teacher(name, mobile, expected_std):
    print(f"\n==========================================")
    print(f"Testing {name} ({mobile}) -> Expected Std: {expected_std}")
    print(f"==========================================")
    cj = http.cookiejar.CookieJar()
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

    login_data = urllib.parse.urlencode({'mobile': mobile, 'password': 'Teacher@Kuchi2026', 'role': 'teacher'}).encode('utf-8')
    res = opener.open('http://localhost/hpc/zpschool2kuchi/portal/index.php', data=login_data)

    # 1. CCE
    cce = opener.open('http://localhost/hpc/zpschool2kuchi/portal/cce/index.php').read().decode('utf-8')
    cce_matches = [s for s in range(1, 8) if f'std={s}' in cce]
    print(f"CCE Standards visible: {cce_matches} (Expected: [{expected_std}])")

    # 2. HPC
    hpc = opener.open('http://localhost/hpc/zpschool2kuchi/portal/hpc/index.php').read().decode('utf-8')
    hpc_std_matches = [s for s in range(1, 8) if f'std={s}' in hpc]
    print(f"HPC Standards visible: {hpc_std_matches} (Expected: [{expected_std}])")

    # 3. Students
    st = opener.open('http://localhost/hpc/zpschool2kuchi/portal/students/list.php').read().decode('utf-8')
    st_matches = [s for s in range(1, 8) if f'std={s}' in st]
    print(f"Students Standards visible: {st_matches} (Expected: [{expected_std}])")

    # 4. Attendance
    att = opener.open('http://localhost/hpc/zpschool2kuchi/portal/modules/attendance/index.php').read().decode('utf-8')
    att_classes = re.findall(r'<option\s+value="(\d+)"[^>]*>([^<]+)</option>', att)
    print(f"Attendance Classes visible: {att_classes}")

    # 5. Bonafide
    bona = opener.open('http://localhost/hpc/zpschool2kuchi/portal/modules/classes/bonafide.php').read().decode('utf-8')
    bona_classes = re.findall(r'<option\s+value="(\d+)"[^>]*>([^<]+)</option>', bona)
    print(f"Bonafide Standards visible: {bona_classes}")

teachers = [
    ("अपर्णा पाटील", "9766000001", 1),
    ("नीता पाटील",   "9021000002", 2),
    ("उर्मिला जाधव", "7709000003", 3),
    ("सिंधू पाटील",   "9370000004", 4),
    ("नंदा चव्हाण",   "9552000005", 5),
    ("अमोल हंकारे", "9960967574", 6),
    ("पद्मजा पोसणे", "8793000007", 7),
]

for name, mobile, std in teachers:
    test_teacher(name, mobile, std)
