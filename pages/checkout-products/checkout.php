<?php
session_start();
require '../db.php';

// Cek login
if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('Silakan login terlebih dahulu.'); location.href='../login.php';</script>";
    exit;
}

$user_id = $_SESSION['user_id'];
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Ambil data produk
$product_q = mysqli_query($conn, "SELECT * FROM tb_adminProduct WHERE id = $product_id");
$product = mysqli_fetch_assoc($product_q);
if (!$product) {
    echo "Produk tidak ditemukan.";
    exit;
}

// Ambil alamat utama user dari tb_alamat_user
$alamat_q = mysqli_query($conn, "SELECT * FROM tb_alamat_user WHERE user_id = $user_id LIMIT 1");
$alamat = mysqli_fetch_assoc($alamat_q);

include '../db.php';

$user_id = $_SESSION['user_id'] ?? 0;
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Ambil alamat terpilih jika ada
$alamat = null;
if (!empty($_SESSION['selected_alamat_id'])) {
    $selected_alamat_id = (int)$_SESSION['selected_alamat_id'];
    $stmt = $conn->prepare("SELECT * FROM tb_alamat_user WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $selected_alamat_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $alamat = $result->fetch_assoc();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Checkout Produk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .jumlah-input {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .jumlah-input input {
            width: 60px;
            text-align: center;
        }
    </style>
</head>

<body>

    <div class="container mt-5 mb-5">
        <div class="card shadow">
            <div class="card-body">
                <h3>Checkout Produk</h3>
                <div class="d-flex p-3 bg-white mb-4">
                    <div class="col-md-3">
                        <img src="../../admin/uploads/<?= $product['foto_thumbnail'] ?>" class="rounded" alt="" style="height: 120px; width: 120px;">
                    </div>
                    <?php
                    // Ambil data produk (ganti dengan metode yang kamu gunakan, misal berdasarkan $_GET['id'])
                    $product_id = $_GET['id'];
                    $stmt = $conn->prepare("SELECT * FROM tb_adminProduct WHERE id = ?");
                    $stmt->bind_param("i", $product_id);
                    $stmt->execute();
                    $product = $stmt->get_result()->fetch_assoc();

                    // Ambil varian produk (jika ada)
                    $varianProduk = [];
                    $stmtVarian = $conn->prepare("SELECT * FROM tb_varian_product WHERE product_id = ?");
                    $stmtVarian->bind_param("i", $product['id']);
                    $stmtVarian->execute();
                    $resultVarian = $stmtVarian->get_result();
                    while ($row = $resultVarian->fetch_assoc()) {
                        $varianProduk[] = $row;
                    }
                    ?>

                    <div class="col-md-3">
                        <h5><?= htmlspecialchars($product['nama_produk']) ?></h5>
                        <?php
                        $variant_id = isset($_GET['variant_id']) ? intval($_GET['variant_id']) : 0;
                        $variant = null;

                        if ($variant_id > 0) {
                            $variant_q = mysqli_query($conn, "SELECT * FROM tb_varian_product WHERE id = $variant_id AND product_id = $product_id");
                            $variant = mysqli_fetch_assoc($variant_q);
                        }

                        ?>

                        <?php if ($variant): ?>
                            <p><strong>Varian:</strong> <?= htmlspecialchars($variant['varian']) ?></p>
                            <p><strong>Stok Varian:</strong> <?= (int)$variant['stok'] ?></p>
                        <?php endif; ?>
                        <div>
                            <strong>Rp<?= number_format($product['is_diskon'] ? $product['harga_diskon'] : $product['harga'], 0, ',', '.') ?></strong>
                        </div>

                        <!-- Input Jumlah -->
                        <?php
                        $jumlah = isset($_GET['jumlah']) ? (int)$_GET['jumlah'] : 1;
                        ?>

                        <div class="jumlah-input mt-2">
                            <label for="jumlah">Jumlah:</label>
                            <button class="btn btn-outline-secondary btn-sm" onclick="ubahJumlah(-1)">-</button>
                            <input type="text"
                                id="jumlah"
                                name="jumlah"
                                value="<?= $jumlah ?>"
                                min="1"
                                max="<?= empty($varianProduk) ? $product['jumlah_stok'] : $varianProduk[0]['stok'] ?>"
                                readonly>

                            <button class="btn btn-outline-secondary btn-sm" onclick="ubahJumlah(1)">+</button>
                        </div>

                        <!-- Total Harga -->
                        <div class="mt-2 text-success fw-bold" id="totalHarga">
                            Total Harga: Rp<?= number_format(($product['is_diskon'] ? $product['harga_diskon'] : $product['harga']), 0, ',', '.') ?>
                        </div>
                    </div>

                    <!-- JavaScript -->
                    <script>
                        function ubahJumlah(delta) {
                            const input = document.getElementById("jumlah");
                            const max = parseInt(input.max);
                            let value = parseInt(input.value) + delta;

                            if (value < 1) value = 1;
                            if (value > max) value = max;

                            input.value = value;
                            hitungTotalHarga();
                        }

                        function ubahStokLangsung(radio) {
                            const stok = parseInt(radio.getAttribute("data-stok"));
                            const jumlahInput = document.getElementById("jumlah");
                            jumlahInput.max = stok;
                            jumlahInput.value = 1;
                            hitungTotalHarga();
                        }

                        function hitungTotalHarga() {
                            const jumlah = parseInt(document.getElementById("jumlah").value);
                            const harga = <?= $product['is_diskon'] ? $product['harga_diskon'] : $product['harga'] ?>;
                            const total = jumlah * harga;
                            document.getElementById("totalHarga").innerText = "Total Harga: Rp" + total.toLocaleString("id-ID");
                        }
                    </script>

                </div>

                <!-- alamat pengiriman -->
                <h5>Alamat Pengiriman</h5>
                <?php if ($alamat): ?>
                    <div class="border rounded bg-white p-3 mb-3">
                        <p class="mb-1"><strong><i class="bi bi-geo-alt-fill"></i> <?= htmlspecialchars($alamat['label_alamat'] ?: 'Alamat Utama') ?></strong></p>
                        <p class="mb-1">id: <?= $alamat['id'] ?></p>
                        <p class="mb-1">Nama: <?= htmlspecialchars($alamat['nama_user']) ?></p>
                        <p class="mb-1">No. HP: <?= htmlspecialchars($alamat['nomor_hp']) ?></p>
                        <p>Alamat: <?= htmlspecialchars($alamat['alamat_lengkap']) ?>,
                            <?= htmlspecialchars($alamat['kota']) ?>,
                            <?= htmlspecialchars($alamat['provinsi']) ?>,
                            Kode Pos: <?= htmlspecialchars($alamat['kode_post']) ?>
                        </p>
                        <div class="mt-3 mb-3">
                            <a href="../map address/editAlamat.php?id=<?= $alamat['id'] ?>" class="btn btn-primary btn-sm">Edit alamat</a>
                        </div>
                        <a href="../map address/alamatLain.php?id=<?= $product_id ?>" class="btn btn-success btn-sm">Ganti alamat</a>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">Alamat belum tersedia. Silakan tambahkan alamat.</div>
                    <a href="../map address/alamatLain.php?id=<?= $product_id ?>" class="btn btn-success btn-sm">Pilih alamat</a>
                <?php endif; ?>

                <!-- Metode Pembayaran -->

                <!-- button konfirmasi pesanan -->
                <div class="mt-3">
                    <button class="btn btn-success">Konfirmasi pesanan</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function ubahJumlah(n) {
            let input = document.getElementById('jumlah');
            let harga = <?= $product['is_diskon'] ? $product['harga_diskon'] : $product['harga'] ?>;
            let max = <?= $product['jumlah_stok'] ?>;
            let val = parseInt(input.value);
            val = Math.max(1, Math.min(max, val + n));
            input.value = val;
            document.getElementById('totalHarga').textContent = "Total Harga: Rp" + (val * harga).toLocaleString('id-ID');
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>