<?php
$page_title = 'सातत्यपूर्ण मूल्यमापन';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="mb-5">
    <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #0f766e 0%, #1493a1 40%, #13644B 100%); color: white; border-radius: 22px; overflow: hidden;">
        <div class="card-body p-4 p-md-5">
            <div class="row align-items-center g-4">
                <div class="col-lg-7">
                    <div class="small text-uppercase fw-semibold mb-3" style="letter-spacing: 0.18em; opacity: 0.8;">School assessment module</div>
                    <h1 class="display-5 fw-bold mb-3">सातत्यपूर्ण मूल्यमापन</h1>
                    <p class="lead mb-4 opacity-90">विद्यार्थ्यांची प्रगती, गुण, वर्णनात्मक नोंदी, निष्पत्ती आणि वार्षिक रिपोर्ट एका क्लिकवर व्यवस्थापित करा.</p>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="<?= APP_URL ?>/cce/index.php" class="btn btn-light text-primary fw-semibold px-4 py-3"><i class="bi bi-grid"></i> सातत्यपूर्ण मूल्यमापन डॅशबोर्ड</a>
                        <a href="<?= APP_URL ?>/cce/marks_entry.php?std=1&semester=1" class="btn btn-outline-light fw-semibold px-4 py-3"><i class="bi bi-pencil-square"></i> गुण नोंद</a>
                    </div>
                </div>
                <div class="col-lg-5 text-center">
                    <div class="rounded-4 p-4 mx-auto" style="max-width: 360px; background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.18); backdrop-filter: blur(6px);">
                        <div class="display-1 mb-2">📊</div>
                        <div class="fw-bold fs-5 mb-2">शाळेसाठी डिजिटल मूल्यमापन</div>
                        <div class="small opacity-80">गुण, प्रगती, रिपोर्ट आणि PDF — सर्व एकाच ठिकाणी</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-4">
    <div class="text-center mb-4">
        <p class="text-primary fw-bold mb-2">तुमच्या शाळेसाठी आवश्यक साधने</p>
        <h2 class="mb-0">सातत्यपूर्ण मूल्यमापन चे मुख्य वैशिष्ट्ये</h2>
    </div>

    <div class="row g-4">
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 border-0 shadow-sm p-3">
                <div class="fs-1 mb-3">📝</div>
                <h5>FE / SE गुण नोंद</h5>
                <p class="text-muted mb-0">आकारिक आणि संकलित मूल्यमापन गुणांची नोंद, % आणि श्रेणी आपोआप तयार होते.</p>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 border-0 shadow-sm p-3">
                <div class="fs-1 mb-3">💬</div>
                <h5>वर्णनात्मक नोंदी</h5>
                <p class="text-muted mb-0">प्रगती, वर्तन आणि कौशल्याचा तपशील शिक्षक आपल्याच स्वरूपात नोंदवू शकतो.</p>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 border-0 shadow-sm p-3">
                <div class="fs-1 mb-3">🎯</div>
                <h5>अध्ययन निष्पत्ती</h5>
                <p class="text-muted mb-0">शिकण्याच्या पातळ्यांचे मूल्यमापन, निष्पत्ती आणि प्रगतीचा आढावा.</p>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 border-0 shadow-sm p-3">
                <div class="fs-1 mb-3">📄</div>
                <h5>निकालपत्रके</h5>
                <p class="text-muted mb-0">सत्रनिहाय निकाल, प्रगतीपत्रक आणि वर्गनिहाय रिपोर्ट एकाच क्लिकवर.</p>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 border-0 shadow-sm p-3">
                <div class="fs-1 mb-3">🏆</div>
                <h5>वार्षिक निकाल</h5>
                <p class="text-muted mb-0">दोन्ही सत्रांची बेरीज करून वार्षिक सारांश आणि श्रेणी तयार करा.</p>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 border-0 shadow-sm p-3">
                <div class="fs-1 mb-3">🖨️</div>
                <h5>PDF / प्रिंट</h5>
                <p class="text-muted mb-0">प्रगती पत्रक आणि निकालपत्रके PDF म्हणून जतन किंवा प्रिंट करा.</p>
            </div>
        </div>
    </div>
</section>

<section class="py-5">
    <div class="text-center mb-4">
        <p class="text-primary fw-bold mb-2">कसे काम करते?</p>
        <h2 class="mb-0">फक्त 3 सोप्या स्टेप्समध्ये सुरुवात</h2>
    </div>

    <div class="row g-4 text-center">
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm p-4">
                <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mx-auto mb-3" style="width:64px;height:64px;font-size:1.6rem;font-weight:700;">1</div>
                <h5>विषय आणि इयत्ता सेट करा</h5>
                <p class="text-muted mb-0">विद्यार्थी, इयत्ता आणि विषयांची यादी तयार करा.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm p-4">
                <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mx-auto mb-3" style="width:64px;height:64px;font-size:1.6rem;font-weight:700;">2</div>
                <h5>गुण आणि नोंदी भरा</h5>
                <p class="text-muted mb-0">FE / SE गुण, वर्णनात्मक नोंदी आणि निष्पत्ती भरा.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm p-4">
                <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mx-auto mb-3" style="width:64px;height:64px;font-size:1.6rem;font-weight:700;">3</div>
                <h5>रिपोर्ट आणि PDF तयार करा</h5>
                <p class="text-muted mb-0">निकालपत्रक, प्रगतीपत्रक आणि वार्षिक रिपोर्ट डाउनलोड करा.</p>
            </div>
        </div>
    </div>
</section>

<section class="py-4">
    <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #f8fafc 0%, #ecfeff 100%); border-radius: 22px;">
        <div class="card-body p-4 p-md-5 text-center">
            <h3 class="mb-3">आता तुमच्या शाळेसाठी सातत्यपूर्ण मूल्यमापन सुरू करा</h3>
            <p class="text-muted mb-4">विद्यार्थ्यांच्या विकासासाठी एक व्यवस्थित, जलद आणि पारदर्शक मूल्यमापन प्रणाली.</p>
            <div class="d-flex flex-wrap justify-content-center gap-3">
                <a href="<?= APP_URL ?>/cce/index.php" class="btn btn-primary btn-lg px-4"><i class="bi bi-play-fill"></i> सातत्यपूर्ण मूल्यमापन सुरू करा</a>
                <a href="<?= APP_URL ?>/auth/register.php" class="btn btn-outline-primary btn-lg px-4"><i class="bi bi-person-plus"></i> नोंदणी करा</a>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
