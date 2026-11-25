<?php
declare(strict_types=1);

$pageTitle = 'Profil Penyedia Jasa';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/shared_functions.php';

requireRole('pelanggan');

$pdo = getDBConnection();
$providerId = (int) ($_GET['id'] ?? 0);

$stmtP = $pdo->prepare("SELECT * FROM users WHERE id = :id AND role = 'penyedia' LIMIT 1");
$stmtP->execute(['id' => $providerId]);
$provider = $stmtP->fetch();

if (!$provider) {
    setFlash('danger', 'Profil penyedia jasa tidak ditemukan.');
    redirect('customer/services.php');
}

$stmtL = $pdo->prepare(
    "SELECT l.*, k.nama_kategori
     FROM layanan l
     LEFT JOIN kategori_jasa k ON l.kategori_id = k.id
     WHERE l.penyedia_id = :pid AND l.status_layanan = 'aktif'
     ORDER BY l.id DESC"
);
$stmtL->execute(['pid' => $providerId]);
$services = $stmtL->fetchAll();

$stmtPort = $pdo->prepare('SELECT * FROM portofolio WHERE penyedia_id = :pid ORDER BY id DESC');
$stmtPort->execute(['pid' => $providerId]);
$portfolios = $stmtPort->fetchAll();

$stmtRev = $pdo->prepare(
    'SELECT r.*, u.nama_lengkap AS nama_pelanggan, l.judul_layanan
     FROM ulasan r
     LEFT JOIN users u ON r.pelanggan_id = u.id
     LEFT JOIN layanan l ON r.layanan_id = l.id
     WHERE r.penyedia_id = :pid
     ORDER BY r.id DESC'
);
$stmtRev->execute(['pid' => $providerId]);
$reviews = $stmtRev->fetchAll();

$avgRating = 0.0;
if (!empty($reviews)) {
    $sum = array_sum(array_map(static fn(array $r): float => (float) ($r['rating'] ?? 0), $reviews));
    $avgRating = round($sum / count($reviews), 1);
}

$namaPenyedia = (string) ($provider['nama_lengkap'] ?? 'Penyedia Jasa');
$emailPenyedia = (string) ($provider['email'] ?? '');

$pageTitle = 'Profil Penyedia - ' . $namaPenyedia;
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<?php renderFlashMessages(); ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 fw-bold mb-0">Profil Penyedia Jasa</h1>
    <a href="<?= e(url('customer/services.php')); ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali ke Daftar Layanan
    </a>
</div>

<div class="card border-0 shadow-sm p-4 mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h2 class="h4 fw-bold mb-1"><?= e($namaPenyedia); ?></h2>
            <?php if ($emailPenyedia !== ''): ?>
                <p class="text-muted mb-1"><?= e($emailPenyedia); ?></p>
            <?php endif; ?>
            <span class="badge bg-success"><?= e(labelRole('penyedia')); ?></span>
        </div>
        <div class="text-md-end">
            <div class="fs-3 fw-bold text-warning">
                <i class="bi bi-star-fill me-1"></i><?= number_format($avgRating, 1); ?> / 5.0
            </div>
            <small class="text-muted"><?= count($reviews); ?> ulasan pelanggan</small>
        </div>
    </div>
</div>

<h2 class="h5 fw-bold mb-3">Layanan Ditawarkan</h2>
<div class="row g-3 mb-4">
    <?php if (empty($services)): ?>
        <div class="col-12">
            <div class="card border-0 shadow-sm p-3 text-muted">Belum ada layanan aktif.</div>
        </div>
    <?php else: ?>
        <?php foreach ($services as $srv): ?>
            <?php
            $judulLayanan = (string) ($srv['judul_layanan'] ?? $srv['nama_layanan'] ?? ('Layanan #' . $srv['id']));
            $namaKategori = (string) ($srv['nama_kategori'] ?? 'Umum');
            $hargaPatokan = (float) ($srv['harga_patokan'] ?? $srv['harga'] ?? 0);
            ?>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm p-3 d-flex flex-column">
                    <span class="badge text-bg-secondary align-self-start mb-2"><?= e($namaKategori); ?></span>
                    <h3 class="h6 fw-bold"><?= e($judulLayanan); ?></h3>
                    <p class="small text-muted flex-grow-1"><?= e(mb_strimwidth((string) ($srv['deskripsi'] ?? ''), 0, 95, '...')); ?></p>
                    <div class="fw-bold text-primary mb-2"><?= e(formatRupiah($hargaPatokan)); ?></div>
                    <a href="<?= e(url('customer/service_detail.php?id=' . (int) $srv['id'])); ?>" class="btn btn-sm btn-primary">
                        Pesan Layanan
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<h2 class="h5 fw-bold mb-3">Portofolio</h2>
<div class="row g-3 mb-4">
    <?php if (empty($portfolios)): ?>
        <div class="col-12">
            <div class="card border-0 shadow-sm p-3 text-muted">Belum ada portofolio diunggah.</div>
        </div>
    <?php else: ?>
        <?php foreach ($portfolios as $prt): ?>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm p-3">
                    <h3 class="h6 fw-bold mb-1"><?= e((string) ($prt['judul'] ?? $prt['judul_portofolio'] ?? ('Portofolio #' . $prt['id']))); ?></h3>
                    <p class="small text-muted mb-0"><?= e((string) ($prt['deskripsi'] ?? '')); ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<h2 class="h5 fw-bold mb-3">Ulasan Pelanggan</h2>
<div class="card border-0 shadow-sm p-4">
    <?php if (empty($reviews)): ?>
        <p class="text-muted mb-0">Belum ada ulasan untuk penyedia jasa ini.</p>
    <?php else: ?>
        <?php foreach ($reviews as $rev): ?>
            <div class="border-bottom pb-3 mb-3">
                <div class="d-flex justify-content-between">
                    <strong><?= e((string) ($rev['nama_pelanggan'] ?? 'Pelanggan')); ?></strong>
                    <span class="badge bg-warning text-dark">Rating: <?= (int) ($rev['rating'] ?? 0); ?>/5</span>
                </div>
                <small class="text-muted d-block mb-1">Layanan: <?= e((string) ($rev['judul_layanan'] ?? '-')); ?></small>
                <p class="mb-0"><?= e((string) ($rev['komentar'] ?? '')); ?></p>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>