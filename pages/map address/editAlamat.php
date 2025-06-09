<?php
require '../db.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // contoh user_id sementara
}

$user_id = $_SESSION['user_id'];

// Ambil nama user
$stmtUser = $conn->prepare("SELECT username FROM tb_userLogin WHERE id = ?");
$stmtUser->bind_param("i", $user_id);
$stmtUser->execute();
$stmtUser->bind_result($user_name);
$stmtUser->fetch();
$stmtUser->close();

$errors = [];
$success = '';

// Ambil ID dari parameter URL
$alamat_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Ambil data alamat berdasarkan ID
$alamatUser = null;
if ($alamat_id > 0) {
    $stmtAlamat = $conn->prepare("SELECT id, label_alamat, nama_user, email, nomor_hp, alamat_lengkap, kota, provinsi, kecamatan, kode_post FROM tb_alamat_user WHERE id = ? AND user_id = ?");
    $stmtAlamat->bind_param("ii", $alamat_id, $user_id);
    $stmtAlamat->execute();
    $resultAlamat = $stmtAlamat->get_result();
    
    if ($resultAlamat->num_rows > 0) {
        $alamatUser = $resultAlamat->fetch_assoc();
    } else {
        $errors[] = "Alamat tidak ditemukan atau tidak memiliki akses.";
    }
    $stmtAlamat->close();
} else {
    $errors[] = "ID alamat tidak valid.";
}

// Jika update form dikirim
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $label_alamat = trim($_POST['label_alamat'] ?? '');
    $nama_user = trim($_POST['nama_user'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $nomor_hp = trim($_POST['nomor_hp'] ?? '');
    $alamat_lengkap = trim($_POST['alamat_lengkap'] ?? '');
    $kota = trim($_POST['kota'] ?? '');
    $provinsi = trim($_POST['provinsi'] ?? '');
    $kecamatan = trim($_POST['kecamatan'] ?? '');
    $kode_post = trim($_POST['kode_post'] ?? '');

    // validasi input
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

    if (empty($errors) && $alamatUser) {
        // update alamat existing
        $stmtUpdate = $conn->prepare("UPDATE tb_alamat_user SET label_alamat=?, nama_user=?, email=?, nomor_hp=?, alamat_lengkap=?, kota=?, provinsi=?, kecamatan=?, kode_post=? WHERE id=? AND user_id=?");
        $stmtUpdate->bind_param(
            "sssssssssii",
            $label_alamat,
            $nama_user,
            $email,
            $nomor_hp,
            $alamat_lengkap,
            $kota,
            $provinsi,
            $kecamatan,
            $kode_post,
            $alamat_id,
            $user_id
        );

        if ($stmtUpdate->execute()) {
            $success = "Alamat berhasil diperbarui.";
            // update data $alamatUser agar form tampil data terbaru
            $alamatUser = [
                'id' => $alamat_id,
                'label_alamat' => $label_alamat,
                'nama_user' => $nama_user,
                'email' => $email,
                'nomor_hp' => $nomor_hp,
                'alamat_lengkap' => $alamat_lengkap,
                'kota' => $kota,
                'provinsi' => $provinsi,
                'kecamatan' => $kecamatan,
                'kode_post' => $kode_post,
            ];
        } else {
            $errors[] = "Gagal memperbarui alamat: " . $stmtUpdate->error;
        }
        $stmtUpdate->close();
    }
}

// Jika belum submit update, pakai data alamat yang sudah diambil
if ($alamatUser) {
    $label_alamat = $alamatUser['label_alamat'];
    $nama_user = $alamatUser['nama_user'];
    $email = $alamatUser['email'];
    $nomor_hp = $alamatUser['nomor_hp'];
    $alamat_lengkap = $alamatUser['alamat_lengkap'];
    $kota = $alamatUser['kota'];
    $provinsi = $alamatUser['provinsi'];
    $kecamatan = $alamatUser['kecamatan'];
    $kode_post = $alamatUser['kode_post'];
} else {
    // default kosong jika tidak ada alamat
    $label_alamat = $nama_user = $email = $nomor_hp = $alamat_lengkap = $kota = $provinsi = $kecamatan = $kode_post = '';
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Edit Alamat Pengiriman</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>

<body>
    <div class="container py-4">
        <h2>Edit Alamat Pengiriman</h2>
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

        <?php if ($alamatUser): ?>
            <p>Mengedit alamat ID: <?= htmlspecialchars($alamatUser['id']) ?></p>
            
            <form method="POST" class="row g-3 needs-validation" novalidate>
                <input type="hidden" name="update" value="1" />
                <div class="col-md-4">
                    <label for="label_alamat" class="form-label">Label Alamat (Contoh: Rumah, Kantor)</label>
                    <input type="text" class="form-control" id="label_alamat" name="label_alamat" value="<?= htmlspecialchars($label_alamat) ?>" />
                </div>
                <div class="col-md-4">
                    <label for="nama_user" class="form-label">Nama Lengkap *</label>
                    <input type="text" class="form-control" id="nama_user" name="nama_user" value="<?= htmlspecialchars($nama_user) ?>" required />
                    <div class="invalid-feedback">Nama lengkap wajib diisi.</div>
                </div>
                <div class="col-md-4">
                    <label for="email" class="form-label">Email (Gmail) *</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($email) ?>" placeholder="example@gmail.com" required />
                    <div class="invalid-feedback">Email wajib diisi dan format harus valid.</div>
                </div>
                <div class="col-md-4">
                    <label for="nomor_hp" class="form-label">Nomor HP *</label>
                    <input type="tel" pattern="[0-9+\-\s]{7,20}" class="form-control" id="nomor_hp" name="nomor_hp" value="<?= htmlspecialchars($nomor_hp) ?>" required />
                    <div class="invalid-feedback">Nomor HP wajib diisi dan valid.</div>
                </div>
                <div class="col-md-6">
                    <label for="alamat_lengkap" class="form-label">Alamat Lengkap *</label>
                    <textarea class="form-control" id="alamat_lengkap" name="alamat_lengkap" rows="2" required><?= htmlspecialchars($alamat_lengkap) ?></textarea>
                    <div class="invalid-feedback">Alamat lengkap wajib diisi.</div>
                </div>
                <div class="col-md-2">
                    <label for="kota" class="form-label">Kota *</label>
                    <input type="text" class="form-control" id="kota" name="kota" value="<?= htmlspecialchars($kota) ?>" required />
                    <div class="invalid-feedback">Kota wajib diisi.</div>
                </div>
                <div class="col-md-2">
                    <label for="provinsi" class="form-label">Provinsi *</label>
                    <input type="text" class="form-control" id="provinsi" name="provinsi" value="<?= htmlspecialchars($provinsi) ?>" required />
                    <div class="invalid-feedback">Provinsi wajib diisi.</div>
                </div>
                <div class="col-md-2">
                    <label for="kecamatan" class="form-label">Kecamatan *</label>
                    <input type="text" class="form-control" id="kecamatan" name="kecamatan" value="<?= htmlspecialchars($kecamatan) ?>" required />
                    <div class="invalid-feedback">Kecamatan wajib diisi.</div>
                </div>
                <div class="col-md-2">
                    <label for="kode_post" class="form-label">Kode Pos *</label>
                    <input type="text" class="form-control" id="kode_post" name="kode_post" value="<?= htmlspecialchars($kode_post) ?>" required />
                    <div class="invalid-feedback">Kode pos wajib diisi.</div>
                </div>
                <div class="col-12 mt-3">
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                    <a href="tambahAlamat.php" class="btn btn-secondary">Tambahkan Alamat Baru</a>
                    <a href="../checkout/checkoutFromCart.php" class="btn btn-outline-primary">Kembali ke Checkout</a>
                </div>
            </form>
        <?php else: ?>
            <div class="alert alert-warning">
                Tidak dapat mengedit alamat. Silakan <a href="tambahAlamat.php" class="alert-link">tambahkan alamat baru</a> atau <a href="../checkout/checkoutFromCart.php" class="alert-link">kembali ke checkout</a>.
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Bootstrap form validation client-side
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
        })()
    </script>
</body>

</html>