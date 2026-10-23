import sqlite3

con = sqlite3.connect('/var/www/html/zpschool2kuchi/data/schools/27350302306.sqlite')
tables = [r[0] for r in con.execute("SELECT name FROM sqlite_master WHERE type='table'").fetchall()]
print("Tables in tenant sqlite:", tables)
for t in tables:
    if any(k in t.lower() for k in ["time", "tachan", "plan", "teacher", "class"]):
        count = con.execute(f"SELECT count(*) FROM {t}").fetchone()[0]
        print(f"  {t}: {count} rows")
        if count > 0 and count < 30:
            sample = con.execute(f"SELECT * FROM {t} LIMIT 3").fetchall()
            print(f"    sample: {sample}")
