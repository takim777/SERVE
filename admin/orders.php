<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$pdo = adminDb();
$errors = [];
$editOrder = null;

$customers = $pdo->query("SELECT id, nama_lengkap FROM users WHERE role = 'pelanggan' ORDER BY nama_lengkap ASC")->fetchAll();
$providers = $pdo->query("SELECT id, nama_lengkap FROM users WHERE role = 'penyedia' ORDER BY nama_lengkap ASC")->fetchAll();
$services = $pdo->query('SELECT id, judul_layanan, harga, deskripsi FROM layanan ORDER BY judul_layanan ASC')->fetchAll();

$customerIds = array_map(static fn(array $row): int => (int) $row['id'], $customers);
$providerIds = array_map(static fn(array $row): int => (int) $row['id'], $providers);
$serviceIds = array_map(static fn(array $row): int => (int) $row['id'], $services);

$allowedStatuses = ['menunggu_konfirmasi', 'diterima', 'ditolak'];

$resolveActorId = static function () use ($pdo): int {
    $actorId = (int) (currentUser()['id'] ?? 0);
    if ($actorId > 0) {
        return $actorId;
    }
    $fallbackId = (int) $pdo->query('SELECT id FROM users ORDER BY id ASC LIMIT 1')->fetchColumn();
    return $fallbackId > 0 ? $fallbackId : 1;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create' || $action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $kode = trim((string) ($_POST['kode_pesanan'] ?? ''));
        $pelangganId = (int) ($_POST['pelanggan_id'] ?? 0);
        $penyediaId = (int) ($_POST['penyedia_id'] ?? 0);
        $layananId = (int) ($_POST['layanan_id'] ?? 0);
        $deskripsiKebutuhan = trim((string) ($_POST['deskripsi_kebutuhan'] ?? ''));
        $tanggalPengerjaan = trim((string) ($_POST['tanggal_pengerjaan'] ?? ''));
        $alamatLokasi = trim((string) ($_POST['alamat_lokasi'] ?? ''));
        $totalBiaya = (float) ($_POST['total_biaya'] ?? 0);
        $statusOrder = trim((string) ($_POST['status_terkini'] ?? 'menunggu_konfirmasi'));
        $catatanPenyedia = trim((string) ($_POST['catatan_penyedia'] ?? ''));

        if ($pelangganId <= 0 || !in_array($pelangganId, $customerIds, true)) {
            $errors[] = 'Pelanggan wajib dipilih dan harus valid.';
        }
        if ($penyediaId <= 0 || !in_array($penyediaId, $providerIds, true)) {
            $errors[] = 'Penyedia jasa wajib dipilih dan harus valid.';
        }
        if ($layananId <= 0 || !in_array($layananId, $serviceIds, true)) {
            $errors[] = 'Layanan wajib dipilih dan harus valid.';
        }
        if ($deskripsiKebutuhan === '') {
            $errors[] = 'Deskripsi kebutuhan wajib diisi.';
        }
        if ($tanggalPengerjaan === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalPengerjaan)) {
            $errors[] = 'Tanggal pengerjaan wajib diisi dengan format yang valid.';
        }
        if ($totalBiaya <= 0) {
            $errors[] = 'Total biaya harus lebih besar dari 0.';
        }
        if ($statusOrder === '') {
            $errors[] = 'Status terkini wajib diisi.';
        }
        if ($action === 'update' && $id <= 0) {
            $errors[] = 'ID pemesanan tidak valid.';
        }

        if ($kode === '') {
            $kode = 'SRV-' . date('Ymd') . '-' . random_int(100, 999);
        }

        if (empty($errors)) {
            $actorId = $resolveActorId();
            $alamatParam = $alamatLokasi !== '' ? $alamatLokasi : null;
            $catatanParam = $catatanPenyedia !== '' ? $catatanPenyedia : null;

            if ($action === 'create') {
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare(
                        'INSERT INTO pemesanan (
                            kode_pesanan, pelanggan_id, penyedia_id, layanan_id,
                            deskripsi_kebutuhan, tanggal_pengerjaan, alamat_lokasi,
                            total_biaya, status_terkini, catatan_penyedia
                         ) VALUES (
                            :kode, :pelanggan_id, :penyedia_id, :layanan_id,
                            :deskripsi, :tanggal, :alamat,
                            :total, :status, :catatan
                         )'
                    );
                    $stmt->execute([
                        'kode'         => $kode,
                        'pelanggan_id' => $pelangganId,
                        'penyedia_id'  => $penyediaId,
                        'layanan_id'   => $layananId,
                        'deskripsi'    => $deskripsiKebutuhan,
                        'tanggal'      => $tanggalPengerjaan,
                        'alamat'       => $alamatParam,
                        'total'        => $totalBiaya,
                        'status'       => $statusOrder,
                        'catatan'      => $catatanParam,
                    ]);
                    $newId = (int) $pdo->lastInsertId();
                    $hist = $pdo->prepare(
                        'INSERT INTO status_pemesanan (pemesanan_id, status_lama, status_baru, diubah_oleh, keterangan)
                         VALUES (:oid, :status_lama, :status_baru, :by, :ket)'
                    );
                    $hist->execute([
                        'oid'         => $newId,
                        'status_lama' => null,
                        'status_baru' => $statusOrder,
                        'by'          => $actorId,
                        'ket'         => 'Pemesanan dibuat oleh Admin',
                    ]);
                    $pdo->commit();
                    setFlash('success', 'Pemesanan berhasil ditambahkan.');
                    redirect('admin/orders.php');
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }
            } else {
                $oldStmt = $pdo->prepare('SELECT status_terkini FROM pemesanan WHERE id = :id LIMIT 1');
                $oldStmt->execute(['id' => $id]);
                $oldStatus = (string) ($oldStmt->fetchColumn() ?: '');

                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare(
                        'UPDATE pemesanan
                         SET kode_pesanan = :kode,
                             pelanggan_id = :pelanggan_id,
                             penyedia_id = :penyedia_id,
                             layanan_id = :layanan_id,
                             deskripsi_kebutuhan = :deskripsi,
                             tanggal_pengerjaan = :tanggal,
                             alamat_lokasi = :alamat,
                             total_biaya = :total,
                             status_terkini = :status,
                             catatan_penyedia = :catatan
                         WHERE id = :id'
                    );
                    $stmt->execute([
                        'kode'         => $kode,
                        'pelanggan_id' => $pelangganId,
                        'penyedia_id'  => $penyediaId,
                        'layanan_id'   => $layananId,
                        'deskripsi'    => $deskripsiKebutuhan,
                        'tanggal'      => $tanggalPengerjaan,
                        'alamat'       => $alamatParam,
                        'total'        => $totalBiaya,
                        'status'       => $statusOrder,
                        'catatan'      => $catatanParam,
                        'id'           => $id,
                    ]);
                    $hist = $pdo->prepare(
                        'INSERT INTO status_pemesanan (pemesanan_id, status_lama, status_baru, diubah_oleh, keterangan)
                         VALUES (:oid, :status_lama, :status_baru, :by, :ket)'
                    );
                    $hist->execute([
                        'oid'         => $id,
                        'status_lama' => $oldStatus !== '' ? $oldStatus : null,
                        'status_baru' => $statusOrder,
                        'by'          => $actorId,
                        'ket'         => 'Diperbarui oleh Admin',
                    ]);
                    $pdo->commit();
                    setFlash('success', 'Pemesanan berhasil diperbarui.');
                    redirect('admin/orders.php');
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }
            }
        }
    } elseif ($action === 'cancel') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $actorId = $resolveActorId();
            $oldStmt = $pdo->prepare('SELECT status_terkini FROM pemesanan WHERE id = :id LIMIT 1');
            $oldStmt->execute(['id' => $id]);
            $oldStatus = (string) ($oldStmt->fetchColumn() ?: '');

            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("UPDATE pemesanan SET status_terkini = 'ditolak' WHERE id = :id");
                $stmt->execute(['id' => $id]);

                $hist = $pdo->prepare(
                    "INSERT INTO status_pemesanan (pemesanan_id, status_lama, status_baru, diubah_oleh, keterangan)
                     VALUES (:oid, :status_lama, 'ditolak', :by, :ket)"
                );
                $hist->execute([
                    'oid'         => $id,
                    'status_lama' => $oldStatus !== '' ? $oldStatus : null,
                    'by'          => $actorId,
                    'ket'         => 'Dibatalkan/ditolak oleh Admin',
                ]);
                $pdo->commit();
                setFlash('info', 'Status pemesanan diperbarui.');
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
        }
        redirect('admin/orders.php');
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $cekPay = $pdo->prepare('SELECT COUNT(*) FROM pembayaran WHERE pemesanan_id = :id');
            $cekPay->execute(['id' => $id]);
            $cekRev = $pdo->prepare('SELECT COUNT(*) FROM ulasan WHERE pemesanan_id = :id');
            $cekRev->execute(['id' => $id]);

            if ((int) $cekPay->fetchColumn() > 0 || (int) $cekRev->fetchColumn() > 0) {
                setFlash('warning', 'Pemesanan tidak dapat dihapus kerana sudah memiliki data pembayaran atau ulasan.');
            } else {
                $pdo->beginTransaction();
                try {
                    $pdo->prepare('DELETE FROM status_pemesanan WHERE pemesanan_id = :id')->execute(['id' => $id]);
                    $pdo->prepare('DELETE FROM pemesanan WHERE id = :id')->execute(['id' => $id]);
                    $pdo->commit();
                    setFlash('success', 'Pemesanan berhasil dihapus.');
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }
            }
        }
        redirect('admin/orders.php');
    }
}

if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    if ($editId > 0) {
        $st = $pdo->prepare('SELECT * FROM pemesanan WHERE id = :id LIMIT 1');
        $st->execute(['id' => $editId]);
        $editOrder = $st->fetch() ?: null;
    }
}

$statusList = $allowedStatuses;
if ($editOrder && !empty($editOrder['status_terkini']) && !in_array((string) $editOrder['status_terkini'], $statusList, true)) {
    $statusList[] = (string) $editOrder['status_terkini'];
}

$q = trim((string) ($_GET['q'] ?? ''));
$sql = 'SELECT p.*, pl.nama_lengkap AS nama_pelanggan, py.nama_lengkap AS nama_penyedia, l.judul_layanan
        FROM pemesanan p
        LEFT JOIN users pl ON p.pelanggan_id = pl.id
        LEFT JOIN users py ON p.penyedia_id = py.id
        LEFT JOIN layanan l ON p.layanan_id = l.id
        WHERE 1=1';
$params = [];
if ($q !== '') {
    $sql .= ' AND (p.kode_pesanan LIKE :q_kode OR pl.nama_lengkap LIKE :q_pelanggan OR l.judul_layanan LIKE :q_layanan OR p.status_terkini LIKE :q_status)';
    $likeQ = '%' . $q . '%';
    $params['q_kode'] = $likeQ;
    $params['q_pelanggan'] = $likeQ;
    $params['q_layanan'] = $likeQ;
    $params['q_status'] = $likeQ;
}
$sql .= ' ORDER BY p.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

renderAdminHeader('Manajemen Pemesanan', 'orders');
?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger shadow-sm">
        <ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err); ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3">
                <h2 class="h6 fw-bold mb-0"><?= $editOrder ? 'Edit Pemesanan #' . (int) $editOrder['id'] : 'Tambah Pemesanan'; ?></h2>
            </div>
            <div class="card-body">
                <form method="post" action="<?= e(url('admin/orders.php')); ?>">
                    <input type="hidden" name="action" value="<?= $editOrder ? 'update' : 'create'; ?>">
                    <?php if ($editOrder): ?><input type="hidden" name="id" value="<?= (int) $editOrder['id']; ?>"><?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Kode Pesanan</label>
                        <input type="text" name="kode_pesanan" class="form-control" placeholder="Otomatis jika dikosongkan" value="<?= e((string) ($editOrder['kode_pesanan'] ?? '')); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Pelanggan <span class="text-danger">*</span></label>
                        <select name="pelanggan_id" class="form-select" required>
                            <option value="">-- Pilih Pelanggan --</option>
                            <?php foreach ($customers as $c): ?>
                                <option value="<?= (int) $c['id']; ?>" <?= ((int) ($editOrder['pelanggan_id'] ?? 0) === (int) $c['id']) ? 'selected' : ''; ?>>
                                    <?= e((string) $c['nama_lengkap']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Penyedia Jasa <span class="text-danger">*</span></label>
                        <select name="penyedia_id" class="form-select" required>
                            <option value="">-- Pilih Penyedia --</option>
                            <?php foreach ($providers as $p): ?>
                                <option value="<?= (int) $p['id']; ?>" <?= ((int) ($editOrder['penyedia_id'] ?? 0) === (int) $p['id']) ? 'selected' : ''; ?>>
                                    <?= e((string) $p['nama_lengkap']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Layanan <span class="text-danger">*</span></label>
                        <select name="layanan_id" id="layananSelect" class="form-select" required>
                            <option value="">-- Pilih Layanan --</option>
                            <?php foreach ($services as $s): ?>
                                <option value="<?= (int) $s['id']; ?>"
                                        data-harga="<?= e((string) (float) $s['harga']); ?>"
                                        data-deskripsi="<?= e((string) ($s['deskripsi'] ?? '')); ?>"
                                        <?= ((int) ($editOrder['layanan_id'] ?? 0) === (int) $s['id']) ? 'selected' : ''; ?>>
                                    <?= e((string) $s['judul_layanan']); ?> (<?= e(formatRupiah((float) $s['harga'])); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="layananPreview" class="form-text text-muted mt-1"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Deskripsi Kebutuhan <span class="text-danger">*</span></label>
                        <textarea name="deskripsi_kebutuhan" class="form-control" rows="2" required><?= e((string) ($editOrder['deskripsi_kebutuhan'] ?? '')); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tanggal Pengerjaan <span class="text-danger">*</span></label>
                        <input type="date" name="tanggal_pengerjaan" class="form-control" value="<?= e((string) ($editOrder['tanggal_pengerjaan'] ?? date('Y-m-d'))); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Alamat Lokasi</label>
                        <textarea name="alamat_lokasi" class="form-control" rows="2"><?= e((string) ($editOrder['alamat_lokasi'] ?? '')); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Total Biaya (Rp) <span class="text-danger">*</span></label>
                        <input type="number" name="total_biaya" step="1" min="1" class="form-control" value="<?= e((string) ($editOrder['total_biaya'] ?? '')); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Status Terkini</label>
                        <?php $stOrder = (string) ($editOrder['status_terkini'] ?? 'menunggu_konfirmasi'); ?>
                        <select name="status_terkini" class="form-select">
                            <?php foreach ($statusList as $stOpt): ?>
                                <option value="<?= e($stOpt); ?>" <?= $stOrder === $stOpt ? 'selected' : ''; ?>><?= e(str_replace('_', ' ', ucfirst($stOpt))); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Catatan Penyedia</label>
                        <textarea name="catatan_penyedia" class="form-control" rows="2"><?= e((string) ($editOrder['catatan_penyedia'] ?? '')); ?></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-check-lg me-1"></i>Simpan</button>
                        <?php if ($editOrder): ?><a href="<?= e(url('admin/orders.php')); ?>" class="btn btn-outline-secondary">Batal</a><?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3">
                <form method="get" action="<?= e(url('admin/orders.php')); ?>" class="d-flex gap-2">
                    <input type="text" name="q" class="form-control" placeholder="Cari kode, pelanggan, layanan, atau status..." value="<?= e($q); ?>">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Kode &amp; Pengerjaan</th>
                            <th>Pelanggan &amp; Layanan</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">Belum ada data pemesanan.</td></tr>
                        <?php else: ?>
                            <?php foreach ($orders as $ord): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= e((string) ($ord['kode_pesanan'] ?? '-')); ?></div>
                                        <small class="text-muted"><?= e((string) ($ord['tanggal_pengerjaan'] ?? '-')); ?></small>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?= e((string) ($ord['nama_pelanggan'] ?? '-')); ?></div>
                                        <small class="text-muted"><?= e((string) ($ord['judul_layanan'] ?? '-')); ?></small>
                                    </td>
                                    <td class="fw-semibold"><?= e(formatRupiah((float) ($ord['total_biaya'] ?? 0))); ?></td>
                                    <td><span class="badge bg-primary-subtle text-primary border"><?= e((string) ($ord['status_terkini'] ?? '-')); ?></span></td>
                                    <td class="text-end text-nowrap">
                                        <a href="<?= e(url('admin/orders.php?edit=' . (int) $ord['id'])); ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                        <?php if (($ord['status_terkini'] ?? '') !== 'ditolak'): ?>
                                            <form method="post" action="<?= e(url('admin/orders.php')); ?>" class="d-inline" data-confirm-action="Tolak/batalkan pemesanan ini?">
                                                <input type="hidden" name="action" value="cancel">
                                                <input type="hidden" name="id" value="<?= (int) $ord['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-warning"><i class="bi bi-x-circle"></i></button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="post" action="<?= e(url('admin/orders.php')); ?>" class="d-inline" data-confirm-action="Hapus pemesanan ini jika belum memiliki pembayaran atau ulasan?">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $ord['id']; ?>">
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    const sel = document.getElementById('layananSelect');
    const preview = document.getElementById('layananPreview');
    const totalInput = document.querySelector('input[name="total_biaya"]');
    if (!sel || !preview) return;

    function updatePreview(autoFillPrice) {
        const opt = sel.options[sel.selectedIndex];
        if (!opt || !opt.value) {
            preview.textContent = '';
            return;
        }
        const harga = opt.getAttribute('data-harga') || '';
        const desk = opt.getAttribute('data-deskripsi') || '';
        const hargaFmt = harga ? 'Rp ' + Number(harga).toLocaleString('id-ID') : '-';
        preview.textContent = desk ? ('Tarif dasar: ' + hargaFmt + ' — ' + desk) : ('Tarif dasar: ' + hargaFmt);

        if (autoFillPrice && totalInput && !totalInput.value && harga) {
            totalInput.value = harga;
        }
    }

    updatePreview(false);
    sel.addEventListener('change', function () {
        updatePreview(true);
    });
});
</script>

<?php renderAdminFooter(); ?>