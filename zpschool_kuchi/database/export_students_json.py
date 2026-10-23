import openpyxl
import json

students_file = r'c:\xampp\htdocs\hpc\27350302306_Students_Details 2026-27 (1).xlsx'
wb = openpyxl.load_workbook(students_file, data_only=True)
st = wb['Students List']

roman_to_std = {
    'I': 1, 'II': 2, 'III': 3, 'IV': 4,
    'V': 5, 'VI': 6, 'VII': 7, 'VIII': 8
}

students = []
roll_counters = {1: 1, 2: 1, 3: 1, 4: 1, 5: 1, 6: 1, 7: 1, 8: 1}

for r in st.iter_rows(min_row=4, values_only=True):
    if not r[2]:
        continue
    c_roman = str(r[0]).strip()
    std = roman_to_std.get(c_roman, 1)
    sec = str(r[1]).strip() if r[1] else 'A'
    name = str(r[2]).strip()
    gender = 'मुलगी' if 'female' in str(r[3]).lower() else 'मुलगा'
    pen = str(r[5]).strip() if r[5] else ''
    state_code = str(r[6]).strip() if r[6] else ''
    father = str(r[7]).strip() if r[7] else ''
    mother = str(r[8]).strip() if r[8] else ''
    cat = str(r[9]).strip() if r[9] else ''

    roll = roll_counters[std]
    roll_counters[std] += 1

    students.append({
        'std': std,
        'section': sec,
        'roll_no': str(roll),
        'name': name,
        'gender': gender,
        'pen': pen,
        'state_code': state_code,
        'father': father,
        'mother': mother,
        'category': cat
    })

out_json = r'c:\xampp\htdocs\hpc\zpschool2kuchi\database\students_data.json'
with open(out_json, 'w', encoding='utf-8') as f:
    json.dump(students, f, ensure_ascii=False, indent=2)

print(f"Exported {len(students)} students to {out_json}")
