<?php
/**
 * वार्षिक निकालपत्रके — तुकडी निवडून सत्र १ व सत्र २ दोन्ही एकाच तक्त्यात.
 * संक्षिप्त (view=summary, एकत्र बेरीज) किंवा सविस्तर (view=detailed, सत्रनिहाय आकारिक/संकलित तपशील).
 */
require_once __DIR__ . '/../includes/cce_functions.php';
requireLogin();

$standards = cce_school_standards((int)$_SESSION['school_id']);
$std = (int)($_GET['std'] ?? array_key_first($standards) ?? 1);
$view = ($_GET['view'] ?? 'detailed') === 'summary' ? 'summary' : 'detailed';

$qs = 'std=' . $std . '&print=1';
if ($view === 'summary') {
    // संक्षिप्त: एकत्र बेरीज, एका ओळीत प्रति विद्यार्थी
    header('Location: ' . APP_URL . '/cce/annual.php?' . $qs);
} else {
    // सविस्तर: सत्रनिहाय आकारिक/संकलित तपशील, दोन ओळी प्रति विद्यार्थी
    header('Location: ' . APP_URL . '/cce/annual_register.php?std=' . $std);
}
exit;
