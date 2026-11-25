<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$pdo = adminDb();
$errors = [];
$editUser = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create' || $action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $nama = trim((string) ($_POST['nama_lengkap'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $role = (string) ($_POST['role'] ?? 'pelanggan');
        $noTelepon = trim((string) ($_POST['no_telepon'] ?? ''));
        $alamat = trim((string) ($_POST['alamat'] ?? ''));
        $statusAkun = (string) ($_POST['status_akun'] ?? 'aktif');
        $password = (string) ($_POST['password'] ?? '');

        if ($nama === '' || mb_strlen($nama) < 3) {
            $errors[] = 'Nama lengkap wajib diisi minimal 3 karakter.';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Format email tidak valid.';
        }
        if (!in_array($role, ['admin', 'penyedia', 'pelanggan'], true)) {
            $errors[] = 'Role pengguna tidak valid.';
        }
        if (!in_array($statusAkun, ['aktif', 'nonaktif', 'diblokir'], true)) {
            $errors[] = 'Status akun tidak valid.';
        }
        if ($action === 'create' && strlen($password) < 8) {
            $errors[] = 'Password wajib diisi minimal 8 karakter untuk pengguna baru.';
        }
        if ($action === 'update' && $password !== '' && strlen($password) < 8) {
            $errors[] = 'Password baru minimal harus 8 karakter.';
        }

        if (empty($errors)) {
            if ($action === 'create') {
                $cek = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
                $cek->execute(['email' => $email]);
                if ($cek->fetch()) {
                    $errors[] = 'Email sudah digunakan oleh akun lain.';
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO users (nama_lengkap, email, password, role, no_telepon, alamat, status_akun)
                         VALUES (:nama, :email, :password, :role, :telepon, :alamat, :status)'
                    );
                    $stmt->execute([
                        'nama'     => $nama,
                        'email'    => $email,
                        'password' => password_hash($password, PASSWORD_DEFAULT),
                        'role'     => $role,
                        'telepon'  => $noTelepon !== '' ? $noTelepon : null,
                        'alamat'   => $alamat !== '' ? $alamat : null,
                        'status'   => $statusAkun,
                    ]);
                    setFlash('success', 'Pengguna baru berhasil ditambahkan.');
                    redirect('admin/users.php');
                }
            } else {
                if ($id <= 0) {
                    $errors[] = 'ID pengguna tidak valid.';
                } else {
                    $cek = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id != :id LIMIT 1');
                    $cek->execute(['email' => $email, 'id' => $id]);
                    if ($cek->fetch()) {
                        $errors[] = 'Email sudah digunakan oleh pengguna lain.';
                    } else {
                        if ($password !== '') {
                            $stmt = $pdo->prepare(
                                'UPDATE users
                                 SET nama_lengkap = :nama, email = :email, password = :password,
                                     role = :role, no_telepon = :telepon, alamat = :alamat, status_akun = :status
                                 WHERE id = :id'
                            );
                            $stmt->execute([
                                'nama'     => $nama,
                                'email'    => $email,
                                'password' => password_hash($password, PASSWORD_DEFAULT),
                                'role'     => $role,
                                'telepon'  => $noTelepon !== '' ? $noTelepon : null,
                                'alamat'   => $alamat !== '' ? $alamat : null,
                                'status'   => $statusAkun,
                                'id'       => $id,
                            ]);
                        } else {
                            $stmt = $pdo->prepare(
                                'UPDATE users
                                 SET nama_lengkap = :nama, email = :email, role = :role,
                                     no_telepon = :telepon, alamat = :alamat, status_akun = :status
                                 WHERE id = :id'
                            );
                            $stmt->execute([
                                'nama'    => $nama,
                                'email'   => $email,
                                'role'    => $role,
                                'telepon' => $noTelepon !== '' ? $noTelepon : null,
                                'alamat'  => $alamat !== '' ? $alamat : null,
                                'status'  => $statusAkun,
                                'id'      => $id,
                            ]);
                        }
                        setFlash('success', 'Data pengguna berhasil diperbarui.');
                        redirect('admin/users.php');
                    }
                }
            }
        }
    } elseif ($action === 'deactivate') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id === (int) (currentUser()['id'] ?? 0)) {
            setFlash('warning', 'Anda tidak dapat menonaktifkan akun admin yang sedang digunakan.');
        } elseif ($id > 0) {
            $stmt = $pdo->prepare("UPDATE users SET status_akun = 'nonaktif' WHERE id = :id");
            $stmt->execute(['id' => $id]);
            setFlash('info', 'Status pengguna diubah menjadi nonaktif.');
        }
        redirect('admin/users.php');
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            setFlash('warning', 'ID pengguna tidak valid.');
            redirect('admin/users.php');
        }
        if ($id === (int) (currentUser()['id'] ?? 0)) {
            setFlash('danger', 'Akun admin yang sedang aktif tidak boleh dihapus.');
            redirect('admin/users.php');
        }

        // Cek keterkaitan data transaksi/layanan/portofolio/ulasan/notifikasi dengan placeholder unik
        $refChecks = [
            [
                'sql'    => 'SELECT COUNT(*) FROM layanan WHERE penyedia_id = :id',
                'params' => ['id' => $id],
            ],
            [
                'sql'    => 'SELECT COUNT(*) FROM pemesanan WHERE pelanggan_id = :id_pelanggan OR penyedia_id = :id_penyedia',
                'params' => ['id_pelanggan' => $id, 'id_penyedia' => $id],
            ],
            [
                'sql'    => 'SELECT COUNT(*) FROM portofolio WHERE penyedia_id = :id',
                'params' => ['id' => $id],
            ],
            [
                'sql'    => 'SELECT COUNT(*) FROM ulasan WHERE pelanggan_id = :id',
                'params' => ['id' => $id],
            ],
            [
                'sql'    => 'SELECT COUNT(*) FROM notifikasi WHERE user_id = :id',
                'params' => ['id' => $id],
            ],
        ];
        $hasRelation = false;
        foreach ($refChecks as $ref) {
            $st = $pdo->prepare($ref['sql']);
            $st->execute($ref['params']);
            if ((int) $st->fetchColumn() > 0) {
                $hasRelation = true;
                break;
            }
        }

        if ($hasRelation) {
            setFlash('warning', 'Pengguna tidak dapat dihapus permanen karena sudah terkait dengan layanan, pemesanan, portofolio, ulasan, atau notifikasi. Gunakan tombol Nonaktifkan agar riwayat transaksi tetap aman.');
        } else {
            $del = $pdo->prepare('DELETE FROM users WHERE id = :id');
            $del->execute(['id' => $id]);
            setFlash('success', 'Pengguna berhasil dihapus.');
        }
        redirect('admin/users.php');
    }
}

if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    if ($editId > 0) {
        $st = $pdo->prepare('SELECT id, nama_lengkap, email, role, no_telepon, alamat, status_akun FROM users WHERE id = :id LIMIT 1');
        $st->execute(['id' => $editId]);
        $editUser = $st->fetch() ?: null;
    }
}

$q = trim((string) ($_GET['q'] ?? ''));
$roleFilter = (string) ($_GET['role'] ?? '');

$sql = 'SELECT id, nama_lengkap, email, role, no_telepon, alamat, status_akun, created_at FROM users WHERE 1=1';
$params = [];

if ($q !== '') {
    $sql .= ' AND (nama_lengkap LIKE :q_nama OR email LIKE :q_email OR no_telepon LIKE :q_telepon)';
    $likeQ = '%' . $q . '%';
    $params['q_nama'] = $likeQ;
    $params['q_email'] = $likeQ;
    $params['q_telepon'] = $likeQ;
}
if (in_array($roleFilter, ['admin', 'penyedia', 'pelanggan'], true)) {
    $sql .= ' AND role = :role';
    $params['role'] = $roleFilter;
}
$sql .= ' ORDER BY id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

renderAdminHeader('Manajemen Pengguna', 'users');
?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger shadow-sm">
        <strong>Validasi gagal:</strong>
        <ul class="mb-0 mt-1">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3">
                <h2 class="h6 fw-bold mb-0"><?= $editUser ? 'Edit Pengguna #' . (int) $editUser['id'] : 'Tambah Pengguna Baru'; ?></h2>
            </div>
            <div class="card-body">
                <form method="post" action="<?= e(url('admin/users.php')); ?>">
                    <input type="hidden" name="action" value="<?= $editUser ? 'update' : 'create'; ?>">
                    <?php if ($editUser): ?>
                        <input type="hidden" name="id" value="<?= (int) $editUser['id']; ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="nama_lengkap" class="form-control" value="<?= e((string) ($editUser['nama_lengkap'] ?? '')); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" value="<?= e((string) ($editUser['email'] ?? '')); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Role <span class="text-danger">*</span></label>
                        <select name="role" class="form-select" required>
                            <?php $curRole = (string) ($editUser['role'] ?? 'pelanggan'); ?>
                            <option value="pelanggan" <?= $curRole === 'pelanggan' ? 'selected' : ''; ?>>Pelanggan</option>
                            <option value="penyedia" <?= $curRole === 'penyedia' ? 'selected' : ''; ?>>Penyedia Jasa</option>
                            <option value="admin" <?= $curRole === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Status Akun <span class="text-danger">*</span></label>
                        <select name="status_akun" class="form-select" required>
                            <?php $curStatus = (string) ($editUser['status_akun'] ?? 'aktif'); ?>
                            <option value="aktif" <?= $curStatus === 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                            <option value="nonaktif" <?= $curStatus === 'nonaktif' ? 'selected' : ''; ?>>Nonaktif</option>
                            <option value="diblokir" <?= $curStatus === 'diblokir' ? 'selected' : ''; ?>>Diblokir</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">No. Telepon</label>
                        <input type="text" name="no_telepon" class="form-control" value="<?= e((string) ($editUser['no_telepon'] ?? '')); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Alamat</label>
                        <textarea name="alamat" class="form-control" rows="2"><?= e((string) ($editUser['alamat'] ?? '')); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Password <?= $editUser ? '(Kosongkan jika tidak diubah)' : '<span class="text-danger">*</span>'; ?>
                        </label>
                        <input type="password" name="password" class="form-control" minlength="8" <?= $editUser ? '' : 'required'; ?>>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="bi bi-check-lg me-1"></i><?= $editUser ? 'Simpan Perubahan' : 'Simpan Pengguna'; ?>
                        </button>
                        <?php if ($editUser): ?>
                            <a href="<?= e(url('admin/users.php')); ?>" class="btn btn-outline-secondary">Batal</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3">
                <form method="get" action="<?= e(url('admin/users.php')); ?>" class="row g-2 align-items-center">
                    <div class="col-12 col-sm-6">
                        <input type="text" name="q" class="form-control" placeholder="Cari nama, email, atau telepon..." value="<?= e($q); ?>">
                    </div>
                    <div class="col-6 col-sm-3">
                        <select name="role" class="form-select">
                            <option value="">Semua Role</option>
                            <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            <option value="penyedia" <?= $roleFilter === 'penyedia' ? 'selected' : ''; ?>>Penyedia</option>
                            <option value="pelanggan" <?= $roleFilter === 'pelanggan' ? 'selected' : ''; ?>>Pelanggan</option>
                        </select>
                    </div>
                    <div class="col-6 col-sm-3 d-grid">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>Cari</button>
                    </div>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">Data pengguna tidak ditemukan.</td></tr>
                        <?php else: ?>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= e((string) $u['nama_lengkap']); ?></div>
                                        <small class="text-muted"><?= e((string) ($u['no_telepon'] ?: '-')); ?></small>
                                    </td>
                                    <td><?= e((string) $u['email']); ?></td>
                                    <td><span class="badge bg-primary-subtle text-primary"><?= e(labelRole((string) $u['role'])); ?></span></td>
                                    <td>
                                        <span class="badge <?= $u['status_akun'] === 'aktif' ? 'bg-success' : 'bg-secondary'; ?>">
                                            <?= e((string) $u['status_akun']); ?>
                                        </span>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <a href="<?= e(url('admin/users.php?edit=' . (int) $u['id'])); ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <?php if ($u['status_akun'] === 'aktif'): ?>
                                            <form method="post" action="<?= e(url('admin/users.php')); ?>" class="d-inline" data-confirm-action="Nonaktifkan pengguna ini?">
                                                <input type="hidden" name="action" value="deactivate">
                                                <input type="hidden" name="id" value="<?= (int) $u['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-warning" title="Nonaktifkan">
                                                    <i class="bi bi-slash-circle"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="post" action="<?= e(url('admin/users.php')); ?>" class="d-inline" data-confirm-action="Hapus permanen pengguna ini jika tidak memiliki relasi data?">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $u['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php renderAdminFooter(); ?>