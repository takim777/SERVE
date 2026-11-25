<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
?>
<?php if (isset($_SESSION['user']) && is_array($_SESSION['user'])): ?>
        </main>
    </div>
</div>
<?php else: ?>
</main>
<?php endif; ?>

<footer class="bg-white border-top py-3 mt-auto">
    <div class="container-fluid px-3 px-lg-4 d-flex flex-column flex-sm-row justify-content-between align-items-center small text-muted gap-2">
        <span>&copy; <?= date('Y'); ?> <strong><?= e(APP_NAME); ?></strong> - Service Marketplace &amp; Management System.</span>
        <span>PHP Native &amp; MySQL</span>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(url('assets/js/app.js')); ?>"></script>
</body>
</html>