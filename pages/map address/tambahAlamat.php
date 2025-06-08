<?php
require '../db.php';
session_start();

// Sementara (ganti sesuai session login asli)
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
}
$user_id = $_SESSION['user_id'];

// Ambil username
$user_name = '';
$stmtUser = $conn->prepare("SELECT username FROM tb_userLogin WHERE id = ?");
$stmtUser->bind_param("i", $user_id);
$stmtUser->execute();
$stmtUser->bind_result($user_name);
$stmtUser->fetch();
$stmtUser->close();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $label_alamat = trim($_POST['label_alamat'] ?? '');
    $nama_user = trim($_POST['nama_user'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $nomor_hp = trim($_POST['nomor_hp'] ?? '');
    $alamat_lengkap = trim($_POST['alamat_lengkap'] ?? '');
    $kota = trim($_POST['kota'] ?? '');
    $provinsi = trim($_POST['provinsi'] ?? '');
    $kecamatan = trim($_POST['kecamatan'] ?? '');
    $kode_post = trim($_POST['kode_post'] ?? '');

    if ($nama_user === '') $errors[] = "Nama wajib diisi.";
    if ($email === '') {
        $errors[] = "Email wajib diisi.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Format email tidak valid.";
    }
    if ($nomor_hp === '') $errors[] = "Nomor HP wajib diisi.";
    if ($alamat_lengkap === '') $errors[] = "Alamat lengkap wajib diisi.";
    if ($kota === '') $errors[] = "Kota wajib diisi.";
    if ($provinsi === '') $errors[] = "Provinsi wajib diisi.";
    if ($kecamatan === '') $errors[] = "Kecamatan wajib diisi.";
    if ($kode_post === '') $errors[] = "Kode pos wajib diisi.";

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO tb_alamat_user (user_id, label_alamat, nama_user, email, nomor_hp, alamat_lengkap, kota, provinsi, kecamatan, kode_post) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssssssss", $user_id, $label_alamat, $nama_user, $email, $nomor_hp, $alamat_lengkap, $kota, $provinsi, $kecamatan, $kode_post);
        if ($stmt->execute()) {
            $success = "Alamat berhasil disimpan.";
            $label_alamat = $nama_user = $email = $nomor_hp = $alamat_lengkap = $kota = $provinsi = $kecamatan = $kode_post = '';
        } else {
            $errors[] = "Gagal menyimpan alamat: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Ambil alamat terakhir
$stmtLast = $conn->prepare("SELECT * FROM tb_alamat_user WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmtLast->bind_param("i", $user_id);
$stmtLast->execute();
$resultLast = $stmtLast->get_result();
$alamatTerakhir = $resultLast->fetch_assoc();
$stmtLast->close();

// Ambil semua alamat lainnya (kecuali yang terbaru)
$stmtLainnya = $conn->prepare("SELECT * FROM tb_alamat_user WHERE user_id = ? AND id != ? ORDER BY id DESC");
$last_id = $alamatTerakhir['id'] ?? 0;
$stmtLainnya->bind_param("ii", $user_id, $last_id);
$stmtLainnya->execute();
$resultLainnya = $stmtLainnya->get_result();
$alamatLainnya = $resultLainnya->fetch_all(MYSQLI_ASSOC);
$stmtLainnya->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Form Input Alamat Pengiriman</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body>
<div class="container py-4">
    <h2 class="mb-4">Buat Alamat Baru <?= htmlspecialchars($user_name ?: 'User') ?></h2>
    <?php if ($errors): ?>
        <div class="alert alert-danger"><ul><?php foreach ($errors as $err): ?><li><?= htmlspecialchars($err) ?></li><?php endforeach ?></ul></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST" class="row g-3 needs-validation" novalidate>
        <div class="col-md-4">
            <label for="label_alamat" class="form-label">Label Alamat</label>
            <input type="text" class="form-control" name="label_alamat" value="<?= htmlspecialchars($label_alamat ?? '') ?>" />
        </div>
        <div class="col-md-4">
            <label for="nama_user" class="form-label">Nama Lengkap *</label>
            <input type="text" class="form-control" name="nama_user" value="<?= htmlspecialchars($nama_user ?? '') ?>" required />
            <div class="invalid-feedback">Nama wajib diisi.</div>
        </div>
        <div class="col-md-4">
            <label for="email" class="form-label">Email *</label>
            <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($email ?? '') ?>" required />
            <div class="invalid-feedback">Email wajib diisi dan valid.</div>
        </div>
        <div class="col-md-4">
            <label for="nomor_hp" class="form-label">Nomor HP *</label>
            <input type="tel" class="form-control" name="nomor_hp" pattern="[0-9+\-\s]{7,20}" value="<?= htmlspecialchars($nomor_hp ?? '') ?>" required />
            <div class="invalid-feedback">Nomor HP wajib diisi.</div>
        </div>
        <div class="col-md-6">
            <label for="alamat_lengkap" class="form-label">Alamat Lengkap *</label>
            <textarea class="form-control" name="alamat_lengkap" rows="2" required><?= htmlspecialchars($alamat_lengkap ?? '') ?></textarea>
        </div>
        <div class="col-md-2">
            <label for="kota" class="form-label">Kota *</label>
            <input type="text" class="form-control" name="kota" value="<?= htmlspecialchars($kota ?? '') ?>" required />
        </div>
        <div class="col-md-2">
            <label for="provinsi" class="form-label">Provinsi *</label>
            <input type="text" class="form-control" name="provinsi" value="<?= htmlspecialchars($provinsi ?? '') ?>" required />
        </div>
        <div class="col-md-2">
            <label for="kecamatan" class="form-label">Kecamatan *</label>
            <input type="text" class="form-control" name="kecamatan" value="<?= htmlspecialchars($kecamatan ?? '') ?>" required />
        </div>
        <div class="col-md-2">
            <label for="kode_post" class="form-label">Kode Pos *</label>
            <input type="text" class="form-control" name="kode_post" value="<?= htmlspecialchars($kode_post ?? '') ?>" required />
        </div>
        <div class="col-12">
            <button class="btn btn-primary" type="submit">Simpan Alamat</button>
        </div>
    </form>

    <!-- Alamat Terakhir -->
    <?php if ($alamatTerakhir): ?>
        <div class="mt-5">
            <h4>Alamat Pengiriman Terakhir</h4>
            <div class="card">
                <div class="card-body">
                    <strong><?= htmlspecialchars($alamatTerakhir['label_alamat'] ?: 'Tanpa Label') ?></strong><br>
                    <?= htmlspecialchars($alamatTerakhir['nama_user']) ?> <br>
                    <?= htmlspecialchars($alamatTerakhir['nomor_hp']) ?> <br>
                    <?= nl2br(htmlspecialchars($alamatTerakhir['alamat_lengkap'])) ?>, <?= htmlspecialchars($alamatTerakhir['kecamatan']) ?>, <?= htmlspecialchars($alamatTerakhir['kota']) ?>, <?= htmlspecialchars($alamatTerakhir['provinsi']) ?>, <?= htmlspecialchars($alamatTerakhir['kode_post']) ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Alamat Lainnya -->
    <?php if (!empty($alamatLainnya)): ?>
        <div class="mt-4">
            <h5>Gunakan Alamat Lainnya</h5>
            <div class="list-group">
                <?php foreach ($alamatLainnya as $alamat): ?>
                    <div class="list-group-item">
                        <strong><?= htmlspecialchars($alamat['label_alamat'] ?: 'Tanpa Label') ?></strong><br>
                        <?= htmlspecialchars($alamat['nama_user']) ?> <br>
                        <?= htmlspecialchars($alamat['nomor_hp']) ?> <br>
                        <?= nl2br(htmlspecialchars($alamat['alamat_lengkap'])) ?>, <?= htmlspecialchars($alamat['kecamatan']) ?>, <?= htmlspecialchars($alamat['kota']) ?>, <?= htmlspecialchars($alamat['provinsi']) ?>, <?= htmlspecialchars($alamat['kode_post']) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(() => {
    'use strict';
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
})();
</script>
</body>
</html>
