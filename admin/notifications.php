<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

$pdo = adminDb();
$errors = [];
$editNotif = null;

$allUsers = $pdo->query('SELECT id, nama_lengkap, role FROM users ORDER BY nama_lengkap ASC')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create' || $action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $userId = (int) ($_POST['user_id'] ?? 0);
        $judul = trim((string) ($_POST['judul'] ?? ''));
        $pesan = trim((string) ($_POST['pesan'] ?? ''));
        $isRead = (int) ($_POST['is_read'] ?? 0);

        if ($userId <= 0) {
            $errors[] = 'Pengguna tujuan wajib dipilih.';
        }
        if ($judul === '') {
            $errors[] = 'Judul notifikasi wajib diisi.';
        }
        if ($pesan === '') {
            $errors[] = 'Isi pesan notifikasi wajib diisi.';
        }
        if ($action === 'update' && $id <= 0) {
            $errors[] = 'ID notifikasi tidak valid.';
        }

        if (empty($errors)) {
            $readVal = $isRead === 1 ? 1 : 0;

            if ($action === 'create') {
                $stmt = $pdo->prepare(
                    'INSERT INTO notifikasi (user_id, judul, pesan, is_read)
                     VALUES (:user_id, :judul, :pesan, :is_read)'
                );
                $stmt->execute([
                    'user_id' => $userId,
                    'judul'   => $judul,
                    'pesan'   => $pesan,
                    'is_read' => $readVal,
                ]);
                setFlash('success', 'Notifikasi berhasil dikirim.');
                redirect('admin/notifications.php');
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE notifikasi
                     SET user_id = :user_id, judul = :judul, pesan = :pesan, is_read = :is_read
                     WHERE id = :id'
                );
                $stmt->execute([
                    'user_id' => $userId,
                    'judul'   => $judul,
                    'pesan'   => $pesan,
                    'is_read' => $readVal,
                    'id'      => $id,
                ]);
                setFlash('success', 'Notifikasi berhasil diperbarui.');
                redirect('admin/notifications.php');
            }
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $del = $pdo->prepare('DELETE FROM notifikasi WHERE id = :id');
            $del->execute(['id' => $id]);
            setFlash('success', 'Notifikasi berhasil dihapus.');
        }
        redirect('admin/notifications.php');
    }
}

if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    if ($editId > 0) {
        $st = $pdo->prepare('SELECT * FROM notifikasi WHERE id = :id LIMIT 1');
        $st->execute(['id' => $editId]);
        $editNotif = $st->fetch() ?: null;
    }
}

$q = trim((string) ($_GET['q'] ?? ''));
$sql = 'SELECT n.*, u.nama_lengkap, u.email
        FROM notifikasi n
        LEFT JOIN users u ON n.user_id = u.id
        WHERE 1=1';
$params = [];
if ($q !== '') {
    $sql .= ' AND (n.judul LIKE :q_judul OR n.pesan LIKE :q_pesan OR u.nama_lengkap LIKE :q_user)';
    $likeQ = '%' . $q . '%';
    $params['q_judul'] = $likeQ;
    $params['q_pesan'] = $likeQ;
    $params['q_user'] = $likeQ;
}
$sql .= ' ORDER BY n.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$notifications = $stmt->fetchAll();

renderAdminHeader('Manajemen Notifikasi', 'notifications');
?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger shadow-sm">
        <ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err); ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3">
                <h2 class="h6 fw-bold mb-0"><?= $editNotif ? 'Edit Notifikasi #' . (int) $editNotif['id'] : 'Kirim Notifikasi'; ?></h2>
            </div>
            <div class="card-body">
                <form method="post" action="<?= e(url('admin/notifications.php')); ?>">
                    <input type="hidden" name="action" value="<?= $editNotif ? 'update' : 'create'; ?>">
                    <?php if ($editNotif): ?><input type="hidden" name="id" value="<?= (int) $editNotif['id']; ?>"><?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Pengguna Tujuan <span class="text-danger">*</span></label>
                        <select name="user_id" class="form-select" required>
                            <option value="">-- Pilih Pengguna --</option>
                            <?php foreach ($allUsers as $u): ?>
                                <option value="<?= (int) $u['id']; ?>" <?= ((int) ($editNotif['user_id'] ?? 0) === (int) $u['id']) ? 'selected' : ''; ?>>
                                    <?= e((string) $u['nama_lengkap']); ?> (<?= e((string) $u['role']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Judul <span class="text-danger">*</span></label>
                        <input type="text" name="judul" class="form-control" value="<?= e((string) ($editNotif['judul'] ?? '')); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Pesan <span class="text-danger">*</span></label>
                        <textarea name="pesan" class="form-control" rows="3" required><?= e((string) ($editNotif['pesan'] ?? '')); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Status Baca</label>
                        <?php $sb = (int) ($editNotif['is_read'] ?? 0); ?>
                        <select name="is_read" class="form-select">
                            <option value="0" <?= $sb === 0 ? 'selected' : ''; ?>>Belum Dibaca</option>
                            <option value="1" <?= $sb === 1 ? 'selected' : ''; ?>>Sudah Dibaca</option>
                        </select>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-send me-1"></i>Simpan</button>
                        <?php if ($editNotif): ?><a href="<?= e(url('admin/notifications.php')); ?>" class="btn btn-outline-secondary">Batal</a><?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3">
                <form method="get" action="<?= e(url('admin/notifications.php')); ?>" class="d-flex gap-2">
                    <input type="text" name="q" class="form-control" placeholder="Cari judul, pesan, atau penerima..." value="<?= e($q); ?>">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Penerima</th>
                            <th>Notifikasi</th>
                            <th>Status</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($notifications)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">Belum ada notifikasi.</td></tr>
                        <?php else: ?>
                            <?php foreach ($notifications as $nt): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= e((string) ($nt['nama_lengkap'] ?? '-')); ?></div>
                                        <small class="text-muted"><?= e((string) ($nt['email'] ?? '')); ?></small>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?= e((string) $nt['judul']); ?></div>
                                        <small class="text-muted"><?= e((string) $nt['pesan']); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge <?= (int) ($nt['is_read'] ?? 0) === 1 ? 'bg-secondary' : 'bg-primary'; ?>">
                                            <?= (int) ($nt['is_read'] ?? 0) === 1 ? 'Dibaca' : 'Baru'; ?>
                                        </span>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <a href="<?= e(url('admin/notifications.php?edit=' . (int) $nt['id'])); ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                        <form method="post" action="<?= e(url('admin/notifications.php')); ?>" class="d-inline" data-confirm-action="Hapus notifikasi ini?">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $nt['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
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