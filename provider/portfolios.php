<?php
declare(strict_types=1);

$pageTitle = 'Manajemen Portofolio';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/shared_functions.php';

requireRole('penyedia');

$pdo = getDBConnection();
$user = currentUser();
$penyediaId = (int) ($user['id'] ?? 0);

$colsStmt = $pdo->query('SHOW COLUMNS FROM portofolio');
$portCols = array_column($colsStmt->fetchAll(), 'Field');

$titleCol = null;
foreach (['judul', 'judul_portofolio', 'judul_proyek', 'judul_karya', 'nama_proyek', 'nama_portofolio', 'nama', 'title'] as $candidate) {
    if (in_array($candidate, $portCols, true)) {
        $titleCol = $candidate;
        break;
    }
}

$descCol = null;
foreach (['deskripsi', 'keterangan', 'catatan', 'detail', 'description'] as $candidate) {
    if (in_array($candidate, $portCols, true)) {
        $descCol = $candidate;
        break;
    }
}

$hasProviderCol = in_array('penyedia_id', $portCols, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'create');

    if ($action === 'create') {
        $judul = trim((string) ($_POST['judul'] ?? ''));
        $deskripsi = trim((string) ($_POST['deskripsi'] ?? ''));

        if ($titleCol === null) {
            setFlash('danger', 'Kolom judul pada tabel portofolio tidak ditemukan. Kolom tersedia: ' . implode(', ', $portCols));
        } elseif ($judul === '') {
            setFlash('danger', 'Judul pekerjaan/proyek wajib diisi.');
        } else {
            $columns = [];
            $placeholders = [];
            $params = [];

            if ($hasProviderCol) {
                $columns[] = 'penyedia_id';
                $placeholders[] = ':pid';
                $params['pid'] = $penyediaId;
            }

            $columns[] = $titleCol;
            $placeholders[] = ':judul';
            $params['judul'] = $judul;

            if ($descCol !== null) {
                $columns[] = $descCol;
                $placeholders[] = ':desk';
                $params['desk'] = $deskripsi;
            }

            $sql = 'INSERT INTO portofolio (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
            $ins = $pdo->prepare($sql);
            $ins->execute($params);

            setFlash('success', 'Portofolio berhasil ditambahkan.');
            redirect('provider/portfolios.php');
        }
    } elseif ($action === 'delete') {
        $portId = (int) ($_POST['id'] ?? 0);
        if ($hasProviderCol) {
            $del = $pdo->prepare('DELETE FROM portofolio WHERE id = :id AND penyedia_id = :pid');
            $del->execute(['id' => $portId, 'pid' => $penyediaId]);
        } else {
            $del = $pdo->prepare('DELETE FROM portofolio WHERE id = :id');
            $del->execute(['id' => $portId]);
        }
        setFlash('success', 'Portofolio berhasil dihapus.');
        redirect('provider/portfolios.php');
    }
}

if ($hasProviderCol) {
    $stmt = $pdo->prepare('SELECT * FROM portofolio WHERE penyedia_id = :pid ORDER BY id DESC');
    $stmt->execute(['pid' => $penyediaId]);
} else {
    $stmt = $pdo->query('SELECT * FROM portofolio ORDER BY id DESC');
}
$portfolios = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<?php renderFlashMessages(); ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 fw-bold mb-0">Manajemen Portofolio</h1>
    <a href="<?= e(url('provider/dashboard.php')); ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali ke Dashboard
    </a>
</div>

<?php if ($titleCol === null): ?>
    <div class="alert alert-warning">
        Kolom judul pada tabel <code>portofolio</code> belum cocok otomatis. Daftar kolom yang ada: <strong><?= e(implode(', ', $portCols)); ?></strong>.
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm p-4">
            <h2 class="h6 fw-bold mb-3">Tambah Portofolio</h2>
            <form method="post">
                <input type="hidden" name="action" value="create">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Judul Pekerjaan/Proyek <span class="text-danger">*</span></label>
                    <input type="text" name="judul" class="form-control" required>
                </div>
                <?php if ($descCol !== null): ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Deskripsi</label>
                        <textarea name="deskripsi" rows="4" class="form-control"></textarea>
                    </div>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary fw-semibold">
                    <i class="bi bi-plus-lg me-1"></i>Tambah Portofolio
                </button>
            </form>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card border-0 shadow-sm p-4">
            <h2 class="h6 fw-bold mb-3">Daftar Portofolio</h2>
            <?php if (empty($portfolios)): ?>
                <p class="text-muted mb-0">Belum ada portofolio tersimpan.</p>
            <?php else: ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($portfolios as $prt): ?>
                        <?php
                        $judulItem = $titleCol !== null
                            ? (string) ($prt[$titleCol] ?? ('Portofolio #' . ($prt['id'] ?? '')))
                            : ('Portofolio #' . ($prt['id'] ?? ''));
                        $deskItem = $descCol !== null ? (string) ($prt[$descCol] ?? '') : '';
                        ?>
                        <div class="border rounded-3 p-3 d-flex justify-content-between align-items-start gap-3">
                            <div>
                                <div class="fw-bold"><?= e($judulItem); ?></div>
                                <?php if ($deskItem !== ''): ?>
                                    <div class="small text-muted mt-1"><?= e($deskItem); ?></div>
                                <?php endif; ?>
                            </div>
                            <form method="post" onsubmit="return confirm('Hapus portofolio ini?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) ($prt['id'] ?? 0); ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>