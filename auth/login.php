<?php
declare(strict_types=1);

$pageTitle = 'Masuk';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';

if (isLoggedIn()) {
    $user = currentUser();
    redirect(dashboardUrlByRole($user['role'] ?? null));
}

$errors = [];
$oldEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $oldEmail = $email;

    if ($email === '') {
        $errors[] = 'Email wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid.';
    }

    if ($password === '') {
        $errors[] = 'Password wajib diisi.';
    }

    if (empty($errors)) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare(
            'SELECT id, nama_lengkap, email, password, role, foto_profil, status_akun
             FROM users
             WHERE email = :email
             LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, (string) $user['password'])) {
            $errors[] = 'Email atau password salah.';
        } elseif ($user['status_akun'] !== 'aktif') {
            $errors[] = 'Akun Anda sedang tidak aktif. Silakan hubungi administrator.';
        } else {
            session_regenerate_id(true);

            $_SESSION['user'] = [
                'id'           => (int) $user['id'],
                'nama_lengkap' => (string) $user['nama_lengkap'],
                'email'        => (string) $user['email'],
                'role'         => (string) $user['role'],
                'foto_profil'  => $user['foto_profil'],
                'status_akun'  => (string) $user['status_akun'],
            ];

            setFlash('success', 'Selamat datang kembali, ' . (string) $user['nama_lengkap'] . '!');
            redirect(dashboardUrlByRole((string) $user['role']));
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="row justify-content-center my-3">
    <div class="col-md-7 col-lg-5">
        <?php renderFlashMessages(); ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <strong>Gagal masuk:</strong>
                <ul class="mb-0 mt-1">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error); ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
            </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm auth-card">
            <div class="card-body p-4 p-md-5">
                <div class="text-center mb-4">
                    <h1 class="h4 fw-bold mb-1">Masuk ke <?= e(APP_NAME); ?></h1>
                    <p class="text-muted small mb-0">Gunakan email dan password akun terdaftar Anda</p>
                </div>

                <form action="<?= e(url('auth/login.php')); ?>" method="post" data-auth-login="true" novalidate>
                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                        <input
                            type="email"
                            class="form-control"
                            id="email"
                            name="email"
                            value="<?= e($oldEmail); ?>"
                            placeholder="nama@email.com"
                            required
                            autofocus
                        >
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input
                                type="password"
                                class="form-control"
                                id="password"
                                name="password"
                                placeholder="Masukkan password"
                                required
                            >
                            <button class="btn btn-outline-secondary" type="button" data-toggle-password="password" aria-label="Tampilkan atau sembunyikan password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary fw-semibold py-2">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Masuk
                        </button>
                    </div>
                </form>

                <hr class="my-4">

                <p class="text-center text-muted small mb-0">
                    Belum punya akun pelanggan?
                    <a href="<?= e(url('auth/register.php')); ?>" class="fw-semibold text-decoration-none">Daftar sekarang</a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>