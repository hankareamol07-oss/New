<?php
declare(strict_types=1);
header('Location: /modules/mdm/reports/prapatr_b.php');
exit;
require_once __DIR__ . '/../lib/print_layout.php';
require_once __DIR__ . '/../lib/auth.php';
$u = require_login();
$from = (string) ($_GET['from'] ?? date('Y-m-01'));
$to = (string) ($_GET['to'] ?? date('Y-m-d'));
$stmt = db()->prepare('SELECT * FROM mdm_entries WHERE school_id=? AND date BETWEEN ? AND ? ORDER BY date,class_group');
$stmt->execute([(int) $u['school_id'], $from, $to]);
print_start('MDM मासिक अहवाल');
?>
<h1>पोषण आहार अहवाल</h1><p>कालावधी: <?= e($from) ?> ते <?= e($to) ?></p>
<table><thead><tr><th>दिनांक</th><th>गट</th><th>उपस्थित</th><th>तांदूळ (kg)</th><th>डाळ (kg)</th><th>तेल (kg)</th><th>मेनू</th><th>शेरा</th></tr></thead><tbody><?php $totalPresent = 0; foreach ($stmt as $r): $totalPresent += (int) $r['present_count']; ?><tr><td><?= e($r['date']) ?></td><td><?= e($r['class_group']) ?></td><td><?= e((string) $r['present_count']) ?></td><td><?= e((string) $r['rice_kg']) ?></td><td><?= e((string) $r['dal_kg']) ?></td><td><?= e((string) $r['oil_kg']) ?></td><td><?= e($r['menu']) ?></td><td><?= e($r['remarks']) ?></td></tr><?php endforeach; ?></tbody><tfoot><tr><th colspan="2">एकूण उपस्थिती</th><th><?= $totalPresent ?></th><td colspan="5"></td></tr></tfoot></table>
<?php print_signature_row(['शाळा पोषण आहार प्रभारी', 'मुख्याध्यापक']); print_end(); ?>
