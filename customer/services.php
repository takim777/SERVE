<?php
declare(strict_types=1);

$pageTitle = 'Daftar Layanan Jasa';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/shared_functions.php';

requireRole('pelanggan');

$pdo = getDBConnection();
$q = trim((string) ($_GET['q'] ?? ''));
$catId = (int) ($_GET['kategori'] ?? 0);

$sql = "SELECT l.*, k.nama_kategori, u.nama_lengkap AS nama_penyedia
        FROM layanan l
        LEFT JOIN kategori_jasa k ON l.kategori_id = k.id
        LEFT JOIN users u ON l.penyedia_id = u.id
        WHERE l.status_layanan = 'aktif'";
$params = [];

if ($catId > 0) {
    $sql .= " AND l.kategori_id = :catId";
    $params['catId'] = $catId;
}
if ($q !== '') {
    $sql .= " AND (l.judul_layanan LIKE :q1 OR l.deskripsi LIKE :q2)";
    $likeTerm = "%{$q}%";
    $params['q1'] = $likeTerm;
    $params['q2'] = $likeTerm;
}

$sql .= " ORDER BY l.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$services = $stmt->fetchAll();

$categories = $pdo->query('SELECT * FROM kategori_jasa ORDER BY id DESC')->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<?php renderFlashMessages(); ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <h1 class="h4 fw-bold mb-3">Daftar Layanan Jasa</h1>
        <form method="get" action="<?= e(url('customer/services.php')); ?>" class="row g-2">
            <div class="col-12 col-md-4">
                <select name="kategori" class="form-select">
                    <option value="">Semua Kategori Jasa</option>
                    <?php foreach ($categories as $c): ?>
                        <?php $labelCat = (string) ($c['nama_kategori'] ?? $c['nama'] ?? ('Kategori #' . $c['id'])); ?>
                        <option value="<?= (int) $c['id']; ?>" <?= $catId === (int) $c['id'] ? 'selected' : ''; ?>>
                            <?= e($labelCat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-6">
                <input type="text" name="q" class="form-control" placeholder="Cari layanan jasa atau deskripsi..." value="<?= e($q); ?>">
            </div>
            <div class="col-12 col-md-2 d-grid">
                <button type="submit" class="btn btn-primary fw-semibold">
                    <i class="bi bi-search me-1"></i>Cari
                </button>
            </div>
        </form>
    </div>
</div>

<div class="row g-4">
    <?php if (empty($services)): ?>
        <div class="col-12">
            <div class="card border-0 shadow-sm p-4 text-center text-muted">
                Layanan jasa tidak ditemukan.
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($services as $srv): ?>
            <?php
            $judul = (string) ($srv['judul_layanan'] ?? $srv['nama_layanan'] ?? ('Layanan #' . $srv['id']));
            $kategoriNama = (string) ($srv['nama_kategori'] ?? 'Umum');
            $penyediaNama = (string) ($srv['nama_penyedia'] ?? '-');
            $harga = (float) ($srv['harga_patokan'] ?? $srv['harga'] ?? 0);
            ?>
            <div class="col-12 col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body d-flex flex-column">
                        <span class="badge text-bg-secondary mb-2 align-self-start"><?= e($kategoriNama); ?></span>
                        <h2 class="h6 fw-bold"><?= e($judul); ?></h2>
                        <p class="small text-muted mb-3 flex-grow-1"><?= e(mb_strimwidth((string) ($srv['deskripsi'] ?? ''), 0, 110, '...')); ?></p>
                        <div class="mb-3">
                            <small class="text-muted d-block">Penyedia: <strong><?= e($penyediaNama); ?></strong></small>
                            <span class="fw-bold text-primary fs-5"><?= e(formatRupiah($harga)); ?></span>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="<?= e(url('customer/provider_profile.php?id=' . (int) $srv['penyedia_id'])); ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-person me-1"></i>Profil
                            </a>
                            <a href="<?= e(url('customer/service_detail.php?id=' . (int) $srv['id'])); ?>" class="btn btn-sm btn-primary flex-grow-1">
                                Detail &amp; Pesan
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>