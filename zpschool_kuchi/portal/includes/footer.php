        </div>
    </main>

    <!-- Footer -->
    <footer class="text-light py-5 mt-5" style="background: linear-gradient(180deg, #0B3D2E 0%, #05231A 100%); border-top: 4px solid #E9A23B;">
        <style>
            footer a.foot-link { color: #CBD5E1; text-decoration: none; transition: all 0.2s ease; display: inline-flex; align-items: center; gap: 6px; }
            footer a.foot-link:hover { color: #FBBF24; transform: translateX(3px); }
            footer .foot-desc { color: #BFE3D3; font-size: 0.92rem; line-height: 1.6; }
            footer .foot-contact { color: #E2E8F0; font-size: 0.92rem; }
            footer h5 { color: #FFF !important; font-weight: 700; font-size: 1.05rem; margin-bottom: 1.1rem; letter-spacing: -0.01em; }
        </style>
        <div class="container">
            <div class="row g-4">
                <!-- Col 1: Brand & Mission -->
                <div class="col-lg-4 col-md-6">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <img src="<?= APP_URL ?>/assets/images/logo-icon.svg?v=4" alt="TechGuruji" width="40" height="40" class="rounded-3 shadow-sm">
                        <div>
                            <h4 class="mb-0 fw-bold text-white" style="letter-spacing: -0.01em; color: #FFFFFF !important;">TechGuruji</h4>
                            <small style="color: #FDE047; font-size: 0.78rem; font-weight: 600;">स्मार्ट स्कूल हब</small>
                        </div>
                    </div>
                    <p class="foot-desc">
                        महाराष्ट्रातील प्राथमिक व माध्यमिक शाळांसाठी आधुनिक, परिपूर्ण व सुरक्षित शैक्षणिक SaaS व्यवस्थापन प्रणाली.
                        राष्ट्रीय शिक्षण धोरण २०२० (NEP 2020) व राज्य शैक्षणिक मार्गदर्शक तत्त्वांनुसार CCE आणि HPC स्वयंचलित मूल्यमापन.
                    </p>
                    <div class="d-flex gap-2 pt-1">
                        <span class="badge bg-warning text-dark px-2 py-1"><i class="bi bi-shield-check me-1"></i> UDISE सुरक्षित</span>
                        <span class="badge bg-success px-2 py-1"><i class="bi bi-patch-check me-1"></i> शासकीय निकष</span>
                        <span class="badge bg-primary px-2 py-1"><i class="bi bi-file-earmark-pdf me-1"></i> A4/A5 प्रिंट</span>
                    </div>
                </div>

                <!-- Col 2: Quick Links -->
                <div class="col-lg-2 col-md-6 col-6">
                    <h5>महत्त्वाचे दुवे</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="<?= APP_URL ?>" class="foot-link"><i class="bi bi-house-door"></i> मुख्यपृष्ठ</a></li>
                        <li class="mb-2"><a href="<?= APP_URL ?>/subscription/plans.php" class="foot-link"><i class="bi bi-gem text-warning"></i> सदस्यता योजना</a></li>
                        <li class="mb-2"><a href="<?= APP_URL ?>/auth/register.php" class="foot-link"><i class="bi bi-person-plus text-success"></i> शाळा मोफत नोंदणी</a></li>
                        <li class="mb-2"><a href="<?= APP_URL ?>/auth/login.php" class="foot-link"><i class="bi bi-box-arrow-in-right"></i> शाळा / शिक्षक लॉगिन</a></li>
                        <li class="mb-2"><a href="<?= PLAY_STORE_URL ?>" target="_blank" rel="noopener" class="foot-link text-warning-emphasis"><i class="bi bi-google-play text-warning"></i> Play Store ॲप</a></li>
                        <li class="mb-2"><a href="<?= APP_URL ?>/download_app.php" class="foot-link"><i class="bi bi-download"></i> ॲप डाऊनलोड (APK)</a></li>
                        <li class="mb-2"><a href="<?= APP_URL ?>/modules/paripath/index.php" class="foot-link"><i class="bi bi-sun text-warning"></i> दैनिक परिपाठ</a></li>
                    </ul>
                </div>

                <!-- Col 3: Legal & Gov Rules -->
                <div class="col-lg-3 col-md-6 col-6">
                    <h5>माहिती व वैधानिक</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="<?= APP_URL ?>/pages/view.php?page=gov_rules" class="foot-link text-warning-emphasis"><i class="bi bi-bank"></i> शासकीय निकष व नियम</a></li>
                        <li class="mb-2"><a href="<?= APP_URL ?>/pages/view.php?page=terms" class="foot-link"><i class="bi bi-file-earmark-text"></i> अटी व शर्ती (Terms)</a></li>
                        <li class="mb-2"><a href="<?= APP_URL ?>/pages/view.php?page=privacy" class="foot-link"><i class="bi bi-shield-lock"></i> गोपनीयता धोरण (Privacy)</a></li>
                        <li class="mb-2"><a href="<?= APP_URL ?>/pages/view.php?page=refund" class="foot-link"><i class="bi bi-currency-rupee"></i> परतावा व रद्द धोरण</a></li>
                        <li class="mb-2"><a href="<?= APP_URL ?>/pages/view.php?page=about" class="foot-link"><i class="bi bi-info-circle"></i> आमच्याबद्दल (About)</a></li>
                        <li class="mb-2"><a href="https://parakh.ncert.gov.in/hpc" target="_blank" class="foot-link text-info"><i class="bi bi-box-arrow-up-right"></i> PARAKH NCERT HPC</a></li>
                    </ul>
                </div>

                <!-- Col 4: Contact & Support -->
                <div class="col-lg-3 col-md-6">
                    <h5>संपर्क व साहाय्य</h5>
                    <ul class="list-unstyled">
                        <li class="foot-contact mb-2 d-flex align-items-center gap-2">
                            <i class="bi bi-envelope-fill text-warning"></i> 
                            <a href="mailto:support@smartschoolhub.in" class="foot-link">support@smartschoolhub.in</a>
                        </li>
                        <li class="foot-contact mb-2 d-flex align-items-center gap-2">
                            <i class="bi bi-whatsapp text-success"></i> 
                            <span>+९१-९८२२XXXXXX (WhatsApp)</span>
                        </li>
                        <li class="foot-contact mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-clock-fill text-info"></i> 
                            <small class="text-secondary">सोम–शनि, सकाळी ९:३० ते सायं ६:००</small>
                        </li>
                        <li>
                            <a href="<?= APP_URL ?>/pages/view.php?page=contact" class="btn btn-outline-warning btn-sm w-100 fw-bold">
                                <i class="bi bi-chat-dots-fill me-1"></i> संपर्क केंद्र उघडा
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <hr class="border-secondary my-4 opacity-50">

            <div class="row align-items-center gy-2">
                <div class="col-md-7 text-center text-md-start" style="color: #94A3B8; font-size: 0.85rem;">
                    &copy; <?= date('Y') ?> <strong>स्मार्ट स्कूल हब (Smart School Hub)</strong>. सर्व हक्क राखीव.
                    <span class="d-none d-sm-inline"> | </span>
                    <span class="text-warning-emphasis">NEP 2020, RTE 2009 व महाराष्ट्र राज्य शालेय निकषांनुसार संचलित.</span>
                </div>
                <div class="col-md-5 text-center text-md-end">
                    <span class="badge bg-dark border border-secondary text-secondary small px-3 py-1">
                        <i class="bi bi-lock-fill text-success me-1"></i> SSL Secured • UDISE Isolated Data
                    </span>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= APP_URL ?>/assets/js/app.js?v=2"></script>
    <?php if (file_exists(__DIR__ . '/anti_copy.php')) require_once __DIR__ . '/anti_copy.php'; ?>
    <?php if (file_exists(__DIR__ . '/mobile_bottom_nav.php')) require_once __DIR__ . '/mobile_bottom_nav.php'; ?>
</body>
</html>
