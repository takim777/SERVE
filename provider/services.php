<?php
declare(strict_types=1);

$pageTitle = 'Kelola Layanan';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/shared_functions.php';

requireRole('penyedia');

$pdo = getDBConnection();
$user = currentUser();
$penyediaId = (int) ($user['id'] ?? 0);

$categories = $pdo->query('SELECT * FROM kategori_jasa ORDER BY nama_kategori ASC')->fetchAll();

// Deteksi nama kolom pada tabel layanan secara aman
$colsStmt = $pdo->query('SHOW COLUMNS FROM layanan');
$srvCols = array_column($colsStmt->fetchAll(), 'Field');

$titleCol = 'judul_layanan';
foreach (['judul_layanan', 'nama_layanan', 'judul', 'nama'] as $candidate) {
    if (in_array($candidate, $srvCols, true)) {
        $titleCol = $candidate;
        break;
    }
}

$priceCol = null;
foreach (['harga_patokan', 'harga', 'tarif', 'harga_layanan', 'biaya'] as $candidate) {
    if (in_array($candidate, $srvCols, true)) {
        $priceCol = $candidate;
        break;
    }
}

$descCol = null;
foreach (['deskripsi', 'keterangan', 'detail'] as $candidate) {
    if (in_array($candidate, $srvCols, true)) {
        $descCol = $candidate;
        break;
    }
}

$statusCol = null;
foreach (['status_layanan', 'status'] as $candidate) {
    if (in_array($candidate, $srvCols, true)) {
        $statusCol = $candidate;
        break;
    }
}

$hasProviderCol = in_array('penyedia_id', $srvCols, true);
$hasCategoryCol = in_array('kategori_id', $srvCols, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'create');

    if ($action === 'create') {
        $kategoriId = (int) ($_POST['kategori_id'] ?? 0);
        $judul = trim((string) ($_POST['judul_layanan'] ?? ''));
        $deskripsi = trim((string) ($_POST['deskripsi'] ?? ''));
        $harga = (float) ($_POST['harga_patokan'] ?? 0);
        $status = (string) ($_POST['status_layanan'] ?? 'aktif');
        if (!in_array($status, ['aktif', 'nonaktif'], true)) {
            $status = 'aktif';
        }

        if ($judul === '' || $harga < 0 || ($hasCategoryCol && $kategoriId <= 0)) {
            setFlash('danger', 'Kategori, judul layanan, dan harga wajib diisi dengan benar.');
        } else {
            $columns = [];
            $placeholders = [];
            $params = [];

            if ($hasProviderCol) {
                $columns[] = 'penyedia_id';
                $placeholders[] = ':pid';
                $params['pid'] = $penyediaId;
            }
            if ($hasCategoryCol) {
                $columns[] = 'kategori_id';
                $placeholders[] = ':kid';
                $params['kid'] = $kategoriId;
            }

            $columns[] = $titleCol;
            $placeholders[] = ':judul';
            $params['judul'] = $judul;

            if ($descCol !== null) {
                $columns[] = $descCol;
                $placeholders[] = ':desk';
                $params['desk'] = $deskripsi;
            }
            if ($priceCol !== null) {
                $columns[] = $priceCol;
                $placeholders[] = ':harga';
                $params['harga'] = $harga;
            }
            if ($statusCol !== null) {
                $columns[] = $statusCol;
                $placeholders[] = ':st';
                $params['st'] = $status;
            }

            $sql = 'INSERT INTO layanan (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
            $ins = $pdo->prepare($sql);
            $ins->execute($params);

            setFlash('success', 'Layanan baru berhasil ditambahkan.');
            redirect('provider/services.php');
        }
    } elseif ($action === 'update') {
        $layananId = (int) ($_POST['id'] ?? 0);
        $kategoriId = (int) ($_POST['kategori_id'] ?? 0);
        $judul = trim((string) ($_POST['judul_layanan'] ?? ''));
        $deskripsi = trim((string) ($_POST['deskripsi'] ?? ''));
        $harga = (float) ($_POST['harga_patokan'] ?? 0);
        $status = (string) ($_POST['status_layanan'] ?? 'aktif');
        if (!in_array($status, ['aktif', 'nonaktif'], true)) {
            $status = 'aktif';
        }

        if ($hasProviderCol) {
            $cek = $pdo->prepare('SELECT id FROM layanan WHERE id = :id AND penyedia_id = :pid LIMIT 1');
            $cek->execute(['id' => $layananId, 'pid' => $penyediaId]);
        } else {
            $cek = $pdo->prepare('SELECT id FROM layanan WHERE id = :id LIMIT 1');
            $cek->execute(['id' => $layananId]);
        }

        if (!$cek->fetch()) {
            setFlash('danger', 'Layanan tidak ditemukan atau bukan milik Anda.');
        } elseif ($judul === '' || $harga < 0 || ($hasCategoryCol && $kategoriId <= 0)) {
            setFlash('danger', 'Kategori, judul layanan, dan harga wajib diisi dengan benar.');
        } else {
            $sets = [];
            $params = ['id' => $layananId];

            if ($hasCategoryCol) {
                $sets[] = 'kategori_id = :kid';
                $params['kid'] = $kategoriId;
            }
            $sets[] = "{$titleCol} = :judul";
            $params['judul'] = $judul;

            if ($descCol !== null) {
                $sets[] = "{$descCol} = :desk";
                $params['desk'] = $deskripsi;
            }
            if ($priceCol !== null) {
                $sets[] = "{$priceCol} = :harga";
                $params['harga'] = $harga;
            }
            if ($statusCol !== null) {
                $sets[] = "{$statusCol} = :st";
                $params['st'] = $status;
            }

            $where = 'WHERE id = :id';
            if ($hasProviderCol) {
                $where .= ' AND penyedia_id = :pid';
                $params['pid'] = $penyediaId;
            }

            $upd = $pdo->prepare('UPDATE layanan SET ' . implode(', ', $sets) . ' ' . $where);
            $upd->execute($params);

            setFlash('success', 'Layanan berhasil diperbarui.');
            redirect('provider/services.php');
        }
    } elseif ($action === 'toggle_status' && $statusCol !== null) {
        $layananId = (int) ($_POST['id'] ?? 0);
        $nextStatus = (string) ($_POST['next_status'] ?? 'nonaktif');
        if (!in_array($nextStatus, ['aktif', 'nonaktif'], true)) {
            $nextStatus = 'nonaktif';
        }

        if ($hasProviderCol) {
            $upd = $pdo->prepare("UPDATE layanan SET {$statusCol} = :st WHERE id = :id AND penyedia_id = :pid");
            $upd->execute(['st' => $nextStatus, 'id' => $layananId, 'pid' => $penyediaId]);
        } else {
            $upd = $pdo->prepare("UPDATE layanan SET {$statusCol} = :st WHERE id = :id");
            $upd->execute(['st' => $nextStatus, 'id' => $layananId]);
        }

        setFlash('success', 'Status layanan berhasil diubah menjadi ' . $nextStatus . '.');
        redirect('provider/services.php');
    } elseif ($action === 'delete') {
        $layananId = (int) ($_POST['id'] ?? 0);

        try {
            if ($hasProviderCol) {
                $del = $pdo->prepare('DELETE FROM layanan WHERE id = :id AND penyedia_id = :pid');
                $del->execute(['id' => $layananId, 'pid' => $penyediaId]);
            } else {
                $del = $pdo->prepare('DELETE FROM layanan WHERE id = :id');
                $del->execute(['id' => $layananId]);
            }

            if ($del->rowCount() > 0) {
                setFlash('success', 'Layanan berhasil dihapus.');
            } else {
                setFlash('warning', 'Layanan tidak ditemukan atau sudah dihapus.');
            }
        } catch (PDOException $e) {
            if ((string) $e->getCode() === '23000' || str_contains($e->getMessage(), '1451')) {
                setFlash(
                    'warning',
                    'Layanan tidak bisa dihapus karena sudah memiliki data pemesanan. Ubah status layanan menjadi "nonaktif" agar tidak dapat dipesan lagi.'
                );
            } else {
                throw $e;
            }
        }

        redirect('provider/services.php');
    }
}

if ($hasProviderCol && $hasCategoryCol) {
    $stmt = $pdo->prepare(
        'SELECT l.*, k.nama_kategori
         FROM layanan l
         LEFT JOIN kategori_jasa k ON l.kategori_id = k.id
         WHERE l.penyedia_id = :pid
         ORDER BY l.id DESC'
    );
    $stmt->execute(['pid' => $penyediaId]);
} elseif ($hasProviderCol) {
    $stmt = $pdo->prepare('SELECT * FROM layanan WHERE penyedia_id = :pid ORDER BY id DESC');
    $stmt->execute(['pid' => $penyediaId]);
} else {
    $stmt = $pdo->query('SELECT * FROM layanan ORDER BY id DESC');
}
$services = $stmt->fetchAll();

$editItem = null;
$editId = (int) ($_GET['edit'] ?? 0);
if ($editId > 0) {
    foreach ($services as $srv) {
        if ((int) $srv['id'] === $editId) {
            $editItem = $srv;
            break;
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<?php renderFlashMessages(); ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 fw-bold mb-0">Kelola Layanan</h1>
    <a href="<?= e(url('provider/dashboard.php')); ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali ke Dashboard
    </a>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm p-4">
            <h2 class="h6 fw-bold mb-3">
                <?= $editItem !== null ? 'Edit Layanan #' . (int) $editItem['id'] : 'Tambah Layanan Baru'; ?>
            </h2>
            <form method="post">
                <input type="hidden" name="action" value="<?= $editItem !== null ? 'update' : 'create'; ?>">
                <?php if ($editItem !== null): ?>
                    <input type="hidden" name="id" value="<?= (int) $editItem['id']; ?>">
                <?php endif; ?>

                <?php if ($hasCategoryCol): ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Kategori Jasa <span class="text-danger">*</span></label>
                        <select name="kategori_id" class="form-select" required>
                            <option value="">-- Pilih Kategori --</option>
                            <?php $selectedKat = (int) ($editItem['kategori_id'] ?? 0); ?>
                            <?php foreach ($categories as $kat): ?>
                                <?php $katLabel = (string) ($kat['nama_kategori'] ?? $kat['nama'] ?? ('Kategori #' . $kat['id'])); ?>
                                <option value="<?= (int) $kat['id']; ?>" <?= $selectedKat === (int) $kat['id'] ? 'selected' : ''; ?>>
                                    <?= e($katLabel); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Judul Layanan <span class="text-danger">*</span></label>
                    <input type="text" name="judul_layanan" class="form-control" value="<?= e((string) ($editItem[$titleCol] ?? '')); ?>" required>
                </div>

                <?php if ($priceCol !== null): ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Harga Patokan (Rp) <span class="text-danger">*</span></label>
                        <input type="number" name="harga_patokan" class="form-control" min="0" step="100" value="<?= e((string) ($editItem[$priceCol] ?? '0')); ?>" required>
                    </div>
                <?php endif; ?>

                <?php if ($statusCol !== null): ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Status Layanan</label>
                        <?php $curStatus = (string) ($editItem[$statusCol] ?? 'aktif'); ?>
                        <select name="status_layanan" class="form-select">
                            <option value="aktif" <?= $curStatus === 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                            <option value="nonaktif" <?= $curStatus === 'nonaktif' ? 'selected' : ''; ?>>Nonaktif</option>
                        </select>
                    </div>
                <?php endif; ?>

                <?php if ($descCol !== null): ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Deskripsi</label>
                        <textarea name="deskripsi" rows="3" class="form-control"><?= e((string) ($editItem[$descCol] ?? '')); ?></textarea>
                    </div>
                <?php endif; ?>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary fw-semibold">
                        <i class="bi bi-check-lg me-1"></i><?= $editItem !== null ? 'Simpan Perubahan' : 'Tambah Layanan'; ?>
                    </button>
                    <?php if ($editItem !== null): ?>
                        <a href="<?= e(url('provider/services.php')); ?>" class="btn btn-outline-secondary">Batal</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Layanan</th>
                            <?php if ($hasCategoryCol): ?><th>Kategori</th><?php endif; ?>
                            <?php if ($priceCol !== null): ?><th>Harga</th><?php endif; ?>
                            <?php if ($statusCol !== null): ?><th>Status</th><?php endif; ?>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($services)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">Belum ada layanan yang ditambahkan.</td></tr>
                        <?php else: ?>
                            <?php foreach ($services as $srv): ?>
                                <?php
                                $judulItem = (string) ($srv[$titleCol] ?? '-');
                                $deskItem = $descCol !== null ? (string) ($srv[$descCol] ?? '') : '';
                                $hargaItem = $priceCol !== null ? (float) ($srv[$priceCol] ?? 0) : 0.0;
                                $st = $statusCol !== null ? (string) ($srv[$statusCol] ?? 'aktif') : 'aktif';
                                $nextSt = $st === 'aktif' ? 'nonaktif' : 'aktif';
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= e($judulItem); ?></div>
                                        <?php if ($deskItem !== ''): ?>
                                            <small class="text-muted"><?= e(mb_strimwidth($deskItem, 0, 70, '...')); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($hasCategoryCol): ?>
                                        <td><?= e((string) ($srv['nama_kategori'] ?? '-')); ?></td>
                                    <?php endif; ?>
                                    <?php if ($priceCol !== null): ?>
                                        <td class="fw-semibold"><?= e(formatRupiah($hargaItem)); ?></td>
                                    <?php endif; ?>
                                    <?php if ($statusCol !== null): ?>
                                        <td>
                                            <span class="badge <?= $st === 'aktif' ? 'bg-success' : 'bg-secondary'; ?>">
                                                <?= e($st); ?>
                                            </span>
                                        </td>
                                    <?php endif; ?>
                                    <td class="text-end text-nowrap">
                                        <a href="<?= e(url('provider/services.php?edit=' . (int) $srv['id'])); ?>" class="btn btn-sm btn-outline-primary">
                                            Edit
                                        </a>
                                        <?php if ($statusCol !== null): ?>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="id" value="<?= (int) $srv['id']; ?>">
                                                <input type="hidden" name="next_status" value="<?= e($nextSt); ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                    <?= $st === 'aktif' ? 'Nonaktifkan' : 'Aktifkan'; ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Hapus layanan ini?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $srv['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>