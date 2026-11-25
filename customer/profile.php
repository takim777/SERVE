<?php
declare(strict_types=1);

$pageTitle = 'Profil Pelanggan';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/shared_functions.php';

requireRole('pelanggan');

$pdo = getDBConnection();
$sessionUser = currentUser();
$userId = (int) ($sessionUser['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $userId]);
$dbUser = $stmt->fetch() ?: $sessionUser;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'profile');

    if ($action === 'profile') {
        $nama = trim((string) ($_POST['nama_lengkap'] ?? ''));

        if ($nama === '') {
            setFlash('danger', 'Nama lengkap wajib diisi.');
        } else {
            $up = $pdo->prepare('UPDATE users SET nama_lengkap = :n WHERE id = :id');
            $up->execute(['n' => $nama, 'id' => $userId]);
            $_SESSION['user']['nama_lengkap'] = $nama;
            setFlash('success', 'Profil berhasil diperbarui.');
            redirect('customer/profile.php');
        }
    } elseif ($action === 'password') {
        $passLama = (string) ($_POST['password_lama'] ?? '');
        $passBaru = (string) ($_POST['password_baru'] ?? '');
        $passKonf = (string) ($_POST['konfirmasi_password'] ?? '');

        $storedHash = (string) ($dbUser['password'] ?? $dbUser['password_hash'] ?? '');
        $passCol = array_key_exists('password_hash', $dbUser) ? 'password_hash' : 'password';

        if ($passLama === '' || $passBaru === '' || $passKonf === '') {
            setFlash('danger', 'Semua kolom password wajib diisi.');
        } elseif ($storedHash === '' || !password_verify($passLama, $storedHash)) {
            setFlash('danger', 'Password lama tidak sesuai.');
        } elseif (strlen($passBaru) < 6) {
            setFlash('danger', 'Password baru minimal 6 karakter.');
        } elseif ($passBaru !== $passKonf) {
            setFlash('danger', 'Konfirmasi password baru tidak cocok.');
        } else {
            $newHash = password_hash($passBaru, PASSWORD_DEFAULT);
            $upPass = $pdo->prepare("UPDATE users SET {$passCol} = :p WHERE id = :id");
            $upPass->execute(['p' => $newHash, 'id' => $userId]);
            setFlash('success', 'Password berhasil diperbarui.');
            redirect('customer/profile.php');
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<?php renderFlashMessages(); ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 fw-bold mb-0">Profil Pelanggan</h1>
    <a href="<?= e(url('customer/dashboard.php')); ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali ke Dashboard
    </a>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm p-4">
            <h2 class="h6 fw-bold mb-3">Informasi Profil</h2>
            <form method="post">
                <input type="hidden" name="action" value="profile">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Email (Akun)</label>
                    <input type="text" class="form-control" value="<?= e((string) ($dbUser['email'] ?? '')); ?>" disabled>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Role</label>
                    <input type="text" class="form-control" value="<?= e(labelRole((string) ($dbUser['role'] ?? 'pelanggan'))); ?>" disabled>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
                    <input type="text" name="nama_lengkap" class="form-control" value="<?= e((string) ($dbUser['nama_lengkap'] ?? '')); ?>" required>
                </div>
                <button type="submit" class="btn btn-primary fw-semibold">
                    <i class="bi bi-check-lg me-1"></i>Simpan Perubahan
                </button>
            </form>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card border-0 shadow-sm p-4">
            <h2 class="h6 fw-bold mb-3">Ubah Password</h2>
            <form method="post">
                <input type="hidden" name="action" value="password">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Password Lama <span class="text-danger">*</span></label>
                    <input type="password" name="password_lama" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Password Baru <span class="text-danger">*</span></label>
                    <input type="password" name="password_baru" class="form-control" minlength="6" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Konfirmasi Password Baru <span class="text-danger">*</span></label>
                    <input type="password" name="konfirmasi_password" class="form-control" minlength="6" required>
                </div>
                <button type="submit" class="btn btn-warning fw-semibold">
                    <i class="bi bi-key me-1"></i>Perbarui Password
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>