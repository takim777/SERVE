<?php
declare(strict_types=1);

$pageTitle = 'Dashboard Penyedia Jasa';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/shared_functions.php';

requireRole('penyedia');

$pdo = getDBConnection();
$user = currentUser();
$penyediaId = (int) ($user['id'] ?? 0);

// Statistik khusus pesanan yang ditujukan kepada penyedia ini
$stTotal = $pdo->prepare('SELECT COUNT(*) FROM pemesanan WHERE penyedia_id = :pid');
$stTotal->execute(['pid' => $penyediaId]);
$totalJobs = (int) $stTotal->fetchColumn();

$stPending = $pdo->prepare("SELECT COUNT(*) FROM pemesanan WHERE penyedia_id = :pid AND status_terkini = 'menunggu_konfirmasi'");
$stPending->execute(['pid' => $penyediaId]);
$pendingJobs = (int) $stPending->fetchColumn();

$stActive = $pdo->prepare("SELECT COUNT(*) FROM pemesanan WHERE penyedia_id = :pid AND status_terkini IN ('diterima', 'diproses')");
$stActive->execute(['pid' => $penyediaId]);
$activeJobs = (int) $stActive->fetchColumn();

$stDone = $pdo->prepare("SELECT COUNT(*) FROM pemesanan WHERE penyedia_id = :pid AND status_terkini = 'selesai'");
$stDone->execute(['pid' => $penyediaId]);
$doneJobs = (int) $stDone->fetchColumn();

// Pesanan terbaru milik penyedia ini
$stmtRecent = $pdo->prepare(
    'SELECT p.*, l.judul_layanan, u.nama_lengkap AS nama_pelanggan
     FROM pemesanan p
     LEFT JOIN layanan l ON p.layanan_id = l.id
     LEFT JOIN users u ON p.pelanggan_id = u.id
     WHERE p.penyedia_id = :pid
     ORDER BY p.id DESC
     LIMIT 5'
);
$stmtRecent->execute(['pid' => $penyediaId]);
$recentOrders = $stmtRecent->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<?php renderFlashMessages(); ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h1 class="h4 fw-bold mb-1">Dashboard Penyedia Jasa</h1>
                <p class="text-muted mb-0">
                    Masuk sebagai <strong><?= e((string) ($user['nama_lengkap'] ?? '')); ?></strong>
                    (<?= e((string) ($user['email'] ?? '')); ?>)
                </p>
            </div>
            <span class="badge text-bg-success px-3 py-2"><?= e(labelRole('penyedia')); ?></span>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= e(url('provider/orders.php')); ?>" class="btn btn-sm btn-primary">
                <i class="bi bi-inbox me-1"></i>Pesanan Masuk
            </a>
            <a href="<?= e(url('provider/services.php')); ?>" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-briefcase me-1"></i>Kelola Layanan
            </a>
            <a href="<?= e(url('provider/portfolios.php')); ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-images me-1"></i>Portofolio
            </a>
            <a href="<?= e(url('provider/profile.php')); ?>" class="btn btn-sm btn-outline-dark">
                <i class="bi bi-person-gear me-1"></i>Profil Penyedia
            </a>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Total Pesanan</div>
                <div class="h3 fw-bold mb-0 mt-1"><?= $totalJobs; ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Menunggu Konfirmasi</div>
                <div class="h3 fw-bold text-warning mb-0 mt-1"><?= $pendingJobs; ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Sedang Dikerjakan</div>
                <div class="h3 fw-bold text-primary mb-0 mt-1"><?= $activeJobs; ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Pekerjaan Selesai</div>
                <div class="h3 fw-bold text-success mb-0 mt-1"><?= $doneJobs; ?></div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h5 fw-bold mb-0">Pesanan Masuk Terbaru</h2>
    <a href="<?= e(url('provider/orders.php')); ?>" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Kode</th>
                    <th>Pelanggan</th>
                    <th>Layanan</th>
                    <th>Tanggal Pengerjaan</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentOrders)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">Belum ada pesanan masuk.</td></tr>
                <?php else: ?>
                    <?php foreach ($recentOrders as $ro): ?>
                        <tr>
                            <td class="fw-semibold"><?= e((string) ($ro['kode_pesanan'] ?: ('ORD-' . $ro['id']))); ?></td>
                            <td><?= e((string) ($ro['nama_pelanggan'] ?? '-')); ?></td>
                            <td><?= e((string) ($ro['judul_layanan'] ?? '-')); ?></td>
                            <td><?= e((string) ($ro['tanggal_pengerjaan'] ?? '-')); ?></td>
                            <td class="fw-semibold"><?= e(formatRupiah((float) $ro['total_biaya'])); ?></td>
                            <td><span class="badge bg-info text-dark"><?= e((string) $ro['status_terkini']); ?></span></td>
                            <td class="text-end">
                                <a href="<?= e(url('provider/order_detail.php?id=' . (int) $ro['id'])); ?>" class="btn btn-sm btn-outline-primary">Kelola</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>