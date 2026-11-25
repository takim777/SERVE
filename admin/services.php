<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$pdo = adminDb();
$errors = [];
$editService = null;

$providers = $pdo->query("SELECT id, nama_lengkap FROM users WHERE role = 'penyedia' ORDER BY nama_lengkap ASC")->fetchAll();
$categories = $pdo->query('SELECT id, nama_kategori FROM kategori_jasa ORDER BY nama_kategori ASC')->fetchAll();

$providerIds = array_map(static fn(array $row): int => (int) $row['id'], $providers);
$categoryIds = array_map(static fn(array $row): int => (int) $row['id'], $categories);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create' || $action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $penyediaId = (int) ($_POST['penyedia_id'] ?? 0);
        $kategoriId = (int) ($_POST['kategori_id'] ?? 0);
        $judul = trim((string) ($_POST['judul_layanan'] ?? ''));
        $deskripsi = trim((string) ($_POST['deskripsi'] ?? ''));
        $harga = (float) ($_POST['harga'] ?? 0);
        $tersedia = (int) ($_POST['tersedia'] ?? 1);

        if ($penyediaId <= 0 || !in_array($penyediaId, $providerIds, true)) {
            $errors[] = 'Penyedia jasa wajib dipilih dan harus valid.';
        }
        if ($kategoriId <= 0 || !in_array($kategoriId, $categoryIds, true)) {
            $errors[] = 'Kategori jasa wajib dipilih dan harus valid.';
        }
        if ($judul === '' || mb_strlen($judul) < 3) {
            $errors[] = 'Judul layanan wajib diisi minimal 3 karakter.';
        }
        if ($harga <= 0) {
            $errors[] = 'Harga layanan harus lebih besar dari 0.';
        }
        if ($action === 'update' && $id <= 0) {
            $errors[] = 'ID layanan tidak valid.';
        }

        if (empty($errors)) {
            if ($action === 'create') {
                $stmt = $pdo->prepare(
                    'INSERT INTO layanan (penyedia_id, kategori_id, judul_layanan, deskripsi, harga)
                     VALUES (:penyedia_id, :kategori_id, :judul, :deskripsi, :harga)'
                );
                $stmt->execute([
                    'penyedia_id' => $penyediaId,
                    'kategori_id' => $kategoriId,
                    'judul'       => $judul,
                    'deskripsi'   => $deskripsi !== '' ? $deskripsi : null,
                    'harga'       => $harga,
                ]);
                setFlash('success', 'Layanan berhasil ditambahkan.');
                redirect('admin/services.php');
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE layanan
                     SET penyedia_id = :penyedia_id, kategori_id = :kategori_id, judul_layanan = :judul,
                         deskripsi = :deskripsi, harga = :harga
                     WHERE id = :id'
                );
                $stmt->execute([
                    'penyedia_id' => $penyediaId,
                    'kategori_id' => $kategoriId,
                    'judul'       => $judul,
                    'deskripsi'   => $deskripsi !== '' ? $deskripsi : null,
                    'harga'       => $harga,
                    'id'          => $id,
                ]);
                setFlash('success', 'Layanan berhasil diperbarui.');
                redirect('admin/services.php');
            }
        }
    } elseif ($action === 'deactivate') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            setFlash('info', 'Status layanan diperbarui.');
        }
        redirect('admin/services.php');
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $cek = $pdo->prepare('SELECT COUNT(*) FROM pemesanan WHERE layanan_id = :id');
            $cek->execute(['id' => $id]);
            if ((int) $cek->fetchColumn() > 0) {
                setFlash('warning', 'Layanan tidak dapat dihapus karena sudah ada dalam data pemesanan.');
            } else {
                $del = $pdo->prepare('DELETE FROM layanan WHERE id = :id');
                $del->execute(['id' => $id]);
                setFlash('success', 'Layanan berhasil dihapus.');
            }
        }
        redirect('admin/services.php');
    }
}

if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    if ($editId > 0) {
        $st = $pdo->prepare('SELECT * FROM layanan WHERE id = :id LIMIT 1');
        $st->execute(['id' => $editId]);
        $editService = $st->fetch() ?: null;
    }
}

$q = trim((string) ($_GET['q'] ?? ''));
$sql = 'SELECT l.*, u.nama_lengkap AS nama_penyedia, k.nama_kategori
        FROM layanan l
        LEFT JOIN users u ON l.penyedia_id = u.id
        LEFT JOIN kategori_jasa k ON l.kategori_id = k.id
        WHERE 1=1';
$params = [];
if ($q !== '') {
    $sql .= ' AND (l.judul_layanan LIKE :q_judul OR u.nama_lengkap LIKE :q_penyedia OR k.nama_kategori LIKE :q_kategori)';
    $likeQ = '%' . $q . '%';
    $params['q_judul'] = $likeQ;
    $params['q_penyedia'] = $likeQ;
    $params['q_kategori'] = $likeQ;
}
$sql .= ' ORDER BY l.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$services = $stmt->fetchAll();

renderAdminHeader('Manajemen Layanan', 'services');
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
                <h2 class="h6 fw-bold mb-0"><?= $editService ? 'Edit Layanan #' . (int) $editService['id'] : 'Tambah Layanan'; ?></h2>
            </div>
            <div class="card-body">
                <form method="post" action="<?= e(url('admin/services.php')); ?>">
                    <input type="hidden" name="action" value="<?= $editService ? 'update' : 'create'; ?>">
                    <?php if ($editService): ?><input type="hidden" name="id" value="<?= (int) $editService['id']; ?>"><?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Penyedia Jasa <span class="text-danger">*</span></label>
                        <select name="penyedia_id" class="form-select" required>
                            <option value="">-- Pilih Penyedia --</option>
                            <?php foreach ($providers as $p): ?>
                                <option value="<?= (int) $p['id']; ?>" <?= ((int) ($editService['penyedia_id'] ?? 0) === (int) $p['id']) ? 'selected' : ''; ?>>
                                    <?= e((string) $p['nama_lengkap']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Kategori Jasa <span class="text-danger">*</span></label>
                        <select name="kategori_id" class="form-select" required>
                            <option value="">-- Pilih Kategori --</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= (int) $c['id']; ?>" <?= ((int) ($editService['kategori_id'] ?? 0) === (int) $c['id']) ? 'selected' : ''; ?>>
                                    <?= e((string) $c['nama_kategori']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Judul Layanan <span class="text-danger">*</span></label>
                        <input type="text" name="judul_layanan" class="form-control" value="<?= e((string) ($editService['judul_layanan'] ?? '')); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Harga (Rp) <span class="text-danger">*</span></label>
                        <input type="number" name="harga" step="1" min="1" class="form-control" value="<?= e((string) ($editService['harga'] ?? '')); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Ketersediaan</label>
                        <?php $avail = (int) ($editService['tersedia'] ?? 1); ?>
                        <select name="tersedia" class="form-select">
                            <option value="1" <?= $avail === 1 ? 'selected' : ''; ?>>Tersedia</option>
                            <option value="0" <?= $avail === 0 ? 'selected' : ''; ?>>Tidak Tersedia</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Deskripsi</label>
                        <textarea name="deskripsi" class="form-control" rows="2"><?= e((string) ($editService['deskripsi'] ?? '')); ?></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-check-lg me-1"></i>Simpan</button>
                        <?php if ($editService): ?><a href="<?= e(url('admin/services.php')); ?>" class="btn btn-outline-secondary">Batal</a><?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3">
                <form method="get" action="<?= e(url('admin/services.php')); ?>" class="d-flex gap-2">
                    <input type="text" name="q" class="form-control" placeholder="Cari judul layanan, kategori, atau penyedia..." value="<?= e($q); ?>">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Layanan</th>
                            <th>Penyedia &amp; Kategori</th>
                            <th>Harga</th>
                            <th>Status</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($services)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">Belum ada data layanan.</td></tr>
                        <?php else: ?>
                            <?php foreach ($services as $srv): ?>
                                <tr>
                                    <td class="fw-semibold"><?= e((string) $srv['judul_layanan']); ?></td>
                                    <td>
                                        <div><?= e((string) ($srv['nama_penyedia'] ?? '-')); ?></div>
                                        <small class="text-muted"><?= e((string) ($srv['nama_kategori'] ?? '-')); ?></small>
                                    </td>
                                    <td class="fw-semibold"><?= e(formatRupiah((float) $srv['harga'])); ?></td>
                                    <td>
                                        <span class="badge bg-success">Tersedia</span>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <a href="<?= e(url('admin/services.php?edit=' . (int) $srv['id'])); ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                        <form method="post" action="<?= e(url('admin/services.php')); ?>" class="d-inline" data-confirm-action="Hapus layanan ini jika belum pernah dipesan?">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $srv['id']; ?>">
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