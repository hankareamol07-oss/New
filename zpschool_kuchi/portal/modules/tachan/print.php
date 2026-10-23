<?php
declare(strict_types=1);

/**
 * Smart School Hub — Single Lesson Plan Print Sheet
 */
require_once __DIR__ . '/../lib/print_layout.php';
require_once __DIR__ . '/../lib/module_helpers.php';
require_once __DIR__ . '/../lib/tachan_engine.php';

$u = require_login();
$sid = (int)$u['school_id'];
$id = (int)($_GET['id'] ?? 0);

$stmt = db()->prepare('SELECT l.*, c.name as class_name 
                      FROM lesson_plans l 
                      LEFT JOIN classes c ON c.id = l.class_id AND c.school_id = l.school_id 
                      WHERE l.id = ? AND l.school_id = ?');
$stmt->execute([$id, $sid]);
$r = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$r) {
    http_response_code(404);
    exit('टाचण नोंद सापडली नाही.');
}

print_start('दैनिक पाठ टाचण');
?>
<table style="margin-bottom: 1.5rem;">
    <tr>
        <th style="width: 15%;">दिनांक</th>
        <td style="width: 35%;"><?= e(date('d-m-Y', strtotime((string)$r['date']))) ?> (<?= e(marathi_date_words($r['date'])) ?>)</td>
        <th style="width: 15%;">वर्ग / इयत्ता</th>
        <td style="width: 35%;"><?= e($r['class_name'] ?? '—') ?> (तासिका <?= (int)$r['period'] ?>)</td>
    </tr>
    <tr>
        <th>विषय</th>
        <td><strong><?= e($r['subject']) ?></strong></td>
        <th>घटक / पाठ</th>
        <td><strong><?= e($r['topic']) ?></strong></td>
    </tr>
    <tr>
        <th>अध्ययन निष्पत्ती</th>
        <td colspan="3"><?= nl2br(e((string)($r['learning_outcome'] ?? '—'))) ?></td>
    </tr>
    <tr>
        <th>अध्ययन-अध्यापन कृती</th>
        <td colspan="3"><?= nl2br(e((string)($r['activities'] ?? '—'))) ?></td>
    </tr>
    <tr>
        <th>मूल्यमापन साधने व तंत्रे</th>
        <td colspan="3"><?= nl2br(e((string)($r['eval_tool'] ?? '—'))) ?></td>
    </tr>
    <tr>
        <th>आवश्यक शैक्षणिक साहित्य</th>
        <td colspan="3"><?= nl2br(e((string)($r['materials'] ?? '—'))) ?></td>
    </tr>
    <tr>
        <th>गृहपाठ / स्वाध्याय</th>
        <td colspan="3"><?= nl2br(e((string)($r['homework'] ?? '—'))) ?></td>
    </tr>
    <?php if (!empty($r['notes'])): ?>
    <tr>
        <th>शेरा</th>
        <td colspan="3"><?= nl2br(e($r['notes'])) ?></td>
    </tr>
    <?php endif; ?>
</table>

<?php
print_signature_row(['वर्गशिक्षक', 'मुख्याध्यापक']);
print_end();
?>
