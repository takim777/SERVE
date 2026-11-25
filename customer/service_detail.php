<?php
declare(strict_types=1);

$pageTitle = 'Detail & Pemesanan Layanan';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/shared_functions.php';
require_once __DIR__ . '/../includes/business_workflow.php';

requireRole('pelanggan');

$pdo = getDBConnection();
$user = currentUser();
$pelangganId = (int) ($user['id'] ?? 0);
$serviceId = (int) ($_GET['id'] ?? $_POST['layanan_id'] ?? 0);

$srvCols = serveTableColumns($pdo, 'layanan');
$titleCol = serveFirstMatchingColumn($srvCols, ['judul_layanan', 'nama_layanan', 'judul', 'nama']) ?? 'judul_layanan';
$priceCol = serveFirstMatchingColumn($srvCols, ['harga_patokan', 'harga', 'tarif', 'harga_layanan', 'biaya']);
$descCol = serveFirstMatchingColumn($srvCols, ['deskripsi', 'keterangan', 'detail']);
$statusCol = serveFirstMatchingColumn($srvCols, ['status_layanan', 'status']);
$hasProviderCol = in_array('penyedia_id', $srvCols, true);
$hasCategoryCol = in_array('kategori_id', $srvCols, true);

$selectParts = ['l.*', 'u.nama_lengkap AS nama_penyedia', 'u.email AS email_penyedia'];
$joins = ['LEFT JOIN users u ON l.penyedia_id = u.id'];
if ($hasCategoryCol) {
    $selectParts[] = 'k.nama_kategori';
    $joins[] = 'LEFT JOIN kategori_jasa k ON l.kategori_id = k.id';
}

$whereStatus = $statusCol !== null ? " AND l.{$statusCol} = 'aktif'" : '';
$stmt = $pdo->prepare(
    'SELECT ' . implode(', ', $selectParts) . '
     FROM layanan l ' . implode(' ', $joins) . '
     WHERE l.id = :id' . $whereStatus . ' LIMIT 1'
);
$stmt->execute(['id' => $serviceId]);
$service = $stmt->fetch();

if (!$service) {
    setFlash('danger', 'Layanan tidak ditemukan atau sedang tidak aktif.');
    redirect('customer/services.php');
}

$providers = [];
if ($hasProviderCol) {
    $provStmt = $pdo->query("SELECT id, nama_lengkap, email FROM users WHERE role = 'penyedia' ORDER BY nama_lengkap ASC");
    $providers = $provStmt->fetchAll();
}

$defaultProviderId = (int) ($service['penyedia_id'] ?? ($providers[0]['id'] ?? 0));
$hargaSatuan = $priceCol !== null ? (float) ($service[$priceCol] ?? 0) : 0.0;
$judulLayanan = (string) ($service[$titleCol] ?? ('Layanan #' . $serviceId));
$namaKategori = (string) ($service['nama_kategori'] ?? 'Umum');
$deskripsiLayanan = $descCol !== null ? (string) ($service[$descCol] ?? '') : '';

$orderCols = serveTableColumns($pdo, 'pemesanan');
$orderStatusCol = serveFirstMatchingColumn($orderCols, ['status_terkini', 'status']) ?? 'status_terkini';
$orderDescCol = serveFirstMatchingColumn($orderCols, ['deskripsi_kebutuhan', 'deskripsi', 'catatan']);
$orderDateCol = serveFirstMatchingColumn($orderCols, ['tanggal_pengerjaan', 'tanggal_jadwal', 'tanggal_mulai']);
$orderAddrCol = serveFirstMatchingColumn($orderCols, ['alamat_lokasi', 'alamat', 'lokasi']);
$orderTotalCol = serveFirstMatchingColumn($orderCols, ['total_biaya', 'total_harga', 'total', 'nominal']);
$orderCodeCol = serveFirstMatchingColumn($orderCols, ['kode_pesanan', 'kode']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedProviderId = (int) ($_POST['penyedia_id'] ?? $defaultProviderId);
    $deskripsiKebutuhan = trim((string) ($_POST['deskripsi_kebutuhan'] ?? ''));
    $tanggalPengerjaan = trim((string) ($_POST['tanggal_pengerjaan'] ?? ''));
    $alamatLokasi = trim((string) ($_POST['alamat_lokasi'] ?? ''));

    $validProvider = false;
    foreach ($providers as $p) {
        if ((int) $p['id'] === $selectedProviderId) {
            $validProvider = true;
            break;
        }
    }
    if (!$validProvider && $selectedProviderId === (int) ($service['penyedia_id'] ?? 0)) {
        $validProvider = true;
    }

    if (!$validProvider || $selectedProviderId <= 0) {
        setFlash('danger', 'Pilih penyedia jasa yang valid.');
    } elseif ($deskripsiKebutuhan === '' || mb_strlen($deskripsiKebutuhan) < 5) {
        setFlash('danger', 'Deskripsi kebutuhan wajib diisi minimal 5 karakter.');
    } elseif ($tanggalPengerjaan === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalPengerjaan)) {
        setFlash('danger', 'Tanggal pengerjaan wajib diisi dengan format tanggal yang valid.');
    } elseif ($alamatLokasi === '' || mb_strlen($alamatLokasi) < 5) {
        setFlash('danger', 'Alamat atau lokasi pengerjaan wajib diisi.');
    } elseif ($hargaSatuan <= 0) {
        setFlash('danger', 'Harga layanan belum valid.');
    } else {
        try {
            $pdo->beginTransaction();

            $kodePesanan = 'ORD-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 7));
            $cols = ['pelanggan_id', 'penyedia_id', 'layanan_id'];
            $vals = [':pelanggan_id', ':penyedia_id', ':layanan_id'];
            $params = [
                'pelanggan_id' => $pelangganId,
                'penyedia_id'  => $selectedProviderId,
                'layanan_id'   => $serviceId,
            ];

            if ($orderCodeCol !== null) {
                $cols[] = $orderCodeCol;
                $vals[] = ':kode';
                $params['kode'] = $kodePesanan;
            }
            if ($orderDescCol !== null) {
                $cols[] = $orderDescCol;
                $vals[] = ':desk';
                $params['desk'] = $deskripsiKebutuhan;
            }
            if ($orderDateCol !== null) {
                $cols[] = $orderDateCol;
                $vals[] = ':tgl';
                $params['tgl'] = $tanggalPengerjaan;
            }
            if ($orderAddrCol !== null) {
                $cols[] = $orderAddrCol;
                $vals[] = ':alamat';
                $params['alamat'] = $alamatLokasi;
            }
            if ($orderTotalCol !== null) {
                $cols[] = $orderTotalCol;
                $vals[] = ':total';
                $params['total'] = $hargaSatuan;
            }

            $cols[] = $orderStatusCol;
            $vals[] = ':status_awal';
            $params['status_awal'] = 'menunggu_konfirmasi';

            $sql = 'INSERT INTO pemesanan (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')';
            $ins = $pdo->prepare($sql);
            $ins->execute($params);
            $newOrderId = (int) $pdo->lastInsertId();

            $pdo->commit();

            setFlash('success', "Pesanan {$kodePesanan} berhasil dikirim dan menunggu konfirmasi penyedia.");
            redirect('customer/order_detail.php?id=' . $newOrderId);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            setFlash('danger', 'Gagal menyimpan pemesanan: ' . $e->getMessage());
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<?php renderFlashMessages(); ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 fw-bold mb-0">Detail &amp; Pemesanan Layanan</h1>
    <a href="<?= e(url('customer/services.php')); ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali ke Daftar Layanan
    </a>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm p-4">
            <span class="badge text-bg-secondary align-self-start mb-2"><?= e($namaKategori); ?></span>
            <h2 class="h5 fw-bold mb-2"><?= e($judulLayanan); ?></h2>
            <div class="fs-4 fw-bold text-primary mb-3"><?= e(formatRupiah($hargaSatuan)); ?></div>

            <?php if ($deskripsiLayanan !== ''): ?>
                <p class="text-muted small mb-3"><?= nl2br(e($deskripsiLayanan)); ?></p>
            <?php endif; ?>

            <hr>
            <div class="small">
                <div class="text-muted">Penyedia Bawaan Layanan:</div>
                <a href="<?= e(url('customer/provider_profile.php?id=' . $defaultProviderId)); ?>" class="fw-semibold text-decoration-none">
                    <?= e((string) ($service['nama_penyedia'] ?? 'Penyedia Jasa')); ?>
                </a>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card border-0 shadow-sm p-4">
            <h2 class="h6 fw-bold mb-3">Form Pemesanan</h2>
            <form method="post">
                <input type="hidden" name="layanan_id" value="<?= $serviceId; ?>">

                <div class="mb-3">
                    <label class="form-label fw-semibold">Layanan Dipilih</label>
                    <input type="text" class="form-control" value="<?= e($judulLayanan); ?>" readonly>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Penyedia Jasa <span class="text-danger">*</span></label>
                    <select name="penyedia_id" class="form-select" required>
                        <?php foreach ($providers as $prov): ?>
                            <option value="<?= (int) $prov['id']; ?>" <?= (int) $prov['id'] === $defaultProviderId ? 'selected' : ''; ?>>
                                <?= e((string) $prov['nama_lengkap']); ?> (<?= e((string) $prov['email']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Tanggal Pengerjaan <span class="text-danger">*</span></label>
                        <input type="date" name="tanggal_pengerjaan" class="form-control" min="<?= e(date('Y-m-d')); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Estimasi Harga</label>
                        <input type="text" class="form-control fw-bold text-primary" value="<?= e(formatRupiah($hargaSatuan)); ?>" readonly>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Alamat / Lokasi Pengerjaan <span class="text-danger">*</span></label>
                    <input type="text" name="alamat_lokasi" class="form-control" placeholder="Contoh: Jl. Tjilik Riwut Km 3,5, Palangka Raya" required>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Deskripsi Kebutuhan <span class="text-danger">*</span></label>
                    <textarea name="deskripsi_kebutuhan" rows="4" class="form-control" placeholder="Jelaskan detail pekerjaan, kondisi, atau permintaan khusus Anda..." required></textarea>
                </div>

                <button type="submit" class="btn btn-primary fw-semibold">
                    <i class="bi bi-send me-1"></i>Kirim Pemesanan
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>