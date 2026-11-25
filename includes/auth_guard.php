<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

/**
 * Mengecek apakah pengguna sudah login.
 */
function isLoggedIn(): bool
{
    return !empty($_SESSION['user']) && is_array($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

/**
 * Mengambil data pengguna yang sedang login dari session.
 *
 * @return array<string, mixed>|null
 */
function currentUser(): ?array
{
    if (!isLoggedIn()) {
        return null;
    }

    return $_SESSION['user'];
}

/**
 * Mengecek apakah pengguna memiliki salah satu role yang diizinkan.
 *
 * @param string|array<int, string> $roles
 */
function hasRole(string|array $roles): bool
{
    $user = currentUser();
    if ($user === null || empty($user['role'])) {
        return false;
    }

    $allowed = is_array($roles) ? $roles : [$roles];
    return in_array((string) $user['role'], $allowed, true);
}

/**
 * Mengembalikan path dashboard sesuai role pengguna.
 */
function dashboardUrlByRole(?string $role): string
{
    return match ($role) {
        'admin'     => 'admin/dashboard.php',
        'penyedia'  => 'provider/dashboard.php',
        'pelanggan' => 'customer/dashboard.php',
        default     => 'index.php',
    };
}

/**
 * Memastikan pengguna sudah login dan akunnya masih aktif di database.
 */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        setFlash('warning', 'Silakan login terlebih dahulu untuk mengakses halaman tersebut.');
        redirect('auth/login.php');
    }

    $userId = (int) $_SESSION['user']['id'];
    $pdo = getDBConnection();

    $stmt = $pdo->prepare(
        'SELECT id, nama_lengkap, email, role, foto_profil, status_akun FROM users WHERE id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $userId]);
    $dbUser = $stmt->fetch();

    if (!$dbUser || $dbUser['status_akun'] !== 'aktif') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();

        setFlash('danger', 'Sesi berakhir atau akun Anda sedang tidak aktif.');
        redirect('auth/login.php');
    }

    $_SESSION['user'] = [
        'id'           => (int) $dbUser['id'],
        'nama_lengkap' => (string) $dbUser['nama_lengkap'],
        'email'        => (string) $dbUser['email'],
        'role'         => (string) $dbUser['role'],
        'foto_profil'  => $dbUser['foto_profil'],
        'status_akun'  => (string) $dbUser['status_akun'],
    ];
}

/**
 * Membatasi akses halaman hanya untuk role tertentu.
 *
 * @param string|array<int, string> $allowedRoles
 */
function requireRole(string|array $allowedRoles): void
{
    requireLogin();

    if (!hasRole($allowedRoles)) {
        setFlash('danger', 'Anda tidak memiliki hak akses untuk membuka halaman tersebut.');
        $user = currentUser();
        redirect(dashboardUrlByRole($user['role'] ?? null));
    }
}