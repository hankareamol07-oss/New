<?php
/**
 * सातत्यपूर्ण मूल्यमापन नोंदवही अनुक्रमणिका (Report-02) -> cce/nondvahi.php?view=index ला पुनर्निर्देशित (Redirected)
 * नोंदवहीच्या दुसऱ्या पानावर (Page 2: Index) अधिकृत अनुक्रमणिका आधीपासूनच समाविष्ट असल्याने
 * हे पान थेट nondvahi.php च्या अनुक्रमणिका पानावर घेऊन जाते.
 */
require_once __DIR__ . '/../config/database.php';

$params = $_GET;
$params['view'] = 'index';

$target = APP_URL . '/cce/nondvahi.php?' . http_build_query($params);
header("Location: " . $target);
exit;
