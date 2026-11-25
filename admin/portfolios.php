<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$pdo = adminDb();
$errors = [];
$editPort = null;

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
        $judulKarya = trim((string) ($_POST['judul_karya'] ?? ''));
        $fileGambar = trim((string) ($_POST['file_gambar'] ?? ''));
        $tautanEksternal = trim((string) ($_POST['tautan_eksternal'] ?? ''));
        $tanggalSelesai = trim((string) ($_POST['tanggal_selesai'] ?? ''));
        $deskripsi = trim((string) ($_POST['deskripsi'] ?? ''));

        if ($penyediaId <= 0 || !in_array($penyediaId, $providerIds, true)) {
            $errors[] = 'Penyedia jasa wajib dipilih dan harus valid.';
        }
        if ($kategoriId > 0 && !in_array($kategoriId, $categoryIds, true)) {
            $errors[] = 'Kategori portofolio tidak valid.';
        }
        if ($judulKarya === '' || mb_strlen($judulKarya) < 3) {
            $errors[] = 'Judul karya wajib diisi minimal 3 karakter.';
        }
        if ($fileGambar === '') {
            $errors[] = 'Nama file / path gambar wajib diisi.';
        }
        if ($tanggalSelesai !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalSelesai)) {
            $errors[] = 'Format tanggal selesai tidak valid (gunakan YYYY-MM-DD).';
        }
        if ($action === 'update' && $id <= 0) {
            $errors[] = 'ID portofolio tidak valid.';
        }

        if (empty($errors)) {
            $kategoriVal = $kategoriId > 0 ? $kategoriId : null;
            $tautanVal = $tautanEksternal !== '' ? $tautanEksternal : null;
            $tanggalVal = $tanggalSelesai !== '' ? $tanggalSelesai : null;
            $deskripsiVal = $deskripsi !== '' ? $deskripsi : null;

            if ($action === 'create') {
                $stmt = $pdo->prepare(
                    'INSERT INTO portofolio (penyedia_id, kategori_id, judul_karya, deskripsi, file_gambar, tautan_eksternal, tanggal_selesai)
                     VALUES (:penyedia_id, :kategori_id, :judul_karya, :deskripsi, :file_gambar, :tautan_eksternal, :tanggal_selesai)'
                );
                $stmt->execute([
                    'penyedia_id'      => $penyediaId,
                    'kategori_id'      => $kategoriVal,
                    'judul_karya'      => $judulKarya,
                    'deskripsi'        => $deskripsiVal,
                    'file_gambar'      => $fileGambar,
                    'tautan_eksternal' => $tautanVal,
                    'tanggal_selesai'  => $tanggalVal,
                ]);
                setFlash('success', 'Portofolio berhasil ditambahkan.');
                redirect('admin/portfolios.php');
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE portofolio
                     SET penyedia_id = :penyedia_id,
                         kategori_id = :kategori_id,
                         judul_karya = :judul_karya,
                         deskripsi = :deskripsi,
                         file_gambar = :file_gambar,
                         tautan_eksternal = :tautan_eksternal,
                         tanggal_selesai = :tanggal_selesai
                     WHERE id = :id'
                );
                $stmt->execute([
                    'penyedia_id'      => $penyediaId,
                    'kategori_id'      => $kategoriVal,
                    'judul_karya'      => $judulKarya,
                    'deskripsi'        => $deskripsiVal,
                    'file_gambar'      => $fileGambar,
                    'tautan_eksternal' => $tautanVal,
                    'tanggal_selesai'  => $tanggalVal,
                    'id'               => $id,
                ]);
                setFlash('success', 'Portofolio berhasil diperbarui.');
                redirect('admin/portfolios.php');
            }
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $del = $pdo->prepare('DELETE FROM portofolio WHERE id = :id');
            $del->execute(['id' => $id]);
            setFlash('success', 'Portofolio berhasil dihapus.');
        }
        redirect('admin/portfolios.php');
    }
}

if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    if ($editId > 0) {
        $st = $pdo->prepare('SELECT * FROM portofolio WHERE id = :id LIMIT 1');
        $st->execute(['id' => $editId]);
        $editPort = $st->fetch() ?: null;
    }
}

$q = trim((string) ($_GET['q'] ?? ''));
$sql = 'SELECT pf.*, u.nama_lengkap AS nama_penyedia, k.nama_kategori
        FROM portofolio pf
        LEFT JOIN users u ON pf.penyedia_id = u.id
        LEFT JOIN kategori_jasa k ON pf.kategori_id = k.id
        WHERE 1=1';
$params = [];
if ($q !== '') {
    $sql .= ' AND (pf.judul_karya LIKE :q_judul OR pf.deskripsi LIKE :q_desk OR u.nama_lengkap LIKE :q_penyedia OR k.nama_kategori LIKE :q_kategori)';
    $likeQ = '%' . $q . '%';
    $params['q_judul'] = $likeQ;
    $params['q_desk'] = $likeQ;
    $params['q_penyedia'] = $likeQ;
    $params['q_kategori'] = $likeQ;
}
$sql .= ' ORDER BY pf.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$portfolios = $stmt->fetchAll();

renderAdminHeader('Manajemen Portofolio', 'portfolios');
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
                <h2 class="h6 fw-bold mb-0"><?= $editPort ? 'Edit Portofolio #' . (int) $editPort['id'] : 'Tambah Portofolio'; ?></h2>
            </div>
            <div class="card-body">
                <form method="post" action="<?= e(url('admin/portfolios.php')); ?>">
                    <input type="hidden" name="action" value="<?= $editPort ? 'update' : 'create'; ?>">
                    <?php if ($editPort): ?><input type="hidden" name="id" value="<?= (int) $editPort['id']; ?>"><?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Penyedia Jasa <span class="text-danger">*</span></label>
                        <select name="penyedia_id" class="form-select" required>
                            <option value="">-- Pilih Penyedia --</option>
                            <?php foreach ($providers as $p): ?>
                                <option value="<?= (int) $p['id']; ?>" <?= ((int) ($editPort['penyedia_id'] ?? 0) === (int) $p['id']) ? 'selected' : ''; ?>>
                                    <?= e((string) $p['nama_lengkap']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Kategori Jasa</label>
                        <select name="kategori_id" class="form-select">
                            <option value="">-- Tanpa Kategori --</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= (int) $c['id']; ?>" <?= ((int) ($editPort['kategori_id'] ?? 0) === (int) $c['id']) ? 'selected' : ''; ?>>
                                    <?= e((string) $c['nama_kategori']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Judul Karya <span class="text-danger">*</span></label>
                        <input type="text" name="judul_karya" class="form-control" value="<?= e((string) ($editPort['judul_karya'] ?? '')); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">File Gambar <span class="text-danger">*</span></label>
                        <input type="text" name="file_gambar" class="form-control" placeholder="contoh: uploads/portfolios/karya1.jpg" value="<?= e((string) ($editPort['file_gambar'] ?? '')); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tautan Eksternal</label>
                        <input type="text" name="tautan_eksternal" class="form-control" placeholder="https://..." value="<?= e((string) ($editPort['tautan_eksternal'] ?? '')); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tanggal Selesai</label>
                        <input type="date" name="tanggal_selesai" class="form-control" value="<?= e((string) ($editPort['tanggal_selesai'] ?? '')); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Deskripsi</label>
                        <textarea name="deskripsi" class="form-control" rows="3"><?= e((string) ($editPort['deskripsi'] ?? '')); ?></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-check-lg me-1"></i>Simpan</button>
                        <?php if ($editPort): ?><a href="<?= e(url('admin/portfolios.php')); ?>" class="btn btn-outline-secondary">Batal</a><?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3">
                <form method="get" action="<?= e(url('admin/portfolios.php')); ?>" class="d-flex gap-2">
                    <input type="text" name="q" class="form-control" placeholder="Cari judul karya, kategori, atau penyedia..." value="<?= e($q); ?>">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Karya</th>
                            <th>Penyedia &amp; Kategori</th>
                            <th>File Gambar</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($portfolios)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">Belum ada portofolio.</td></tr>
                        <?php else: ?>
                            <?php foreach ($portfolios as $pf): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= e((string) ($pf['judul_karya'] ?? '-')); ?></div>
                                        <small class="text-muted"><?= e((string) ($pf['deskripsi'] ?: '-')); ?></small>
                                        <?php if (!empty($pf['tanggal_selesai'])): ?>
                                            <div><small class="text-muted">Selesai: <?= e((string) $pf['tanggal_selesai']); ?></small></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div><?= e((string) ($pf['nama_penyedia'] ?? '-')); ?></div>
                                        <small class="text-muted"><?= e((string) ($pf['nama_kategori'] ?: 'Tanpa Kategori')); ?></small>
                                    </td>
                                    <td>
                                        <div><small class="text-muted"><?= e((string) ($pf['file_gambar'] ?? '-')); ?></small></div>
                                        <?php if (!empty($pf['tautan_eksternal'])): ?>
                                            <a href="<?= e((string) $pf['tautan_eksternal']); ?>" target="_blank" rel="noopener noreferrer" class="small">Link Eksternal</a>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <a href="<?= e(url('admin/portfolios.php?edit=' . (int) $pf['id'])); ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                        <form method="post" action="<?= e(url('admin/portfolios.php')); ?>" class="d-inline" data-confirm-action="Hapus portofolio ini?">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $pf['id']; ?>">
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