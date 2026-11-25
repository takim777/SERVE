<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$pdo = adminDb();
$adminId = (int) (currentUser()['id'] ?? 0);
$errors = [];

$stmt = $pdo->prepare('SELECT id, nama_lengkap, email, password, no_telepon, alamat, role, status_akun FROM users WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $adminId]);
$profile = $stmt->fetch();

if (!$profile) {
    setFlash('danger', 'Data profil admin tidak ditemukan.');
    redirect('auth/logout.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formType = (string) ($_POST['form_type'] ?? 'profile');

    if ($formType === 'profile') {
        $nama = trim((string) ($_POST['nama_lengkap'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $telepon = trim((string) ($_POST['no_telepon'] ?? ''));
        $alamat = trim((string) ($_POST['alamat'] ?? ''));

        if ($nama === '' || mb_strlen($nama) < 3) {
            $errors[] = 'Nama lengkap wajib diisi minimal 3 karakter.';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Format email tidak valid.';
        }

        if (empty($errors)) {
            $cek = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id != :id LIMIT 1');
            $cek->execute(['email' => $email, 'id' => $adminId]);
            if ($cek->fetch()) {
                $errors[] = 'Email sudah digunakan akun lain.';
            } else {
                $upd = $pdo->prepare(
                    'UPDATE users SET nama_lengkap = :nama, email = :email, no_telepon = :telepon, alamat = :alamat WHERE id = :id'
                );
                $upd->execute([
                    'nama'    => $nama,
                    'email'   => $email,
                    'telepon' => $telepon !== '' ? $telepon : null,
                    'alamat'  => $alamat !== '' ? $alamat : null,
                    'id'      => $adminId,
                ]);

                $_SESSION['user']['nama_lengkap'] = $nama;
                $_SESSION['user']['email'] = $email;

                setFlash('success', 'Profil administrator berhasil diperbarui.');
                redirect('admin/profile.php');
            }
        }
    } elseif ($formType === 'password') {
        $currentPass = (string) ($_POST['current_password'] ?? '');
        $newPass = (string) ($_POST['new_password'] ?? '');
        $confirmPass = (string) ($_POST['confirm_password'] ?? '');

        if ($currentPass === '' || !password_verify($currentPass, (string) $profile['password'])) {
            $errors[] = 'Password saat ini tidak sesuai.';
        }
        if (strlen($newPass) < 8) {
            $errors[] = 'Password baru minimal harus 8 karakter.';
        }
        if ($newPass !== $confirmPass) {
            $errors[] = 'Konfirmasi password baru tidak cocok.';
        }

        if (empty($errors)) {
            $updPass = $pdo->prepare('UPDATE users SET password = :password WHERE id = :id');
            $updPass->execute([
                'password' => password_hash($newPass, PASSWORD_DEFAULT),
                'id'       => $adminId,
            ]);
            setFlash('success', 'Password administrator berhasil diubah.');
            redirect('admin/profile.php');
        }
    }
}

renderAdminHeader('Profil Admin', 'profile');
?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger shadow-sm">
        <ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err); ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-12 col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3">
                <h2 class="h6 fw-bold mb-0">Informasi Profil</h2>
            </div>
            <div class="card-body">
                <form method="post" action="<?= e(url('admin/profile.php')); ?>">
                    <input type="hidden" name="form_type" value="profile">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="nama_lengkap" class="form-control" value="<?= e((string) $profile['nama_lengkap']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" value="<?= e((string) $profile['email']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">No. Telepon</label>
                        <input type="text" name="no_telepon" class="form-control" value="<?= e((string) ($profile['no_telepon'] ?? '')); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Alamat</label>
                        <textarea name="alamat" class="form-control" rows="2"><?= e((string) ($profile['alamat'] ?? '')); ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Simpan Profil</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3">
                <h2 class="h6 fw-bold mb-0">Ubah Password</h2>
            </div>
            <div class="card-body">
                <form method="post" action="<?= e(url('admin/profile.php')); ?>">
                    <input type="hidden" name="form_type" value="password">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Password Saat Ini <span class="text-danger">*</span></label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Password Baru <span class="text-danger">*</span></label>
                        <input type="password" name="new_password" class="form-control" minlength="8" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Konfirmasi Password Baru <span class="text-danger">*</span></label>
                        <input type="password" name="confirm_password" class="form-control" minlength="8" required>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-key me-1"></i>Perbarui Password</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php renderAdminFooter(); ?>