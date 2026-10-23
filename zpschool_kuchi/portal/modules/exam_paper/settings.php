<?php
require_once __DIR__ . '/includes/functions.php';

$msg = '';
$sync_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'sync_hub') {
        if (ep_sync_from_hub()) {
            header('Location: settings.php?synced=1');
            exit;
        } else {
            $msg = 'हब प्रोफाईल माहिती सापडली नाही.';
        }
    } else {
        foreach (['school_name', 'school_address', 'paper_footer', 'watermark_text'] as $k) {
            ep_save_setting($k, trim($_POST[$k] ?? ''));
        }
        ep_save_setting('public_base_url', rtrim(trim($_POST['public_base_url'] ?? ''), '/'));
        ep_save_setting('watermark_logo', !empty($_POST['watermark_logo']) ? '1' : '0');
        ep_save_setting('default_paper_format', ($_POST['default_paper_format'] ?? '') === 'standard' ? 'standard' : 'lines');
        $validStyles = ['board', 'center', 'modern', 'compact'];
        $headerStyle = in_array($_POST['header_style'] ?? '', $validStyles, true) ? $_POST['header_style'] : 'board';
        ep_save_setting('header_style', $headerStyle);
        if (!empty($_FILES['school_logo']['tmp_name']) && is_uploaded_file($_FILES['school_logo']['tmp_name'])) {
            $info = @getimagesize($_FILES['school_logo']['tmp_name']);
            $allowed = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
            if ($info && isset($allowed[$info[2]])) {
                if (!is_dir(EP_UPLOAD_DIR)) {
                    mkdir(EP_UPLOAD_DIR, 0775, true);
                }
                $name = 'logo_' . time() . '.' . $allowed[$info[2]];
                move_uploaded_file($_FILES['school_logo']['tmp_name'], EP_UPLOAD_DIR . '/' . $name);
                $old = ep_setting('school_logo');
                if ($old && is_file(EP_UPLOAD_DIR . '/' . $old)) {
                    @unlink(EP_UPLOAD_DIR . '/' . $old);
                }
                ep_save_setting('school_logo', $name);
            } else {
                $msg = 'Logo must be a PNG, JPG, GIF or WEBP image.';
            }
        }
        if (!empty($_POST['remove_logo'])) {
            $old = ep_setting('school_logo');
            if ($old && is_file(EP_UPLOAD_DIR . '/' . $old)) {
                @unlink(EP_UPLOAD_DIR . '/' . $old);
            }
            ep_save_setting('school_logo', '');
        }
        header('Location: settings.php?saved=1' . ($msg ? '&err=' . urlencode($msg) : ''));
        exit;
    }
}

$epPage = 'settings';
$epTitle = 'शाळा व प्रश्नपत्रिका सेटिंग्ज';
$hubSchool = ep_get_hub_school();
require __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-9">
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h3 class="mb-1"><i class="bi bi-gear-wide-connected text-primary"></i> शाळा व प्रश्नपत्रिका सेटिंग्ज</h3>
    <p class="text-muted mb-0">ही माहिती प्रत्येक तयार केलेल्या प्रश्नपत्रिकेच्या शीर्षकावर (Header) आपोआप छापली जाते.</p>
  </div>
  <?php if ($hubSchool): ?>
    <form method="post">
      <button type="submit" name="action" value="sync_hub" class="btn btn-primary d-flex align-items-center gap-1 shadow-sm">
        <i class="bi bi-arrow-repeat"></i> हब प्रोफाईलवरून माहिती रिफ्रेश करा
      </button>
    </form>
  <?php endif; ?>
</div>

<?php if ($hubSchool): ?>
  <div class="card border-primary border-opacity-25 bg-primary bg-opacity-10 mb-4 shadow-sm">
    <div class="card-body p-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
      <div class="d-flex align-items-center gap-3">
        <?php $hubLogo = ep_logo_url(); ?>
        <?php if ($hubLogo): ?>
          <img src="<?= h($hubLogo) ?>" alt="Hub Logo" style="height: 50px;" class="rounded border p-1 bg-white">
        <?php else: ?>
          <i class="bi bi-building fs-1 text-primary"></i>
        <?php endif; ?>
        <div>
          <span class="badge bg-success mb-1"><i class="bi bi-check2-circle"></i> स्मार्ट स्कूल हब कनेक्टेड</span>
          <h5 class="mb-0 text-dark"><?= h($hubSchool['name_mr'] ?: $hubSchool['name']) ?></h5>
          <small class="text-muted"><?= h($hubSchool['formatted_address']) ?></small>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php if (isset($_GET['saved'])): ?><div class="alert alert-success"><i class="bi bi-check-circle me-1"></i> सेटिंग्ज यशस्वीरित्या सेव्ह केल्या!</div><?php endif; ?>
<?php if (isset($_GET['synced'])): ?><div class="alert alert-success"><i class="bi bi-check-circle me-1"></i> स्मार्ट स्कूल हबवरून शाळेची माहिती यशस्वीरित्या सिंक झाली!</div><?php endif; ?>
<?php if (isset($_GET['err'])): ?><div class="alert alert-danger"><?= h($_GET['err']) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="card shadow-sm"><div class="card-body">
  <div class="mb-3">
    <label class="form-label">School / Institute Name *</label>
    <input class="form-control" name="school_name" required value="<?= h(ep_setting('school_name')) ?>">
  </div>
  <div class="mb-3">
    <label class="form-label">Address / Tagline</label>
    <input class="form-control" name="school_address" value="<?= h(ep_setting('school_address')) ?>" placeholder="e.g. Near Bus Stand, Pune - 411001 | Ph. 98xxxxxxxx">
  </div>
  <div class="mb-3">
    <label class="form-label">School Logo</label>
    <div class="d-flex align-items-center gap-3">
      <?php if (ep_setting('school_logo')): ?>
        <img src="uploads/<?= h(ep_setting('school_logo')) ?>" alt="logo" style="height:70px" class="border rounded p-1 bg-white">
        <div class="form-check"><input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="rm"><label class="form-check-label" for="rm">Remove</label></div>
      <?php endif; ?>
      <input class="form-control" type="file" name="school_logo" accept="image/*">
    </div>
  </div>
  <div class="mb-3">
    <label class="form-label">Paper Footer Text</label>
    <input class="form-control" name="paper_footer" value="<?= h(ep_setting('paper_footer')) ?>">
  </div>
  <div class="mb-3">
    <label class="form-label">संकलित / आकारिक paper format (for papers saved before this option existed)</label>
    <select class="form-select" name="default_paper_format">
      <option value="lines" <?= ep_setting('default_paper_format', 'lines') === 'lines' ? 'selected' : '' ?>>उत्तर-लेखन ओळींसह — answer lines &amp; writing space under every question</option>
      <option value="standard" <?= ep_setting('default_paper_format', 'lines') === 'standard' ? 'selected' : '' ?>>Compact — questions only</option>
    </select>
  </div>
  <div class="mb-3">
    <label class="form-label">डीफॉल्ट शाळा हेडर ब्रँडिंग स्टाईल (Default School Header Branding Style)</label>
    <select class="form-select" name="header_style">
      <option value="board" <?= ep_setting('header_style', 'board') === 'board' ? 'selected' : '' ?>>1. Board Official (अधिकृत बोर्ड परीक्षा - Seat No, Center Code बॉक्स ग्रिडसह)</option>
      <option value="center" <?= ep_setting('header_style', 'board') === 'center' ? 'selected' : '' ?>>2. Center Heritage (मध्यभागी ठळक लोगो व दुहेरी बॉर्डर)</option>
      <option value="modern" <?= ep_setting('header_style', 'board') === 'modern' ? 'selected' : '' ?>>3. Modern Minimal (दोन-कॉलम स्वच्छ आधुनिक कार्ड)</option>
      <option value="compact" <?= ep_setting('header_style', 'board') === 'compact' ? 'selected' : '' ?>>4. Ultra Compact (जागा वाचवणारा १-ओळीचा हेडर)</option>
    </select>
    <div class="form-text">प्रश्नपत्रिका प्रिंट करतानाही तुम्ही टूलबारमधून स्टाईल बदलू शकता.</div>
  </div>
  <div class="mb-3">
    <label class="form-label">Watermark Text (optional)</label>
    <input class="form-control" name="watermark_text" value="<?= h(ep_setting('watermark_text')) ?>" placeholder="Printed faintly behind the paper">
  </div>
  <div class="mb-3">
    <label class="form-label">Public URL of this module (for quiz links / QR codes)</label>
    <input class="form-control" name="public_base_url" value="<?= h(ep_setting('public_base_url')) ?>" placeholder="https://www.yourschool.in/exam_paper">
    <div class="form-text">Address students' phones can reach. Leave blank to use this server's own address.</div>
  </div>
  <div class="mb-3 form-check">
    <input class="form-check-input" type="checkbox" name="watermark_logo" value="1" id="wmLogo" <?= ep_setting('watermark_logo') === '1' ? 'checked' : '' ?>>
    <label class="form-check-label" for="wmLogo">Print the school logo as a faint watermark behind every page</label>
  </div>
  <button class="btn btn-primary"><i class="bi bi-save"></i> Save Settings</button>
</div></form>

<!-- Hindi Import Tool -->
<div class="card border-danger border-opacity-25 mt-4">
  <div class="card-header fw-semibold bg-danger bg-opacity-10 text-danger">
    <i class="bi bi-translate me-2"></i>हिंदी डेटा अपडेट (Hindi Data Update)
  </div>
  <div class="card-body">
    <p class="mb-2 small text-muted">
      <strong>हिंदी सुलभभारती (Std&nbsp;5-8)</strong> textbook bank, bridge subjects &amp; templates
      for the <b>Create Paper</b> &amp; <b>Assessment Builder</b> dropdowns.
      Re-import after updating <code>hindi_update.zip</code>.
    </p>
    <a href="import_hindi_update.php" class="btn btn-outline-danger btn-sm">
      <i class="bi bi-cloud-upload me-1"></i> Import / Update Hindi Data
    </a>
  </div>
</div>
</div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
