<?php
declare(strict_types=1);
require_once __DIR__ . '/mdm_helpers.php';
require_once __DIR__ . '/print_layout.php';

function render_mdm_report(string $kind, string $title): void
{
    $u = require_login();
    $sid = (int) $u['school_id'];
    $from = (string) ($_GET['from'] ?? date('Y-m-01'));
    $to = (string) ($_GET['to'] ?? date('Y-m-d'));
    if (isset($_GET['month'], $_GET['year'])) {
        $from = sprintf('%04d-%02d-01', (int) $_GET['year'], (int) $_GET['month']);
        $to = date('Y-m-t', strtotime($from));
    }
    $group = isset($_GET['class_group']) && isset(mdm_groups()[(string) $_GET['class_group']]) ? (string) $_GET['class_group'] : null;
    $rows = mdm_report_rows(db(), $sid, $from, $to, $group);
    $headers = ['दिनांक', 'इयत्ता गट', 'लाभार्थी', 'मेनू', 'शिजवले', 'शेरा'];
    if (isset($_GET['excel'])) {
        mdm_report_csv($kind . '.csv', $headers, array_map(static fn(array $r): array => [$r['date'], $r['class_group'], $r['beneficiaries'], $r['menu_name'] ?? '', $r['cooked'] ? 'होय' : 'नाही', $r['remarks']], $rows));
    }
    print_start($title);
    echo '<p>कालावधी: ' . e($from) . ' ते ' . e($to) . '</p>';
    echo '<p>शासनाच्या पोषण आहार योजनेअंतर्गत शाळेतील लाभार्थी व धान्य वापर नोंद.</p>';
    echo '<table><thead><tr>';
    foreach ($headers as $header) echo '<th>' . e($header) . '</th>';
    echo '</tr></thead><tbody>';
    $total = 0;
    foreach ($rows as $row) {
        $total += (int) $row['beneficiaries'];
        echo '<tr><td>' . e($row['date']) . '</td><td>' . e(mdm_groups()[$row['class_group']] ?? $row['class_group']) . '</td><td>' . e((string) $row['beneficiaries']) . '</td><td>' . e($row['menu_name'] ?? '—') . '</td><td>' . ($row['cooked'] ? 'होय' : 'नाही') . '</td><td>' . e($row['remarks']) . '</td></tr>';
    }
    echo '</tbody><tfoot><tr><th colspan="2">एकूण लाभार्थी नोंदी</th><th>' . $total . '</th><td colspan="3"></td></tr></tfoot></table>';
    echo '<p style="margin-top:2rem">वरील माहिती शाळेच्या नोंदीनुसार बरोबर आहे.</p>';
    print_signature_row(['MDM प्रभारी', 'मुख्याध्यापक']);
    print_end();
}
