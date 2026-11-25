<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../includes/report_helper.php';

$pdo = reportDb();
$reportTypes = serveReportTypes();

$type = (string) ($_GET['type'] ?? 'customers');
if (!array_key_exists($type, $reportTypes)) {
    $type = 'customers';
}

$format = strtolower((string) ($_GET['format'] ?? 'html'));
$autoPrint = (string) ($_GET['print'] ?? '') === '1';

$startDate = serveNormalizeDate($_GET['start_date'] ?? '');
$endDate = serveNormalizeDate($_GET['end_date'] ?? '');
if ($startDate !== '' && $endDate !== '' && $startDate > $endDate) {
    [$startDate, $endDate] = [$endDate, $startDate];
}

$reportTitle = $reportTypes[$type]['title'];
$isTransaction = $reportTypes[$type]['is_transaction'];
$generatedAt = date('d/m/Y H:i:s');
$periodLabel = $isTransaction
    ? (($startDate !== '' ? $startDate : 'Semua Awal') . ' s/d ' . ($endDate !== '' ? $endDate : 'Semua Akhir'))
    : 'Seluruh Data Tersedia';

$headers = [];
$rows = [];
$summaryItems = [];

if ($type === 'customers') {
    $userCols = serveTableColumns($pdo, 'users');
    $emailCol = serveFirstMatchingColumn($userCols, ['email']);
    // Menambahkan 'no_telepon' dan variasi lainnya sesuai struktur database tabel users
    $phoneCol = serveFirstMatchingColumn($userCols, ['no_telepon', 'nomor_telepon', 'no_hp', 'no_telp', 'telepon', 'telp', 'hp', 'wa', 'whatsapp', 'phone', 'kontak']);
    $statusCol = serveFirstMatchingColumn($userCols, ['status', 'status_akun']);

    $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(COALESCE(role, '')) = 'pelanggan' ORDER BY id ASC");
    $stmt->execute();
    $data = $stmt->fetchAll();

    $headers = ['No', 'Nama Pelanggan', 'Email', 'Kontak'];
    if ($statusCol !== null) {
        $headers[] = 'Status';
    }

    $no = 1;
    foreach ($data as $u) {
        $rawPhone = $phoneCol !== null ? trim((string) ($u[$phoneCol] ?? '')) : '';
        $kontak = $rawPhone !== '' ? $rawPhone : '-';

        $r = [
            (string) $no++,
            e((string) ($u['nama_lengkap'] ?? '-')),
            e((string) ($emailCol !== null ? ($u[$emailCol] ?? '-') : '-')),
            e($kontak),
        ];
        if ($statusCol !== null) {
            $r[] = e((string) ($u[$statusCol] ?? '-'));
        }
        $rows[] = $r;
    }

    $summaryItems = [
        ['label' => 'Total Pelanggan', 'value' => count($data) . ' Orang'],
    ];
} elseif ($type === 'providers') {
    $srvCols = serveTableColumns($pdo, 'layanan');
    $hasSrvProv = in_array('penyedia_id', $srvCols, true);
    $portCols = serveTableColumns($pdo, 'portofolio');
    $hasPortProv = in_array('penyedia_id', $portCols, true);

    $srvCountMap = [];
    if ($hasSrvProv) {
        foreach ($pdo->query('SELECT penyedia_id, COUNT(*) AS jml FROM layanan GROUP BY penyedia_id') as $sc) {
            $srvCountMap[(int) $sc['penyedia_id']] = (int) $sc['jml'];
        }
    }

    $portCountMap = [];
    if ($hasPortProv) {
        foreach ($pdo->query('SELECT penyedia_id, COUNT(*) AS jml FROM portofolio GROUP BY penyedia_id') as $pc) {
            $portCountMap[(int) $pc['penyedia_id']] = (int) $pc['jml'];
        }
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(COALESCE(role, '')) = 'penyedia' ORDER BY id ASC");
    $stmt->execute();
    $data = $stmt->fetchAll();

    $headers = ['No', 'Nama Penyedia Jasa', 'Email', 'Jumlah Layanan', 'Jumlah Portofolio'];
    $no = 1;
    $totalLayanan = 0;
    foreach ($data as $p) {
        $pid = (int) ($p['id'] ?? 0);
        $jmlSrv = $srvCountMap[$pid] ?? 0;
        $jmlPort = $portCountMap[$pid] ?? 0;
        $totalLayanan += $jmlSrv;

        $rows[] = [
            (string) $no++,
            e((string) ($p['nama_lengkap'] ?? '-')),
            e((string) ($p['email'] ?? '-')),
            (string) $jmlSrv,
            (string) $jmlPort,
        ];
    }

    $summaryItems = [
        ['label' => 'Total Penyedia Jasa', 'value' => count($data) . ' Mitra'],
        ['label' => 'Total Layanan Terdaftar', 'value' => $totalLayanan . ' Layanan'],
    ];
} elseif ($type === 'services') {
    $srvCols = serveTableColumns($pdo, 'layanan');
    $titleCol = serveFirstMatchingColumn($srvCols, ['judul_layanan', 'nama_layanan', 'judul', 'nama']) ?? 'judul_layanan';
    $priceCol = serveFirstMatchingColumn($srvCols, ['harga_patokan', 'harga', 'tarif', 'harga_layanan', 'biaya']);
    $statusCol = serveFirstMatchingColumn($srvCols, ['status_layanan', 'status']);
    $hasProv = in_array('penyedia_id', $srvCols, true);
    $hasKat = in_array('kategori_id', $srvCols, true);

    $selects = ['l.*'];
    $joins = [];
    if ($hasKat) {
        $selects[] = 'k.nama_kategori';
        $joins[] = 'LEFT JOIN kategori_jasa k ON l.kategori_id = k.id';
    }
    if ($hasProv) {
        $selects[] = 'u.nama_lengkap AS nama_penyedia';
        $joins[] = 'LEFT JOIN users u ON l.penyedia_id = u.id';
    }

    $data = $pdo->query('SELECT ' . implode(', ', $selects) . ' FROM layanan l ' . implode(' ', $joins) . ' ORDER BY l.id DESC')->fetchAll();

    $headers = ['No', 'Judul Layanan', 'Kategori', 'Penyedia', 'Harga Patokan'];
    if ($statusCol !== null) {
        $headers[] = 'Status';
    }

    $no = 1;
    $sumHarga = 0.0;
    foreach ($data as $s) {
        $harga = $priceCol !== null ? (float) ($s[$priceCol] ?? 0) : 0.0;
        $sumHarga += $harga;

        $r = [
            (string) $no++,
            e((string) ($s[$titleCol] ?? '-')),
            e((string) ($s['nama_kategori'] ?? '-')),
            e((string) ($s['nama_penyedia'] ?? '-')),
            e(formatRupiah($harga)),
        ];
        if ($statusCol !== null) {
            $r[] = e((string) ($s[$statusCol] ?? '-'));
        }
        $rows[] = $r;
    }

    $summaryItems = [
        ['label' => 'Total Layanan', 'value' => count($data) . ' Layanan'],
        ['label' => 'Akumulasi Harga Patokan', 'value' => formatRupiah($sumHarga)],
    ];
} elseif ($type === 'orders') {
    $ordCols = serveTableColumns($pdo, 'pemesanan');
    $statusCol = serveFirstMatchingColumn($ordCols, ['status_terkini', 'status']) ?? 'status_terkini';
    $dateCol = serveFirstMatchingColumn($ordCols, ['created_at', 'tanggal_pesan', 'tanggal_pengerjaan', 'tanggal']);
    $totalCol = serveFirstMatchingColumn($ordCols, ['total_biaya', 'total_harga', 'total', 'nominal']);
    $codeCol = serveFirstMatchingColumn($ordCols, ['kode_pesanan', 'kode']);

    $srvCols = serveTableColumns($pdo, 'layanan');
    $srvTitleCol = serveFirstMatchingColumn($srvCols, ['judul_layanan', 'nama_layanan', 'judul', 'nama']) ?? 'judul_layanan';

    $sql = "SELECT p.*, l.{$srvTitleCol} AS judul_layanan,
                   pl.nama_lengkap AS nama_pelanggan,
                   py.nama_lengkap AS nama_penyedia
            FROM pemesanan p
            LEFT JOIN layanan l ON p.layanan_id = l.id
            LEFT JOIN users pl ON p.pelanggan_id = pl.id
            LEFT JOIN users py ON p.penyedia_id = py.id
            WHERE 1=1";
    $params = [];

    if ($dateCol !== null) {
        if ($startDate !== '') {
            $sql .= " AND p.{$dateCol} >= :start_date";
            $params['start_date'] = $startDate . ' 00:00:00';
        }
        if ($endDate !== '') {
            $sql .= " AND p.{$dateCol} <= :end_date";
            $params['end_date'] = $endDate . ' 23:59:59';
        }
    }
    $sql .= ' ORDER BY p.id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll();

    $headers = ['No', 'Kode', 'Pelanggan', 'Penyedia', 'Layanan', 'Tanggal', 'Status', 'Total Biaya'];
    $no = 1;
    $sumOrder = 0.0;
    foreach ($data as $o) {
        $kode = $codeCol !== null && !empty($o[$codeCol]) ? (string) $o[$codeCol] : ('ORD-' . $o['id']);
        $tgl = $dateCol !== null ? (string) ($o[$dateCol] ?? '-') : '-';
        $st = (string) ($o[$statusCol] ?? '-');
        $tot = $totalCol !== null ? (float) ($o[$totalCol] ?? 0) : 0.0;
        if (!in_array(strtolower($st), ['ditolak', 'dibatalkan'], true)) {
            $sumOrder += $tot;
        }

        $rows[] = [
            (string) $no++,
            e($kode),
            e((string) ($o['nama_pelanggan'] ?? '-')),
            e((string) ($o['nama_penyedia'] ?? '-')),
            e((string) ($o['judul_layanan'] ?? '-')),
            e($tgl),
            e(serveOrderStatusLabel($st)),
            e(formatRupiah($tot)),
        ];
    }

    $summaryItems = [
        ['label' => 'Total Pemesanan', 'value' => count($data) . ' Pesanan'],
        ['label' => 'Nilai Pesanan Aktif/Selesai', 'value' => formatRupiah($sumOrder)],
    ];
} elseif ($type === 'payments') {
    $payCols = serveTableColumns($pdo, 'pembayaran');
    $payOrderCol = serveFirstMatchingColumn($payCols, ['pemesanan_id', 'order_id']) ?? 'pemesanan_id';
    $payCustCol = serveFirstMatchingColumn($payCols, ['pelanggan_id', 'user_id']);
    $payMethodCol = serveFirstMatchingColumn($payCols, ['metode_pembayaran', 'metode']);
    $payAmountCol = serveFirstMatchingColumn($payCols, ['nominal', 'nominal_pembayaran', 'jumlah_bayar', 'jumlah']);
    $payDateCol = serveFirstMatchingColumn($payCols, ['created_at', 'tanggal_bayar', 'tanggal_pembayaran', 'tanggal']);
    $payStatusCol = serveFirstMatchingColumn($payCols, ['status_pembayaran', 'status']);

    $custJoin = $payCustCol !== null
        ? "LEFT JOIN users u ON pb.{$payCustCol} = u.id"
        : 'LEFT JOIN users u ON pm.pelanggan_id = u.id';

    $sql = "SELECT pb.*, pm.kode_pesanan, u.nama_lengkap AS nama_pelanggan
            FROM pembayaran pb
            LEFT JOIN pemesanan pm ON pb.{$payOrderCol} = pm.id
            {$custJoin}
            WHERE 1=1";
    $params = [];

    if ($payDateCol !== null) {
        if ($startDate !== '') {
            $sql .= " AND pb.{$payDateCol} >= :start_date";
            $params['start_date'] = $startDate . ' 00:00:00';
        }
        if ($endDate !== '') {
            $sql .= " AND pb.{$payDateCol} <= :end_date";
            $params['end_date'] = $endDate . ' 23:59:59';
        }
    }
    $sql .= ' ORDER BY pb.id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll();

    $headers = ['No', 'Kode Pesanan', 'Pelanggan', 'Metode', 'Tanggal Bayar', 'Status', 'Nominal'];
    $no = 1;
    $totalPendapatan = 0.0;
    $totalSemua = 0.0;

    foreach ($data as $pb) {
        $nominal = $payAmountCol !== null ? (float) ($pb[$payAmountCol] ?? 0) : 0.0;
        $stPay = strtolower((string) ($payStatusCol !== null ? ($pb[$payStatusCol] ?? '') : ''));
        $metode = $payMethodCol !== null ? (string) ($pb[$payMethodCol] ?? '-') : '-';
        $tgl = $payDateCol !== null ? (string) ($pb[$payDateCol] ?? '-') : '-';

        $totalSemua += $nominal;
        if (in_array($stPay, ['valid', 'lunas', 'terverifikasi', 'diterima', 'berhasil'], true)) {
            $totalPendapatan += $nominal;
        }

        $rows[] = [
            (string) $no++,
            e((string) ($pb['kode_pesanan'] ?: ('ORD-' . ($pb[$payOrderCol] ?? '')))),
            e((string) ($pb['nama_pelanggan'] ?? '-')),
            e($metode),
            e($tgl),
            e($stPay !== '' ? $stPay : '-'),
            e(formatRupiah($nominal)),
        ];
    }

    $summaryItems = [
        ['label' => 'Total Transaksi Pembayaran', 'value' => count($data) . ' Data'],
        ['label' => 'Total Pendapatan Terverifikasi', 'value' => formatRupiah($totalPendapatan)],
        ['label' => 'Total Nominal Tercatat', 'value' => formatRupiah($totalSemua)],
    ];
} elseif ($type === 'reviews') {
    $revCols = serveTableColumns($pdo, 'ulasan');
    $revCustCol = serveFirstMatchingColumn($revCols, ['pelanggan_id', 'user_id']);
    $revProvCol = serveFirstMatchingColumn($revCols, ['penyedia_id']);
    $revSrvCol = serveFirstMatchingColumn($revCols, ['layanan_id']);
    $revRatingCol = serveFirstMatchingColumn($revCols, ['rating', 'nilai']) ?? 'rating';
    $revCommentCol = serveFirstMatchingColumn($revCols, ['komentar', 'ulasan']);

    $srvCols = serveTableColumns($pdo, 'layanan');
    $srvTitleCol = serveFirstMatchingColumn($srvCols, ['judul_layanan', 'nama_layanan', 'judul', 'nama']) ?? 'judul_layanan';

    $selects = ['r.*'];
    $joins = [];
    if ($revCustCol !== null) {
        $selects[] = 'uc.nama_lengkap AS nama_pelanggan';
        $joins[] = "LEFT JOIN users uc ON r.{$revCustCol} = uc.id";
    }
    if ($revProvCol !== null) {
        $selects[] = 'up.nama_lengkap AS nama_penyedia';
        $joins[] = "LEFT JOIN users up ON r.{$revProvCol} = up.id";
    }
    if ($revSrvCol !== null) {
        $selects[] = "l.{$srvTitleCol} AS judul_layanan";
        $joins[] = "LEFT JOIN layanan l ON r.{$revSrvCol} = l.id";
    }

    $data = $pdo->query('SELECT ' . implode(', ', $selects) . ' FROM ulasan r ' . implode(' ', $joins) . ' ORDER BY r.id DESC')->fetchAll();

    $headers = ['No', 'Pelanggan', 'Penyedia', 'Layanan', 'Rating', 'Komentar'];
    $no = 1;
    $sumRating = 0;
    foreach ($data as $rv) {
        $rt = (int) ($rv[$revRatingCol] ?? 0);
        $sumRating += $rt;

        $rows[] = [
            (string) $no++,
            e((string) ($rv['nama_pelanggan'] ?? '-')),
            e((string) ($rv['nama_penyedia'] ?? '-')),
            e((string) ($rv['judul_layanan'] ?? '-')),
            $rt . ' / 5',
            e((string) ($revCommentCol !== null ? ($rv[$revCommentCol] ?? '') : '')),
        ];
    }

    $avgRating = count($data) > 0 ? number_format($sumRating / count($data), 2) : '0.00';
    $summaryItems = [
        ['label' => 'Total Ulasan', 'value' => count($data) . ' Ulasan'],
        ['label' => 'Rata-rata Rating', 'value' => $avgRating . ' / 5.00'],
    ];
}

$html = serveRenderReportHtml($reportTitle, $periodLabel, $headers, $rows, $summaryItems, $generatedAt);

if ($format === 'pdf') {
    $fileSlug = 'laporan_' . $type . '_' . date('Ymd_His') . '.pdf';
    serveOutputDompdf($html, $fileSlug, true);
}

// Tampilan pratinjau HTML A4 + tombol cetak & unduh PDF
$pdfUrlParams = ['type' => $type, 'format' => 'pdf'];
if ($startDate !== '') {
    $pdfUrlParams['start_date'] = $startDate;
}
if ($endDate !== '') {
    $pdfUrlParams['end_date'] = $endDate;
}
$pdfDownloadUrl = url('admin/report_view.php?' . http_build_query($pdfUrlParams));
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title><?= e($reportTitle); ?> - SERVE</title>
<style>
  body {
    background: #eef2f6;
    font-family: Arial, Helvetica, sans-serif;
    margin: 0;
    padding: 20px;
  }
  .toolbar {
    max-width: 210mm;
    margin: 0 auto 14px auto;
    background: #ffffff;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    padding: 10px 14px;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: space-between;
    align-items: center;
  }
  .toolbar-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }
  .btn {
    display: inline-block;
    padding: 7px 12px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    border-radius: 5px;
    border: 1px solid transparent;
    cursor: pointer;
  }
  .btn-primary { background: #0d6efd; color: #fff; }
  .btn-success { background: #198754; color: #fff; }
  .btn-secondary { background: #fff; color: #374151; border-color: #cbd5e1; }
  .a4-sheet {
    width: 210mm;
    min-height: 297mm;
    margin: 0 auto;
    background: #ffffff;
    padding: 15mm 14mm;
    box-shadow: 0 4px 16px rgba(0,0,0,0.08);
  }
  @media print {
    body {
      background: #ffffff;
      padding: 0;
    }
    .toolbar {
      display: none !important;
    }
    .a4-sheet {
      width: auto;
      min-height: auto;
      margin: 0;
      padding: 0;
      box-shadow: none;
    }
  }
</style>
</head>
<body<?= $autoPrint ? ' onload="window.print()"' : ''; ?>>
  <div class="toolbar">
    <a href="<?= e(url('admin/reports.php')); ?>" class="btn btn-secondary">&larr; Kembali ke Pusat Laporan</a>
    <div class="toolbar-actions">
      <button type="button" onclick="window.print();" class="btn btn-primary">Cetak Laporan (A4)</button>
      <a href="<?= e($pdfDownloadUrl); ?>" class="btn btn-success">Unduh PDF (Dompdf)</a>
    </div>
  </div>

  <div class="a4-sheet">
    <?php
    // Ambil bagian isi <body> dari $html agar tidak bersarang <html>
    if (preg_match('/<body[^>]*>(.*)<\/body>/is', $html, $m)) {
        echo $m[1];
    } else {
        echo $html;
    }
    ?>
  </div>
</body>
</html>