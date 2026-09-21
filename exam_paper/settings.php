<?php
require_once __DIR__ . '/includes/functions.php';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['school_name', 'school_address', 'paper_footer', 'watermark_text'] as $k) {
        ep_save_setting($k, trim($_POST[$k] ?? ''));
    }
    ep_save_setting('watermark_logo', !empty($_POST['watermark_logo']) ? '1' : '0');
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

$epPage = 'settings';
$epTitle = 'School Settings';
require __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-8">
<h3>School Settings</h3>
<p class="text-muted">These details are printed in the header of every question paper.</p>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-success">Settings saved.</div><?php endif; ?>
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
    <label class="form-label">Watermark Text (optional)</label>
    <input class="form-control" name="watermark_text" value="<?= h(ep_setting('watermark_text')) ?>" placeholder="Printed faintly behind the paper">
  </div>
  <div class="mb-3 form-check">
    <input class="form-check-input" type="checkbox" name="watermark_logo" value="1" id="wmLogo" <?= ep_setting('watermark_logo') === '1' ? 'checked' : '' ?>>
    <label class="form-check-label" for="wmLogo">Print the school logo as a faint watermark behind every page</label>
  </div>
  <button class="btn btn-primary"><i class="bi bi-save"></i> Save Settings</button>
</div></form>
</div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
