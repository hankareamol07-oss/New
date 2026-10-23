<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../lib/paripath_engine.php';

$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

$panchang = paripath_get_panchang($date);
$marathiDate = paripath_marathi_date($date);
$marathiDay = paripath_marathi_day($date);

$prevDate = date('Y-m-d', strtotime($date . ' -1 day'));
$nextDate = date('Y-m-d', strtotime($date . ' +1 day'));
$todayDate = date('Y-m-d');

$page_title = "दैनिक पंचांग — " . $marathiDate;
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid px-lg-4 py-3">

    <!-- Breadcrumb & Top Bar -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="../../dashboard.php">डॅशबोर्ड</a></li>
                    <li class="breadcrumb-item"><a href="index.php?date=<?= $date ?>">शालेय परिपाठ</a></li>
                    <li class="breadcrumb-item active" aria-current="page">दैनिक पंचांग</li>
                </ol>
            </nav>
            <h3 class="fw-bold mb-0 text-primary">
                <i class="bi bi-calendar-heart me-2"></i> दैनिक पंचांग (महाराष्ट्र)
            </h3>
        </div>

        <div class="d-flex align-items-center gap-2">
            <a href="panchang.php?date=<?= $prevDate ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-chevron-left"></i> काल
            </a>
            <a href="panchang.php?date=<?= $todayDate ?>" class="btn <?= $date===$todayDate ? 'btn-primary' : 'btn-outline-primary' ?> btn-sm">
                आज
            </a>
            <a href="panchang.php?date=<?= $nextDate ?>" class="btn btn-outline-secondary btn-sm">
                उद्या <i class="bi bi-chevron-right"></i>
            </a>
            <input type="date" value="<?= $date ?>" onchange="location.href='panchang.php?date='+this.value" class="form-control form-control-sm" style="width:auto; border-radius:8px;">
            <a href="blackboard.php?date=<?= $date ?>" target="_blank" class="btn btn-dark btn-sm">
                <i class="bi bi-display"></i> फळा मोड
            </a>
        </div>
    </div>

    <!-- Main Panchang Highlights Card -->
    <div class="card shadow-sm border-0 mb-4" style="border-radius:16px; overflow:hidden;">
        <div class="card-header text-white p-4" style="background:linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <span class="badge bg-white text-dark fw-bold mb-2">
                        <?= sanitize($marathiDay) ?> • <?= sanitize($panchang['ritu'] ?? 'शरद') ?> ऋतू
                    </span>
                    <h2 class="fw-bold mb-1"><?= sanitize($marathiDate) ?></h2>
                    <div class="fs-5 opacity-90">
                        हिंदू महिना: <strong><?= sanitize($panchang['hindu_month'] ?? 'भाद्रपद') ?></strong> (<?= sanitize($panchang['paksha'] ?? 'शुक्ल पक्ष') ?>)
                    </div>
                </div>
                <div class="col-md-4 text-md-end text-start mt-3 mt-md-0">
                    <div class="bg-white bg-opacity-20 p-3 rounded-3 d-inline-block text-start border border-white border-opacity-25">
                        <div class="small fw-semibold">प्रमुख तिथी</div>
                        <div class="fs-4 fw-bold"><?= sanitize($panchang['tithi'] ?? '-') ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body p-4 bg-light">
            <div class="row g-3">
                <!-- Row 1: Key Coordinates -->
                <div class="col-lg-3 col-sm-6 col-12">
                    <div class="p-3 bg-white rounded-3 shadow-sm border h-100">
                        <div class="small text-muted fw-bold">तिथी (Tithi)</div>
                        <div class="fs-5 fw-bold text-primary mt-1"><?= sanitize($panchang['tithi'] ?? '-') ?></div>
                        <small class="text-secondary">चंद्र कलेनुसार तिथी गणना</small>
                    </div>
                </div>

                <div class="col-lg-3 col-sm-6 col-12">
                    <div class="p-3 bg-white rounded-3 shadow-sm border h-100">
                        <div class="small text-muted fw-bold">नक्षत्र (Nakshatra)</div>
                        <div class="fs-5 fw-bold text-dark mt-1"><?= sanitize($panchang['nakshatra'] ?? '-') ?></div>
                        <small class="text-secondary">आकाशातील २७ नक्षत्रांपैकी चालू नक्षत्र</small>
                    </div>
                </div>

                <div class="col-lg-3 col-sm-6 col-12">
                    <div class="p-3 bg-white rounded-3 shadow-sm border h-100">
                        <div class="small text-muted fw-bold">योग (Yoga)</div>
                        <div class="fs-5 fw-bold text-success mt-1"><?= sanitize($panchang['yoga'] ?? '-') ?></div>
                        <small class="text-secondary">सूर्य व चंद्राच्या गतीवर आधारित २७ योग</small>
                    </div>
                </div>

                <div class="col-lg-3 col-sm-6 col-12">
                    <div class="p-3 bg-white rounded-3 shadow-sm border h-100">
                        <div class="small text-muted fw-bold">करण (Karana)</div>
                        <div class="fs-5 fw-bold text-info mt-1"><?= sanitize($panchang['karana'] ?? '-') ?></div>
                        <small class="text-secondary">तिथीचा अर्धा भाग (११ करण)</small>
                    </div>
                </div>

                <!-- Row 2: Sun & Moon Timings -->
                <div class="col-lg-3 col-sm-6 col-12">
                    <div class="p-3 bg-white rounded-3 shadow-sm border h-100">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="bi bi-sunrise-fill text-warning fs-5"></i>
                            <span class="small text-muted fw-bold">सूर्योदय (Sunrise)</span>
                        </div>
                        <div class="fs-4 fw-bold text-warning-emphasis"><?= sanitize($panchang['sunrise'] ?? '06:24 AM') ?></div>
                        <small class="text-secondary">प्रातःकाल प्रारंभ</small>
                    </div>
                </div>

                <div class="col-lg-3 col-sm-6 col-12">
                    <div class="p-3 bg-white rounded-3 shadow-sm border h-100">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="bi bi-sunset-fill text-danger fs-5"></i>
                            <span class="small text-muted fw-bold">सूर्यास्त (Sunset)</span>
                        </div>
                        <div class="fs-4 fw-bold text-danger-emphasis"><?= sanitize($panchang['sunset'] ?? '06:33 PM') ?></div>
                        <small class="text-secondary">सायंकाल प्रारंभ</small>
                    </div>
                </div>

                <div class="col-lg-3 col-sm-6 col-12">
                    <div class="p-3 bg-white rounded-3 shadow-sm border h-100">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="bi bi-moon-stars-fill text-primary fs-5"></i>
                            <span class="small text-muted fw-bold">चंद्रोदय (Moonrise)</span>
                        </div>
                        <div class="fs-4 fw-bold text-primary"><?= sanitize($panchang['moonrise'] ?? '02:08 PM') ?></div>
                        <small class="text-secondary">आकाशात चंद्र दर्शन</small>
                    </div>
                </div>

                <div class="col-lg-3 col-sm-6 col-12">
                    <div class="p-3 bg-white rounded-3 shadow-sm border h-100">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="bi bi-moon fs-5 text-secondary"></i>
                            <span class="small text-muted fw-bold">चंद्रास्त (Moonset)</span>
                        </div>
                        <div class="fs-4 fw-bold text-secondary"><?= sanitize($panchang['moonset'] ?? '01:30 AM') ?></div>
                        <small class="text-secondary">चंद्र अस्त वेळ</small>
                    </div>
                </div>
            </div>

            <!-- Rahu Kaal & Auspicious Timings -->
            <div class="row g-3 mt-1">
                <div class="col-md-6 col-12">
                    <div class="p-3 bg-danger bg-opacity-10 rounded-3 border border-danger border-opacity-25">
                        <h5 class="fw-bold text-danger mb-2">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i> राहू काळ (Rahu Kaal)
                        </h5>
                        <div class="fs-4 fw-bold text-danger mb-1">
                            <?= sanitize($panchang['rahu_kaal'] ?? 'उपलब्ध नाही') ?>
                        </div>
                        <small class="text-muted">ज्योतिष व भारतीय परंपरेनुसार शुभ कार्यासाठी टाळला जाणारा दैनंदिन काळ.</small>
                    </div>
                </div>

                <div class="col-md-6 col-12">
                    <div class="p-3 bg-success bg-opacity-10 rounded-3 border border-success border-opacity-25">
                        <h5 class="fw-bold text-success mb-2">
                            <i class="bi bi-stars me-2"></i> सण, उत्सव व व्रते (Festivals & Vrat)
                        </h5>
                        <div class="fs-5 fw-semibold text-dark">
                            <?php 
                            if (!empty($panchang['festivals_list'])) {
                                foreach ($panchang['festivals_list'] as $f) {
                                    echo '<span class="badge bg-success me-1 mb-1">' . sanitize(is_array($f) ? ($f['name_mr'] ?? json_encode($f)) : $f) . '</span> ';
                                }
                            } else {
                                echo '<span class="text-muted">आज कोणताही मोठा सण नोंदवलेला नाही.</span>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Educational Context / What is Panchang -->
    <div class="card shadow-sm border-0" style="border-radius:14px;">
        <div class="card-header bg-white fw-bold fs-5 py-3">
            <i class="bi bi-info-circle text-primary me-2"></i> पंचांग म्हणजे काय? (विद्यार्थ्यांसाठी माहिती)
        </div>
        <div class="card-body p-4">
            <p class="mb-3" style="line-height:1.7;">
                'पंचांग' हा शब्द <strong>पंच + अंग</strong> या दोन शब्दांपासून बनला आहे, ज्याचा अर्थ 'पाच अंगे' असा होतो. भारतीय कालगणनेमध्ये दिवसाचे पाच प्रमुख घटक मोजले जातात:
            </p>
            <div class="row g-3">
                <div class="col-md">
                    <div class="p-3 border rounded bg-light">
                        <strong class="text-primary d-block mb-1">१. तिथी</strong>
                        <small class="text-muted">सूर्य व चंद्रातील १२ अंशांच्या अंतराला एक तिथी म्हणतात. महिन्यात एकूण ३० तिथी असतात.</small>
                    </div>
                </div>
                <div class="col-md">
                    <div class="p-3 border rounded bg-light">
                        <strong class="text-success d-block mb-1">२. वार</strong>
                        <small class="text-muted">सूर्योदयापासून दुसऱ्या सूर्योदयापर्यंतचा काळ म्हणजे वार. आठवड्यात एकूण ७ वार असतात.</small>
                    </div>
                </div>
                <div class="col-md">
                    <div class="p-3 border rounded bg-light">
                        <strong class="text-warning-emphasis d-block mb-1">३. नक्षत्र</strong>
                        <small class="text-muted">चंद्र ज्या तारकापुंजात असतो त्यास नक्षत्र म्हणतात. आकाशात एकूण २७ नक्षत्रे आहेत.</small>
                    </div>
                </div>
                <div class="col-md">
                    <div class="p-3 border rounded bg-light">
                        <strong class="text-info d-block mb-1">४. योग</strong>
                        <small class="text-muted">सूर्य आणि चंद्राच्या भोगांशांची बेरीज करून योग काढला जातो. एकूण २७ योग असतात.</small>
                    </div>
                </div>
                <div class="col-md">
                    <div class="p-3 border rounded bg-light">
                        <strong class="text-danger d-block mb-1">५. करण</strong>
                        <small class="text-muted">एका तिथीच्या अर्ध्या भागाला करण म्हणतात. पंचांगात एकूण ११ करणे असतात.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
