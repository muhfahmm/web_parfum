<?php
session_start();
include '../db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$cart_id = isset($_GET['cart_id']) ? (int)$_GET['cart_id'] : 0;
$alamatList = [];

// Ambil alamat
$stmt = $conn->prepare("SELECT id, label_alamat, nama_user, nomor_hp, alamat_lengkap, kota, provinsi, kecamatan, kode_post FROM tb_alamat_user WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $alamatList[] = $row;
}
$stmt->close();

// Proses jika pilih alamat
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['alamat_id'])) {
    $_SESSION['selected_alamat_id'] = (int)$_POST['alamat_id'];
    header("Location: ../checkout-products/checkoutFromCart.php?id=" . $cart_id);

    exit;
}
?>


<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Pilih Alamat</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        .alamat-box {
            border: 2px solid #ccc;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            cursor: pointer;
            transition: border-color 0.3s ease;
        }

        .alamat-box.selected {
            border-color: #28a745;
            /* Hijau */
            background-color: #e9f9ec;
        }

        .btn-konfirmasi {
            display: none;
        }

        .alamat-box.selected .btn-konfirmasi {
            display: inline-block;
            margin-top: 10px;
        }
    </style>
</head>

<body class="container mt-4">

    <h3 class="mb-4">Ganti Alamat Pengiriman</h3>

    <?php if (empty($alamatList)): ?>
        <p>Belum ada alamat yang ditambahkan.</p>
        <a href="../map address/tambahAlamat.php" class="btn btn-success">Tambah Alamat Baru</a>
    <?php else: ?>
        <form method="post" id="formKonfirmasi">
            <?php foreach ($alamatList as $alamat): ?>
                id: <?= htmlspecialchars($alamat['id']) ?><br>
                <div class="alamat-box" onclick="pilihAlamat(this, <?= $alamat['id'] ?>)">
                    <strong><?= htmlspecialchars($alamat['label_alamat'] ?: 'Tanpa Label') ?></strong><br>
                    <?= htmlspecialchars($alamat['nama_user']) ?> <br>
                    <?= htmlspecialchars($alamat['nomor_hp']) ?> <br>
                    <?= nl2br(htmlspecialchars($alamat['alamat_lengkap'])) ?>,
                    <?= htmlspecialchars($alamat['kecamatan']) ?>,
                    <?= htmlspecialchars($alamat['kota']) ?>,
                    <?= htmlspecialchars($alamat['provinsi']) ?>,
                    Kode Pos: <?= htmlspecialchars($alamat['kode_post']) ?>

                    <input type="hidden" name="alamat_id" value="<?= $alamat['id'] ?>" disabled>
                    <div>
                        <button type="submit" class="btn btn-success btn-konfirmasi">Konfirmasi</button>
                    </div>

                </div>
            <?php endforeach; ?>
        </form>
        <a href="tambahAlamat.php" class="btn btn-success">tambahkan alamat lain</a>
    <?php endif; ?>

    <script>
        function pilihAlamat(elem, alamatId) {
            const allBoxes = document.querySelectorAll('.alamat-box');
            allBoxes.forEach(box => {
                box.classList.remove('selected');
                box.querySelector('input[name="alamat_id"]').disabled = true;
                box.querySelector('.btn-konfirmasi').style.display = 'none';
            });

            elem.classList.add('selected');
            elem.querySelector('input[name="alamat_id"]').disabled = false;
            elem.querySelector('.btn-konfirmasi').style.display = 'inline-block';
        }
    </script>

</body>

</html>