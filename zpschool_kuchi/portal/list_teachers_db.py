import pymysql, sys
sys.stdout.reconfigure(encoding='utf-8')

conn = pymysql.connect(host='127.0.0.1', user='root', password='', database='hpc_new')
cur = conn.cursor(pymysql.cursors.DictCursor)
cur.execute("""
    SELECT u.id, u.name, u.mobile, u.role, u.linked_id, t.name as t_name, tc.class_id, tc.std, tc.division 
    FROM users u 
    LEFT JOIN teachers t ON u.linked_id = t.id 
    LEFT JOIN teacher_classes tc ON t.id = tc.teacher_id 
    WHERE u.school_id = 2 AND u.role = 'teacher'
    ORDER BY tc.std
""")
for row in cur.fetchall():
    print(row)
