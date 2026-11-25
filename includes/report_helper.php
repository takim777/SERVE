<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/business_workflow.php';

/**
 * Memuat autoloader Dompdf dari vendor Composer atau instalasi manual di vendor/dompdf/.
 */
function serveLoadDompdfAutoload(): bool
{
    if (class_exists(\Dompdf\Dompdf::class, false)) {
        return true;
    }

    $autoloadCandidates = [
        __DIR__ . '/../vendor/autoload.php',
        __DIR__ . '/../vendor/dompdf/autoload.inc.php',
        __DIR__ . '/../vendor/dompdf/vendor/autoload.php',
    ];

    foreach ($autoloadCandidates as $autoloadFile) {
        if (is_file($autoloadFile)) {
            require_once $autoloadFile;
            if (class_exists(\Dompdf\Dompdf::class)) {
                return true;
            }
        }
    }

    return class_exists(\Dompdf\Dompdf::class);
}

serveLoadDompdfAutoload();

/**
 * Mengambil koneksi PDO utama aplikasi SERVE.
 */
function reportDb(): PDO
{
    return getDBConnection();
}

/**
 * Daftar jenis laporan yang tersedia.
 *
 * @return array<string, array{title: string, subtitle: string, is_transaction: bool}>
 */
function serveReportTypes(): array
{
    return [
        'customers' => [
            'title'          => 'Laporan Data Pelanggan',
            'subtitle'       => 'Rekapitulasi akun pengguna dengan peran pelanggan di sistem SERVE.',
            'is_transaction' => false,
        ],
        'providers' => [
            'title'          => 'Laporan Data Penyedia Jasa',
            'subtitle'       => 'Rekapitulasi mitra penyedia jasa, jumlah layanan, dan portofolio.',
            'is_transaction' => false,
        ],
        'services' => [
            'title'          => 'Laporan Data Layanan',
            'subtitle'       => 'Daftar layanan jasa beserta kategori, penyedia, harga patokan, dan status.',
            'is_transaction' => false,
        ],
        'orders' => [
            'title'          => 'Laporan Pemesanan',
            'subtitle'       => 'Laporan transaksi pemesanan jasa berdasarkan periode tanggal.',
            'is_transaction' => true,
        ],
        'payments' => [
            'title'          => 'Laporan Pembayaran dan Pendapatan',
            'subtitle'       => 'Rekapitulasi pembayaran manual, status verifikasi, dan total pendapatan.',
            'is_transaction' => true,
        ],
        'reviews' => [
            'title'          => 'Laporan Ulasan Pelanggan',
            'subtitle'       => 'Rekapitulasi penilaian (rating 1-5) dan komentar ulasan pelanggan.',
            'is_transaction' => false,
        ],
    ];
}

/**
 * Normalisasi input tanggal YYYY-MM-DD.
 */
function serveNormalizeDate(?string $raw): string
{
    $val = trim((string) $raw);
    if ($val !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
        return $val;
    }
    return '';
}

/**
 * Render HTML laporan berformat A4 siap cetak & konversi Dompdf.
 *
 * @param array<int, string> $headers
 * @param array<int, array<int, string>> $rows
 * @param array<int, array{label: string, value: string}> $summaryItems
 */
function serveRenderReportHtml(
    string $reportTitle,
    string $periodLabel,
    array $headers,
    array $rows,
    array $summaryItems,
    string $generatedAt
): string {
    $safeTitle = e($reportTitle);
    $safePeriod = e($periodLabel);
    $safeGeneratedAt = e($generatedAt);

    $thHtml = '';
    foreach ($headers as $idx => $h) {
        $align = $idx === 0 ? 'text-center' : 'text-left';
        $thHtml .= '<th class="' . $align . '">' . e($h) . '</th>';
    }

    $tbodyHtml = '';
    if (empty($rows)) {
        $colCount = max(1, count($headers));
        $tbodyHtml = '<tr><td colspan="' . $colCount . '" class="text-center muted">Tidak ada data pada kriteria laporan ini.</td></tr>';
    } else {
        foreach ($rows as $row) {
            $tbodyHtml .= '<tr>';
            foreach ($row as $cIdx => $cell) {
                $cls = $cIdx === 0 ? 'text-center' : 'text-left';
                $tbodyHtml .= '<td class="' . $cls . '">' . $cell . '</td>';
            }
            $tbodyHtml .= '</tr>';
        }
    }

    $summaryHtml = '';
    foreach ($summaryItems as $item) {
        $summaryHtml .= '<div class="summary-box">'
            . '<div class="summary-label">' . e($item['label']) . '</div>'
            . '<div class="summary-value">' . e($item['value']) . '</div>'
            . '</div>';
    }

    return <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>{$safeTitle} - SERVE</title>
<style>
  @page {
    size: A4 portrait;
    margin: 15mm 14mm 15mm 14mm;
  }
  * {
    box-sizing: border-box;
  }
  body {
    font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
    font-size: 10pt;
    color: #1f2937;
    margin: 0;
    padding: 0;
    background: #ffffff;
  }
  .header {
    border-bottom: 2px solid #0d6efd;
    padding-bottom: 10px;
    margin-bottom: 14px;
  }
  .system-name {
    font-size: 15pt;
    font-weight: bold;
    color: #0d6efd;
    letter-spacing: 0.5px;
    margin: 0 0 2px 0;
  }
  .report-title {
    font-size: 13pt;
    font-weight: bold;
    color: #111827;
    margin: 0 0 4px 0;
  }
  .meta {
    font-size: 8.5pt;
    color: #4b5563;
  }
  .summary-grid {
    display: table;
    width: 100%;
    table-layout: fixed;
    margin-bottom: 14px;
  }
  .summary-box {
    display: table-cell;
    border: 1px solid #d1d5db;
    background: #f9fafb;
    padding: 8px 10px;
    vertical-align: top;
  }
  .summary-label {
    font-size: 8pt;
    color: #6b7280;
    margin-bottom: 3px;
  }
  .summary-value {
    font-size: 10.5pt;
    font-weight: bold;
    color: #111827;
  }
  table.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 8.8pt;
  }
  table.data-table th {
    background: #f3f4f6;
    color: #111827;
    font-weight: bold;
    border: 1px solid #cbd5e1;
    padding: 6px 7px;
  }
  table.data-table td {
    border: 1px solid #cbd5e1;
    padding: 6px 7px;
    vertical-align: top;
    word-wrap: break-word;
  }
  table.data-table tr:nth-child(even) td {
    background: #fafafa;
  }
  .text-center { text-align: center; }
  .text-left { text-align: left; }
  .text-right { text-align: right; }
  .muted { color: #6b7280; }
  .footer {
    margin-top: 16px;
    padding-top: 8px;
    border-top: 1px solid #e5e7eb;
    font-size: 8pt;
    color: #6b7280;
    text-align: right;
  }
  @media print {
    .no-print { display: none !important; }
  }
</style>
</head>
<body>
  <div class="header">
    <div class="system-name">SERVE — Sistem Manajemen Layanan</div>
    <h1 class="report-title">{$safeTitle}</h1>
    <div class="meta">
      <strong>Periode:</strong> {$safePeriod} &nbsp;|&nbsp;
      <strong>Tanggal Cetak Laporan:</strong> {$safeGeneratedAt}
    </div>
  </div>

  <div class="summary-grid">
    {$summaryHtml}
  </div>

  <table class="data-table">
    <thead>
      <tr>{$thHtml}</tr>
    </thead>
    <tbody>
      {$tbodyHtml}
    </tbody>
  </table>

  <div class="footer">
    Dokumen dihasilkan otomatis oleh sistem SERVE &bull; Format A4
  </div>
</body>
</html>
HTML;
}

/**
 * Menghasilkan PDF dengan Dompdf dan mengirimkannya ke browser.
 */
function serveOutputDompdf(string $html, string $filename, bool $download): void
{
    if (!serveLoadDompdfAutoload()) {
        http_response_code(500);
        echo '<h3>Dompdf belum terpasang</h3>';
        echo '<p>Jalankan <code>composer require dompdf/dompdf</code> di folder proyek <code>serve</code> atau letakkan library Dompdf di <code>vendor/dompdf/</code>.</p>';
        exit;
    }

    $options = new \Dompdf\Options();
    $options->set('isRemoteEnabled', false);
    $options->set('defaultFont', 'DejaVu Sans');

    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $safeName = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $filename) ?: 'laporan_serve.pdf';
    $dompdf->stream($safeName, ['Attachment' => $download ? 1 : 0]);
    exit;
}