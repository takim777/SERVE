<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

/**
 * Menyimpan flash message ke session.
 */
function setFlash(string $type, string $message): void
{
    $allowedTypes = ['success', 'danger', 'warning', 'info'];
    if (!in_array($type, $allowedTypes, true)) {
        $type = 'info';
    }

    $_SESSION['serve_flash'][] = [
        'type'    => $type,
        'message' => $message,
    ];
}

/**
 * Mengambil dan menghapus seluruh flash message dari session.
 *
 * @return array<int, array{type: string, message: string}>
 */
function consumeFlashes(): array
{
    if (empty($_SESSION['serve_flash']) || !is_array($_SESSION['serve_flash'])) {
        return [];
    }

    $flashes = $_SESSION['serve_flash'];
    unset($_SESSION['serve_flash']);

    return $flashes;
}

/**
 * Merender HTML alert Bootstrap 5 untuk seluruh flash message aktif.
 */
function renderFlashMessages(): void
{
    $flashes = consumeFlashes();
    if (empty($flashes)) {
        return;
    }

    foreach ($flashes as $flash) {
        $type = htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8');
        $message = htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8');
        echo '<div class="alert alert-' . $type . ' alert-dismissible fade show shadow-sm" role="alert">';
        echo $message;
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>';
        echo '</div>';
    }
}