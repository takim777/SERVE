<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/shared_functions.php';

requireRole('penyedia');

function providerDb(): PDO
{
    return getDBConnection();
}

function renderProviderHeader(string $pageTitle, string $activeNav = ''): void
{
    $user = currentUser();
    $userName = (string) ($user['nama_lengkap'] ?? 'Penyedia Jasa');
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle); ?> | SERVE Mitra Penyedia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(url('assets/css/style.css')); ?>" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold text-warning" href="<?= e(url('provider/dashboard.php')); ?>"><i class="bi bi-briefcase me-2"></i>SERVE Mitra</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navProvider">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navProvider">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link <?= $activeNav === 'dashboard' ? 'active' : ''; ?>" href="<?= e(url('provider/dashboard.php')); ?>">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link <?= $activeNav === 'orders' ? 'active' : ''; ?>" href="<?= e(url('provider/orders.php')); ?>">Pesanan Masuk</a></li>
                <li class="nav-item"><a class="nav-link <?= $activeNav === 'services' ? 'active' : ''; ?>" href="<?= e(url('provider/services.php')); ?>">Kelola Layanan</a></li>
                <li class="nav-item"><a class="nav-link <?= $activeNav === 'portfolios' ? 'active' : ''; ?>" href="<?= e(url('provider/portfolios.php')); ?>">Portofolio</a></li>
            </ul>
            <div class="d-flex align-items-center gap-3">
                <span class="text-white small">Mitra: <strong><?= e($userName); ?></strong></span>
                <a href="<?= e(url('provider/profile.php')); ?>" class="btn btn-sm btn-outline-warning"><i class="bi bi-person"></i> Profil</a>
                <a href="<?= e(url('auth/logout.php')); ?>" class="btn btn-sm btn-danger"><i class="bi bi-box-arrow-right"></i> Keluar</a>
            </div>
        </div>
    </div>
</nav>
<main class="container pb-5">
    <?php renderFlashMessages(); ?>
    <?php
}

function renderProviderFooter(): void
{
    ?>
</main>
<footer class="bg-dark text-white-50 py-3 mt-auto">
    <div class="container text-center small">
        &copy; <?= date('Y'); ?> SERVE — Panel Mitra Penyedia Jasa
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
    <?php
}