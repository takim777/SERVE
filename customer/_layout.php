<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/shared_functions.php';

requireRole('pelanggan');

function customerDb(): PDO
{
    return getDBConnection();
}

function renderCustomerHeader(string $pageTitle, string $activeNav = ''): void
{
    $user = currentUser();
    $userName = (string) ($user['nama_lengkap'] ?? 'Pelanggan');
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle); ?> | SERVE Pelanggan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(url('assets/css/style.css')); ?>" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?= e(url('customer/dashboard.php')); ?>"><i class="bi bi-layers-half me-2"></i>SERVE</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navCustomer">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navCustomer">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link <?= $activeNav === 'dashboard' ? 'active' : ''; ?>" href="<?= e(url('customer/dashboard.php')); ?>">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link <?= $activeNav === 'services' ? 'active' : ''; ?>" href="<?= e(url('customer/services.php')); ?>">Cari Jasa</a></li>
                <li class="nav-item"><a class="nav-link <?= $activeNav === 'orders' ? 'active' : ''; ?>" href="<?= e(url('customer/orders.php')); ?>">Pesanan Saya</a></li>
            </ul>
            <div class="d-flex align-items-center gap-3">
                <span class="text-white small">Halo, <strong><?= e($userName); ?></strong></span>
                <a href="<?= e(url('customer/profile.php')); ?>" class="btn btn-sm btn-outline-light"><i class="bi bi-person-circle"></i> Profil</a>
                <a href="<?= e(url('auth/logout.php')); ?>" class="btn btn-sm btn-light text-primary fw-semibold"><i class="bi bi-box-arrow-right"></i> Keluar</a>
            </div>
        </div>
    </div>
</nav>
<main class="container pb-5">
    <?php renderFlashMessages(); ?>
    <?php
}

function renderCustomerFooter(): void
{
    ?>
</main>
<footer class="bg-white border-top py-3 mt-auto">
    <div class="container text-center text-muted small">
        &copy; <?= date('Y'); ?> SERVE — Platform Layanan Jasa
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
    <?php
}