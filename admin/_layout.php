<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';

requireRole('admin');

/**
 * Mengambil koneksi PDO aktif untuk modul admin.
 */
function adminDb(): PDO
{
    return getDBConnection();
}

/**
 * Mengambil ringkasan statistik utama untuk dashboard admin.
 *
 * @return array<string, int|float>
 */
function adminGetStats(PDO $pdo): array
{
    $stats = [
        'total_users'      => 0,
        'total_services'   => 0,
        'total_orders'     => 0,
        'total_revenue'    => 0.0,
        'pending_payments' => 0,
        'total_reviews'    => 0,
    ];

    $stats['total_users'] = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $stats['total_services'] = (int) $pdo->query('SELECT COUNT(*) FROM layanan')->fetchColumn();
    $stats['total_orders'] = (int) $pdo->query('SELECT COUNT(*) FROM pemesanan')->fetchColumn();
    $stats['pending_payments'] = (int) $pdo->query("SELECT COUNT(*) FROM pembayaran WHERE status_pembayaran IN ('menunggu_verifikasi', 'menunggu', 'pending')")->fetchColumn();
    $stats['total_reviews'] = (int) $pdo->query('SELECT COUNT(*) FROM ulasan')->fetchColumn();

    $revStmt = $pdo->query("SELECT COALESCE(SUM(nominal), 0) FROM pembayaran WHERE status_pembayaran IN ('valid', 'lunas', 'terverifikasi', 'berhasil', 'selesai')");
    $stats['total_revenue'] = (float) $revStmt->fetchColumn();

    return $stats;
}

/**
 * Mengambil data grafik pemesanan & pendapatan 12 bulan (Jan-Des) berdasarkan tahun terpilih.
 *
 * @return array{labels: array<int, string>, orders: array<int, int>, revenue: array<int, float>, year: int}
 */
function adminGetMonthlyChartData(PDO $pdo, ?int $year = null): array
{
    $targetYear = ($year !== null && $year >= 1970 && $year <= 2100) ? $year : (int) date('Y');
    $monthNames = [
        1  => 'Jan',
        2  => 'Feb',
        3  => 'Mar',
        4  => 'Apr',
        5  => 'Mei',
        6  => 'Jun',
        7  => 'Jul',
        8  => 'Agu',
        9  => 'Sep',
        10 => 'Okt',
        11 => 'Nov',
        12 => 'Des',
    ];

    $labels = [];
    $orderMap = [];
    $revenueMap = [];

    for ($m = 1; $m <= 12; $m++) {
        $key = sprintf('%04d-%02d', $targetYear, $m);
        $labels[] = $monthNames[$m] . ' ' . $targetYear;
        $orderMap[$key] = 0;
        $revenueMap[$key] = 0.0;
    }

    $orderStmt = $pdo->prepare(
        "SELECT DATE_FORMAT(created_at, '%Y-%m') AS bln, COUNT(*) AS jml
         FROM pemesanan
         WHERE created_at IS NOT NULL
           AND YEAR(created_at) = :yr
         GROUP BY bln"
    );
    $orderStmt->execute(['yr' => $targetYear]);
    foreach ($orderStmt->fetchAll() as $row) {
        $bln = (string) $row['bln'];
        if (isset($orderMap[$bln])) {
            $orderMap[$bln] = (int) $row['jml'];
        }
    }

    $payStmt = $pdo->prepare(
        "SELECT DATE_FORMAT(COALESCE(tanggal_bayar, created_at), '%Y-%m') AS bln, COALESCE(SUM(nominal), 0) AS total
         FROM pembayaran
         WHERE COALESCE(tanggal_bayar, created_at) IS NOT NULL
           AND YEAR(COALESCE(tanggal_bayar, created_at)) = :yr
           AND status_pembayaran IN ('valid', 'lunas', 'terverifikasi', 'berhasil', 'selesai')
         GROUP BY bln"
    );
    $payStmt->execute(['yr' => $targetYear]);
    foreach ($payStmt->fetchAll() as $row) {
        $bln = (string) $row['bln'];
        if (isset($revenueMap[$bln])) {
            $revenueMap[$bln] = (float) $row['total'];
        }
    }

    return [
        'labels'  => $labels,
        'orders'  => array_values($orderMap),
        'revenue' => array_values($revenueMap),
        'year'    => $targetYear,
    ];
}

/**
 * Merender header, sidebar biru tua, dan navbar atas khusus halaman admin.
 */
function renderAdminHeader(string $pageTitle, string $activeNav, array $chartData = []): void
{
    $adminUser = currentUser();
    $adminName = (string) ($adminUser['nama_lengkap'] ?? 'Administrator');
    $adminEmail = (string) ($adminUser['email'] ?? '');

    $navItems = [
        ['key' => 'dashboard',     'label' => 'Dashboard',      'icon' => 'bi-speedometer2',           'href' => 'admin/dashboard.php'],
        ['key' => 'users',         'label' => 'Pengguna',       'icon' => 'bi-people',                 'href' => 'admin/users.php'],
        ['key' => 'categories',    'label' => 'Kategori Jasa',  'icon' => 'bi-tags',                   'href' => 'admin/categories.php'],
        ['key' => 'services',      'label' => 'Layanan',        'icon' => 'bi-briefcase',              'href' => 'admin/services.php'],
        ['key' => 'portfolios',    'label' => 'Portofolio',     'icon' => 'bi-images',                 'href' => 'admin/portfolios.php'],
        ['key' => 'orders',        'label' => 'Pemesanan',      'icon' => 'bi-calendar-check',         'href' => 'admin/orders.php'],
        ['key' => 'payments',      'label' => 'Pembayaran',     'icon' => 'bi-credit-card',            'href' => 'admin/payments.php'],
        ['key' => 'reviews',       'label' => 'Ulasan',         'icon' => 'bi-star',                   'href' => 'admin/reviews.php'],
        ['key' => 'reports',       'label' => 'Laporan',        'icon' => 'bi-file-earmark-bar-graph', 'href' => 'admin/reports.php'],
        ['key' => 'notifications', 'label' => 'Notifikasi',     'icon' => 'bi-bell',                   'href' => 'admin/notifications.php'],
        ['key' => 'profile',       'label' => 'Profil Admin',   'icon' => 'bi-person-gear',            'href' => 'admin/profile.php'],
    ];
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle); ?> | <?= e(APP_NAME); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(url('assets/css/style.css')); ?>" rel="stylesheet">
</head>
<body class="serve-admin-body">
<div class="serve-admin-wrapper">
    <div class="serve-sidebar-backdrop" id="serveSidebarBackdrop"></div>

    <aside class="serve-sidebar" id="serveSidebar">
        <div class="serve-sidebar-brand">
            <a href="<?= e(url('admin/dashboard.php')); ?>" class="d-flex align-items-center gap-2 text-white text-decoration-none">
                <span class="serve-brand-badge"><i class="bi bi-layers-half"></i></span>
                <div>
                    <div class="fw-bold lh-1"><?= e(APP_NAME); ?></div>
                    <small class="text-white-50">Panel Admin</small>
                </div>
            </a>
            <button type="button" class="btn btn-sm btn-link text-white-50 d-lg-none p-0" id="serveSidebarClose" aria-label="Tutup menu">
                <i class="bi bi-x-lg fs-5"></i>
            </button>
        </div>

        <nav class="serve-sidebar-nav" aria-label="Menu Administrator">
            <?php foreach ($navItems as $item): ?>
                <?php $isActive = ($activeNav === $item['key']); ?>
                <a href="<?= e(url($item['href'])); ?>" class="serve-nav-link <?= $isActive ? 'active' : ''; ?>">
                    <i class="bi <?= e($item['icon']); ?>"></i>
                    <span><?= e($item['label']); ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="serve-sidebar-footer">
            <a href="<?= e(url('auth/logout.php')); ?>" class="btn btn-outline-light btn-sm w-100">
                <i class="bi bi-box-arrow-right me-1"></i>Keluar
            </a>
        </div>
    </aside>

    <div class="serve-main-panel">
        <header class="serve-topbar">
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-outline-primary d-lg-none" id="serveSidebarToggle" aria-label="Buka menu">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <div>
                    <h1 class="h5 fw-bold mb-0 text-dark"><?= e($pageTitle); ?></h1>
                    <small class="text-muted d-none d-sm-inline"><?= e(defined('APP_TAGLINE') ? (string) APP_TAGLINE : 'Sistem Manajemen Layanan'); ?></small>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3">
                <a href="<?= e(url('admin/reports.php')); ?>" class="btn btn-outline-primary btn-sm d-none d-sm-inline-flex align-items-center gap-1" title="Pusat Laporan & Cetak">
                    <i class="bi bi-printer"></i>
                    <span>Cetak Laporan</span>
                </a>
                <a href="<?= e(url('admin/notifications.php')); ?>" class="btn btn-light position-relative border" title="Notifikasi">
                    <i class="bi bi-bell text-primary"></i>
                </a>
                <div class="dropdown">
                    <button class="btn btn-light border dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="serve-avatar-sm"><?= e(strtoupper(mb_substr($adminName, 0, 1))); ?></span>
                        <span class="text-start d-none d-md-inline">
                            <span class="d-block small fw-semibold lh-1"><?= e($adminName); ?></span>
                            <small class="text-muted"><?= e($adminEmail); ?></small>
                        </span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li><a class="dropdown-item" href="<?= e(url('admin/profile.php')); ?>"><i class="bi bi-person-gear me-2"></i>Profil Admin</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?= e(url('auth/logout.php')); ?>"><i class="bi bi-box-arrow-right me-2"></i>Keluar</a></li>
                    </ul>
                </div>
            </div>
        </header>

        <main class="serve-content container-fluid py-4 px-3 px-md-4">
            <?php renderFlashMessages(); ?>
    <?php
}

/**
 * Merender penutup layout admin dan script Chart.js bila tersedia.
 */
function renderAdminFooter(array $chartData = []): void
{
    ?>
        </main>

        <footer class="serve-admin-footer px-3 px-md-4 py-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 small text-muted">
                <span>&copy; <?= date('Y'); ?> <strong><?= e(APP_NAME); ?></strong> — Sistem Manajemen Layanan</span>
                <span>PHP Native &bull; MySQL &bull; Bootstrap 5</span>
            </div>
        </footer>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if (!empty($chartData)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const canvas = document.getElementById('serveOrdersChart');
    if (!canvas || typeof Chart === 'undefined') {
        return;
    }

    const ctx = canvas.getContext('2d');
    const labels = <?= json_encode($chartData['labels'] ?? [], JSON_UNESCAPED_UNICODE); ?>;
    const orders = <?= json_encode($chartData['orders'] ?? []); ?>;
    const revenue = <?= json_encode($chartData['revenue'] ?? []); ?>;

    const ordersFill = ctx ? ctx.createLinearGradient(0, 0, 0, 300) : null;
    if (ordersFill) {
        ordersFill.addColorStop(0, 'rgba(37, 99, 235, 0.16)');
        ordersFill.addColorStop(1, 'rgba(37, 99, 235, 0.0)');
    }

    const revenueFill = ctx ? ctx.createLinearGradient(0, 0, 0, 300) : null;
    if (revenueFill) {
        revenueFill.addColorStop(0, 'rgba(13, 148, 136, 0.18)');
        revenueFill.addColorStop(1, 'rgba(13, 148, 136, 0.0)');
    }

    new Chart(canvas, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Jumlah Pemesanan',
                    data: orders,
                    borderColor: '#2563eb',
                    backgroundColor: ordersFill || 'rgba(37, 99, 235, 0.08)',
                    borderWidth: 2.5,
                    tension: 0.42,
                    cubicInterpolationMode: 'monotone',
                    fill: true,
                    pointRadius: 3.5,
                    pointHoverRadius: 6,
                    pointHoverBorderWidth: 2.5,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#2563eb',
                    pointBorderWidth: 2.2,
                    pointHoverBackgroundColor: '#2563eb',
                    pointHoverBorderColor: '#ffffff',
                    yAxisID: 'yOrders'
                },
                {
                    label: 'Pendapatan Lunas (Rp)',
                    data: revenue,
                    borderColor: '#0d9488',
                    backgroundColor: revenueFill || 'rgba(13, 148, 136, 0.08)',
                    borderWidth: 2.5,
                    tension: 0.42,
                    cubicInterpolationMode: 'monotone',
                    fill: true,
                    pointRadius: 3.5,
                    pointHoverRadius: 6,
                    pointHoverBorderWidth: 2.5,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#0d9488',
                    pointBorderWidth: 2.2,
                    pointHoverBackgroundColor: '#0d9488',
                    pointHoverBorderColor: '#ffffff',
                    yAxisID: 'yRevenue'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    position: 'top',
                    align: 'end',
                    onClick: function (e, legendItem, legend) {
                        const index = legendItem.datasetIndex;
                        const ci = legend.chart;
                        if (ci.isDatasetVisible(index)) {
                            ci.hide(index);
                            legendItem.hidden = true;
                        } else {
                            ci.show(index);
                            legendItem.hidden = false;
                        }

                        if (ci.options.scales && ci.options.scales.yRevenue) {
                            ci.options.scales.yRevenue.display = ci.isDatasetVisible(1);
                        }
                        ci.update();
                    },
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'circle',
                        boxWidth: 9,
                        padding: 18,
                        color: '#475569',
                        font: { size: 12, weight: '600' }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.94)',
                    titleColor: '#f8fafc',
                    bodyColor: '#e2e8f0',
                    borderColor: 'rgba(148, 163, 184, 0.2)',
                    borderWidth: 1,
                    padding: 12,
                    cornerRadius: 10,
                    displayColors: true,
                    boxPadding: 5,
                    callbacks: {
                        label: function (context) {
                            const val = Number(context.parsed.y || 0);
                            if (context.dataset.yAxisID === 'yRevenue') {
                                return ' ' + context.dataset.label + ': Rp ' + Math.round(val).toLocaleString('id-ID');
                            }
                            return ' ' + context.dataset.label + ': ' + val + ' pesanan';
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    border: { display: false },
                    ticks: {
                        color: '#64748b',
                        font: { size: 12, weight: '500' }
                    }
                },
                yOrders: {
                    type: 'linear',
                    position: 'left',
                    beginAtZero: true,
                    border: { display: false },
                    ticks: {
                        precision: 0,
                        color: '#64748b',
                        font: { size: 11 }
                    },
                    grid: {
                        color: 'rgba(148, 163, 184, 0.16)',
                        borderDash: [4, 4]
                    }
                },
                yRevenue: {
                    type: 'linear',
                    position: 'right',
                    display: true,
                    beginAtZero: true,
                    border: { display: false },
                    grid: { drawOnChartArea: false },
                    ticks: {
                        color: '#0f766e',
                        font: { size: 11 },
                        callback: function (value) {
                            const num = Number(value);
                            if (!Number.isInteger(num) && num < 1) {
                                return '';
                            }
                            return 'Rp ' + Math.round(num).toLocaleString('id-ID');
                        }
                    }
                }
            }
        }
    });
});
</script>
<?php endif; ?>
<script src="<?= e(url('assets/js/app.js')); ?>"></script>
</body>
</html>
    <?php
}