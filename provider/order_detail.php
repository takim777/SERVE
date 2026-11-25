<?php
declare(strict_types=1);

$pageTitle = 'Detail Pesanan Penyedia';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/shared_functions.php';
require_once __DIR__ . '/../includes/business_workflow.php';

requireRole('penyedia');

$pdo = getDBConnection();
$user = currentUser();
$penyediaId = (int) ($user['id'] ?? 0);
$orderId = (int) ($_GET['id'] ?? $_POST['order_id'] ?? 0);

$orderCols = serveTableColumns($pdo, 'pemesanan');
$statusCol = serveFirstMatchingColumn($orderCols, ['status_terkini', 'status']) ?? 'status_terkini';
$descCol = serveFirstMatchingColumn($orderCols, ['deskripsi_kebutuhan', 'deskripsi', 'catatan']);
$dateCol = serveFirstMatchingColumn($orderCols, ['tanggal_pengerjaan', 'tanggal_jadwal', 'tanggal_mulai']);
$addrCol = serveFirstMatchingColumn($orderCols, ['alamat_lokasi', 'alamat', 'lokasi']);
$totalCol = serveFirstMatchingColumn($orderCols, ['total_biaya', 'total_harga', 'total', 'nominal']);
$codeCol = serveFirstMatchingColumn($orderCols, ['kode_pesanan', 'kode']);

$stmt = $pdo->prepare(
    'SELECT p.*, l.judul_layanan, u.nama_lengkap AS nama_pelanggan, u.email AS email_pelanggan
     FROM pemesanan p
     LEFT JOIN layanan l ON p.layanan_id = l.id
     LEFT JOIN users u ON p.pelanggan_id = u.id
     WHERE p.id = :id AND p.penyedia_id = :pid
     LIMIT 1'
);
$stmt->execute([
    'id'  => $orderId,
    'pid' => $penyediaId,
]);
$order = $stmt->fetch();

if (!$order) {
    setFlash('danger', 'Pesanan tidak ditemukan atau bukan milik Anda.');
    redirect('provider/orders.php');
}

$currentStatus = (string) ($order[$statusCol] ?? $order['status_terkini'] ?? $order['status'] ?? 'menunggu_konfirmasi');
$allowedNext = serveAllowedOrderTransitions($currentStatus, 'penyedia');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nextStatus = trim((string) ($_POST['status_baru'] ?? ''));

    if (!in_array($nextStatus, serveOrderStatuses(), true)) {
        setFlash('danger', 'Status pesanan tidak dikenali.');
    } elseif (!serveCanTransitionOrder($currentStatus, $nextStatus, 'penyedia')) {
        setFlash('danger', 'Perpindahan status dari ' . serveOrderStatusLabel($currentStatus) . ' ke ' . serveOrderStatusLabel($nextStatus) . ' tidak diizinkan.');
    } else {
        $upd = $pdo->prepare("UPDATE pemesanan SET {$statusCol} = :st WHERE id = :id AND penyedia_id = :pid");
        $upd->execute([
            'st'  => $nextStatus,
            'id'  => $orderId,
            'pid' => $penyediaId,
        ]);
        setFlash('success', 'Status pesanan berhasil diperbarui menjadi ' . serveOrderStatusLabel($nextStatus) . '.');
        redirect('provider/order_detail.php?id=' . $orderId);
    }
}

$kodePesanan = $codeCol !== null && !empty($order[$codeCol]) ? (string) $order[$codeCol] : ('ORD-' . $order['id']);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<?php renderFlashMessages(); ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 fw-bold mb-0">Detail Pesanan: <?= e($kodePesanan); ?></h1>
    <a href="<?= e(url('provider/orders.php')); ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali ke Pesanan Masuk
    </a>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm p-4">
            <h2 class="h6 fw-bold mb-3">Informasi Pesanan</h2>
            <table class="table table-borderless mb-0">
                <tr>
                    <th class="text-muted ps-0" style="width: 35%;">Kode Pesanan</th>
                    <td class="fw-semibold"><?= e($kodePesanan); ?></td>
                </tr>
                <tr>
                    <th class="text-muted ps-0">Status Saat Ini</th>
                    <td><span class="badge <?= e(serveOrderStatusBadgeClass($currentStatus)); ?>"><?= e(serveOrderStatusLabel($currentStatus)); ?></span></td>
                </tr>
                <tr>
                    <th class="text-muted ps-0">Layanan</th>
                    <td><?= e((string) ($order['judul_layanan'] ?? '-')); ?></td>
                </tr>
                <tr>
                    <th class="text-muted ps-0">Pelanggan</th>
                    <td><?= e((string) ($order['nama_pelanggan'] ?? '-')); ?></td>
                </tr>
                <tr>
                    <th class="text-muted ps-0">Email Pelanggan</th>
                    <td><?= e((string) ($order['email_pelanggan'] ?? '-')); ?></td>
                </tr>
                <?php if ($dateCol !== null && !empty($order[$dateCol])): ?>
                    <tr>
                        <th class="text-muted ps-0">Tanggal Pengerjaan</th>
                        <td><?= e((string) $order[$dateCol]); ?></td>
                    </tr>
                <?php endif; ?>
                <?php if ($addrCol !== null && !empty($order[$addrCol])): ?>
                    <tr>
                        <th class="text-muted ps-0">Lokasi Pengerjaan</th>
                        <td><?= e((string) $order[$addrCol]); ?></td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <th class="text-muted ps-0">Total Biaya</th>
                    <td class="fw-bold text-primary"><?= e(formatRupiah((float) ($totalCol !== null ? ($order[$totalCol] ?? 0) : 0))); ?></td>
                </tr>
                <?php if ($descCol !== null && !empty($order[$descCol])): ?>
                    <tr>
                        <th class="text-muted ps-0">Deskripsi Kebutuhan</th>
                        <td><?= nl2br(e((string) $order[$descCol])); ?></td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm p-4">
            <h2 class="h6 fw-bold mb-3">Perbarui Status Pesanan</h2>
            <?php if (empty($allowedNext)): ?>
                <p class="text-muted small mb-0">Status pesanan sudah final dan tidak dapat diubah lagi.</p>
            <?php else: ?>
                <form method="post">
                    <input type="hidden" name="order_id" value="<?= $orderId; ?>">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Pilih Status Berikutnya</label>
                        <select name="status_baru" class="form-select" required>
                            <option value="">-- Pilih Status --</option>
                            <?php foreach ($allowedNext as $st): ?>
                                <option value="<?= e($st); ?>"><?= e(serveOrderStatusLabel($st)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary fw-semibold">
                        <i class="bi bi-check2-circle me-1"></i>Simpan Status
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>