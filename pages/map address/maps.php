<?php
// konfigurasi koneksi database (ubah sesuai DB kamu)
require '../db.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // contoh user_id sementara, sesuaikan dengan session login asli
}

$user_name = ''; // default kosong

// Ambil nama user dari DB berdasarkan session user_id
$user_id = $_SESSION['user_id'];
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

    // validasi sederhana
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
        // Pastikan tb_alamat_user sudah punya kolom email VARCHAR(100)
        $stmt = $conn->prepare("INSERT INTO tb_alamat_user (user_id, label_alamat, nama_user, email, nomor_hp, alamat_lengkap, kota, provinsi, kecamatan, kode_post) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssssssss", $user_id, $label_alamat, $nama_user, $email, $nomor_hp, $alamat_lengkap, $kota, $provinsi, $kecamatan, $kode_post);

        if ($stmt->execute()) {
            $success = "Alamat berhasil disimpan.";
            // kosongkan form setelah sukses submit
            $label_alamat = $nama_user = $email = $nomor_hp = $alamat_lengkap = $kota = $provinsi = $kecamatan = $kode_post = '';
        } else {
            $errors[] = "Gagal menyimpan alamat: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Form Input Alamat Pengiriman</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>

<body>

    <div class="container py-4">
        <h2 class="mb-4">Form Alamat Pengiriman produk</h2>
        <h3>Halo, <?= htmlspecialchars($user_name ?: 'User') ?>!</h3>

        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" class="row g-3 needs-validation" novalidate>
            <div class="col-md-4">
                <label for="label_alamat" class="form-label">Label Alamat (Contoh: Rumah, Kantor)</label>
                <input type="text" class="form-control" id="label_alamat" name="label_alamat" value="<?= htmlspecialchars($label_alamat ?? '') ?>" />
            </div>
            <div class="col-md-4">
                <label for="nama_user" class="form-label">Nama Lengkap *</label>
                <input type="text" class="form-control" id="nama_user" name="nama_user" value="<?= htmlspecialchars($nama_user ?? '') ?>" required />
                <div class="invalid-feedback">Nama lengkap wajib diisi.</div>
            </div>
            <div class="col-md-4">
                <label for="email" class="form-label">Email (Gmail) *</label>
                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($email ?? '') ?>" placeholder="example@gmail.com" required />
                <div class="invalid-feedback">Email wajib diisi dan format harus valid.</div>
            </div>
            <div class="col-md-4">
                <label for="nomor_hp" class="form-label">Nomor HP *</label>
                <input type="tel" pattern="[0-9+\-\s]{7,20}" class="form-control" id="nomor_hp" name="nomor_hp" value="<?= htmlspecialchars($nomor_hp ?? '') ?>" required />
                <div class="invalid-feedback">Nomor HP wajib diisi dan valid.</div>
            </div>

            <div class="col-md-6">
                <label for="alamat_lengkap" class="form-label">Alamat Lengkap *</label>
                <textarea class="form-control" id="alamat_lengkap" name="alamat_lengkap" rows="2" required><?= htmlspecialchars($alamat_lengkap ?? '') ?></textarea>
                <div class="invalid-feedback">Alamat lengkap wajib diisi.</div>
            </div>
            <div class="col-md-2">
                <label for="kota" class="form-label">Kota *</label>
                <input type="text" class="form-control" id="kota" name="kota" value="<?= htmlspecialchars($kota ?? '') ?>" required />
                <div class="invalid-feedback">Kota wajib diisi.</div>
            </div>
            <div class="col-md-2">
                <label for="provinsi" class="form-label">Provinsi *</label>
                <input type="text" class="form-control" id="provinsi" name="provinsi" value="<?= htmlspecialchars($provinsi ?? '') ?>" required />
                <div class="invalid-feedback">Provinsi wajib diisi.</div>
            </div>
            <div class="col-md-2">
                <label for="kecamatan" class="form-label">Kecamatan *</label>
                <input type="text" class="form-control" id="kecamatan" name="kecamatan" value="<?= htmlspecialchars($kecamatan ?? '') ?>" required />
                <div class="invalid-feedback">Kecamatan wajib diisi.</div>
            </div>
            <div class="col-md-2">
                <label for="kode_post" class="form-label">Kode Pos *</label>
                <input type="text" class="form-control" id="kode_post" name="kode_post" value="<?= htmlspecialchars($kode_post ?? '') ?>" required />
                <div class="invalid-feedback">Kode pos wajib diisi.</div>
            </div>

            <div class="col-12">
                <button class="btn btn-primary" type="submit">Simpan Alamat</button>
            </div>
        </form>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        (() => {
            'use strict'

            const forms = document.querySelectorAll('.needs-validation')
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    form.classList.add('was-validated')
                }, false)
            })
        })();
    </script>
    <?php
    // Ambil 1 alamat user terbaru dari database untuk ditampilkan
    $alamatUser = null;
    $stmtAlamat = $conn->prepare("SELECT id, label_alamat, nama_user, nomor_hp, alamat_lengkap, kota, provinsi, kecamatan, kode_post FROM tb_alamat_user WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmtAlamat->bind_param("i", $user_id);
    $stmtAlamat->execute();
    $resultAlamat = $stmtAlamat->get_result();
    if ($resultAlamat->num_rows > 0) {
        $alamatUser = $resultAlamat->fetch_assoc();
    }
    $stmtAlamat->close();
    ?>
    <?php if ($alamatUser): ?>
        <div class="container">
            <h4 class="mt-5">Alamat Pengiriman Terakhir yang Disimpan</h4>
            <div class="list-group">
                <div class="list-group-item">
                    <strong><?= htmlspecialchars($alamatUser['label_alamat'] ?: 'Alamat tanpa label') ?></strong><br>
                    Nama: <?= htmlspecialchars($alamatUser['nama_user']) ?> <br>
                    No. HP: <?= htmlspecialchars($alamatUser['nomor_hp']) ?> <br>
                    Alamat: <?= nl2br(htmlspecialchars($alamatUser['alamat_lengkap'])) ?>, <?= htmlspecialchars($alamatUser['kecamatan']) ?>, <?= htmlspecialchars($alamatUser['kota']) ?>, <?= htmlspecialchars($alamatUser['provinsi']) ?>, Kode Pos: <?= htmlspecialchars($alamatUser['kode_post']) ?>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="container">
            <p class="text-muted mt-4">Belum ada alamat yang disimpan.</p>
        </div>
    <?php endif; ?>

</body>

</html>