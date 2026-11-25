<?php
declare(strict_types=1);

$pageTitle = 'Registrasi Pelanggan';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';

if (isLoggedIn()) {
    $user = currentUser();
    redirect(dashboardUrlByRole($user['role'] ?? null));
}

$errors = [];
$old = [
    'nama_lengkap' => '',
    'email'        => '',
    'no_telepon'   => '',
    'alamat'       => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $namaLengkap = trim((string) ($_POST['nama_lengkap'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $noTelepon = trim((string) ($_POST['no_telepon'] ?? ''));
    $alamat = trim((string) ($_POST['alamat'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $konfirmasiPassword = (string) ($_POST['konfirmasi_password'] ?? '');

    $old['nama_lengkap'] = $namaLengkap;
    $old['email'] = $email;
    $old['no_telepon'] = $noTelepon;
    $old['alamat'] = $alamat;

    if ($namaLengkap === '') {
        $errors[] = 'Nama lengkap wajib diisi.';
    } elseif (mb_strlen($namaLengkap) < 3 || mb_strlen($namaLengkap) > 120) {
        $errors[] = 'Nama lengkap harus terdiri dari 3 sampai 120 karakter.';
    }

    if ($email === '') {
        $errors[] = 'Email wajib diisi.';
    } elseif (mb_strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid.';
    }

    if ($noTelepon !== '' && !preg_match('/^[0-9+\-\s]{8,25}$/', $noTelepon)) {
        $errors[] = 'Nomor telepon hanya boleh berisi angka, spasi, +, atau - (8-25 karakter).';
    }

    if ($password === '') {
        $errors[] = 'Password wajib diisi.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password minimal harus 8 karakter.';
    }

    if ($konfirmasiPassword === '') {
        $errors[] = 'Konfirmasi password wajib diisi.';
    } elseif ($password !== $konfirmasiPassword) {
        $errors[] = 'Konfirmasi password tidak cocok dengan password.';
    }

    if (empty($errors)) {
        $pdo = getDBConnection();

        $checkStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $checkStmt->execute(['email' => $email]);

        if ($checkStmt->fetch()) {
            $errors[] = 'Email sudah terdaftar. Silakan gunakan email lain atau masuk ke akun Anda.';
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            // Role dikunci secara tegas sebagai pelanggan di sisi server
            $insertStmt = $pdo->prepare(
                'INSERT INTO users (
                    nama_lengkap, email, password, role, no_telepon, alamat, status_akun
                ) VALUES (
                    :nama_lengkap, :email, :password, :role, :no_telepon, :alamat, :status_akun
                )'
            );

            $insertStmt->execute([
                'nama_lengkap' => $namaLengkap,
                'email'        => $email,
                'password'     => $passwordHash,
                'role'         => 'pelanggan',
                'no_telepon'   => $noTelepon !== '' ? $noTelepon : null,
                'alamat'       => $alamat !== '' ? $alamat : null,
                'status_akun'  => 'aktif',
            ]);

            setFlash('success', 'Registrasi berhasil! Silakan masuk menggunakan email dan password Anda.');
            redirect('auth/login.php');
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="row justify-content-center my-2">
    <div class="col-md-8 col-lg-6">
        <?php renderFlashMessages(); ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <strong>Registrasi gagal:</strong>
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
                    <h1 class="h4 fw-bold mb-1">Daftar Akun Pelanggan</h1>
                    <p class="text-muted small mb-0">Buat akun untuk memesan layanan di <?= e(APP_NAME); ?></p>
                </div>

                <form action="<?= e(url('auth/register.php')); ?>" method="post" data-auth-register="true" novalidate>
                    <div class="mb-3">
                        <label for="nama_lengkap" class="form-label fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
                        <input
                            type="text"
                            class="form-control"
                            id="nama_lengkap"
                            name="nama_lengkap"
                            value="<?= e($old['nama_lengkap']); ?>"
                            placeholder="Contoh: Budi Santoso"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                        <input
                            type="email"
                            class="form-control"
                            id="email"
                            name="email"
                            value="<?= e($old['email']); ?>"
                            placeholder="nama@email.com"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label for="no_telepon" class="form-label fw-semibold">Nomor Telepon / WhatsApp</label>
                        <input
                            type="text"
                            class="form-control"
                            id="no_telepon"
                            name="no_telepon"
                            value="<?= e($old['no_telepon']); ?>"
                            placeholder="Contoh: 081234567890"
                        >
                    </div>

                    <div class="mb-3">
                        <label for="alamat" class="form-label fw-semibold">Alamat</label>
                        <textarea
                            class="form-control"
                            id="alamat"
                            name="alamat"
                            rows="2"
                            placeholder="Alamat domisili atau lokasi layanan (opsional)"
                        ><?= e($old['alamat']); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input
                                type="password"
                                class="form-control"
                                id="password"
                                name="password"
                                minlength="8"
                                placeholder="Minimal 8 karakter"
                                required
                            >
                            <button class="btn btn-outline-secondary" type="button" data-toggle-password="password" aria-label="Tampilkan atau sembunyikan password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="form-text">Gunakan minimal 8 karakter.</div>
                    </div>

                    <div class="mb-4">
                        <label for="konfirmasi_password" class="form-label fw-semibold">Konfirmasi Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input
                                type="password"
                                class="form-control"
                                id="konfirmasi_password"
                                name="konfirmasi_password"
                                minlength="8"
                                placeholder="Ulangi password"
                                required
                            >
                            <button class="btn btn-outline-secondary" type="button" data-toggle-password="konfirmasi_password" aria-label="Tampilkan atau sembunyikan konfirmasi password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary fw-semibold py-2">
                            <i class="bi bi-person-plus me-1"></i>Daftar Sebagai Pelanggan
                        </button>
                    </div>
                </form>

                <hr class="my-4">

                <p class="text-center text-muted small mb-0">
                    Sudah memiliki akun?
                    <a href="<?= e(url('auth/login.php')); ?>" class="fw-semibold text-decoration-none">Masuk di sini</a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>