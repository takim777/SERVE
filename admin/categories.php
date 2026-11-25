<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$pdo = adminDb();
$errors = [];
$editCat = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create' || $action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $nama = trim((string) ($_POST['nama_kategori'] ?? ''));
        $deskripsi = trim((string) ($_POST['deskripsi'] ?? ''));

        if ($nama === '' || mb_strlen($nama) < 2) {
            $errors[] = 'Nama kategori jasa wajib diisi minimal 2 karakter.';
        }

        if (empty($errors)) {
            if ($action === 'create') {
                $stmt = $pdo->prepare(
                    'INSERT INTO kategori_jasa (nama_kategori, deskripsi)
                     VALUES (:nama, :deskripsi)'
                );
                $stmt->execute([
                    'nama'      => $nama,
                    'deskripsi' => $deskripsi !== '' ? $deskripsi : null,
                ]);
                setFlash('success', 'Kategori jasa berhasil ditambahkan.');
                redirect('admin/categories.php');
            } else {
                if ($id <= 0) {
                    $errors[] = 'ID kategori tidak valid.';
                } else {
                    $stmt = $pdo->prepare(
                        'UPDATE kategori_jasa
                         SET nama_kategori = :nama, deskripsi = :deskripsi
                         WHERE id = :id'
                    );
                    $stmt->execute([
                        'nama'      => $nama,
                        'deskripsi' => $deskripsi !== '' ? $deskripsi : null,
                        'id'        => $id,
                    ]);
                    setFlash('success', 'Kategori jasa berhasil diperbarui.');
                    redirect('admin/categories.php');
                }
            }
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $cek = $pdo->prepare('SELECT COUNT(*) FROM layanan WHERE kategori_id = :id');
            $cek->execute(['id' => $id]);
            if ((int) $cek->fetchColumn() > 0) {
                setFlash('warning', 'Kategori tidak dapat dihapus karena masih dipakai oleh data layanan. Hapus atau pindahkan layanan terkait terlebih dahulu.');
            } else {
                $del = $pdo->prepare('DELETE FROM kategori_jasa WHERE id = :id');
                $del->execute(['id' => $id]);
                setFlash('success', 'Kategori jasa berhasil dihapus.');
            }
        }
        redirect('admin/categories.php');
    }
}

if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    if ($editId > 0) {
        $st = $pdo->prepare('SELECT * FROM kategori_jasa WHERE id = :id LIMIT 1');
        $st->execute(['id' => $editId]);
        $editCat = $st->fetch() ?: null;
    }
}

$q = trim((string) ($_GET['q'] ?? ''));
$sql = 'SELECT k.*, (SELECT COUNT(*) FROM layanan l WHERE l.kategori_id = k.id) AS jumlah_layanan
        FROM kategori_jasa k
        WHERE 1=1';
$params = [];

if ($q !== '') {
    $sql .= ' AND (k.nama_kategori LIKE :q_nama OR k.deskripsi LIKE :q_desk)';
    $likeQ = '%' . $q . '%';
    $params['q_nama'] = $likeQ;
    $params['q_desk'] = $likeQ;
}

$sql .= ' ORDER BY k.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$categories = $stmt->fetchAll();

renderAdminHeader('Manajemen Kategori Jasa', 'categories');
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
                <h2 class="h6 fw-bold mb-0"><?= $editCat ? 'Edit Kategori #' . (int) $editCat['id'] : 'Tambah Kategori Jasa'; ?></h2>
            </div>
            <div class="card-body">
                <form method="post" action="<?= e(url('admin/categories.php')); ?>">
                    <input type="hidden" name="action" value="<?= $editCat ? 'update' : 'create'; ?>">
                    <?php if ($editCat): ?><input type="hidden" name="id" value="<?= (int) $editCat['id']; ?>"><?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Kategori <span class="text-danger">*</span></label>
                        <input type="text" name="nama_kategori" class="form-control" value="<?= e((string) ($editCat['nama_kategori'] ?? '')); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Deskripsi</label>
                        <textarea name="deskripsi" class="form-control" rows="3"><?= e((string) ($editCat['deskripsi'] ?? '')); ?></textarea>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-check-lg me-1"></i>Simpan</button>
                        <?php if ($editCat): ?><a href="<?= e(url('admin/categories.php')); ?>" class="btn btn-outline-secondary">Batal</a><?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3">
                <form method="get" action="<?= e(url('admin/categories.php')); ?>" class="d-flex gap-2">
                    <input type="text" name="q" class="form-control" placeholder="Cari nama atau deskripsi kategori..." value="<?= e($q); ?>">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Kategori</th>
                            <th>Layanan</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($categories)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-4">Belum ada kategori jasa.</td></tr>
                        <?php else: ?>
                            <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= e((string) $cat['nama_kategori']); ?></div>
                                        <small class="text-muted"><?= e((string) ($cat['deskripsi'] ?: '-')); ?></small>
                                    </td>
                                    <td><span class="badge bg-light text-dark border"><?= (int) $cat['jumlah_layanan']; ?> layanan</span></td>
                                    <td class="text-end text-nowrap">
                                        <a href="<?= e(url('admin/categories.php?edit=' . (int) $cat['id'])); ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                        <form method="post" action="<?= e(url('admin/categories.php')); ?>" class="d-inline" data-confirm-action="Hapus kategori ini jika belum dipakai layanan?">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $cat['id']; ?>">
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