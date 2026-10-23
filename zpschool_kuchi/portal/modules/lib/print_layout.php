<?php
declare(strict_types=1);
require_once __DIR__ . '/module_errors.php';
require_once __DIR__ . '/auth.php';

function print_start(string $title, array $options = []): void
{
    $user = require_login();
    $school = school_row((int) $user['school_id']) ?: [];
    $schoolName = (string) (($school['name_mr'] ?? '') ?: ($school['name'] ?? ''));
    $udise = (string) ($school['udise_code'] ?? ($school['udise'] ?? ''));
    $compact = !empty($options['compact']);
    $rawHeader = !empty($options['raw_header']);
    $pageMargin = $options['margin'] ?? ($compact ? '5mm' : '10mm');
    $isLandscape = stripos((string)$pageSize, 'landscape') !== false;
    $pSize = stripos((string)$pageSize, 'A5') !== false ? 'A5' : (stripos((string)$pageSize, 'A3') !== false ? 'A3' : 'A4');
    ?>
    <!doctype html><html lang="mr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="print-orientation" content="<?= $isLandscape ? 'landscape' : 'portrait' ?>"><meta name="print-paper-size" content="<?= $pSize ?>"><title><?= e($title) ?></title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Mukta:wght@300;400;500;600;700;800&family=Martel:wght@600;700;800;900&family=Noto+Sans+Devanagari:wght@400;500;600;700&display=swap" rel="stylesheet"><style>@page{size:<?= e($pageSize) ?>;margin:<?= e($pageMargin) ?>}body{font-family:'Mukta','Mukta',-apple-system,BlinkMacSystemFont,sans-serif;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale;color:#0f172a;margin:<?= $compact ? '0' : '1rem' ?>;line-height:<?= $compact ? '1.25' : '1.45' ?>}header.std-header{text-align:center;border-bottom:1.5px solid #1A365D;padding-bottom:.4rem;margin-bottom:.6rem}header.std-header img{max-height:48px;max-width:90px;object-fit:contain;margin:0 auto .25rem}header.std-header h1{font-family:'Martel','Mukta',serif;font-weight:800}header.std-header h2{font-family:'Mukta',sans-serif;font-weight:700}.print-btn{background:#D69E2E;border:0;border-radius:6px;padding:.45rem .85rem;font-weight:700;cursor:pointer;color:#1A365D}.print-btn:hover{background:#b7791f}.sig-row{display:flex;justify-content:space-between;gap:1.5rem;margin-top:1.5rem}.sig-row>div{flex:1;border-top:1px solid #334155;padding-top:.4rem;text-align:center}.sig-row .stamp{border:1px dashed #94a3b8;min-height:2rem;margin-top:.5rem;padding:.35rem;border-top:0;color:#64748b}.no-print{margin-bottom:.5rem}table{width:100%;border-collapse:collapse;font-size:12px}th,td{border:1px solid #334155;padding:4px 6px;text-align:left}th{background:#f1f5f9;font-weight:700}dl{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.4rem 1.5rem}dt{font-weight:700;color:#475569}dd{margin:0}@media print{.no-print{display:none!important}body{margin:0!important;padding:0!important}.sig-row{break-inside:avoid}.page:last-child,.sheet:last-child{page-break-after:avoid!important;break-after:avoid!important}}</style></head><body><?php if (!$rawHeader): ?><div class="no-print" style="text-align:right"><button class="print-btn" onclick="window.print()">🖨️ प्रिंट / PDF जतन करा</button></div><header class="std-header"><?php if (!empty($school['logo'])): ?><img src="<?= e((string) $school['logo']) ?>" alt="शाळेचा लोगो"><?php endif; ?><h1 style="font-size:16px;margin:0 0 2px"><?= e($schoolName) ?></h1><p style="font-size:11px;margin:0 0 4px"><?php if ($udise): ?>UDISE: <?= e($udise) ?> · <?php endif; ?><?= e($school['address'] ?? ($school['address_line1'] ?? '')) ?><?php if (!empty($school['phone'])): ?> · <?= e($school['phone']) ?><?php endif; ?></p><h2 style="font-size:13px;margin:0"><?= e($title) ?></h2></header><?php endif; ?>
    <?php
}

function print_end(): void
{
    echo '</body></html>';
}

function print_signature_row(array $labels = ['वर्गशिक्षक', 'मुख्याध्यापक', 'पालक']): void
{
    echo '<div class="sig-row">';
    foreach ($labels as $label) {
        echo '<div>' . e((string) $label) . '<div class="stamp">स्वाक्षरी / शिक्का</div></div>';
    }
    echo '</div>';
}
