import urllib.request, urllib.parse, http.cookiejar, re, sys

sys.stdout.reconfigure(encoding='utf-8')

cj = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

# Login as Teacher Amol Hankare (Class 6)
login_data = urllib.parse.urlencode({'mobile': '9960967574', 'password': 'Teacher@Kuchi2026', 'role': 'teacher'}).encode('utf-8')
res = opener.open('http://localhost/hpc/zpschool2kuchi/portal/index.php', data=login_data)

def check_url(url, label):
    r = opener.open(url)
    html = r.read().decode('utf-8', errors='ignore')
    return html

# 1. CCE Index
cce_html = check_url('http://localhost/hpc/zpschool2kuchi/portal/cce/index.php', 'CCE')
print("=== CCE Index Standards ===")
# Look for standard badges or links in CCE
for std_num in range(1, 8):
    std_mr = {1: 'पहिली', 2: 'दुसरी', 3: 'तिसरी', 4: 'चौथी', 5: 'पाचवी', 6: 'सहावी', 7: 'सातवी'}[std_num]
    # Check if standard links exist like ?std=1
    has_link = f'std={std_num}' in cce_html
    print(f"Std {std_num} ({std_mr}): present={has_link}")

# 2. HPC Index
hpc_html = check_url('http://localhost/hpc/zpschool2kuchi/portal/hpc/index.php', 'HPC')
print("\n=== HPC Index Stages / Standards ===")
for stage in ['foundational', 'preparatory', 'middle']:
    print(f"Stage {stage}: present={stage in hpc_html}")

# 3. Students List
students_html = check_url('http://localhost/hpc/zpschool2kuchi/portal/students/list.php', 'Students')
print("\n=== Students List Standards ===")
for std_num in range(1, 8):
    std_mr = {1: 'पहिली', 2: 'दुसरी', 3: 'तिसरी', 4: 'चौथी', 5: 'पाचवी', 6: 'सहावी', 7: 'सातवी'}[std_num]
    has_link = f'std={std_num}' in students_html
    print(f"Std {std_num} ({std_mr}): present={has_link}")

# 4. Attendance
att_html = check_url('http://localhost/hpc/zpschool2kuchi/portal/modules/attendance/index.php', 'Attendance')
print("\n=== Attendance Classes ===")
# Match options in <select name="class_id">
options = re.findall(r'<option\s+value="(\d+)"[^>]*>([^<]+)</option>', att_html)
print("Available Class Options in Attendance:", options)

# 5. Bonafide
bona_html = check_url('http://localhost/hpc/zpschool2kuchi/portal/modules/classes/bonafide.php', 'Bonafide')
print("\n=== Bonafide Standards ===")
bona_options = re.findall(r'<option\s+value="(\d+)"[^>]*>([^<]+)</option>', bona_html)
print("Available Std Options in Bonafide:", bona_options)
