<?php
require_once __DIR__ . '/../../new/portal/config/database.php';

$ADMIN_HASH = '$2y$10$ehuokpov.weLfpTYUkoWbu1SD1hVgAAIcVQJ4m6ihRev.riFM5GQy';    // Admin@Kuchi2026
$TEACHER_HASH = '$2y$10$AfEV3l6sDZycN3lI.hTscOsi8u53DD1/apQyPwbkdM5B6YWb.hi0O';  // Teacher@Kuchi2026
$STUDENT_HASH = '$2y$10$NJtSwf.kB6/t3qzIrRllyep.Z7.MrJe13Uu5c3PLywtHXwwVAKbOe';  // Student@Kuchi2026

try {
    $db = getDB();
    echo "Connected to " . DB_NAME . "\n";
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Ensure columns in users table
    $existing_cols = [];
    foreach ($db->query("SHOW COLUMNS FROM `users`") as $c) {
        $existing_cols[strtolower($c['Field'])] = true;
    }
    
    if (!isset($existing_cols['mobile'])) {
        $db->exec("ALTER TABLE `users` ADD COLUMN `mobile` VARCHAR(30) NULL AFTER `name`");
        echo "Added column 'mobile' to users.\n";
    }
    if (!isset($existing_cols['email'])) {
        $db->exec("ALTER TABLE `users` ADD COLUMN `email` VARCHAR(255) NULL AFTER `mobile`");
        echo "Added column 'email' to users.\n";
    }
    if (!isset($existing_cols['linked_id'])) {
        $db->exec("ALTER TABLE `users` ADD COLUMN `linked_id` INT NULL COMMENT 'teacher_id or student_id' AFTER `password_hash`");
        echo "Added column 'linked_id' to users.\n";
    }
    if (!isset($existing_cols['is_active'])) {
        $db->exec("ALTER TABLE `users` ADD COLUMN `is_active` TINYINT(1) DEFAULT 1 AFTER `linked_id`");
        echo "Added column 'is_active' to users.\n";
    }
    if (!isset($existing_cols['last_login'])) {
        $db->exec("ALTER TABLE `users` ADD COLUMN `last_login` DATETIME NULL AFTER `is_active`");
        echo "Added column 'last_login' to users.\n";
    }
    
    // Also ensure phone column is synced if present
    $has_phone = isset($existing_cols['phone']);

    // 2. Ensure single school record: school_id = 2
    $db->exec("INSERT INTO `schools` (`id`, `name`, `name_mr`, `udise_code`, `email`, `phone`, `village`, `taluka`, `district`, `state`, `is_active`)
    VALUES (2, 'Z. P. School No 2 Kuchi', 'जिल्हा परिषद शाळा नं २ कुची', '27350302306', 'admin@zpschool2kuchi.com', '9405599703', 'कुची', 'कवठे महांकाळ', 'सांगली', 'महाराष्ट्र', 1)
    ON DUPLICATE KEY UPDATE
      `name` = 'Z. P. School No 2 Kuchi',
      `name_mr` = 'जिल्हा परिषद शाळा नं २ कुची',
      `udise_code` = '27350302306',
      `phone` = '9405599703',
      `is_active` = 1");
    echo "2. School record verified for Z. P. School No 2 Kuchi.\n";

    // 3. Upsert teachers for School 2
    $teachers_data = [
        ['code' => 'TR83872929', 'name' => 'SANGITA JAYWANT PATIL', 'name_mr' => 'संगीता जयवंत पाटील', 'phone' => '9405599703', 'email' => 'sangitapatil10@gmail.com', 'class' => 'मुख्याध्यापक', 'role' => 'admin', 'std' => 0],
        ['code' => 'TP21956092', 'name' => 'ARPANA DATTATRAY PATIL', 'name_mr' => 'अपर्णा दत्तात्रय पाटील (पोळ)', 'phone' => '9766000001', 'email' => 'polearpana1234@gmail.com', 'class' => 'इयत्ता १ ली', 'role' => 'teacher', 'std' => 1],
        ['code' => 'TP21587226', 'name' => 'NITA KESHAV PATIL', 'name_mr' => 'नीता केशव पाटील', 'phone' => '9021000002', 'email' => 'nitapatil245@gmail.com', 'class' => 'इयत्ता २ री', 'role' => 'teacher', 'std' => 2],
        ['code' => 'TP00841069', 'name' => 'URMILA MAHADEO JADHAV', 'name_mr' => 'उर्मिला महादेव जाधव', 'phone' => '7709000003', 'email' => 'urmilajadhav1969@gmail.com', 'class' => 'इयत्ता ३ री', 'role' => 'teacher', 'std' => 3],
        ['code' => 'TP57948946', 'name' => 'SINDHUTAI RAOSAHEB PATIL', 'name_mr' => 'सिंधुताई रावसाहेब पाटील', 'phone' => '9370000004', 'email' => 'Sindhupatil1971@gmail.com', 'class' => 'इयत्ता ४ थी', 'role' => 'teacher', 'std' => 4],
        ['code' => 'TP11614789', 'name' => 'NANDA SUSHANT CHAVAN', 'name_mr' => 'नंदा सुशांत चव्हाण', 'phone' => '9552000005', 'email' => 'nandajadhav5986@gmail.com', 'class' => 'इयत्ता ५ वी', 'role' => 'teacher', 'std' => 5],
        ['code' => 'TP32639483', 'name' => 'AMOL KISAN HANKARE', 'name_mr' => 'अमोल किसन हंकारे', 'phone' => '9960967574', 'email' => 'amolhankare04@gmail.com', 'class' => 'इयत्ता ६ वी', 'role' => 'teacher', 'std' => 6],
        ['code' => 'TP66435235', 'name' => 'PADAMAJA RAJENDRA POSANE', 'name_mr' => 'पद्मजा राजेंद्र पोसणे', 'phone' => '8793000007', 'email' => 'padamaja82@gmail.com', 'class' => 'इयत्ता ७ वी', 'role' => 'teacher', 'std' => 7],
    ];

    $teacher_ids = [];
    $t_stmt = $db->prepare("SELECT id FROM teachers WHERE school_id = 2 AND (teacher_code = ? OR phone = ? OR name = ?) LIMIT 1");
    $t_ins = $db->prepare("INSERT INTO teachers (school_id, name, name_mr, teacher_code, email, phone, class_assigned, section, is_active, role)
                          VALUES (2, ?, ?, ?, ?, ?, ?, 'A', 1, ?)");
    $t_upd = $db->prepare("UPDATE teachers SET name = ?, name_mr = ?, teacher_code = ?, email = ?, phone = ?, class_assigned = ?, role = ?, is_active = 1
                          WHERE id = ?");

    foreach ($teachers_data as $t) {
        $t_stmt->execute([$t['code'], $t['phone'], $t['name']]);
        $existing_id = $t_stmt->fetchColumn();
        if ($existing_id) {
            $t_upd->execute([$t['name'], $t['name_mr'], $t['code'], $t['email'], $t['phone'], $t['class'], $t['role'], $existing_id]);
            $tid = (int)$existing_id;
        } else {
            $t_ins->execute([$t['name'], $t['name_mr'], $t['code'], $t['email'], $t['phone'], $t['class'], $t['role']]);
            $tid = (int)$db->lastInsertId();
        }
        $teacher_ids[$t['std']] = $tid;
        echo "Teacher {$t['name']} -> Teacher ID: $tid (Std: {$t['std']})\n";
    }

    // 4. Ensure Classes 1 to 7 for School 2
    $c_stmt = $db->prepare("SELECT id FROM classes WHERE school_id = 2 AND std = ? LIMIT 1");
    $c_ins = $db->prepare("INSERT INTO classes (school_id, std, name, section, class_teacher_id, medium) VALUES (2, ?, ?, 'A', ?, 'मराठी')");
    $c_upd = $db->prepare("UPDATE classes SET name = ?, section = 'A', class_teacher_id = ?, medium = 'मराठी' WHERE id = ?");

    $class_ids = [];
    $std_marathi = [
        1 => 'इयत्ता १ ली', 2 => 'इयत्ता २ री', 3 => 'इयत्ता ३ री',
        4 => 'इयत्ता ४ थी', 5 => 'इयत्ता ५ वी', 6 => 'इयत्ता ६ वी', 7 => 'इयत्ता ७ वी'
    ];

    for ($std = 1; $std <= 7; $std++) {
        $c_stmt->execute([$std]);
        $existing_cid = $c_stmt->fetchColumn();
        $ct_id = $teacher_ids[$std] ?? null;
        if ($existing_cid) {
            $c_upd->execute([$std_marathi[$std], $ct_id, $existing_cid]);
            $cid = (int)$existing_cid;
        } else {
            $c_ins->execute([$std, $std_marathi[$std], $ct_id]);
            $cid = (int)$db->lastInsertId();
        }
        $class_ids[$std] = $cid;
        echo "Class Std $std -> Class ID: $cid (Class Teacher: {$ct_id})\n";
    }

    // 5. Teacher Classes Mapping Table
    $db->exec("CREATE TABLE IF NOT EXISTS `teacher_classes` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `school_id` INT NOT NULL DEFAULT 2,
      `teacher_id` INT NOT NULL,
      `class_id` INT NOT NULL,
      `std` TINYINT NOT NULL,
      `division` VARCHAR(10) NOT NULL DEFAULT 'A',
      `academic_year` VARCHAR(10) NOT NULL DEFAULT '2026-27',
      `is_class_teacher` TINYINT(1) NOT NULL DEFAULT 1,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY `uniq_teacher_class` (`teacher_id`, `class_id`, `division`, `academic_year`),
      KEY `idx_class_div` (`class_id`, `division`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("DELETE FROM teacher_classes WHERE school_id = 2");
    $tc_ins = $db->prepare("INSERT INTO teacher_classes (school_id, teacher_id, class_id, std, division, academic_year, is_class_teacher)
                           VALUES (2, ?, ?, ?, 'A', '2026-27', ?)");

    for ($std = 1; $std <= 7; $std++) {
        $tid = $teacher_ids[$std];
        $cid = $class_ids[$std];
        $tc_ins->execute([$tid, $cid, $std, 1]);
    }
    // Headmaster Sangita Patil mapped to all classes
    $hm_id = $teacher_ids[0];
    for ($std = 1; $std <= 7; $std++) {
        $cid = $class_ids[$std];
        $tc_ins->execute([$hm_id, $cid, $std, 0]);
    }
    echo "5. teacher_classes mapping populated.\n";

    // 6. Populate students from JSON
    $jsonFile = __DIR__ . '/students_data.json';
    $students_list = json_decode(file_get_contents($jsonFile), true);
    echo "Loaded " . count($students_list) . " students from JSON.\n";

    // Clean previous student records for school 2
    $db->exec("DELETE FROM `students` WHERE `school_id` = 2");

    $st_ins = $db->prepare("INSERT INTO `students` (
      `school_id`, `class_id`, `teacher_id`, `name`, `name_mr`, `apaar_id`, `roll_no`, 
      `grade`, `std`, `section`, `gender`, `student_pen`, `father_name`, `mother_name`, `caste_category`, `is_active`
    ) VALUES (
      2, ?, ?, ?, ?, ?, ?, 
      ?, ?, 'A', ?, ?, ?, ?, ?, 1
    )");

    $first_student_id = null;
    $student_pen_map = [];
    $inserted_students = 0;

    foreach ($students_list as $s) {
        $std = (int)$s['std'];
        $cid = $class_ids[$std];
        $tid = $teacher_ids[$std];
        $grade = $std_marathi[$std];

        $st_ins->execute([
            $cid, $tid, $s['name'], $s['name'], $s['pen'], $s['roll_no'],
            $grade, $std, $s['gender'], $s['pen'], $s['father'], $s['mother'], $s['category']
        ]);
        $sid = (int)$db->lastInsertId();
        if ($first_student_id === null) {
            $first_student_id = $sid;
        }
        $student_pen_map[$s['pen']] = $sid;
        $inserted_students++;
    }
    echo "6. Inserted $inserted_students students into database.\n";

    // 7. Seed users table
    $db->exec("DELETE FROM `users` WHERE `school_id` = 2");
    
    // Prepare insert for users
    if ($has_phone) {
        $u_ins = $db->prepare("INSERT INTO `users` (`school_id`, `role`, `name`, `mobile`, `phone`, `email`, `password_hash`, `linked_id`, `is_active`)
                              VALUES (2, ?, ?, ?, ?, ?, ?, ?, 1)");
        $do_insert = function($role, $name, $mobile, $email, $hash, $linked_id) use ($u_ins) {
            $u_ins->execute([$role, $name, $mobile, $mobile, $email, $hash, $linked_id]);
        };
    } else {
        $u_ins = $db->prepare("INSERT INTO `users` (`school_id`, `role`, `name`, `mobile`, `email`, `password_hash`, `linked_id`, `is_active`)
                              VALUES (2, ?, ?, ?, ?, ?, ?, 1)");
        $do_insert = function($role, $name, $mobile, $email, $hash, $linked_id) use ($u_ins) {
            $u_ins->execute([$role, $name, $mobile, $email, $hash, $linked_id]);
        };
    }

    // Demo Admin (9988990000) & Real Admin (9405599703)
    $do_insert('admin', 'प्रशासक (मुख्य)', '9988990000', 'admin@zpschool2kuchi.com', $ADMIN_HASH, $teacher_ids[0]);
    $do_insert('admin', 'संगीता जयवंत पाटील (मुख्याध्यापक)', '9405599703', 'sangitapatil10@gmail.com', $ADMIN_HASH, $teacher_ids[0]);

    // Teachers
    foreach ($teachers_data as $t) {
        if ($t['std'] > 0) {
            $tid = $teacher_ids[$t['std']];
            $do_insert('teacher', $t['name_mr'], $t['phone'], $t['email'], $TEACHER_HASH, $tid);
            echo "Created Teacher Login: {$t['phone']} ({$t['name_mr']}) -> Std {$t['std']}\n";
        }
    }

    // Demo Teacher: 9000000010 (mapped to Amol Hankare / Std 6)
    $do_insert('teacher', 'नमुना शिक्षक (अमोल हंकारे - इ. ६ वी)', '9000000010', 'teacher_demo@zpschool2kuchi.com', $TEACHER_HASH, $teacher_ids[6]);

    // Demo Student: 9876543210 (mapped to first student)
    $do_insert('student', 'नमुना विद्यार्थी (इ. १ ली)', '9876543210', 'student_demo@zpschool2kuchi.com', $STUDENT_HASH, $first_student_id);

    // Create student logins using Student PEN
    $user_student_count = 0;
    foreach ($student_pen_map as $pen => $sid) {
        if (!empty($pen)) {
            $st_name = $students_list[$user_student_count]['name'];
            try {
                $do_insert('student', $st_name, $pen, null, $STUDENT_HASH, $sid);
                $user_student_count++;
            } catch (Throwable $e) {}
        }
    }
    echo "7. Created $user_student_count student user logins via Student PEN.\n";

    echo "\n=== PHASE 2 SETUP COMPLETED SUCCESSFULLY! ===\n";

} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
