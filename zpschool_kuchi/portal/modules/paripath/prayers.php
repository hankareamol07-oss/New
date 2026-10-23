<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../lib/paripath_engine.php';

$prayers = paripath_get_prayers();

$page_title = "शालेय प्रार्थना, प्रतिज्ञा व संविधान — परिपाठ";
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
.prayer-card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    background: #fff;
    overflow: hidden;
    margin-bottom: 24px;
    transition: transform 0.2s;
}
.prayer-card:hover {
    box-shadow: 0 8px 30px rgba(0,0,0,0.08);
}
.prayer-content {
    font-family: 'Mukta', serif;
    font-size: 1.15rem;
    line-height: 2.1;
    color: #1e293b;
    white-space: pre-line;
    background: #fdfbf7;
    border-radius: 12px;
    padding: 24px;
    border: 1px solid #f1ece1;
}
.prayer-badge {
    font-size: 0.8rem;
    padding: 5px 12px;
    border-radius: 20px;
    font-weight: 600;
}
</style>

<div class="container-fluid px-lg-4 py-3">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="../../dashboard.php">डॅशबोर्ड</a></li>
                    <li class="breadcrumb-item"><a href="index.php">शालेय परिपाठ</a></li>
                    <li class="breadcrumb-item active" aria-current="page">प्रार्थना व प्रतिज्ञा</li>
                </ol>
            </nav>
            <h3 class="fw-bold mb-0 text-primary">
                <i class="bi bi-music-note-beamed me-2"></i> शालेय प्रार्थना, प्रतिज्ञा व संविधान संग्रह
            </h3>
        </div>

        <div>
            <a href="index.php" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-arrow-left"></i> परिपाठ डॅशबोर्ड
            </a>
            <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-printer"></i> प्रिंट काढा
            </button>
        </div>
    </div>

    <!-- Quick Jump Anchor Links -->
    <div class="card shadow-sm border-0 mb-4" style="border-radius:14px;">
        <div class="card-body p-3">
            <span class="small fw-bold text-muted d-block mb-2"><i class="bi bi-compass"></i> त्वरित उडी घ्या:</span>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($prayers as $idx => $p): ?>
                <a href="#prayer-<?= $p['id'] ?>" class="btn btn-sm btn-light border" style="border-radius:18px;">
                    <?= sanitize($p['title_mr']) ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Prayers List -->
    <div class="row">
        <?php foreach ($prayers as $p): 
            $catColor = match($p['category']) {
                'national' => ['badge' => 'bg-danger', 'text' => 'राष्ट्रगीत'],
                'pledge' => ['badge' => 'bg-primary', 'text' => 'राष्ट्रीय प्रतिज्ञा'],
                'preamble' => ['badge' => 'bg-success', 'text' => 'संविधान उद्देशिका'],
                'song' => ['badge' => 'bg-warning text-dark', 'text' => 'राष्ट्रीय गान'],
                'pasaydan' => ['badge' => 'bg-dark', 'text' => 'पसायदान'],
                default => ['badge' => 'bg-info text-dark', 'text' => 'शालेय प्रार्थना']
            };
        ?>
        <div class="col-12" id="prayer-<?= $p['id'] ?>">
            <div class="prayer-card">
                <div class="p-4 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2 bg-light">
                    <div>
                        <span class="badge <?= $catColor['badge'] ?> prayer-badge mb-1">
                            <?= $catColor['text'] ?>
                        </span>
                        <h4 class="fw-bold text-dark mb-0 mt-1">
                            <?= sanitize($p['title_mr']) ?>
                        </h4>
                        <?php if (!empty($p['author'])): ?>
                        <small class="text-muted">रचनाकार: <strong><?= sanitize($p['author']) ?></strong></small>
                        <?php endif; ?>
                    </div>

                    <button class="btn btn-sm btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('content-<?= $p['id'] ?>').innerText); alert('मजकूर कॉपी झाला!');">
                        <i class="bi bi-clipboard"></i> मजकूर कॉपी करा
                    </button>
                </div>

                <div class="p-4">
                    <div class="prayer-content text-center" id="content-<?= $p['id'] ?>">
                        <?= nl2br(sanitize($p['content'])) ?>
                    </div>

                    <?php if (!empty($p['meaning'])): ?>
                    <div class="mt-3 p-3 bg-light rounded-3 border">
                        <strong class="text-primary small d-block mb-1"><i class="bi bi-chat-left-quote"></i> भावार्थ व महत्त्व:</strong>
                        <p class="mb-0 text-secondary small" style="line-height:1.6;">
                            <?= sanitize($p['meaning']) ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
