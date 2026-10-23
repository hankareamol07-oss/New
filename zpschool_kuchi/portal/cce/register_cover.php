<?php
/**
 * सातत्यपूर्ण मूल्यमापन नोंदवही मुखपृष्ठ (Report-01) -> cce/nondvahi.php?view=cover ला पुनर्निर्देशित (Redirected)
 * नोंदवहीच्या पहिल्या पानावर (Page 1: Cover) अधिकृत मुखपृष्ठ आधीपासूनच समाविष्ट असल्याने
 * हे पान थेट nondvahi.php च्या कव्हर पानावर घेऊन जाते.
 */
require_once __DIR__ . '/../config/database.php';

$params = $_GET;
$params['view'] = 'cover';

$target = APP_URL . '/cce/nondvahi.php?' . http_build_query($params);
header("Location: " . $target);
exit;
