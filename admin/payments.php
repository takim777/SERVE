<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../includes/business_workflow.php';

$pdo = adminDb();
$adminUser = currentUser();
$sessionAdminId = isset($adminUser['id']) && (int) $adminUser['id'] > 0 ? (int) $adminUser['id'] : 0;

$errors = [];
$duplicatePaymentId = 0;
$editPay = null;

$orderCols = serveTableColumns($pdo, 'pemesanan');
$orderStatusCol = serveFirstMatchingColumn($orderCols, ['status_terkini', 'status']) ?? 'status_terkini';

$ordersList = $pdo->query(
    "SELECT pm.id, pm.kode_pesanan, pm.pelanggan_id, pm.total_biaya, pm.{$orderStatusCol} AS status_pesanan, u.nama_lengkap AS nama_pelanggan
     FROM pemesanan pm
     LEFT JOIN users u ON pm.pelanggan_id = u.id
     ORDER BY pm.id DESC"
)->fetchAll();

$ordersById = [];
foreach ($ordersList as $ord) {
    $ordersById[(int) $ord['id']] = $ord;
}

$allowedMethods = [
    'transfer_bank' => 'Transfer Bank',
    'e_wallet'      => 'E-Wallet',
    'tunai'         => 'Tunai',
];

$allowedPayStatuses = [
    'menunggu_verifikasi' => 'Menunggu Verifikasi',
    'valid'               => 'Valid',
    'tidak_valid'         => 'Tidak Valid',
];

$resolveVerifierId = static function () use ($pdo, $sessionAdminId): int {
    if ($sessionAdminId > 0) {
        return $sessionAdminId;
    }
    $fallbackId = (int) $pdo->query('SELECT id FROM users ORDER BY id ASC LIMIT 1')->fetchColumn();
    return $fallbackId > 0 ? $fallbackId : 1;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create' || $action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $pemesananId = (int) ($_POST['pemesanan_id'] ?? 0);
        $metode = trim((string) ($_POST['metode_pembayaran'] ?? 'transfer_bank'));
        $namaPengirim = trim((string) ($_POST['nama_pengirim'] ?? ''));
        $nominal = (float) ($_POST['nominal'] ?? 0);
        $buktiPembayaran = trim((string) ($_POST['bukti_pembayaran'] ?? ''));
        $statusBayar = (string) ($_POST['status_pembayaran'] ?? 'menunggu_verifikasi');
        $catatanAdmin = trim((string) ($_POST['catatan_admin'] ?? ''));
        $tanggalBayar = trim((string) ($_POST['tanggal_bayar'] ?? ''));

        $selectedOrder = $ordersById[$pemesananId] ?? null;
        $pelangganId = (int) ($selectedOrder['pelanggan_id'] ?? 0);

        if ($pelangganId > 0) {
            $chkCust = $pdo->prepare('SELECT id FROM users WHERE id = :cid LIMIT 1');
            $chkCust->execute(['cid' => $pelangganId]);
            if (!$chkCust->fetchColumn()) {
                $pelangganId = 0;
            }
        }

        if (isset($_FILES['bukti_file']) && (int) ($_FILES['bukti_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $uploadRes = serveUploadPaymentProof($_FILES['bukti_file']);
            if (!$uploadRes['ok']) {
                $errors[] = (string) $uploadRes['error'];
            } else {
                $buktiPembayaran = (string) $uploadRes['path'];
            }
        }

        if ($pemesananId <= 0 || !$selectedOrder) {
            $errors[] = 'Pemesanan wajib dipilih dan harus valid.';
        }
        if ($pelangganId <= 0) {
            $errors[] = 'Data pelanggan pada pemesanan terpilih tidak ditemukan atau tidak valid di tabel users.';
        }
        if (!array_key_exists($metode, $allowedMethods)) {
            $errors[] = 'Metode pembayaran tidak valid.';
        }
        if ($nominal <= 0) {
            $errors[] = 'Nominal pembayaran harus lebih besar dari 0.';
        }
        if ($buktiPembayaran === '') {
            $errors[] = 'Bukti pembayaran (nama file / referensi atau upload file) wajib diisi.';
        }
        if (!array_key_exists($statusBayar, $allowedPayStatuses)) {
            $errors[] = 'Status pembayaran tidak valid.';
        }
        if ($action === 'update' && $id <= 0) {
            $errors[] = 'ID pembayaran tidak valid.';
        }

        $parsedTs = $tanggalBayar !== '' ? strtotime($tanggalBayar) : time();
        if ($parsedTs === false) {
            $errors[] = 'Format tanggal bayar tidak valid.';
        }

        if (empty($errors) && $action === 'update' && $id > 0) {
            $chkExisting = $pdo->prepare('SELECT id FROM pembayaran WHERE id = :id LIMIT 1');
            $chkExisting->execute(['id' => $id]);
            if (!$chkExisting->fetch()) {
                $errors[] = 'Data pembayaran yang ingin diubah tidak ditemukan.';
            }
        }

        if (empty($errors) && $pemesananId > 0) {
            $dupSql = 'SELECT id FROM pembayaran WHERE pemesanan_id = :pemesanan_id';
            $dupParams = ['pemesanan_id' => $pemesananId];
            if ($action === 'update') {
                $dupSql .= ' AND id != :id';
                $dupParams['id'] = $id;
            }
            $dupSql .= ' LIMIT 1';
            $dupStmt = $pdo->prepare($dupSql);
            $dupStmt->execute($dupParams);
            $existingPayId = (int) ($dupStmt->fetchColumn() ?: 0);

            if ($existingPayId > 0) {
                $duplicatePaymentId = $existingPayId;
                $errors[] = "Pemesanan ini sudah memiliki data pembayaran (ID #{$existingPayId}). Gunakan tombol Edit pada pembayaran tersebut untuk mengubah nominal atau statusnya.";
            }
        }

        if (empty($errors)) {
            $tglBayarSql = date('Y-m-d H:i:s', (int) $parsedTs);
            $isVerifiedStatus = in_array($statusBayar, ['valid', 'tidak_valid'], true);
            $verifierId = $isVerifiedStatus ? $resolveVerifierId() : null;
            $verifiedAt = $isVerifiedStatus ? date('Y-m-d H:i:s') : null;
            $namaPengirimParam = $namaPengirim !== '' ? $namaPengirim : null;
            $catatanAdminParam = $catatanAdmin !== '' ? $catatanAdmin : null;

            try {
                $pdo->beginTransaction();

                if ($action === 'create') {
                    $stmt = $pdo->prepare(
                        'INSERT INTO pembayaran (
                            pemesanan_id, pelanggan_id, metode_pembayaran, nama_pengirim,
                            nominal, tanggal_bayar, bukti_pembayaran, status_pembayaran,
                            catatan_admin, diverifikasi_oleh, tanggal_verifikasi
                         ) VALUES (
                            :pemesanan_id, :pelanggan_id, :metode_pembayaran, :nama_pengirim,
                            :nominal, :tanggal_bayar, :bukti_pembayaran, :status_pembayaran,
                            :catatan_admin, :diverifikasi_oleh, :tanggal_verifikasi
                         )'
                    );
                    $stmt->execute([
                        'pemesanan_id'       => $pemesananId,
                        'pelanggan_id'       => $pelangganId,
                        'metode_pembayaran'  => $metode,
                        'nama_pengirim'      => $namaPengirimParam,
                        'nominal'            => $nominal,
                        'tanggal_bayar'      => $tglBayarSql,
                        'bukti_pembayaran'   => $buktiPembayaran,
                        'status_pembayaran'  => $statusBayar,
                        'catatan_admin'      => $catatanAdminParam,
                        'diverifikasi_oleh'  => $verifierId,
                        'tanggal_verifikasi' => $verifiedAt,
                    ]);
                } else {
                    $stmt = $pdo->prepare(
                        'UPDATE pembayaran
                         SET pemesanan_id = :pemesanan_id,
                             pelanggan_id = :pelanggan_id,
                             metode_pembayaran = :metode_pembayaran,
                             nama_pengirim = :nama_pengirim,
                             nominal = :nominal,
                             tanggal_bayar = :tanggal_bayar,
                             bukti_pembayaran = :bukti_pembayaran,
                             status_pembayaran = :status_pembayaran,
                             catatan_admin = :catatan_admin,
                             diverifikasi_oleh = :diverifikasi_oleh,
                             tanggal_verifikasi = :tanggal_verifikasi
                         WHERE id = :id'
                    );
                    $stmt->execute([
                        'pemesanan_id'       => $pemesananId,
                        'pelanggan_id'       => $pelangganId,
                        'metode_pembayaran'  => $metode,
                        'nama_pengirim'      => $namaPengirimParam,
                        'nominal'            => $nominal,
                        'tanggal_bayar'      => $tglBayarSql,
                        'bukti_pembayaran'   => $buktiPembayaran,
                        'status_pembayaran'  => $statusBayar,
                        'catatan_admin'      => $catatanAdminParam,
                        'diverifikasi_oleh'  => $verifierId,
                        'tanggal_verifikasi' => $verifiedAt,
                        'id'                 => $id,
                    ]);
                }

                $oStmt = $pdo->prepare("SELECT {$orderStatusCol} AS ord_st FROM pemesanan WHERE id = :oid LIMIT 1 FOR UPDATE");
                $oStmt->execute(['oid' => $pemesananId]);
                $ordRow = $oStmt->fetch();
                if ($ordRow) {
                    $ordSt = (string) ($ordRow['ord_st'] ?? '');
                    if ($statusBayar === 'valid' && serveCanTransitionOrder($ordSt, 'sedang_dikerjakan', 'admin')) {
                        $updOrd = $pdo->prepare("UPDATE pemesanan SET {$orderStatusCol} = 'sedang_dikerjakan' WHERE id = :oid");
                        $updOrd->execute(['oid' => $pemesananId]);
                    } elseif ($statusBayar === 'tidak_valid' && $ordSt === 'menunggu_pembayaran') {
                        $updOrd = $pdo->prepare("UPDATE pemesanan SET {$orderStatusCol} = 'diterima' WHERE id = :oid");
                        $updOrd->execute(['oid' => $pemesananId]);
                    }
                }

                $pdo->commit();
                setFlash(
                    'success',
                    $action === 'create' ? 'Data pembayaran berhasil disimpan.' : 'Data pembayaran berhasil diperbarui.'
                );
                redirect('admin/payments.php');
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = 'Gagal menyimpan pembayaran: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $st = $pdo->prepare('SELECT status_pembayaran FROM pembayaran WHERE id = :id LIMIT 1');
            $st->execute(['id' => $id]);
            $row = $st->fetch();

            if ($row && $row['status_pembayaran'] === 'valid') {
                setFlash('warning', 'Pembayaran berstatus valid tidak boleh dihapus demi integritas keuangan. Ubah status menjadi Tidak Valid jika diperlukan.');
            } else {
                $del = $pdo->prepare('DELETE FROM pembayaran WHERE id = :id');
                $del->execute(['id' => $id]);
                setFlash('success', 'Data pembayaran berhasil dihapus.');
            }
        }
        redirect('admin/payments.php');
    }
}

if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    if ($editId > 0) {
        $st = $pdo->prepare('SELECT * FROM pembayaran WHERE id = :id LIMIT 1');
        $st->execute(['id' => $editId]);
        $editPay = $st->fetch() ?: null;
    }
}

$q = trim((string) ($_GET['q'] ?? ''));
$sql = 'SELECT pb.*, pm.kode_pesanan, u.nama_lengkap AS nama_pelanggan
        FROM pembayaran pb
        LEFT JOIN pemesanan pm ON pb.pemesanan_id = pm.id
        LEFT JOIN users u ON pb.pelanggan_id = u.id
        WHERE 1=1';
$params = [];

if ($q !== '') {
    $sql .= ' AND (
        pm.kode_pesanan LIKE :q_kode
        OR u.nama_lengkap LIKE :q_pelanggan
        OR pb.nama_pengirim LIKE :q_pengirim
        OR pb.metode_pembayaran LIKE :q_metode
        OR pb.status_pembayaran LIKE :q_status
    )';
    $likeQ = '%' . $q . '%';
    $params['q_kode'] = $likeQ;
    $params['q_pelanggan'] = $likeQ;
    $params['q_pengirim'] = $likeQ;
    $params['q_metode'] = $likeQ;
    $params['q_status'] = $likeQ;
}

$sql .= ' ORDER BY pb.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();

renderAdminHeader('Manajemen Pembayaran', 'payments');
?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger shadow-sm">
        <ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err); ?></li><?php endforeach; ?></ul>
        <?php if ($duplicatePaymentId > 0): ?>
            <div class="mt-2">
                <a href="<?= e(url('admin/payments.php?edit=' . $duplicatePaymentId)); ?>" class="btn btn-sm btn-outline-danger">
                    <i class="bi bi-pencil me-1"></i>Buka Pembayaran #<?= $duplicatePaymentId; ?> untuk Diedit
                </a>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3">
                <h2 class="h6 fw-bold mb-0"><?= $editPay ? 'Edit Pembayaran #' . (int) $editPay['id'] : 'Catat Pembayaran'; ?></h2>
            </div>
            <div class="card-body">
                <form method="post" action="<?= e(url('admin/payments.php')); ?>" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="<?= $editPay ? 'update' : 'create'; ?>">
                    <?php if ($editPay): ?><input type="hidden" name="id" value="<?= (int) $editPay['id']; ?>"><?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Pemesanan <span class="text-danger">*</span></label>
                        <?php $selOrderId = (int) ($_POST['pemesanan_id'] ?? ($editPay['pemesanan_id'] ?? 0)); ?>
                        <select name="pemesanan_id" class="form-select" required>
                            <option value="">-- Pilih Kode Pesanan --</option>
                            <?php foreach ($ordersList as $o): ?>
                                <option value="<?= (int) $o['id']; ?>" <?= $selOrderId === (int) $o['id'] ? 'selected' : ''; ?>>
                                    <?= e((string) ($o['kode_pesanan'] ?: ('ORD-' . $o['id']))); ?> - <?= e((string) ($o['nama_pelanggan'] ?? 'Pelanggan')); ?> (<?= e(formatRupiah((float) $o['total_biaya'])); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Metode Pembayaran <span class="text-danger">*</span></label>
                        <?php $curMethod = (string) ($_POST['metode_pembayaran'] ?? ($editPay['metode_pembayaran'] ?? 'transfer_bank')); ?>
                        <select name="metode_pembayaran" class="form-select" required>
                            <?php foreach ($allowedMethods as $mKey => $mLabel): ?>
                                <option value="<?= e($mKey); ?>" <?= $curMethod === $mKey ? 'selected' : ''; ?>><?= e($mLabel); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Pengirim</label>
                        <input type="text" name="nama_pengirim" maxlength="120" class="form-control"
                               value="<?= e((string) ($_POST['nama_pengirim'] ?? ($editPay['nama_pengirim'] ?? ''))); ?>" placeholder="Opsional">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nominal Bayar (Rp) <span class="text-danger">*</span></label>
                        <input type="number" name="nominal" step="1" min="1" class="form-control"
                               value="<?= e((string) ($_POST['nominal'] ?? ($editPay['nominal'] ?? ''))); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Bukti Pembayaran (Nama File / Path) <span class="text-danger">*</span></label>
                        <input type="text" name="bukti_pembayaran" maxlength="255" class="form-control mb-2"
                               value="<?= e((string) ($_POST['bukti_pembayaran'] ?? ($editPay['bukti_pembayaran'] ?? ''))); ?>"
                               placeholder="Contoh: bukti_tf_001.jpg" required>
                        <input type="file" name="bukti_file" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.webp">
                        <small class="text-muted">Opsional: pilih file gambar untuk mengunggah langsung ke server.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Status Pembayaran</label>
                        <?php $stPay = (string) ($_POST['status_pembayaran'] ?? ($editPay['status_pembayaran'] ?? 'menunggu_verifikasi')); ?>
                        <select name="status_pembayaran" class="form-select">
                            <?php foreach ($allowedPayStatuses as $sKey => $sLabel): ?>
                                <option value="<?= e($sKey); ?>" <?= $stPay === $sKey ? 'selected' : ''; ?>><?= e($sLabel); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tanggal Bayar</label>
                        <?php
                        $rawTgl = (string) ($_POST['tanggal_bayar'] ?? ($editPay['tanggal_bayar'] ?? ''));
                        $tglVal = $rawTgl !== '' && strtotime($rawTgl) !== false ? date('Y-m-d\TH:i', strtotime($rawTgl)) : date('Y-m-d\TH:i');
                        ?>
                        <input type="datetime-local" name="tanggal_bayar" class="form-control" value="<?= e($tglVal); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Catatan Admin</label>
                        <textarea name="catatan_admin" rows="2" class="form-control" placeholder="Catatan verifikasi (opsional)"><?= e((string) ($_POST['catatan_admin'] ?? ($editPay['catatan_admin'] ?? ''))); ?></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-check-lg me-1"></i>Simpan</button>
                        <?php if ($editPay): ?><a href="<?= e(url('admin/payments.php')); ?>" class="btn btn-outline-secondary">Batal</a><?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3">
                <form method="get" action="<?= e(url('admin/payments.php')); ?>" class="d-flex gap-2">
                    <input type="text" name="q" class="form-control" placeholder="Cari kode pesanan, pelanggan, pengirim, metode, atau status..." value="<?= e($q); ?>">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Kode Pesanan</th>
                            <th>Pelanggan</th>
                            <th>Metode</th>
                            <th>Nominal</th>
                            <th>Status</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payments)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">Belum ada data pembayaran.</td></tr>
                        <?php else: ?>
                            <?php foreach ($payments as $pb): ?>
                                <?php
                                $badgeClass = match ((string) $pb['status_pembayaran']) {
                                    'valid'       => 'bg-success',
                                    'tidak_valid' => 'bg-danger',
                                    default       => 'bg-warning text-dark',
                                };
                                ?>
                                <tr>
                                    <td class="fw-semibold"><?= e((string) ($pb['kode_pesanan'] ?: ('#' . $pb['pemesanan_id']))); ?></td>
                                    <td><?= e((string) ($pb['nama_pelanggan'] ?? ('User #' . $pb['pelanggan_id']))); ?></td>
                                    <td><?= e((string) ($allowedMethods[$pb['metode_pembayaran']] ?? $pb['metode_pembayaran'])); ?></td>
                                    <td class="fw-semibold"><?= e(formatRupiah((float) $pb['nominal'])); ?></td>
                                    <td>
                                        <span class="badge <?= e($badgeClass); ?>">
                                            <?= e((string) ($allowedPayStatuses[$pb['status_pembayaran']] ?? $pb['status_pembayaran'])); ?>
                                        </span>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <a href="<?= e(url('admin/payments.php?edit=' . (int) $pb['id'])); ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                        <form method="post" action="<?= e(url('admin/payments.php')); ?>" class="d-inline" data-confirm-action="Hapus data pembayaran ini?">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $pb['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php renderAdminFooter(); ?>