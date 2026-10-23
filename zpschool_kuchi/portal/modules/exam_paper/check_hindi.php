<?php
chdir("c:/xampp/htdocs/hpc/new/portal/modules/exam_paper");
require_once "includes/functions.php";
$db = ep_db();
$rows = $db->query("SELECT s.subject_id, s.standard_id, s.name, st.name std_name, st.medium FROM ep_subjects s JOIN ep_standards st ON st.standard_id=s.standard_id WHERE s.subject_id>=900000 AND s.standard_id IN (14,29,35) ORDER BY s.subject_id")->fetchAll();
echo "Std 6 bridge subjects:\n";
foreach ($rows as $r) echo "  [{$r['subject_id']}] std={$r['std_name']} ({$r['medium']}): {$r['name']}\n";
