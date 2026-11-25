<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

/**
 * Mengambil daftar nama kolom pada tabel notifikasi (di-cache per request).
 *
 * @return array<int, string>
 */
function serveNotificationColumns(PDO $pdo): array
{
    static $columns = null;
    if ($columns !== null) {
        return $columns;
    }

    $columns = [];
    $stmt = $pdo->query('DESCRIBE notifikasi');
    foreach ($stmt->fetchAll() as $row) {
        if (!empty($row['Field'])) {
            $columns[] = (string) $row['Field'];
        }
    }

    return $columns;
}

/**
 * Mengirim notifikasi sistem ke pengguna sesuai struktur kolom tabel `notifikasi`.
 */
function createNotification(PDO $pdo, int $userId, string $judul, string $pesan, string $tipe = 'info'): bool
{
    $availableCols = serveNotificationColumns($pdo);
    if (empty($availableCols)) {
        return false;
    }

    $candidates = [
        'user_id'    => $userId,
        'judul'      => $judul,
        'pesan'      => $pesan,
        'tipe'       => $tipe,
        'is_read'    => 0,
        'dibaca'     => 0,
        'created_at' => date('Y-m-d H:i:s'),
    ];

    $insertCols = [];
    $placeholders = [];
    $params = [];

    foreach ($candidates as $col => $val) {
        if (in_array($col, $availableCols, true)) {
            $insertCols[] = $col;
            $paramName = ':' . $col;
            $placeholders[] = $paramName;
            $params[$col] = $val;
        }
    }

    if (empty($insertCols)) {
        return false;
    }

    $sql = sprintf(
        'INSERT INTO notifikasi (%s) VALUES (%s)',
        implode(', ', $insertCols),
        implode(', ', $placeholders)
    );

    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}