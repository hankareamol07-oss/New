</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if (!empty($epScripts)) foreach ($epScripts as $s): ?>
<script src="<?= EP_BASE_URL ?>/assets/<?= h($s) ?>"></script>
<?php endforeach; ?>
</body>
</html>
