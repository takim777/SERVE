<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_guard.php';

$sideUser = currentUser();
$role = $sideUser['role'] ?? null;
$currentScript = basename($_SERVER['PHP_SELF'] ?? '');

$panelSubtitle = match ($role) {
    'admin'     => 'Panel Admin',
    'penyedia'  => 'Panel Penyedia Jasa',
    'pelanggan' => 'Panel Pelanggan',
    default     => 'Panel ' . APP_NAME,
};

$menuItems = match ($role) {
    'admin' => [
        ['label' => 'Dashboard', 'path' => 'admin/dashboard.php', 'icon' => 'bi-speedometer2'],
        ['label' => 'Laporan PDF', 'path' => 'reports/index.php', 'icon' => 'bi-file-earmark-pdf'],
    ],
    'penyedia' => [
        ['label' => 'Dashboard', 'path' => 'provider/dashboard.php', 'icon' => 'bi-speedometer2'],
        ['label' => 'Pesanan Masuk', 'path' => 'provider/orders.php', 'icon' => 'bi-inbox'],
        ['label' => 'Kelola Layanan', 'path' => 'provider/services.php', 'icon' => 'bi-briefcase'],
        ['label' => 'Portofolio', 'path' => 'provider/portfolios.php', 'icon' => 'bi-images'],
        ['label' => 'Profil Penyedia', 'path' => 'provider/profile.php', 'icon' => 'bi-person-gear'],
    ],
    'pelanggan' => [
        ['label' => 'Dashboard', 'path' => 'customer/dashboard.php', 'icon' => 'bi-speedometer2'],
        ['label' => 'Cari Jasa', 'path' => 'customer/services.php', 'icon' => 'bi-search'],
        ['label' => 'Riwayat Pemesanan', 'path' => 'customer/orders.php', 'icon' => 'bi-bag-check'],
        ['label' => 'Profil Saya', 'path' => 'customer/profile.php', 'icon' => 'bi-person-circle'],
    ],
    default => [
        ['label' => 'Beranda', 'path' => 'index.php', 'icon' => 'bi-house'],
    ],
};
?>
<?php if ($sideUser !== null): ?>
<div class="container-fluid">
    <div class="row">
        <aside class="col-lg-2 d-none d-lg-block bg-dark text-white min-vh-100 p-3">
            <a href="<?= e(url(dashboardUrlByRole((string) $role))); ?>" class="d-flex align-items-center gap-2 text-decoration-none text-white pb-3 mb-3 border-bottom border-secondary">
                <span class="d-inline-flex align-items-center justify-content-center rounded-2 bg-primary text-white" style="width: 36px; height: 36px;">
                    <i class="bi bi-layers-half fs-5"></i>
                </span>
                <span class="lh-sm">
                    <span class="d-block fw-bold"><?= e(APP_NAME); ?></span>
                    <span class="d-block small text-secondary"><?= e($panelSubtitle); ?></span>
                </span>
            </a>

            <div class="d-flex flex-column gap-1">
                <?php foreach ($menuItems as $item): ?>
                    <?php $isActive = ($currentScript === basename($item['path'])); ?>
                    <a href="<?= e(url($item['path'])); ?>" class="nav-link d-flex align-items-center px-3 py-2 rounded-3 <?= $isActive ? 'bg-primary text-white fw-semibold' : 'text-light link-light'; ?>">
                        <i class="bi <?= e($item['icon']); ?> me-2"></i>
                        <span><?= e($item['label']); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </aside>

        <div class="offcanvas offcanvas-start bg-dark text-white d-lg-none" tabindex="-1" id="serveSidebarOffcanvas" aria-labelledby="serveSidebarOffcanvasLabel">
            <div class="offcanvas-header border-bottom border-secondary">
                <h5 class="offcanvas-title fw-bold" id="serveSidebarOffcanvasLabel"><?= e(APP_NAME); ?> — <?= e($panelSubtitle); ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
            </div>
            <div class="offcanvas-body p-3">
                <div class="d-flex flex-column gap-1">
                    <?php foreach ($menuItems as $item): ?>
                        <?php $isActive = ($currentScript === basename($item['path'])); ?>
                        <a href="<?= e(url($item['path'])); ?>" class="nav-link d-flex align-items-center px-3 py-2 rounded-3 <?= $isActive ? 'bg-primary text-white fw-semibold' : 'text-light link-light'; ?>">
                            <i class="bi <?= e($item['icon']); ?> me-2"></i>
                            <span><?= e($item['label']); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <main class="col-lg-10 bg-light p-3 p-md-4">
<?php else: ?>
<main class="container py-4">
<?php endif; ?>