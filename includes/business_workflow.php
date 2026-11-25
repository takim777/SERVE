<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

/**
 * Daftar status pesanan resmi SERVE.
 *
 * @return array<int, string>
 */
function serveOrderStatuses(): array
{
    return [
        'menunggu_konfirmasi',
        'diterima',
        'ditolak',
        'menunggu_pembayaran',
        'sedang_dikerjakan',
        'selesai',
        'dibatalkan',
    ];
}

function serveOrderStatusLabel(string $status): string
{
    return match ($status) {
        'menunggu_konfirmasi' => 'Menunggu Konfirmasi',
        'diterima'            => 'Diterima',
        'ditolak'             => 'Ditolak',
        'menunggu_pembayaran' => 'Menunggu Pembayaran',
        'sedang_dikerjakan'   => 'Sedang Dikerjakan',
        'selesai'             => 'Selesai',
        'dibatalkan'          => 'Dibatalkan',
        default               => ucfirst(str_replace('_', ' ', $status)),
    };
}

function serveOrderStatusBadgeClass(string $status): string
{
    return match ($status) {
        'menunggu_konfirmasi' => 'bg-warning text-dark',
        'diterima'            => 'bg-primary',
        'menunggu_pembayaran' => 'bg-info text-dark',
        'sedang_dikerjakan'   => 'bg-primary',
        'selesai'             => 'bg-success',
        'ditolak', 'dibatalkan' => 'bg-danger',
        default               => 'bg-secondary',
    };
}

/**
 * Aturan perpindahan status pesanan per role ('pelanggan', 'penyedia', 'admin').
 *
 * @return array<int, string>
 */
function serveAllowedOrderTransitions(string $currentStatus, string $role): array
{
    $currentStatus = strtolower(trim($currentStatus));
    $role = strtolower(trim($role));

    if ($role === 'admin') {
        return match ($currentStatus) {
            'menunggu_konfirmasi' => ['diterima', 'ditolak', 'menunggu_pembayaran', 'dibatalkan'],
            'diterima'            => ['menunggu_pembayaran', 'sedang_dikerjakan', 'dibatalkan'],
            'menunggu_pembayaran' => ['sedang_dikerjakan', 'dibatalkan'],
            'sedang_dikerjakan'   => ['selesai', 'dibatalkan'],
            'selesai', 'ditolak', 'dibatalkan' => [],
            default               => ['menunggu_konfirmasi', 'diterima', 'ditolak', 'menunggu_pembayaran', 'sedang_dikerjakan', 'selesai', 'dibatalkan'],
        };
    }

    if ($role === 'penyedia') {
        return match ($currentStatus) {
            'menunggu_konfirmasi' => ['diterima', 'ditolak'],
            'diterima'            => ['menunggu_pembayaran', 'sedang_dikerjakan', 'dibatalkan'],
            'menunggu_pembayaran' => ['sedang_dikerjakan', 'dibatalkan'],
            'sedang_dikerjakan'   => ['selesai'],
            'selesai', 'ditolak', 'dibatalkan' => [],
            default               => [],
        };
    }

    if ($role === 'pelanggan') {
        return match ($currentStatus) {
            'menunggu_konfirmasi', 'diterima', 'menunggu_pembayaran' => ['dibatalkan'],
            default => [],
        };
    }

    return [];
}

function serveCanTransitionOrder(string $from, string $to, string $role): bool
{
    if ($from === $to) {
        return true;
    }
    return in_array($to, serveAllowedOrderTransitions($from, $role), true);
}

/**
 * Mengambil daftar kolom tabel secara aman.
 *
 * @return array<int, string>
 */
function serveTableColumns(PDO $pdo, string $table): array
{
    $stmt = $pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`');
    return array_column($stmt->fetchAll(), 'Field');
}

function serveFirstMatchingColumn(array $columns, array $candidates): ?string
{
    foreach ($candidates as $col) {
        if (in_array($col, $columns, true)) {
            return $col;
        }
    }
    return null;
}

/**
 * Validasi dan simpan upload bukti pembayaran ke folder uploads/payments/.
 *
 * @return array{ok: bool, path?: string, error?: string}
 */
function serveUploadPaymentProof(array $file): array
{
    if (!isset($file['error']) || (int) $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'Bukti pembayaran wajib diunggah.'];
    }
    if ((int) $file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Gagal mengunggah file bukti pembayaran.'];
    }

    $maxSize = 3 * 1024 * 1024; // 3 MB
    if ((int) ($file['size'] ?? 0) <= 0 || (int) $file['size'] > $maxSize) {
        return ['ok' => false, 'error' => 'Ukuran bukti pembayaran maksimal 3 MB.'];
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($tmpName);

    $allowedMime = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($allowedMime[$mime])) {
        return ['ok' => false, 'error' => 'Format bukti pembayaran harus JPG, PNG, atau WEBP.'];
    }

    $uploadDir = __DIR__ . '/../uploads/payments';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        return ['ok' => false, 'error' => 'Folder penyimpanan bukti pembayaran tidak dapat dibuat.'];
    }

    $filename = 'proof_' . date('Ymd_His') . '_' . bin2hex(random_bytes(5)) . '.' . $allowedMime[$mime];
    $destPath = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($tmpName, $destPath)) {
        return ['ok' => false, 'error' => 'Tidak dapat memindahkan file bukti pembayaran.'];
    }

    return ['ok' => true, 'path' => 'uploads/payments/' . $filename];
}