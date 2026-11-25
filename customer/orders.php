<?php
declare(strict_types=1);

$pageTitle = 'Riwayat Pemesanan';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/shared_functions.php';

requireRole('pelanggan');

$pdo = getDBConnection();
$user = currentUser();
$pelangganId = (int) ($user['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT p.*, l.judul_layanan, u.nama_lengkap AS nama_penyedia
     FROM pemesanan p
     LEFT JOIN layanan l ON p.layanan_id = l.id
     LEFT JOIN users u ON p.penyedia_id = u.id
     WHERE p.pelanggan_id = :pid
     ORDER BY p.id DESC'
);
$stmt->execute(['pid' => $pelangganId]);
$orders = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<?php renderFlashMessages(); ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 fw-bold mb-0">Riwayat Pemesanan</h1>
    <a href="<?= e(url('customer/services.php')); ?>" class="btn btn-sm btn-primary">
        <i class="bi bi-plus-circle me-1"></i>Pesan Jasa Baru
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Kode</th>
                    <th>Layanan</th>
                    <th>Penyedia</th>
                    <th>Tanggal Pengerjaan</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">Belum ada pemesanan.</td></tr>
                <?php else: ?>
                    <?php foreach ($orders as $o): ?>
                        <tr>
                            <td class="fw-semibold"><?= e((string) ($o['kode_pesanan'] ?: ('ORD-' . $o['id']))); ?></td>
                            <td><?= e((string) ($o['judul_layanan'] ?? '-')); ?></td>
                            <td><?= e((string) ($o['nama_penyedia'] ?? '-')); ?></td>
                            <td><?= e((string) ($o['tanggal_pengerjaan'] ?? '-')); ?></td>
                            <td class="fw-semibold"><?= e(formatRupiah((float) ($o['total_biaya'] ?? 0))); ?></td>
                            <td><span class="badge bg-info text-dark"><?= e((string) ($o['status_terkini'] ?? '-')); ?></span></td>
                            <td class="text-end">
                                <a href="<?= e(url('customer/order_detail.php?id=' . (int) $o['id'])); ?>" class="btn btn-sm btn-primary">Lihat Detail</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>