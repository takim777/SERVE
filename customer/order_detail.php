<?php
declare(strict_types=1);

$pageTitle = 'Detail Pesanan';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/shared_functions.php';
require_once __DIR__ . '/../includes/business_workflow.php';

requireRole('pelanggan');

$pdo = getDBConnection();
$user = currentUser();
$pelangganId = (int) ($user['id'] ?? $user['user_id'] ?? $user['id_user'] ?? 0);
$orderId = (int) ($_GET['id'] ?? $_POST['order_id'] ?? 0);

$orderCols = serveTableColumns($pdo, 'pemesanan');
$orderStatusCol = serveFirstMatchingColumn($orderCols, ['status_terkini', 'status']) ?? 'status_terkini';
$orderDescCol = serveFirstMatchingColumn($orderCols, ['deskripsi_kebutuhan', 'deskripsi', 'catatan']);
$orderDateCol = serveFirstMatchingColumn($orderCols, ['tanggal_pengerjaan', 'tanggal_jadwal', 'tanggal_mulai']);
$orderAddrCol = serveFirstMatchingColumn($orderCols, ['alamat_lokasi', 'alamat', 'lokasi']);
$orderTotalCol = serveFirstMatchingColumn($orderCols, ['total_biaya', 'total_harga', 'total', 'nominal']);
$orderCodeCol = serveFirstMatchingColumn($orderCols, ['kode_pesanan', 'kode']);

if ($pelangganId > 0) {
    $stmt = $pdo->prepare(
        'SELECT p.*, l.judul_layanan, u.nama_lengkap AS nama_penyedia, u.email AS email_penyedia
         FROM pemesanan p
         LEFT JOIN layanan l ON p.layanan_id = l.id
         LEFT JOIN users u ON p.penyedia_id = u.id
         WHERE p.id = :id AND p.pelanggan_id = :pelanggan_id
         LIMIT 1'
    );
    $stmt->execute([
        'id'           => $orderId,
        'pelanggan_id' => $pelangganId,
    ]);
} else {
    $stmt = $pdo->prepare(
        'SELECT p.*, l.judul_layanan, u.nama_lengkap AS nama_penyedia, u.email AS email_penyedia
         FROM pemesanan p
         LEFT JOIN layanan l ON p.layanan_id = l.id
         LEFT JOIN users u ON p.penyedia_id = u.id
         WHERE p.id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $orderId]);
}
$order = $stmt->fetch();

if (!$order) {
    setFlash('danger', 'Pesanan tidak ditemukan.');
    redirect('customer/orders.php');
}

if ($pelangganId <= 0) {
    $pelangganId = (int) ($order['pelanggan_id'] ?? 0);
}

$currentStatus = strtolower(trim((string) ($order[$orderStatusCol] ?? $order['status_terkini'] ?? $order['status'] ?? 'menunggu_konfirmasi')));
$totalBiaya = $orderTotalCol !== null ? (float) ($order[$orderTotalCol] ?? 0) : 0.0;
$kodePesanan = $orderCodeCol !== null && !empty($order[$orderCodeCol]) ? (string) $order[$orderCodeCol] : ('ORD-' . $orderId);

// Deteksi kolom tabel pembayaran
$payCols = serveTableColumns($pdo, 'pembayaran');
$payOrderCol = serveFirstMatchingColumn($payCols, ['pemesanan_id', 'order_id']) ?? 'pemesanan_id';
$payCustomerCol = serveFirstMatchingColumn($payCols, ['pelanggan_id', 'user_id']);
$payMethodCol = serveFirstMatchingColumn($payCols, ['metode_pembayaran', 'metode']);
$payAmountCol = serveFirstMatchingColumn($payCols, ['nominal_pembayaran', 'nominal', 'jumlah_bayar', 'jumlah']);
$payDateCol = serveFirstMatchingColumn($payCols, ['tanggal_pembayaran', 'tanggal_bayar', 'tanggal']);
$payProofCol = serveFirstMatchingColumn($payCols, ['bukti_pembayaran', 'bukti_transfer', 'bukti']);
$payStatusCol = serveFirstMatchingColumn($payCols, ['status_pembayaran', 'status']);

$payStmt = $pdo->prepare("SELECT * FROM pembayaran WHERE {$payOrderCol} = :oid ORDER BY id DESC");
$payStmt->execute(['oid' => $orderId]);
$payments = $payStmt->fetchAll();

$hasActiveOrPaidPayment = false;
foreach ($payments as $py) {
    $stPay = strtolower((string) ($py[$payStatusCol ?? ''] ?? 'menunggu_verifikasi'));
    if (in_array($stPay, ['menunggu_verifikasi', 'menunggu_konfirmasi', 'lunas', 'terverifikasi', 'diterima', 'berhasil', 'valid'], true)) {
        $hasActiveOrPaidPayment = true;
        break;
    }
}

// Deteksi kolom tabel ulasan
$revCols = serveTableColumns($pdo, 'ulasan');
$revOrderCol = serveFirstMatchingColumn($revCols, ['pemesanan_id', 'order_id']);
$revCustomerCol = serveFirstMatchingColumn($revCols, ['pelanggan_id', 'user_id']);
$revProviderCol = serveFirstMatchingColumn($revCols, ['penyedia_id']);
$revServiceCol = serveFirstMatchingColumn($revCols, ['layanan_id']);
$revRatingCol = serveFirstMatchingColumn($revCols, ['rating', 'nilai']) ?? 'rating';
$revCommentCol = serveFirstMatchingColumn($revCols, ['komentar', 'ulasan']);

$existingReview = null;
if ($revOrderCol !== null) {
    $rStmt = $pdo->prepare("SELECT * FROM ulasan WHERE {$revOrderCol} = :oid LIMIT 1");
    $rStmt->execute(['oid' => $orderId]);
    $existingReview = $rStmt->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'cancel_order') {
        try {
            $pdo->beginTransaction();

            $lockOrd = $pdo->prepare("SELECT {$orderStatusCol} AS st FROM pemesanan WHERE id = :id LIMIT 1 FOR UPDATE");
            $lockOrd->execute(['id' => $orderId]);
            $lockedOrd = $lockOrd->fetch();
            $lockedStatus = strtolower(trim((string) ($lockedOrd['st'] ?? '')));

            if (!$lockedOrd) {
                throw new RuntimeException('Pesanan tidak ditemukan.');
            }
            if ($lockedStatus === 'dibatalkan') {
                throw new RuntimeException('Pesanan ini sudah berstatus Dibatalkan.');
            }
            if (!serveCanTransitionOrder($lockedStatus, 'dibatalkan', 'pelanggan')) {
                throw new RuntimeException('Pesanan pada status saat ini tidak dapat dibatalkan.');
            }
            if ($hasActiveOrPaidPayment) {
                throw new RuntimeException('Pesanan sudah memiliki pembayaran aktif/terverifikasi sehingga tidak dapat dibatalkan langsung.');
            }

            $upd = $pdo->prepare("UPDATE pemesanan SET {$orderStatusCol} = :st WHERE id = :id");
            $upd->execute(['st' => 'dibatalkan', 'id' => $orderId]);

            $pdo->commit();
            setFlash('success', 'Pesanan berhasil dibatalkan.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            setFlash('danger', $e->getMessage());
        }
        redirect('customer/order_detail.php?id=' . $orderId);
    } elseif ($action === 'submit_payment') {
        $metode = trim((string) ($_POST['metode_pembayaran'] ?? ''));
        $nominal = (float) ($_POST['nominal_pembayaran'] ?? 0);
        $tglBayar = trim((string) ($_POST['tanggal_pembayaran'] ?? date('Y-m-d')));

        $allowedMethods = ['Transfer Bank', 'E-Wallet', 'QRIS Manual', 'Tunai'];
        $canPayOrderStatus = in_array($currentStatus, ['diterima', 'menunggu_pembayaran'], true);

        if ($pelangganId <= 0) {
            setFlash('danger', 'ID pelanggan tidak valid. Silakan login ulang.');
        } elseif (!$canPayOrderStatus) {
            setFlash('danger', 'Pembayaran manual hanya dapat dikirim setelah pesanan diterima atau berstatus menunggu pembayaran.');
        } elseif ($hasActiveOrPaidPayment) {
            setFlash('danger', 'Pembayaran untuk pesanan ini sudah tercatat atau sedang menunggu verifikasi admin.');
        } elseif (!in_array($metode, $allowedMethods, true)) {
            setFlash('danger', 'Pilih metode pembayaran yang valid.');
        } elseif (round($nominal, 2) !== round($totalBiaya, 2) || $nominal <= 0) {
            setFlash('danger', 'Nominal pembayaran harus sesuai dengan total biaya pesanan (' . formatRupiah($totalBiaya) . ').');
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tglBayar)) {
            setFlash('danger', 'Tanggal pembayaran tidak valid.');
        } else {
            $upload = serveUploadPaymentProof($_FILES['bukti_pembayaran'] ?? []);
            if (!$upload['ok']) {
                setFlash('danger', (string) $upload['error']);
            } else {
                try {
                    $pdo->beginTransaction();

                    $chkUser = $pdo->prepare('SELECT id FROM users WHERE id = :uid LIMIT 1');
                    $chkUser->execute(['uid' => $pelangganId]);
                    if (!$chkUser->fetchColumn()) {
                        throw new RuntimeException('Akun pelanggan tidak ditemukan di tabel users.');
                    }

                    $lockStmt = $pdo->prepare("SELECT {$orderStatusCol} AS st, pelanggan_id FROM pemesanan WHERE id = :id FOR UPDATE");
                    $lockStmt->execute(['id' => $orderId]);
                    $locked = $lockStmt->fetch();
                    $lockedStatus = strtolower(trim((string) ($locked['st'] ?? '')));
                    $orderCustomerId = (int) ($locked['pelanggan_id'] ?? $pelangganId);

                    if (!in_array($lockedStatus, ['diterima', 'menunggu_pembayaran'], true)) {
                        throw new RuntimeException('Status pesanan tidak lagi mendukung pengiriman pembayaran.');
                    }

                    $dupStmt = $pdo->prepare("SELECT id FROM pembayaran WHERE {$payOrderCol} = :oid LIMIT 1 FOR UPDATE");
                    $dupStmt->execute(['oid' => $orderId]);
                    if ($dupStmt->fetch()) {
                        throw new RuntimeException('Pembayaran untuk pesanan ini sudah pernah dikirim.');
                    }

                    $pCols = [$payOrderCol];
                    $pVals = [':oid'];
                    $pParams = ['oid' => $orderId];

                    if ($payCustomerCol !== null) {
                        $pCols[] = $payCustomerCol;
                        $pVals[] = ':cust_id';
                        $pParams['cust_id'] = $orderCustomerId > 0 ? $orderCustomerId : $pelangganId;
                    }
                    if ($payMethodCol !== null) {
                        $pCols[] = $payMethodCol;
                        $pVals[] = ':metode';
                        $pParams['metode'] = $metode;
                    }
                    if ($payAmountCol !== null) {
                        $pCols[] = $payAmountCol;
                        $pVals[] = ':nominal';
                        $pParams['nominal'] = $nominal;
                    }
                    if ($payDateCol !== null) {
                        $pCols[] = $payDateCol;
                        $pVals[] = ':tgl';
                        $pParams['tgl'] = $tglBayar;
                    }
                    if ($payProofCol !== null) {
                        $pCols[] = $payProofCol;
                        $pVals[] = ':bukti';
                        $pParams['bukti'] = (string) $upload['path'];
                    }
                    if ($payStatusCol !== null) {
                        $pCols[] = $payStatusCol;
                        $pVals[] = ':st_pay';
                        $pParams['st_pay'] = 'menunggu_verifikasi';
                    }

                    $insPay = $pdo->prepare('INSERT INTO pembayaran (' . implode(', ', $pCols) . ') VALUES (' . implode(', ', $pVals) . ')');
                    $insPay->execute($pParams);

                    if ($lockedStatus === 'diterima') {
                        $updOrder = $pdo->prepare("UPDATE pemesanan SET {$orderStatusCol} = 'menunggu_pembayaran' WHERE id = :id");
                        $updOrder->execute(['id' => $orderId]);
                    }

                    $pdo->commit();
                    setFlash('success', 'Bukti pembayaran berhasil dikirim dan menunggu konfirmasi admin.');
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    setFlash('danger', $e->getMessage());
                }
            }
        }
        redirect('customer/order_detail.php?id=' . $orderId);
    } elseif ($action === 'submit_review') {
        $rating = (int) ($_POST['rating'] ?? 0);
        $komentar = trim((string) ($_POST['komentar'] ?? ''));

        if ($currentStatus !== 'selesai') {
            setFlash('danger', 'Ulasan hanya dapat diberikan setelah pesanan berstatus Selesai.');
        } elseif ($existingReview !== null) {
            setFlash('warning', 'Anda sudah memberikan ulasan untuk pesanan ini.');
        } elseif ($rating < 1 || $rating > 5) {
            setFlash('danger', 'Rating harus berada di antara 1 sampai 5.');
        } elseif ($komentar === '' || mb_strlen($komentar) < 3) {
            setFlash('danger', 'Komentar ulasan wajib diisi minimal 3 karakter.');
        } else {
            $rCols = [];
            $rVals = [];
            $rParams = [];

            if ($revOrderCol !== null) {
                $rCols[] = $revOrderCol;
                $rVals[] = ':oid';
                $rParams['oid'] = $orderId;
            }
            if ($revCustomerCol !== null) {
                $rCols[] = $revCustomerCol;
                $rVals[] = ':cid';
                $rParams['cid'] = $pelangganId;
            }
            if ($revProviderCol !== null) {
                $rCols[] = $revProviderCol;
                $rVals[] = ':pid';
                $rParams['pid'] = (int) ($order['penyedia_id'] ?? 0);
            }
            if ($revServiceCol !== null) {
                $rCols[] = $revServiceCol;
                $rVals[] = ':sid';
                $rParams['sid'] = (int) ($order['layanan_id'] ?? 0);
            }

            $rCols[] = $revRatingCol;
            $rVals[] = ':rating';
            $rParams['rating'] = $rating;

            if ($revCommentCol !== null) {
                $rCols[] = $revCommentCol;
                $rVals[] = ':komentar';
                $rParams['komentar'] = $komentar;
            }

            $insRev = $pdo->prepare('INSERT INTO ulasan (' . implode(', ', $rCols) . ') VALUES (' . implode(', ', $rVals) . ')');
            $insRev->execute($rParams);

            setFlash('success', 'Terima kasih! Ulasan Anda telah tersimpan.');
        }
        redirect('customer/order_detail.php?id=' . $orderId);
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<?php renderFlashMessages(); ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 fw-bold mb-0">Detail Pesanan: <?= e($kodePesanan); ?></h1>
    <a href="<?= e(url('customer/orders.php')); ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Riwayat Pemesanan
    </a>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm p-4 mb-4">
            <h2 class="h6 fw-bold mb-3">Informasi Pemesanan</h2>
            <table class="table table-borderless mb-0">
                <tr>
                    <th class="text-muted ps-0" style="width: 38%;">Kode Pesanan</th>
                    <td class="fw-semibold"><?= e($kodePesanan); ?></td>
                </tr>
                <tr>
                    <th class="text-muted ps-0">Status</th>
                    <td><span class="badge <?= e(serveOrderStatusBadgeClass($currentStatus)); ?>"><?= e(serveOrderStatusLabel($currentStatus)); ?></span></td>
                </tr>
                <tr>
                    <th class="text-muted ps-0">Layanan</th>
                    <td><?= e((string) ($order['judul_layanan'] ?? '-')); ?></td>
                </tr>
                <tr>
                    <th class="text-muted ps-0">Penyedia Jasa</th>
                    <td><?= e((string) ($order['nama_penyedia'] ?? '-')); ?></td>
                </tr>
                <?php if ($orderDateCol !== null && !empty($order[$orderDateCol])): ?>
                    <tr>
                        <th class="text-muted ps-0">Tanggal Pengerjaan</th>
                        <td><?= e((string) $order[$orderDateCol]); ?></td>
                    </tr>
                <?php endif; ?>
                <?php if ($orderAddrCol !== null && !empty($order[$orderAddrCol])): ?>
                    <tr>
                        <th class="text-muted ps-0">Lokasi Pengerjaan</th>
                        <td><?= e((string) $order[$orderAddrCol]); ?></td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <th class="text-muted ps-0">Total Biaya</th>
                    <td class="fw-bold text-primary"><?= e(formatRupiah($totalBiaya)); ?></td>
                </tr>
                <?php if ($orderDescCol !== null && !empty($order[$orderDescCol])): ?>
                    <tr>
                        <th class="text-muted ps-0">Deskripsi Kebutuhan</th>
                        <td><?= nl2br(e((string) $order[$orderDescCol])); ?></td>
                    </tr>
                <?php endif; ?>
            </table>

            <?php if ($currentStatus !== 'dibatalkan' && serveCanTransitionOrder($currentStatus, 'dibatalkan', 'pelanggan') && !$hasActiveOrPaidPayment): ?>
                <form method="post" class="mt-3 pt-3 border-top" onsubmit="return confirm('Batalkan pesanan ini?');">
                    <input type="hidden" name="action" value="cancel_order">
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-x-circle me-1"></i>Batalkan Pesanan
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <div class="card border-0 shadow-sm p-4">
            <h2 class="h6 fw-bold mb-3">Ulasan Pesanan</h2>
            <?php if ($existingReview !== null): ?>
                <div class="border rounded-3 p-3 bg-light">
                    <div class="fw-bold text-warning mb-1">Rating: <?= (int) ($existingReview[$revRatingCol] ?? 0); ?> / 5</div>
                    <p class="mb-0"><?= e((string) ($revCommentCol !== null ? ($existingReview[$revCommentCol] ?? '') : '')); ?></p>
                </div>
            <?php elseif ($currentStatus === 'selesai'): ?>
                <form method="post">
                    <input type="hidden" name="action" value="submit_review">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Rating (1 - 5) <span class="text-danger">*</span></label>
                        <select name="rating" class="form-select" required>
                            <option value="">-- Pilih Rating --</option>
                            <option value="5">5 - Sangat Memuaskan</option>
                            <option value="4">4 - Memuaskan</option>
                            <option value="3">3 - Cukup</option>
                            <option value="2">2 - Kurang</option>
                            <option value="1">1 - Sangat Kurang</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Komentar <span class="text-danger">*</span></label>
                        <textarea name="komentar" rows="3" class="form-control" placeholder="Bagikan pengalaman Anda menggunakan layanan ini..." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-success fw-semibold">
                        <i class="bi bi-star me-1"></i>Kirim Ulasan
                    </button>
                </form>
            <?php else: ?>
                <p class="text-muted small mb-0">Ulasan dapat dikirim setelah status pesanan menjadi <strong>Selesai</strong>.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm p-4 mb-4">
            <h2 class="h6 fw-bold mb-3">Pembayaran Manual</h2>

            <?php if (in_array($currentStatus, ['diterima', 'menunggu_pembayaran'], true) && !$hasActiveOrPaidPayment): ?>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="submit_payment">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Metode Pembayaran <span class="text-danger">*</span></label>
                        <select name="metode_pembayaran" class="form-select" required>
                            <option value="">-- Pilih Metode --</option>
                            <option value="Transfer Bank">Transfer Bank</option>
                            <option value="E-Wallet">E-Wallet</option>
                            <option value="QRIS Manual">QRIS Manual</option>
                            <option value="Tunai">Tunai</option>
                        </select>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nominal Pembayaran (Rp) <span class="text-danger">*</span></label>
                            <input type="number" name="nominal_pembayaran" class="form-control" step="100" value="<?= e((string) $totalBiaya); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Tanggal Pembayaran <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal_pembayaran" class="form-control" value="<?= e(date('Y-m-d')); ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Upload Bukti Pembayaran (JPG/PNG/WEBP, maks 3 MB) <span class="text-danger">*</span></label>
                        <input type="file" name="bukti_pembayaran" class="form-control" accept=".jpg,.jpeg,.png,.webp" required>
                    </div>

                    <button type="submit" class="btn btn-primary fw-semibold">
                        <i class="bi bi-upload me-1"></i>Kirim Pembayaran
                    </button>
                </form>
            <?php elseif ($hasActiveOrPaidPayment): ?>
                <div class="alert alert-info mb-0">
                    Pembayaran sudah dikirim/diproses untuk pesanan ini. Lihat riwayat pembayaran di bawah.
                </div>
            <?php else: ?>
                <p class="text-muted small mb-0">
                    Form pembayaran aktif setelah pesanan berstatus <strong>Diterima</strong> atau <strong>Menunggu Pembayaran</strong>.
                </p>
            <?php endif; ?>
        </div>

        <div class="card border-0 shadow-sm p-4">
            <h2 class="h6 fw-bold mb-3">Riwayat Pembayaran</h2>
            <?php if (empty($payments)): ?>
                <p class="text-muted small mb-0">Belum ada riwayat pembayaran.</p>
            <?php else: ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($payments as $py): ?>
                        <?php
                        $stPy = (string) ($payStatusCol !== null ? ($py[$payStatusCol] ?? 'menunggu_verifikasi') : 'menunggu_verifikasi');
                        $metodePy = (string) ($payMethodCol !== null ? ($py[$payMethodCol] ?? '-') : '-');
                        $nominalPy = (float) ($payAmountCol !== null ? ($py[$payAmountCol] ?? 0) : 0);
                        $tglPy = (string) ($payDateCol !== null ? ($py[$payDateCol] ?? '-') : '-');
                        $buktiPy = (string) ($payProofCol !== null ? ($py[$payProofCol] ?? '') : '');
                        ?>
                        <div class="border rounded-3 p-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-bold"><?= e($metodePy); ?></span>
                                <span class="badge bg-secondary"><?= e($stPy); ?></span>
                            </div>
                            <div class="small text-muted">Nominal: <strong><?= e(formatRupiah($nominalPy)); ?></strong> • Tanggal: <?= e($tglPy); ?></div>
                            <?php if ($buktiPy !== ''): ?>
                                <div class="mt-2">
                                    <a href="<?= e(url($buktiPy)); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-image me-1"></i>Lihat Bukti
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>