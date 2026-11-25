<?php
declare(strict_types=1);

$pageTitle = 'Beranda';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
require_once __DIR__ . '/includes/sidebar.php';

$pdo = getDBConnection();
$dbVersion = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
?>

<?php renderFlashMessages(); ?>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <h1 class="h4 fw-bold mb-2"><?= e(APP_FULL_NAME); ?></h1>
        <p class="text-muted mb-3">
            Struktur folder, koneksi database PDO, sistem helper, komponen layout, pengamanan sesi/role, dan flash message telah siap.
        </p>
        <div class="small text-secondary">
            Status Database: <strong class="text-success">Terhubung</strong> (MySQL <?= e($dbVersion); ?>)
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>