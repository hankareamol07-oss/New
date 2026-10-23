import urllib.request, urllib.parse, http.cookiejar, sys

cj = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

# 1. Login as Teacher Amol Hankare (Class 6)
login_data = urllib.parse.urlencode({'mobile': '9960967574', 'password': 'Teacher@Kuchi2026', 'role': 'teacher'}).encode('utf-8')
req = urllib.request.Request('http://localhost/hpc/zpschool2kuchi/portal/index.php', data=login_data)
res = opener.open(req)
print(f'Login Response Code: {res.status}')

urls = [
    'http://localhost/hpc/zpschool2kuchi/portal/index.php',
    'http://localhost/hpc/zpschool2kuchi/portal/cce/index.php',
    'http://localhost/hpc/zpschool2kuchi/portal/hpc/index.php',
    'http://localhost/hpc/zpschool2kuchi/portal/students/list.php',
    'http://localhost/hpc/zpschool2kuchi/portal/modules/classes/bonafide.php',
    'http://localhost/hpc/zpschool2kuchi/portal/modules/attendance/index.php',
    'http://localhost/hpc/zpschool2kuchi/portal/modules/tachan/index.php',
    'http://localhost/hpc/zpschool2kuchi/portal/modules/paripath/index.php',
    'http://localhost/hpc/zpschool2kuchi/portal/modules/exam_paper/index.php',
    'http://localhost/hpc/zpschool2kuchi/portal/modules/mdm/dashboard.php',
]

for u in urls:
    try:
        r = opener.open(u)
        content = r.read().decode('utf-8', errors='ignore')
        short = u.replace('http://localhost/hpc/zpschool2kuchi/portal/', '')
        print(f'{short:40} -> {r.status} (Length: {len(content)})')
    except Exception as e:
        short = u.replace('http://localhost/hpc/zpschool2kuchi/portal/', '')
        print(f'{short:40} -> ERROR: {e}')
