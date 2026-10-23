import MySQLdb

db = MySQLdb.connect(host='127.0.0.1', user='school_admin', passwd='SchoolAdmin@2026', db='zpschool_kuchi', charset='utf8mb4')
cur = db.cursor()
cur.execute('INSERT INTO teacher_classes (school_id, teacher_id, class_id, std, division, academic_year, is_class_teacher) VALUES (2, 7, 15, 7, %s, %s, 0) ON DUPLICATE KEY UPDATE std=7', ('A', '2026-27'))
cur.execute('INSERT INTO teacher_classes (school_id, teacher_id, class_id, std, division, academic_year, is_class_teacher) VALUES (2, 8, 14, 6, %s, %s, 0) ON DUPLICATE KEY UPDATE std=6', ('A', '2026-27'))
db.commit()
cur.execute('SELECT tc.teacher_id, t.name_mr, tc.class_id, tc.std FROM teacher_classes tc JOIN teachers t ON tc.teacher_id = t.id WHERE tc.teacher_id IN (7, 8)')
print("Updated teacher_classes for 6th and 7th teachers:")
for row in cur.fetchall():
    print(row)
db.close()
