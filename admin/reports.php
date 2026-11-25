<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../includes/report_helper.php';

$pdo = reportDb();
$reportTypes = serveReportTypes();

$startDate = serveNormalizeDate($_GET['start_date'] ?? '');
$endDate = serveNormalizeDate($_GET['end_date'] ?? '');

$buildReportUrl = static function (string $type, string $format = 'html', bool $autoPrint = false) use ($startDate, $endDate): string {
    $params = [
        'type'   => $type,
        'format' => $format,
    ];
    if ($startDate !== '') {
        $params['start_date'] = $startDate;
    }
    if ($endDate !== '') {
        $params['end_date'] = $endDate;
    }
    if ($autoPrint) {
        $params['print'] = '1';
    }
    return url('admin/report_view.php?' . http_build_query($params));
};

renderAdminHeader('Pusat Laporan', 'reports');
?>

<?php renderFlashMessages(); ?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 fw-bold mb-0">Pusat Laporan SERVE</h1>
        <small class="text-muted">Format kertas A4 &bull; Cetak langsung &bull; Unduh PDF (Dompdf)</small>
    </div>
    <a href="<?= e(url('admin/dashboard.php')); ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali ke Dashboard
    </a>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="get" action="<?= e(url('admin/reports.php')); ?>" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Tanggal Awal Periode (Transaksi)</label>
                <input type="date" name="start_date" class="form-control" value="<?= e($startDate); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Tanggal Akhir Periode (Transaksi)</label>
                <input type="date" name="end_date" class="form-control" value="<?= e($endDate); ?>">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary fw-semibold">
                    <i class="bi bi-funnel me-1"></i>Terapkan Periode
                </button>
                <a href="<?= e(url('admin/reports.php')); ?>" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="row g-4">
    <?php foreach ($reportTypes as $key => $info): ?>
        <div class="col-md-6 col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                        <h2 class="h6 fw-bold mb-0"><?= e($info['title']); ?></h2>
                        <span class="badge <?= $info['is_transaction'] ? 'bg-primary' : 'bg-secondary'; ?>">
                            <?= $info['is_transaction'] ? 'Transaksi' : 'Master/Data'; ?>
                        </span>
                    </div>
                    <p class="text-muted small mb-3"><?= e($info['subtitle']); ?></p>
                    <?php if ($info['is_transaction']): ?>
                        <div class="small text-muted mb-3">
                            Periode aktif:
                            <strong><?= e(($startDate !== '' ? $startDate : 'Awal') . ' s/d ' . ($endDate !== '' ? $endDate : 'Akhir')); ?></strong>
                        </div>
                    <?php endif; ?>

                    <div class="mt-auto d-flex flex-wrap gap-2">
                        <a href="<?= e($buildReportUrl($key, 'html', false)); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye me-1"></i>Buka
                        </a>
                        <a href="<?= e($buildReportUrl($key, 'html', true)); ?>" target="_blank" class="btn btn-sm btn-outline-dark">
                            <i class="bi bi-printer me-1"></i>Cetak
                        </a>
                        <a href="<?= e($buildReportUrl($key, 'pdf', true)); ?>" class="btn btn-sm btn-success">
                            <i class="bi bi-file-earmark-pdf me-1"></i>Unduh PDF
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php renderAdminFooter(); ?>