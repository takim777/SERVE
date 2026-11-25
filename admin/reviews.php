<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../includes/business_workflow.php';

$pdo = adminDb();
$errors = [];
$editReview = null;

$orderCols = serveTableColumns($pdo, 'pemesanan');
$orderStatusCol = serveFirstMatchingColumn($orderCols, ['status_terkini', 'status']) ?? 'status_terkini';

$ordersList = $pdo->query(
    "SELECT p.id, p.kode_pesanan, p.pelanggan_id, p.penyedia_id, p.layanan_id, p.{$orderStatusCol} AS status_pesanan,
            pl.nama_lengkap AS nama_pelanggan,
            py.nama_lengkap AS nama_penyedia,
            l.judul_layanan
     FROM pemesanan p
     LEFT JOIN users pl ON p.pelanggan_id = pl.id
     LEFT JOIN users py ON p.penyedia_id = py.id
     LEFT JOIN layanan l ON p.layanan_id = l.id
     ORDER BY p.id DESC"
)->fetchAll();

$ordersById = [];
foreach ($ordersList as $ord) {
    $ordersById[(int) $ord['id']] = $ord;
}

$customers = $pdo->query(
    "SELECT id, nama_lengkap FROM users WHERE role = 'pelanggan' ORDER BY nama_lengkap ASC"
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create' || $action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $pemesananId = (int) ($_POST['pemesanan_id'] ?? 0);
        $pelangganId = (int) ($_POST['pelanggan_id'] ?? 0);
        $rating = (int) ($_POST['rating'] ?? 5);
        $komentar = trim((string) ($_POST['komentar'] ?? ''));

        $selectedOrder = $ordersById[$pemesananId] ?? null;
        $orderCustomerId = (int) ($selectedOrder['pelanggan_id'] ?? 0);
        $orderStatus = strtolower(trim((string) ($selectedOrder['status_pesanan'] ?? '')));
        $penyediaId = (int) ($selectedOrder['penyedia_id'] ?? 0);
        $layananId = (int) ($selectedOrder['layanan_id'] ?? 0);

        if ($pelangganId <= 0 && $orderCustomerId > 0) {
            $pelangganId = $orderCustomerId;
        }

        if ($pemesananId <= 0 || !$selectedOrder) {
            $errors[] = 'Pemesanan wajib dipilih dan harus valid.';
        } elseif ($orderStatus !== 'selesai') {
            $errors[] = 'Ulasan hanya dapat dicatat untuk pesanan yang berstatus Selesai.';
        }

        if ($pelangganId <= 0) {
            $errors[] = 'Pelanggan wajib dipilih.';
        } elseif ($orderCustomerId > 0 && $pelangganId !== $orderCustomerId) {
            $errors[] = 'Pelanggan yang dipilih tidak sesuai dengan pemilik pemesanan.';
        }

        if ($penyediaId <= 0) {
            $errors[] = 'Data penyedia jasa pada pemesanan terpilih tidak ditemukan atau tidak valid.';
        } else {
            $chkProvider = $pdo->prepare('SELECT id FROM users WHERE id = :pid LIMIT 1');
            $chkProvider->execute(['pid' => $penyediaId]);
            if (!$chkProvider->fetchColumn()) {
                $errors[] = 'Penyedia jasa pada pemesanan terpilih tidak terdaftar di tabel users.';
            }
        }

        if ($layananId <= 0) {
            $errors[] = 'Data layanan pada pemesanan terpilih tidak ditemukan atau tidak valid.';
        } else {
            $chkService = $pdo->prepare('SELECT id FROM layanan WHERE id = :lid LIMIT 1');
            $chkService->execute(['lid' => $layananId]);
            if (!$chkService->fetchColumn()) {
                $errors[] = 'Layanan pada pemesanan terpilih tidak terdaftar di tabel layanan.';
            }
        }

        if ($rating < 1 || $rating > 5) {
            $errors[] = 'Rating harus berada di antara 1 sampai 5.';
        }
        if ($komentar === '') {
            $errors[] = 'Komentar ulasan wajib diisi.';
        }
        if ($action === 'update' && $id <= 0) {
            $errors[] = 'ID ulasan tidak valid.';
        }

        if (empty($errors) && $action === 'update' && $id > 0) {
            $checkCur = $pdo->prepare('SELECT id FROM ulasan WHERE id = :id LIMIT 1');
            $checkCur->execute(['id' => $id]);
            if (!$checkCur->fetch()) {
                $errors[] = 'Data ulasan yang ingin diubah tidak ditemukan.';
            }
        }

        if (empty($errors)) {
            $dupSql = 'SELECT id FROM ulasan WHERE pemesanan_id = :pemesanan_id';
            $dupParams = ['pemesanan_id' => $pemesananId];
            if ($action === 'update') {
                $dupSql .= ' AND id != :id';
                $dupParams['id'] = $id;
            }
            $dupSql .= ' LIMIT 1';
            $dupStmt = $pdo->prepare($dupSql);
            $dupStmt->execute($dupParams);
            if ($dupStmt->fetch()) {
                $errors[] = 'Pemesanan tersebut sudah memiliki ulasan. Gunakan fitur Edit untuk mengubah ulasan yang ada.';
            }
        }

        if (empty($errors)) {
            if ($action === 'create') {
                $stmt = $pdo->prepare(
                    'INSERT INTO ulasan (pemesanan_id, pelanggan_id, penyedia_id, layanan_id, rating, komentar)
                     VALUES (:pemesanan_id, :pelanggan_id, :penyedia_id, :layanan_id, :rating, :komentar)'
                );
                $stmt->execute([
                    'pemesanan_id' => $pemesananId,
                    'pelanggan_id' => $pelangganId,
                    'penyedia_id'  => $penyediaId,
                    'layanan_id'   => $layananId,
                    'rating'       => $rating,
                    'komentar'     => $komentar,
                ]);
                setFlash('success', 'Ulasan berhasil ditambahkan.');
                redirect('admin/reviews.php');
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE ulasan
                     SET pemesanan_id = :pemesanan_id,
                         pelanggan_id = :pelanggan_id,
                         penyedia_id = :penyedia_id,
                         layanan_id = :layanan_id,
                         rating = :rating,
                         komentar = :komentar
                     WHERE id = :id'
                );
                $stmt->execute([
                    'pemesanan_id' => $pemesananId,
                    'pelanggan_id' => $pelangganId,
                    'penyedia_id'  => $penyediaId,
                    'layanan_id'   => $layananId,
                    'rating'       => $rating,
                    'komentar'     => $komentar,
                    'id'           => $id,
                ]);
                setFlash('success', 'Ulasan berhasil diperbarui.');
                redirect('admin/reviews.php');
            }
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $del = $pdo->prepare('DELETE FROM ulasan WHERE id = :id');
            $del->execute(['id' => $id]);
            setFlash('success', 'Ulasan berhasil dihapus.');
        }
        redirect('admin/reviews.php');
    }
}

if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    if ($editId > 0) {
        $st = $pdo->prepare('SELECT * FROM ulasan WHERE id = :id LIMIT 1');
        $st->execute(['id' => $editId]);
        $editReview = $st->fetch() ?: null;
    }
}

$q = trim((string) ($_GET['q'] ?? ''));
$sql = 'SELECT r.*,
               u.nama_lengkap AS nama_pelanggan,
               py.nama_lengkap AS nama_penyedia,
               l.judul_layanan,
               p.kode_pesanan
        FROM ulasan r
        LEFT JOIN users u ON r.pelanggan_id = u.id
        LEFT JOIN users py ON r.penyedia_id = py.id
        LEFT JOIN layanan l ON r.layanan_id = l.id
        LEFT JOIN pemesanan p ON r.pemesanan_id = p.id
        WHERE 1=1';
$params = [];
if ($q !== '') {
    $sql .= ' AND (r.komentar LIKE :q_komentar OR u.nama_lengkap LIKE :q_pelanggan OR py.nama_lengkap LIKE :q_penyedia OR l.judul_layanan LIKE :q_layanan OR p.kode_pesanan LIKE :q_kode)';
    $likeQ = '%' . $q . '%';
    $params['q_komentar'] = $likeQ;
    $params['q_pelanggan'] = $likeQ;
    $params['q_penyedia'] = $likeQ;
    $params['q_layanan'] = $likeQ;
    $params['q_kode'] = $likeQ;
}
$sql .= ' ORDER BY r.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reviews = $stmt->fetchAll();

renderAdminHeader('Manajemen Ulasan', 'reviews');
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
                <h2 class="h6 fw-bold mb-0"><?= $editReview ? 'Edit Ulasan #' . (int) $editReview['id'] : 'Tambah Ulasan'; ?></h2>
            </div>
            <div class="card-body">
                <form method="post" action="<?= e(url('admin/reviews.php')); ?>">
                    <input type="hidden" name="action" value="<?= $editReview ? 'update' : 'create'; ?>">
                    <?php if ($editReview): ?><input type="hidden" name="id" value="<?= (int) $editReview['id']; ?>"><?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Pemesanan <span class="text-danger">*</span></label>
                        <?php $selOrdId = (int) ($_POST['pemesanan_id'] ?? ($editReview['pemesanan_id'] ?? 0)); ?>
                        <select name="pemesanan_id" class="form-select" required>
                            <option value="">-- Pilih Pemesanan --</option>
                            <?php foreach ($ordersList as $o): ?>
                                <?php $ordSt = (string) ($o['status_pesanan'] ?? ''); ?>
                                <option value="<?= (int) $o['id']; ?>" <?= $selOrdId === (int) $o['id'] ? 'selected' : ''; ?>>
                                    <?= e((string) ($o['kode_pesanan'] ?: ('ORD-' . $o['id']))); ?>
                                    <?php if (!empty($o['nama_pelanggan'])): ?>
- <?= e((string) $o['nama_pelanggan']); ?>
                                    <?php endif; ?>
                                    <?php if (!empty($o['judul_layanan'])): ?>
                                        (<?= e((string) $o['judul_layanan']); ?>)
                                    <?php endif; ?>
                                    [<?= e(serveOrderStatusLabel($ordSt)); ?>]
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Hanya pesanan berstatus <strong>Selesai</strong> yang dapat diulas.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Pelanggan <span class="text-danger">*</span></label>
                        <?php $selCustId = (int) ($_POST['pelanggan_id'] ?? ($editReview['pelanggan_id'] ?? 0)); ?>
                        <select name="pelanggan_id" class="form-select" required>
                            <option value="">-- Pilih Pelanggan --</option>
                            <?php foreach ($customers as $c): ?>
                                <option value="<?= (int) $c['id']; ?>" <?= $selCustId === (int) $c['id'] ? 'selected' : ''; ?>>
                                    <?= e((string) $c['nama_lengkap']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Rating (1-5) <span class="text-danger">*</span></label>
                        <?php $rt = (int) ($_POST['rating'] ?? ($editReview['rating'] ?? 5)); ?>
                        <select name="rating" class="form-select" required>
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <option value="<?= $i; ?>" <?= $rt === $i ? 'selected' : ''; ?>><?= $i; ?> Bintang</option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Komentar <span class="text-danger">*</span></label>
                        <textarea name="komentar" class="form-control" rows="3" required><?= e((string) ($_POST['komentar'] ?? ($editReview['komentar'] ?? ''))); ?></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-check-lg me-1"></i>Simpan</button>
                        <?php if ($editReview): ?><a href="<?= e(url('admin/reviews.php')); ?>" class="btn btn-outline-secondary">Batal</a><?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3">
                <form method="get" action="<?= e(url('admin/reviews.php')); ?>" class="d-flex gap-2">
                    <input type="text" name="q" class="form-control" placeholder="Cari komentar, pelanggan, penyedia, layanan, atau kode pesanan..." value="<?= e($q); ?>">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Pelanggan &amp; Pesanan</th>
                            <th>Rating</th>
                            <th>Komentar</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reviews)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">Belum ada ulasan.</td></tr>
                        <?php else: ?>
                            <?php foreach ($reviews as $rev): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= e((string) ($rev['nama_pelanggan'] ?? '-')); ?></div>
                                        <small class="text-muted">
                                            <?= e((string) ($rev['kode_pesanan'] ?? ('#' . $rev['pemesanan_id']))); ?>
                                            <?php if (!empty($rev['judul_layanan'])): ?>
- <?= e((string) $rev['judul_layanan']); ?>
                                            <?php endif; ?>
                                            <?php if (!empty($rev['nama_penyedia'])): ?>
- Penyedia: <?= e((string) $rev['nama_penyedia']); ?>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td><span class="badge bg-primary"><?= (int) $rev['rating']; ?>/5</span></td>
                                    <td><?= e((string) $rev['komentar']); ?></td>
                                    <td class="text-end text-nowrap">
                                        <a href="<?= e(url('admin/reviews.php?edit=' . (int) $rev['id'])); ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                        <form method="post" action="<?= e(url('admin/reviews.php')); ?>" class="d-inline" data-confirm-action="Hapus ulasan ini?">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $rev['id']; ?>">
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