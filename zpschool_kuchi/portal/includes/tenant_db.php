<?php
/**
 * Multi-tenant SQLite Database Architecture
 * Each school (by UDISE code) gets its own isolated SQLite database file.
 * SaaS functions (authentication, plans, schools registry, global master bank) run on MySQL.
 */
require_once __DIR__ . '/../config/database.php';

if (!function_exists('getMainDB')) {
    function getMainDB() {
        return getDB();
    }
}

/**
 * Resolves the UDISE code for a given school ID
 */
function getSchoolUdise($school_id = null) {
    $sess_school_id = isset($_SESSION['school_id']) ? (int)$_SESSION['school_id'] : 0;
    if (!$school_id) {
        $school_id = $sess_school_id;
    }
    if (!$school_id) return null;

    if ($sess_school_id === (int)$school_id && !empty($_SESSION['school_udise'])) {
        return $_SESSION['school_udise'];
    }

    $mysql = getMainDB();
    $stmt = $mysql->prepare("SELECT udise_code FROM schools WHERE id = ?");
    $stmt->execute([$school_id]);
    $code = $stmt->fetchColumn();
    $udise = preg_replace('/\D/', '', (string)$code) ?: 'school_' . $school_id;
    if ($sess_school_id === (int)$school_id) {
        $_SESSION['school_udise'] = $udise;
    }
    return $udise;
}

/**
 * Gets or initializes the SQLite database connection for a school
 * @param int|null $school_id
 * @param string|null $udise
 * @return PDO
 */
function getSchoolTenantDB($school_id = null, $udise = null) {
    static $connections = [];

    if (!$school_id && isset($_SESSION['school_id'])) {
        $school_id = (int)$_SESSION['school_id'];
    }
    if (!$udise) {
        $udise = getSchoolUdise($school_id);
    }
    if (!$udise) {
        $udise = 'default_tenant';
    }

    if (isset($connections[$udise])) {
        return $connections[$udise];
    }

    try {
        $dir = __DIR__ . '/../data/schools';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $htaccess = $dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, "Require all denied\n");
        }

        $sqlite_path = "$dir/$udise.sqlite";
        $is_new = !file_exists($sqlite_path) || filesize($sqlite_path) === 0;

        $pdo = new PDO("sqlite:$sqlite_path");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        try {
            $pdo->exec("PRAGMA journal_mode = WAL;");
        } catch (Throwable $t_wal) {
            try { $pdo->exec("PRAGMA journal_mode = DELETE;"); } catch (Throwable $t_del) {}
        }
        $pdo->exec("PRAGMA foreign_keys = ON;");
        $pdo->exec("PRAGMA synchronous = NORMAL;");

        $has_tables = false;
        try {
            $has_plans = (bool)$pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='lesson_plans'")->fetch();
            $has_mdm = (bool)$pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='mdm_daily'")->fetch();
            $has_tables = $has_plans && $has_mdm;
        } catch (Throwable $e) {
            $has_tables = false;
        }

        if ($is_new || !$has_tables) {
            initTenantSchemaAndMigrate($pdo, $school_id, $udise);
        }

        $connections[$udise] = $pdo;
        return $pdo;
    } catch (Throwable $e) {
        return getMainDB();
    }
}

/**
 * Convenient shortcut to get the current school's tenant SQLite DB
 */
if (!function_exists('getTenantDB')) {
    function getTenantDB($school_id = null, $udise = null) {
        return getSchoolTenantDB($school_id, $udise);
    }
}

/**
 * Dual-sync a record write to MySQL:
 * Whenever a record is inserted or updated in SQLite, also upsert it to MySQL.
 */
function syncTenantRecordToMySQL(string $table, array $data, array $uniqueKeys = []): bool {
    try {
        $mysql = getMainDB();
        $cols = array_keys($data);
        $col_names = implode(',', array_map(function($c) { return "`$c`"; }, $cols));
        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        
        $update_parts = [];
        foreach ($cols as $c) {
            if ($c === 'id' || in_array($c, $uniqueKeys, true)) continue;
            $update_parts[] = "`$c` = VALUES(`$c`)";
        }
        $on_dup = !empty($update_parts) ? " ON DUPLICATE KEY UPDATE " . implode(', ', $update_parts) : "";
        
        $sql = "INSERT INTO `$table` ($col_names) VALUES ($placeholders)" . $on_dup;
        $stmt = $mysql->prepare($sql);
        return $stmt->execute(array_values($data));
    } catch (Throwable $e) {
        error_log("syncTenantRecordToMySQL error on $table: " . $e->getMessage());
        return false;
    }
}

/**
 * Dual-sync a delete operation to MySQL
 */
function syncTenantDeleteToMySQL(string $table, array $criteria): bool {
    try {
        $mysql = getMainDB();
        $where_clauses = [];
        $values = [];
        foreach ($criteria as $col => $val) {
            $where_clauses[] = "`$col` = ?";
            $values[] = $val;
        }
        if (empty($where_clauses)) return false;
        $sql = "DELETE FROM `$table` WHERE " . implode(' AND ', $where_clauses);
        $stmt = $mysql->prepare($sql);
        return $stmt->execute($values);
    } catch (Throwable $e) {
        error_log("syncTenantDeleteToMySQL error on $table: " . $e->getMessage());
        return false;
    }
}

/**
 * Initializes tenant tables in SQLite and syncs existing MySQL records if available
 */
function initTenantSchemaAndMigrate(PDO $sqlite, $school_id, $udise) {
    $mysql = getMainDB();
    $tables = [
        'students', 'teachers', 'attendance', 
        'cce_subjects', 'cce_subject_teachers', 'cce_marks', 'cce_student_notes', 'cce_student_outcomes', 'cce_student_extra',
        'classes', 'timetable', 'holidays', 'lesson_plans', 'tachan_prefs', 'annual_plans', 'calendar_events',
        'mdm_entries', 'mdm_grains', 'mdm_menus', 'mdm_menu_items', 'mdm_daily', 'mdm_stock', 'mdm_cooks', 'mdm_holidays', 'mdm_sunday_meals', 'mdm_grant_settings', 'mdm_bmi', 'mdm_pat_history', 'mdm_students'
    ];

    $uniques = [
        'attendance' => 'UNIQUE(student_id, academic_year, month)',
        'cce_subject_teachers' => 'UNIQUE(school_id, academic_year, std, division, subject_id)',
        'cce_marks' => 'UNIQUE(student_id, subject_id, academic_year, semester)',
        'cce_student_notes' => 'UNIQUE(student_id, academic_year, semester, slot_no)',
        'cce_student_outcomes' => 'UNIQUE(student_id, academic_year, semester, outcome_id)',
        'cce_student_extra' => 'UNIQUE(student_id, academic_year)',
        'tachan_prefs' => 'UNIQUE(school_id, teacher_id)',
        'timetable' => 'UNIQUE(school_id, class_id, day, period)',
        'lesson_plans' => 'UNIQUE(school_id, class_id, date, period)',
        'annual_plans' => 'UNIQUE(school_id, class_id, subject, month)',
        'holidays' => 'UNIQUE(school_id, date)',
        'mdm_daily' => 'UNIQUE(school_id, date, class_group)',
        'mdm_stock' => 'UNIQUE(school_id, grain_id, class_group, month, year)',
        'mdm_pat_history' => 'UNIQUE(school_id, class_group, month, year)',
        'mdm_holidays' => 'UNIQUE(school_id, holiday_date)',
        'mdm_grant_settings' => 'UNIQUE(school_id, effective_from)',
    ];

    foreach ($tables as $t) {
        try {
            $cols = $mysql->query("SHOW COLUMNS FROM `$t`")->fetchAll(PDO::FETCH_ASSOC);
            $col_defs = [];
            foreach ($cols as $c) {
                $field = $c['Field'];
                $type = strtolower($c['Type']);
                if ($field === 'id') {
                    $col_defs[] = "`id` INTEGER PRIMARY KEY" . ($t !== 'students' && $t !== 'teachers' ? " AUTOINCREMENT" : "");
                    continue;
                }
                if (str_contains($type, 'int')) {
                    $sql_type = 'INTEGER';
                } elseif (str_contains($type, 'decimal') || str_contains($type, 'float') || str_contains($type, 'double')) {
                    $sql_type = 'REAL';
                } else {
                    $sql_type = 'TEXT';
                }
                $col_defs[] = "`$field` $sql_type";
            }
            if (isset($uniques[$t])) {
                $col_defs[] = $uniques[$t];
            }
            $create_sql = "CREATE TABLE IF NOT EXISTS `$t` (\n  " . implode(",\n  ", $col_defs) . "\n);";
            $sqlite->exec($create_sql);
        } catch (Exception $e) {
            // fallback if table does not exist in MySQL
        }
    }

    // School info cache table
    $sqlite->exec("
    CREATE TABLE IF NOT EXISTS school_info (
        id INTEGER PRIMARY KEY,
        udise_code TEXT UNIQUE,
        name TEXT,
        name_mr TEXT,
        working_days_monthly TEXT,
        address_line1 TEXT,
        village TEXT,
        taluka TEXT,
        district TEXT,
        pin_code TEXT,
        logo TEXT,
        phone TEXT,
        email TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );");

    if ($school_id) {
        try {
            $stmt = $mysql->prepare("SELECT * FROM schools WHERE id = ?");
            $stmt->execute([$school_id]);
            $s = $stmt->fetch();
            if ($s) {
                $ins = $sqlite->prepare("INSERT OR REPLACE INTO school_info (id, udise_code, name, name_mr, working_days_monthly, address_line1, village, taluka, district, pin_code, logo, phone, email, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP)");
                $ins->execute([
                    $s['id'], $udise, $s['name'], $s['name_mr'], $s['working_days_monthly'] ?? '',
                    $s['address_line1'] ?? '', $s['village'] ?? '', $s['taluka'] ?? '', $s['district'] ?? '',
                    $s['pin_code'] ?? '', $s['logo'] ?? '', $s['phone'] ?? '', $s['email'] ?? ''
                ]);

                // Sync existing records from MySQL
                foreach ($tables as $tbl) {
                    try {
                        if ($tbl === 'attendance') {
                            $rows = $mysql->query("SELECT a.* FROM attendance a JOIN students s ON a.student_id = s.id WHERE s.school_id = $school_id")->fetchAll(PDO::FETCH_ASSOC);
                        } elseif ($tbl === 'mdm_menu_items') {
                            $rows = $mysql->query("SELECT mi.* FROM mdm_menu_items mi JOIN mdm_menus m ON mi.menu_id = m.id WHERE m.school_id = $school_id")->fetchAll(PDO::FETCH_ASSOC);
                        } else {
                            $rows = $mysql->query("SELECT * FROM `$tbl` WHERE school_id = $school_id")->fetchAll(PDO::FETCH_ASSOC);
                        }
                        if ($rows) {
                            $cols = array_keys($rows[0]);
                            $col_names = implode(',', array_map(function($c){ return "`$c`"; }, $cols));
                            $placeholders = implode(',', array_fill(0, count($cols), '?'));
                            $ins_stmt = $sqlite->prepare("INSERT OR REPLACE INTO `$tbl` ($col_names) VALUES ($placeholders)");
                            $sqlite->beginTransaction();
                            foreach ($rows as $r) {
                                $ins_stmt->execute(array_values($r));
                            }
                            $sqlite->commit();
                        }
                    } catch (Throwable $ex) {
                        if ($sqlite->inTransaction()) $sqlite->rollBack();
                    }
                }
            }
        } catch (Exception $e) {
            if ($sqlite->inTransaction()) $sqlite->rollBack();
        }
    }
}

/**
 * Automatically sync classes of a school from MySQL to tenant SQLite DB
 */
function sync_classes_to_sqlite($school_id, ?array $rows = null): bool {
    if (!$school_id) return false;
    try {
        $sqlite = getSchoolTenantDB($school_id);
        if (!$sqlite || $sqlite->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') return false;

        if ($rows === null) {
            $mysql = getMainDB();
            $stmt = $mysql->prepare("SELECT * FROM classes WHERE school_id = ?");
            $stmt->execute([$school_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        if (empty($rows)) return true;

        $hasTbl = (bool)$sqlite->query("SELECT name FROM sqlite_master WHERE type='table' AND name='classes'")->fetch();
        if (!$hasTbl) {
            $sqlite->exec("CREATE TABLE IF NOT EXISTS classes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                school_id INTEGER,
                std INTEGER,
                name TEXT,
                section TEXT,
                class_teacher_id INTEGER,
                medium TEXT,
                stream TEXT,
                academic_year_id INTEGER
            )");
        }

        $cols = array_keys($rows[0]);
        $pragma = $sqlite->query("PRAGMA table_info(classes)")->fetchAll(PDO::FETCH_ASSOC);
        $existing_cols = array_column($pragma, 'name');

        foreach ($cols as $c) {
            if (!in_array($c, $existing_cols, true)) {
                try {
                    $sqlite->exec("ALTER TABLE classes ADD COLUMN `$c` TEXT");
                } catch (Throwable $t) {}
            }
        }

        $pragma = $sqlite->query("PRAGMA table_info(classes)")->fetchAll(PDO::FETCH_ASSOC);
        $valid_cols = array_intersect($cols, array_column($pragma, 'name'));

        $col_names = implode(',', array_map(fn($c) => "`$c`", $valid_cols));
        $placeholders = implode(',', array_fill(0, count($valid_cols), '?'));

        $stmt = $sqlite->prepare("INSERT OR REPLACE INTO classes ($col_names) VALUES ($placeholders)");
        $sqlite->beginTransaction();
        foreach ($rows as $r) {
            $vals = [];
            foreach ($valid_cols as $vc) {
                $vals[] = $r[$vc] ?? null;
            }
            $stmt->execute($vals);
        }
        $sqlite->commit();
        return true;
    } catch (Throwable $e) {
        if (isset($sqlite) && $sqlite->inTransaction()) $sqlite->rollBack();
        error_log("sync_classes_to_sqlite error for school $school_id: " . $e->getMessage());
        return false;
    }
}

/**
 * Automatically sync students of a school from MySQL to tenant SQLite DB
 */
function sync_students_to_sqlite($school_id, ?array $rows = null): bool {
    if (!$school_id) return false;
    try {
        $sqlite = getSchoolTenantDB($school_id);
        if (!$sqlite || $sqlite->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') return false;
        
        if ($rows === null) {
            $mysql = getMainDB();
            $stmt = $mysql->prepare("SELECT * FROM students WHERE school_id = ? AND (is_active = 1 OR is_active IS NULL)");
            $stmt->execute([$school_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        if (empty($rows)) return true;

        $hasTbl = (bool)$sqlite->query("SELECT name FROM sqlite_master WHERE type='table' AND name='students'")->fetch();
        if (!$hasTbl) {
            $sqlite->exec("CREATE TABLE IF NOT EXISTS students (
                id INTEGER PRIMARY KEY,
                school_id INTEGER,
                name TEXT,
                name_mr TEXT,
                roll_no TEXT,
                std INTEGER,
                section TEXT,
                gender TEXT,
                student_pen TEXT,
                apaar_id TEXT,
                aadhar_no TEXT,
                father_name TEXT,
                mother_name TEXT,
                caste_category TEXT,
                class_id INTEGER,
                is_active INTEGER DEFAULT 1,
                created_at TEXT,
                updated_at TEXT
            )");
        }

        $cols = array_keys($rows[0]);
        $pragma = $sqlite->query("PRAGMA table_info(students)")->fetchAll(PDO::FETCH_ASSOC);
        $existing_cols = array_column($pragma, 'name');

        foreach ($cols as $c) {
            if (!in_array($c, $existing_cols, true)) {
                try {
                    $sqlite->exec("ALTER TABLE students ADD COLUMN `$c` TEXT");
                } catch (Throwable $t) {}
            }
        }

        $pragma = $sqlite->query("PRAGMA table_info(students)")->fetchAll(PDO::FETCH_ASSOC);
        $valid_cols = array_intersect($cols, array_column($pragma, 'name'));

        $col_names = implode(',', array_map(fn($c) => "`$c`", $valid_cols));
        $placeholders = implode(',', array_fill(0, count($valid_cols), '?'));

        $sqlite->exec("PRAGMA foreign_keys = OFF;");
        $stmt = $sqlite->prepare("INSERT OR REPLACE INTO students ($col_names) VALUES ($placeholders)");
        $sqlite->beginTransaction();
        foreach ($rows as $r) {
            $vals = [];
            foreach ($valid_cols as $vc) {
                $vals[] = $r[$vc] ?? null;
            }
            $stmt->execute($vals);
        }
        $sqlite->commit();
        $sqlite->exec("PRAGMA foreign_keys = ON;");

        try {
            $sqlite->exec("CREATE INDEX IF NOT EXISTS idx_stu_school ON students(school_id);");
            $sqlite->exec("CREATE INDEX IF NOT EXISTS idx_stu_std ON students(std);");
            $sqlite->exec("CREATE INDEX IF NOT EXISTS idx_stu_pen ON students(student_pen);");
        } catch (Throwable $t_idx) {}

        return true;
    } catch (Throwable $e) {
        if (isset($sqlite) && $sqlite->inTransaction()) $sqlite->rollBack();
        error_log("sync_students_to_sqlite error for school $school_id: " . $e->getMessage());
        return false;
    }
}

/**
 * Automatically sync CCE subjects from MySQL to tenant SQLite DB
 */
function sync_subjects_to_sqlite($school_id, ?array $rows = null): bool {
    if (!$school_id) return false;
    try {
        $sqlite = getSchoolTenantDB($school_id);
        if (!$sqlite) return false;

        if ($rows === null) {
            $mysql = getMainDB();
            $stmt = $mysql->prepare("SELECT * FROM cce_subjects WHERE school_id = ?");
            $stmt->execute([$school_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        if (empty($rows)) return true;

        $cols = array_keys($rows[0]);
        $pragma = $sqlite->query("PRAGMA table_info(cce_subjects)")->fetchAll(PDO::FETCH_ASSOC);
        $existing_cols = array_column($pragma, 'name');

        foreach ($cols as $c) {
            if (!in_array($c, $existing_cols, true)) {
                try {
                    $sqlite->exec("ALTER TABLE cce_subjects ADD COLUMN `$c` TEXT");
                } catch (Throwable $t) {}
            }
        }

        $pragma = $sqlite->query("PRAGMA table_info(cce_subjects)")->fetchAll(PDO::FETCH_ASSOC);
        $valid_cols = array_intersect($cols, array_column($pragma, 'name'));

        $col_names = implode(',', array_map(fn($c) => "`$c`", $valid_cols));
        $placeholders = implode(',', array_fill(0, count($valid_cols), '?'));

        $stmt = $sqlite->prepare("INSERT OR REPLACE INTO cce_subjects ($col_names) VALUES ($placeholders)");
        $sqlite->beginTransaction();
        foreach ($rows as $r) {
            $vals = [];
            foreach ($valid_cols as $vc) {
                $vals[] = $r[$vc] ?? null;
            }
            $stmt->execute($vals);
        }
        $sqlite->commit();
        return true;
    } catch (Throwable $e) {
        if (isset($sqlite) && $sqlite->inTransaction()) $sqlite->rollBack();
        return false;
    }
}

/**
 * Automatically sync CCE subjects from SQLite to MySQL
 */
function sync_subjects_to_mysql($school_id, array $rows): bool {
    if (!$school_id || empty($rows)) return false;
    try {
        $mysql = getMainDB();
        $cols = array_keys($rows[0]);
        $col_names = implode(',', array_map(fn($c) => "`$c`", $cols));
        $placeholders = implode(',', array_fill(0, count($cols), '?'));

        $update_parts = [];
        foreach ($cols as $c) {
            if ($c === 'id' || $c === 'school_id') continue;
            $update_parts[] = "`$c` = VALUES(`$c`)";
        }
        $on_dup = !empty($update_parts) ? " ON DUPLICATE KEY UPDATE " . implode(', ', $update_parts) : "";

        $sql = "INSERT INTO cce_subjects ($col_names) VALUES ($placeholders)" . $on_dup;
        $stmt = $mysql->prepare($sql);
        $mysql->beginTransaction();
        foreach ($rows as $r) {
            $stmt->execute(array_values($r));
        }
        $mysql->commit();
        return true;
    } catch (Throwable $e) {
        if (isset($mysql) && $mysql->inTransaction()) $mysql->rollBack();
        return false;
    }
}

/**
 * Automatically sync CCE marks from SQLite to MySQL
 */
function sync_marks_to_mysql($school_id, array $marks_data): bool {
    if (!$school_id || empty($marks_data)) return false;
    try {
        $mysql = getMainDB();
        $sql = "INSERT INTO cce_marks (school_id, student_id, subject_id, academic_year, semester,
                    fe1,fe2,fe3,fe4,fe5,fe6,fe7,fe8,se1,se2,se3,fe_total,se_total,grand_total,percent,grade)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE
                    fe1=VALUES(fe1), fe2=VALUES(fe2), fe3=VALUES(fe3), fe4=VALUES(fe4),
                    fe5=VALUES(fe5), fe6=VALUES(fe6), fe7=VALUES(fe7), fe8=VALUES(fe8),
                    se1=VALUES(se1), se2=VALUES(se2), se3=VALUES(se3),
                    fe_total=VALUES(fe_total), se_total=VALUES(se_total),
                    grand_total=VALUES(grand_total), percent=VALUES(percent), grade=VALUES(grade)";
        $stmt = $mysql->prepare($sql);
        $mysql->beginTransaction();
        foreach ($marks_data as $m) {
            $stmt->execute([
                $m['school_id'] ?? $school_id,
                $m['student_id'],
                $m['subject_id'],
                $m['academic_year'],
                $m['semester'],
                $m['fe1'] ?? null, $m['fe2'] ?? null, $m['fe3'] ?? null, $m['fe4'] ?? null,
                $m['fe5'] ?? null, $m['fe6'] ?? null, $m['fe7'] ?? null, $m['fe8'] ?? null,
                $m['se1'] ?? null, $m['se2'] ?? null, $m['se3'] ?? null,
                $m['fe_total'] ?? 0, $m['se_total'] ?? 0, $m['grand_total'] ?? 0,
                $m['percent'] ?? 0, $m['grade'] ?? null
            ]);
        }
        $mysql->commit();
        return true;
    } catch (Throwable $e) {
        if (isset($mysql) && $mysql->inTransaction()) $mysql->rollBack();
        return false;
    }
}

/**
 * Automatically sync CCE student notes (वर्णनात्मक नोंदी) from SQLite to MySQL
 */
function sync_notes_to_mysql($school_id, array $notes_data): bool {
    if (!$school_id || empty($notes_data)) return false;
    try {
        $mysql = getMainDB();
        $sql = "INSERT INTO cce_student_notes (school_id, student_id, academic_year, semester, slot_no, note_text)
                VALUES (?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE note_text = VALUES(note_text)";
        $stmt = $mysql->prepare($sql);
        $mysql->beginTransaction();
        foreach ($notes_data as $n) {
            $stmt->execute([
                $n['school_id'] ?? $school_id,
                $n['student_id'],
                $n['academic_year'],
                $n['semester'],
                $n['slot_no'],
                $n['note_text'] ?? ''
            ]);
        }
        $mysql->commit();
        return true;
    } catch (Throwable $e) {
        if (isset($mysql) && $mysql->inTransaction()) $mysql->rollBack();
        return false;
    }
}

/**
 * Automatically sync CCE student outcomes (अध्ययन निष्पत्ती) from SQLite to MySQL
 */
function sync_outcomes_to_mysql($school_id, array $outcomes_data): bool {
    if (!$school_id || empty($outcomes_data)) return false;
    try {
        $mysql = getMainDB();
        $sql = "INSERT INTO cce_student_outcomes (school_id, student_id, academic_year, semester, outcome_id, outcome_text, achieved, level)
                VALUES (?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE outcome_text = VALUES(outcome_text), achieved = VALUES(achieved), level = VALUES(level)";
        $stmt = $mysql->prepare($sql);
        $mysql->beginTransaction();
        foreach ($outcomes_data as $o) {
            $stmt->execute([
                $o['school_id'] ?? $school_id,
                $o['student_id'],
                $o['academic_year'],
                $o['semester'],
                $o['outcome_id'],
                $o['outcome_text'] ?? '',
                $o['achieved'] ?? 1,
                $o['level'] ?? 4
            ]);
        }
        $mysql->commit();
        return true;
    } catch (Throwable $e) {
        if (isset($mysql) && $mysql->inTransaction()) $mysql->rollBack();
        return false;
    }
}

