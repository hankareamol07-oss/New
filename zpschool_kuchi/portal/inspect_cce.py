import urllib.request, urllib.parse, http.cookiejar, sys

cj = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

# Login as Teacher Amol Hankare (Class 6)
login_data = urllib.parse.urlencode({'mobile': '9960967574', 'password': 'Teacher@Kuchi2026', 'role': 'teacher'}).encode('utf-8')
res = opener.open('http://localhost/hpc/zpschool2kuchi/portal/index.php', data=login_data)

r = opener.open('http://localhost/hpc/zpschool2kuchi/portal/cce/index.php')
content = r.read().decode('utf-8', errors='ignore')
with open('c:/xampp/htdocs/hpc/zpschool2kuchi/portal/cce_test_out.html', 'w', encoding='utf-8') as f:
    f.write(content)

print(f"cce/index.php final URL: {r.geturl()}")
print(f"cce/index.php content length: {len(content)}")
print(f"cce/index.php title / snippet: {content[:300].encode('ascii', errors='replace').decode('ascii')}")
