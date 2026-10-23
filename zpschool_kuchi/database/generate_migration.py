import openpyxl
import re
import os

ADMIN_HASH = "$2y$10$ehuokpov.weLfpTYUkoWbu1SD1hVgAAIcVQJ4m6ihRev.riFM5GQy"    # Admin@Kuchi2026
TEACHER_HASH = "$2y$10$AfEV3l6sDZycN3lI.hTscOsi8u53DD1/apQyPwbkdM5B6YWb.hi0O"  # Teacher@Kuchi2026
STUDENT_HASH = "$2y$10$NJtSwf.kB6/t3qzIrRllyep.Z7.MrJe13Uu5c3PLywtHXwwVAKbOe"  # Student@Kuchi2026

roman_to_std = {
    'I': 1, 'II': 2, 'III': 3, 'IV': 4,
    'V': 5, 'VI': 6, 'VII': 7, 'VIII': 8
}

teachers_meta = [
    {
        'id': 1,
        'name': 'SANGITA JAYWANT PATIL',
        'name_mr': 'संगीता जयवंत पाटील',
        'nat_code': 'TR83872929',
        'phone': '9405599703',
        'email': 'sangitapatil10@gmail.com',
        'role': 'headmaster',
        'std': None
    },
    {
        'id': 2,
        'name': 'ARPANA DATTATRAY PATIL',
        'name_mr': 'अपर्णा दत्तात्रय पाटील (पोळ)',
        'nat_code': 'TP21956092',
        'phone': '9766000001',
        'email': 'polearpana1234@gmail.com',
        'role': 'teacher',
        'std': 1
    },
    {
        'id': 3,
        'name': 'NITA KESHAV PATIL',
        'name_mr': 'नीता केशव पाटील',
        'nat_code': 'TP21587226',
        'phone': '9021000002',
        'email': 'nitapatil245@gmail.com',
        'role': 'teacher',
        'std': 2
    },
    {
        'id': 4,
        'name': 'URMILA MAHADEO JADHAV',
        'name_mr': 'उर्मिला महादेव जाधव',
        'nat_code': 'TP00841069',
        'phone': '7709000003',
        'email': 'urmilajadhav1969@gmail.com',
        'role': 'teacher',
        'std': 3
    },
    {
        'id': 5,
        'name': 'SINDHUTAI RAOSAHEB PATIL',
        'name_mr': 'सिंधुताई रावसाहेब पाटील',
        'nat_code': 'TP57948946',
        'phone': '9370000004',
        'email': 'Sindhupatil1971@gmail.com',
        'role': 'teacher',
        'std': 4
    },
    {
        'id': 6,
        'name': 'NANDA SUSHANT CHAVAN',
        'name_mr': 'नंदा सुशांत चव्हाण',
        'nat_code': 'TP11614789',
        'phone': '9552000005',
        'email': 'nandajadhav5986@gmail.com',
        'role': 'teacher',
        'std': 5
    },
    {
        'id': 7,
        'name': 'AMOL KISAN HANKARE',
        'name_mr': 'अमोल किसन हंकारे',
        'nat_code': 'TP32639483',
        'phone': '9960967574',
        'email': 'amolhankare04@gmail.com',
        'role': 'teacher',
        'std': 6
    },
    {
        'id': 8,
        'name': 'PADAMAJA RAJENDRA POSANE',
        'name_mr': 'पद्मजा राजेंद्र पोसणे',
        'nat_code': 'TP66435235',
        'phone': '8793000007',
        'email': 'padamaja82@gmail.com',
        'role': 'teacher',
        'std': 7
    }
]

# Load students
students_file = r'c:\xampp\htdocs\hpc\27350302306_Students_Details 2026-27 (1).xlsx'
wb_s = openpyxl.load_workbook(students_file, data_only=True)
st_s = wb_s['Students List']

students = []
roll_counters = {1: 1, 2: 1, 3: 1, 4: 1, 5: 1, 6: 1, 7: 1, 8: 1}

for r in st_s.iter_rows(min_row=4, values_only=True):
    if not r[2]:
        continue
    c_roman = str(r[0]).strip()
    std = roman_to_std.get(c_roman, 1)
    sec = str(r[1]).strip() if r[1] else 'A'
    name = str(r[2]).strip().replace("'", "''")
    gender = 'मुलगी' if 'female' in str(r[3]).lower() else 'मुलगा'
    pen = str(r[5]).strip() if r[5] else ''
    state_code = str(r[6]).strip() if r[6] else ''
    father = str(r[7]).strip().replace("'", "''") if r[7] else ''
    mother = str(r[8]).strip().replace("'", "''") if r[8] else ''
    cat = str(r[9]).strip().replace("'", "''") if r[9] else ''

    roll = roll_counters[std]
    roll_counters[std] += 1

    # find teacher for this std
    t_id = 7
    for tm in teachers_meta:
        if tm['std'] == std:
            t_id = tm['id']
            break

    students.append({
        'std': std,
        'section': sec,
        'roll_no': roll,
        'name': name,
        'gender': gender,
        'pen': pen,
        'state_code': state_code,
        'father': father,
        'mother': mother,
        'category': cat,
        'teacher_id': t_id
    })

print(f"Loaded {len(students)} students from XLSX.")

# We will generate migration SQL
sql_lines = []
sql_lines.append("-- ============================================================================")
sql_lines.append("-- MIGRATION SCRIPT: Z. P. SCHOOL NO. 2 KUCHI (SINGLE-SCHOOL PRO VERSION)")
sql_lines.append("-- UDISE: 27350302306 | TALUKA: KAVATHE MAHANKAL | DISTRICT: SANGLI")
sql_lines.append("-- ============================================================================\n")
sql_lines.append("SET NAMES utf8mb4;")
sql_lines.append("SET FOREIGN_KEY_CHECKS = 0;\n")

# 1. Users Table
sql_lines.append("-- 1. Unified Role-Based Users Table (Admin, Teacher, Student)")
sql_lines.append("""CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `school_id` INT NOT NULL DEFAULT 2,
  `role` ENUM('admin', 'teacher', 'student') NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `mobile` VARCHAR(30) NOT NULL UNIQUE,
  `email` VARCHAR(255) NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `linked_id` INT NULL COMMENT 'teacher_id or student_id',
  `is_active` TINYINT(1) DEFAULT 1,
  `last_login` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_role` (`role`),
  KEY `idx_school_role` (`school_id`, `role`),
  KEY `idx_linked` (`linked_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n""")

# 2. Schools Table
sql_lines.append("-- 2. Ensure Single School Record: Z. P. SCHOOL NO. 2 KUCHI")
sql_lines.append("""INSERT INTO `schools` (`id`, `name`, `name_mr`, `udise_code`, `email`, `phone`, `village`, `taluka`, `district`, `state`, `is_active`)
VALUES (2, 'Z. P. School No 2 Kuchi', 'जिल्हा परिषद शाळा नं २ कुची', '27350302306', 'admin@zpschool2kuchi.com', '9405599703', 'कुची', 'कवठे महांकाळ', 'सांगली', 'महाराष्ट्र', 1)
ON DUPLICATE KEY UPDATE
  `name` = 'Z. P. School No 2 Kuchi',
  `name_mr` = 'जिल्हा परिषद शाळा नं २ कुची',
  `udise_code` = '27350302306',
  `phone` = '9405599703',
  `is_active` = 1;\n""")

# 3. Classes Table
sql_lines.append("-- 3. Classes 1 to 7 with Assigned Class Teachers")
sql_lines.append("""CREATE TABLE IF NOT EXISTS `classes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `school_id` INT NOT NULL DEFAULT 2,
  `std` INT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `section` VARCHAR(255) NOT NULL DEFAULT 'A',
  `class_teacher_id` INT NULL,
  `medium` VARCHAR(40) NULL DEFAULT 'मराठी',
  `academic_year_id` INT NULL DEFAULT 1,
  UNIQUE KEY `uniq_std_sec` (`school_id`, `std`, `section`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n""")

std_marathi = {
    1: 'इयत्ता १ ली', 2: 'इयत्ता २ री', 3: 'इयत्ता ३ री',
    4: 'इयत्ता ४ थी', 5: 'इयत्ता ५ वी', 6: 'इयत्ता ६ वी', 7: 'इयत्ता ७ वी'
}

for std in range(1, 8):
    ct_id = None
    for tm in teachers_meta:
        if tm['std'] == std:
            ct_id = tm['id']
            break
    sql_lines.append(f"INSERT INTO `classes` (`id`, `school_id`, `std`, `name`, `section`, `class_teacher_id`, `medium`) "
                     f"VALUES ({std}, 2, {std}, '{std_marathi[std]}', 'A', {ct_id}, 'मराठी') "
                     f"ON DUPLICATE KEY UPDATE `class_teacher_id` = {ct_id}, `name` = '{std_marathi[std]}';")

# 4. Teacher Classes Mapping
sql_lines.append("\n-- 4. Teacher Classes Mapping Table (Strict Class Enforcement: Teachers see ONLY their class)")
sql_lines.append("""CREATE TABLE IF NOT EXISTS `teacher_classes` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n""")

for tm in teachers_meta:
    if tm['std']:
        sql_lines.append(f"INSERT INTO `teacher_classes` (`teacher_id`, `class_id`, `std`, `division`, `academic_year`, `is_class_teacher`) "
                         f"VALUES ({tm['id']}, {tm['std']}, {tm['std']}, 'A', '2026-27', 1) "
                         f"ON DUPLICATE KEY UPDATE `is_class_teacher` = 1;")

# Headmaster Sangita Patil mapped to all classes
for std in range(1, 8):
    sql_lines.append(f"INSERT INTO `teacher_classes` (`teacher_id`, `class_id`, `std`, `division`, `academic_year`, `is_class_teacher`) "
                     f"VALUES (1, {std}, {std}, 'A', '2026-27', 0) "
                     f"ON DUPLICATE KEY UPDATE `is_class_teacher` = 0;")

# 5. Teachers Table
sql_lines.append("\n-- 5. Populate Official Teachers for Z. P. School No. 2 Kuchi")
sql_lines.append("""CREATE TABLE IF NOT EXISTS `teachers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `school_id` INT NOT NULL DEFAULT 2,
  `name` VARCHAR(255) NOT NULL,
  `name_mr` VARCHAR(255),
  `teacher_code` VARCHAR(50),
  `email` VARCHAR(255),
  `phone` VARCHAR(25),
  `class_assigned` VARCHAR(50),
  `section` VARCHAR(10) DEFAULT 'A',
  `is_active` TINYINT(1) DEFAULT 1,
  `role` VARCHAR(30) DEFAULT 'teacher',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_school` (`school_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n""")

for tm in teachers_meta:
    cls_assign = f"इयत्ता {tm['std']} ली" if tm['std'] else 'मुख्याध्यापक'
    sql_lines.append(
        f"INSERT INTO `teachers` (`id`, `school_id`, `name`, `name_mr`, `teacher_code`, `email`, `phone`, `class_assigned`, `section`, `is_active`, `role`) "
        f"VALUES ({tm['id']}, 2, '{tm['name']}', '{tm['name_mr']}', '{tm['nat_code']}', '{tm['email']}', '{tm['phone']}', '{cls_assign}', 'A', 1, '{tm['role']}') "
        f"ON DUPLICATE KEY UPDATE `name` = '{tm['name']}', `name_mr` = '{tm['name_mr']}', `teacher_code` = '{tm['nat_code']}', `phone` = '{tm['phone']}', `class_assigned` = '{cls_assign}';"
    )

# 6. Students Table
sql_lines.append("\n-- 6. Populate 190 Students with class and teacher assignment")
sql_lines.append("""CREATE TABLE IF NOT EXISTS `students` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `school_id` INT NOT NULL DEFAULT 2,
  `class_id` INT NOT NULL,
  `teacher_id` INT NULL,
  `name` VARCHAR(255) NOT NULL,
  `name_mr` VARCHAR(255),
  `apaar_id` VARCHAR(50),
  `roll_no` VARCHAR(20),
  `grade` VARCHAR(20) DEFAULT 'इयत्ता १ ली',
  `std` TINYINT NOT NULL DEFAULT 1,
  `section` VARCHAR(10) DEFAULT 'A',
  `gender` ENUM('मुलगा','मुलगी','इतर') DEFAULT 'मुलगा',
  `student_pen` VARCHAR(60) NULL,
  `father_name` VARCHAR(255) NULL,
  `mother_name` VARCHAR(255) NULL,
  `caste_category` VARCHAR(50) NULL,
  `phone` VARCHAR(25) NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_class_sec` (`class_id`, `section`),
  KEY `idx_teacher` (`teacher_id`),
  KEY `idx_pen` (`student_pen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n""")

# Clean previous student records for school 2
sql_lines.append("DELETE FROM `students` WHERE `school_id` = 2;\n")

student_insert_vals = []
for idx, s in enumerate(students, 1):
    gr_text = std_marathi[s['std']]
    student_insert_vals.append(
        f"({idx}, 2, {s['std']}, {s['teacher_id']}, '{s['name']}', '{s['name']}', '{s['pen']}', '{s['roll_no']}', "
        f"'{gr_text}', {s['std']}, '{s['section']}', '{s['gender']}', '{s['pen']}', '{s['father']}', '{s['mother']}', '{s['category']}', 1)"
    )

# Batch insert in chunks of 50
for i in range(0, len(student_insert_vals), 50):
    chunk = student_insert_vals[i:i+50]
    sql_lines.append("INSERT INTO `students` (`id`, `school_id`, `class_id`, `teacher_id`, `name`, `name_mr`, `apaar_id`, `roll_no`, `grade`, `std`, `section`, `gender`, `student_pen`, `father_name`, `mother_name`, `caste_category`, `is_active`) VALUES\n" + ",\n".join(chunk) + ";\n")

# 7. Users Table Seeding
sql_lines.append("\n-- 7. Seed Demo Accounts & Real Logins in `users` table")
sql_lines.append("DELETE FROM `users` WHERE `school_id` = 2;\n")

# 1. Admin Demo & Real
users_vals = [
    # Demo Admin
    f"(2, 'admin', 'प्रशासक (मुख्य)', '9988990000', 'admin@zpschool2kuchi.com', '{ADMIN_HASH}', 1, 1)",
    f"(2, 'admin', 'संगीता जयवंत पाटील (मुख्याध्यापक)', '9405599703', 'sangitapatil10@gmail.com', '{ADMIN_HASH}', 1, 1)",
    # Demo Teacher (mapped to Amol Hankare / Class 6)
    f"(2, 'teacher', 'नमुना शिक्षक (इ. ६ वी)', '9000000010', 'teacher_demo@zpschool2kuchi.com', '{TEACHER_HASH}', 7, 1)",
]

# Teachers
for tm in teachers_meta:
    role = 'admin' if tm['id'] == 1 else 'teacher'
    h = ADMIN_HASH if tm['id'] == 1 else TEACHER_HASH
    users_vals.append(f"(2, '{role}', '{tm['name_mr']}', '{tm['phone']}', '{tm['email']}', '{h}', {tm['id']}, 1)")

# Demo Student (linked to student 1: AARADHYA SUNIL NAGARGOJE / student 2)
users_vals.append(f"(2, 'student', 'नमुना विद्यार्थी (इ. १ ली)', '9876543210', 'student_demo@zpschool2kuchi.com', '{STUDENT_HASH}', 1, 1)")

# Also create logins for the first 10 students using their PEN number as mobile
for s_idx in range(min(15, len(students))):
    st_row = students[s_idx]
    if st_row['pen']:
        users_vals.append(f"(2, 'student', '{st_row['name']}', '{st_row['pen']}', NULL, '{STUDENT_HASH}', {s_idx + 1}, 1)")

sql_lines.append("INSERT INTO `users` (`school_id`, `role`, `name`, `mobile`, `email`, `password_hash`, `linked_id`, `is_active`) VALUES\n" + ",\n".join(users_vals) + ";\n")

sql_lines.append("SET FOREIGN_KEY_CHECKS = 1;\n")

out_file = r'c:\xampp\htdocs\hpc\zpschool2kuchi\database\migration_single_school.sql'
with open(out_file, 'w', encoding='utf-8') as f:
    f.write("\n".join(sql_lines))

print(f"Successfully generated {out_file} with {len(students)} students and role-based users.")
