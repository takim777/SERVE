<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_guard.php';

$navUser = currentUser();
$navRole = (string) ($navUser['role'] ?? '');
$navName = (string) ($navUser['nama_lengkap'] ?? 'Pengguna');
$navEmail = (string) ($navUser['email'] ?? '');
$navInitial = strtoupper(mb_substr($navName, 0, 1) ?: 'U');

$profilePath = match ($navRole) {
    'pelanggan' => 'customer/profile.php',
    'penyedia'  => 'provider/profile.php',
    'admin'     => 'admin/profile.php',
    default     => 'index.php',
};
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm border-bottom border-secondary-subtle">
    <div class="container-fluid px-3 px-lg-4">
        <?php if ($navUser !== null): ?>
            <button class="btn btn-outline-light btn-sm d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#serveSidebarOffcanvas" aria-controls="serveSidebarOffcanvas" aria-label="Buka Menu">
                <i class="bi bi-list fs-5"></i>
            </button>
        <?php endif; ?>

        <a class="navbar-brand fw-bold d-flex align-items-center gap-2 <?= $navUser !== null ? 'd-lg-none' : ''; ?>" href="<?= e(url('index.php')); ?>">
            <i class="bi bi-layers-half text-primary"></i>
            <span><?= e(APP_NAME); ?></span>
        </a>

        <button class="navbar-toggler ms-auto" type="button" data-bs-toggle="collapse" data-bs-target="#serveTopNavbar" aria-controls="serveTopNavbar" aria-expanded="false" aria-label="Toggle navigasi">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="serveTopNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="<?= e(url('index.php')); ?>">Beranda</a>
                </li>
            </ul>

            <div class="d-flex align-items-center gap-2">
                <?php if ($navUser !== null): ?>
                    <div class="dropdown">
                        <button class="btn btn-dark border border-secondary d-flex align-items-center gap-2 px-2 py-1 rounded-3 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="d-inline-flex align-items-center justify-content-center rounded-2 bg-primary text-white fw-bold" style="width: 32px; height: 32px;">
                                <?= e($navInitial); ?>
                            </span>
                            <span class="text-start d-none d-sm-inline-block lh-1 pe-1">
                                <span class="d-block small fw-semibold text-white"><?= e($navName); ?></span>
                                <?php if ($navEmail !== ''): ?>
                                    <span class="d-block text-light-emphasis" style="font-size: 0.72rem;"><?= e($navEmail); ?></span>
                                <?php endif; ?>
                            </span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm mt-1">
                            <li>
                                <a class="dropdown-item" href="<?= e(url(dashboardUrlByRole($navRole))); ?>">
                                    <i class="bi bi-speedometer2 me-2"></i>Dashboard
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?= e(url($profilePath)); ?>">
                                    <i class="bi bi-person-gear me-2"></i>Profil Saya
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="<?= e(url('auth/logout.php')); ?>">
                                    <i class="bi bi-box-arrow-right me-2"></i>Keluar
                                </a>
                            </li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a class="btn btn-sm btn-outline-light" href="<?= e(url('auth/login.php')); ?>">Masuk</a>
                    <a class="btn btn-sm btn-primary" href="<?= e(url('auth/register.php')); ?>">Daftar</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>