<?php
declare(strict_types=1);

$pageTitle = 'Dashboard Pelanggan';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/shared_functions.php';

requireRole('pelanggan');

$pdo = getDBConnection();
$user = currentUser();
$pelangganId = (int) ($user['id'] ?? 0);

$categories = $pdo->query('SELECT * FROM kategori_jasa ORDER BY id DESC')->fetchAll();

$stmtActive = $pdo->prepare(
    "SELECT p.*, l.judul_layanan, u.nama_lengkap AS nama_penyedia
     FROM pemesanan p
     LEFT JOIN layanan l ON p.layanan_id = l.id
     LEFT JOIN users u ON p.penyedia_id = u.id
     WHERE p.pelanggan_id = :pid
       AND p.status_terkini NOT IN ('selesai', 'dibatalkan', 'ditolak')
     ORDER BY p.id DESC"
);
$stmtActive->execute(['pid' => $pelangganId]);
$activeOrders = $stmtActive->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<?php renderFlashMessages(); ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h1 class="h4 fw-bold mb-1">Dashboard Pelanggan</h1>
                <p class="text-muted mb-0">
                    Masuk sebagai <strong><?= e((string) ($user['nama_lengkap'] ?? '')); ?></strong>
                    (<?= e((string) ($user['email'] ?? '')); ?>)
                </p>
            </div>
            <span class="badge text-bg-info text-dark px-3 py-2"><?= e(labelRole('pelanggan')); ?></span>
        </div>

        <form method="get" action="<?= e(url('customer/services.php')); ?>" class="row g-2">
            <div class="col-12 col-md-4">
                <select name="kategori" class="form-select">
                    <option value="">Semua Kategori Jasa</option>
                    <?php foreach ($categories as $cat): ?>
                        <?php $labelCat = (string) ($cat['nama_kategori'] ?? $cat['nama'] ?? ('Kategori #' . $cat['id'])); ?>
                        <option value="<?= (int) $cat['id']; ?>"><?= e($labelCat); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-6">
                <input type="text" name="q" class="form-control" placeholder="Cari layanan jasa yang Anda butuhkan...">
            </div>
            <div class="col-12 col-md-2 d-grid">
                <button type="submit" class="btn btn-primary fw-semibold">
                    <i class="bi bi-search me-1"></i>Cari
                </button>
            </div>
        </form>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h5 fw-bold mb-0">Kategori Jasa</h2>
    <a href="<?= e(url('customer/services.php')); ?>" class="btn btn-sm btn-outline-primary">Lihat Semua Layanan</a>
</div>

<div class="row g-3 mb-4">
    <?php if (empty($categories)): ?>
        <div class="col-12">
            <div class="card border-0 shadow-sm p-3 text-muted text-center">Belum ada kategori jasa tersedia.</div>
        </div>
    <?php else: ?>
        <?php foreach ($categories as $cat): ?>
            <?php $labelCat = (string) ($cat['nama_kategori'] ?? $cat['nama'] ?? ('Kategori #' . $cat['id'])); ?>
            <div class="col-6 col-md-3">
                <a href="<?= e(url('customer/services.php?kategori=' . (int) $cat['id'])); ?>" class="card text-decoration-none border-0 shadow-sm text-center p-3 h-100">
                    <div class="fs-4 text-primary mb-2"><i class="bi bi-tag"></i></div>
                    <div class="fw-semibold text-dark"><?= e($labelCat); ?></div>
                </a>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h5 fw-bold mb-0">Pesanan Aktif Berjalan</h2>
    <a href="<?= e(url('customer/orders.php')); ?>" class="btn btn-sm btn-outline-secondary">Riwayat Pemesanan</a>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Kode</th>
                    <th>Layanan</th>
                    <th>Penyedia</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($activeOrders)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada pesanan aktif saat ini.</td></tr>
                <?php else: ?>
                    <?php foreach ($activeOrders as $ord): ?>
                        <tr>
                            <td class="fw-semibold"><?= e((string) ($ord['kode_pesanan'] ?: ('ORD-' . $ord['id']))); ?></td>
                            <td><?= e((string) ($ord['judul_layanan'] ?? '-')); ?></td>
                            <td><?= e((string) ($ord['nama_penyedia'] ?? '-')); ?></td>
                            <td class="fw-semibold"><?= e(formatRupiah((float) $ord['total_biaya'])); ?></td>
                            <td><span class="badge bg-warning text-dark"><?= e((string) $ord['status_terkini']); ?></span></td>
                            <td class="text-end">
                                <a href="<?= e(url('customer/order_detail.php?id=' . (int) $ord['id'])); ?>" class="btn btn-sm btn-outline-primary">Detail</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>