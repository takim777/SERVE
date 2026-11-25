<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$pdo = adminDb();
$stats = adminGetStats($pdo);

$currentYear = (int) date('Y');
$selectedYear = isset($_GET['year']) && preg_match('/^\d{4}$/', (string) $_GET['year'])
    ? (int) $_GET['year']
    : $currentYear;

$years = [$currentYear];
try {
    $yearSql = "
        SELECT DISTINCT yr FROM (
            SELECT YEAR(created_at) AS yr FROM pemesanan WHERE created_at IS NOT NULL
            UNION
            SELECT YEAR(COALESCE(tanggal_bayar, created_at)) AS yr FROM pembayaran WHERE COALESCE(tanggal_bayar, created_at) IS NOT NULL
        ) AS t
        WHERE yr IS NOT NULL AND yr > 0
        ORDER BY yr DESC
    ";
    foreach ($pdo->query($yearSql)->fetchAll() as $yRow) {
        $yrVal = (int) ($yRow['yr'] ?? 0);
        if ($yrVal > 0 && !in_array($yrVal, $years, true)) {
            $years[] = $yrVal;
        }
    }
} catch (Throwable $e) {
    // Tetap gunakan daftar tahun minimal berisi tahun berjalan bila query tahun gagal
}
rsort($years);
if (!in_array($selectedYear, $years, true)) {
    $years[] = $selectedYear;
    rsort($years);
}

$chartData = adminGetMonthlyChartData($pdo, $selectedYear);

$ordersStmt = $pdo->query(
    'SELECT p.id, p.kode_pesanan, p.tanggal_pengerjaan, p.total_biaya, p.status_terkini, p.created_at,
            u.nama_lengkap AS nama_pelanggan,
            l.judul_layanan
     FROM pemesanan p
     LEFT JOIN users u ON p.pelanggan_id = u.id
     LEFT JOIN layanan l ON p.layanan_id = l.id
     ORDER BY p.created_at DESC, p.id DESC
     LIMIT 8'
);
$latestOrders = $ordersStmt->fetchAll();

renderAdminHeader('Dashboard Administrator', 'dashboard', $chartData);
?>

<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card serve-stat-card h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-semibold">Total Pengguna</div>
                    <div class="h3 fw-bold mb-0 mt-1"><?= number_format((int) $stats['total_users']); ?></div>
                </div>
                <div class="serve-stat-icon"><i class="bi bi-people"></i></div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card serve-stat-card h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-semibold">Total Layanan</div>
                    <div class="h3 fw-bold mb-0 mt-1"><?= number_format((int) $stats['total_services']); ?></div>
                </div>
                <div class="serve-stat-icon"><i class="bi bi-briefcase"></i></div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card serve-stat-card h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-semibold">Total Pemesanan</div>
                    <div class="h3 fw-bold mb-0 mt-1"><?= number_format((int) $stats['total_orders']); ?></div>
                </div>
                <div class="serve-stat-icon"><i class="bi bi-calendar-check"></i></div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card serve-stat-card h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-semibold">Pendapatan Terverifikasi</div>
                    <div class="h4 fw-bold mb-0 mt-1 text-primary"><?= e(formatRupiah((float) $stats['total_revenue'])); ?></div>
                </div>
                <div class="serve-stat-icon"><i class="bi bi-wallet2"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h2 class="h6 fw-bold mb-0 text-dark">Grafik Pemesanan &amp; Pendapatan</h2>
                <div class="d-flex align-items-center gap-2">
                    <form method="get" action="" class="d-flex align-items-center gap-1 mb-0">
                        <label for="chartYearSelect" class="small text-muted fw-semibold mb-0">Tahun:</label>
                        <select id="chartYearSelect" name="year" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                            <?php foreach ($years as $yrOption): ?>
                                <option value="<?= e((string) $yrOption); ?>" <?= ((int) $yrOption === $selectedYear) ? 'selected' : ''; ?>>
                                    <?= e((string) $yrOption); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <span class="badge bg-primary-subtle text-primary">Real-time MySQL</span>
                </div>
            </div>
            <div class="card-body">
                <div class="serve-chart-box">
                    <canvas id="serveOrdersChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom py-3">
                <h2 class="h6 fw-bold mb-0 text-dark">Ringkasan Operasional</h2>
            </div>
            <div class="card-body d-flex flex-column justify-content-between">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span>Menunggu Verifikasi Bayar</span>
                        <span class="badge bg-warning text-dark rounded-pill"><?= number_format((int) $stats['pending_payments']); ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span>Total Ulasan Pelanggan</span>
                        <span class="badge bg-primary rounded-pill"><?= number_format((int) $stats['total_reviews']); ?></span>
                    </li>
                </ul>
                <div class="d-grid gap-2 mt-4">
                    <a href="<?= e(url('admin/orders.php')); ?>" class="btn btn-primary btn-sm">Kelola Pemesanan</a>
                    <a href="<?= e(url('admin/payments.php')); ?>" class="btn btn-outline-primary btn-sm">Verifikasi Pembayaran</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
        <h2 class="h6 fw-bold mb-0 text-dark">Pemesanan Terbaru</h2>
        <a href="<?= e(url('admin/orders.php')); ?>" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Kode</th>
                    <th>Pelanggan</th>
                    <th>Layanan</th>
                    <th>Tanggal Pengerjaan</th>
                    <th>Total</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($latestOrders)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">Belum ada data pemesanan di database.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($latestOrders as $order): ?>
                        <tr>
                            <td class="fw-semibold"><?= e((string) ($order['kode_pesanan'] ?: ('ORD-' . $order['id']))); ?></td>
                            <td><?= e((string) ($order['nama_pelanggan'] ?? '-')); ?></td>
                            <td><?= e((string) ($order['judul_layanan'] ?? '-')); ?></td>
                            <td><?= e((string) ($order['tanggal_pengerjaan'] ?? '-')); ?></td>
                            <td class="fw-semibold"><?= e(formatRupiah((float) ($order['total_biaya'] ?? 0))); ?></td>
                            <td><span class="badge bg-primary-subtle text-primary border"><?= e((string) ($order['status_terkini'] ?? 'menunggu_konfirmasi')); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php renderAdminFooter($chartData); ?>