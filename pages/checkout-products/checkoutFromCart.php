<?php
session_start();
require_once '../db.php';

// Update jumlah jika ada request POST dari fetch()
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cart_id'], $_POST['jumlah'])) {
    $cart_id = intval($_POST['cart_id']);
    $jumlah = intval($_POST['jumlah']);

    if ($cart_id > 0 && $jumlah > 0) {
        $query = $conn->prepare("UPDATE tb_cart SET jumlah = ? WHERE id = ?");
        $query->bind_param("ii", $jumlah, $cart_id);
        $query->execute();
        echo "success";
        exit;
    } else {
        echo "invalid";
        exit;
    }
}

// Validasi ID keranjang (GET)
if (!isset($_GET['id'])) {
    echo "Produk tidak ditemukan.";
    exit;
}

$cart_id = intval($_GET['id']);

// Ambil data keranjang + produk + varian
$query = $conn->prepare("
    SELECT 
        c.id AS cart_id,
        c.jumlah,
        c.foto_thumbnail,
        p.nama_produk,
        p.foto_thumbnail AS produk_foto,
        p.harga,
        p.harga_diskon,
        p.is_diskon,
        v.varian
    FROM tb_cart c
    JOIN tb_adminProduct p ON c.product_id = p.id
    LEFT JOIN tb_varian_product v ON c.varian_id = v.id
    WHERE c.id = ?
");
$query->bind_param("i", $cart_id);
$query->execute();
$result = $query->get_result();
$item = $result->fetch_assoc();

if (!$item) {
    echo "Item tidak ditemukan.";
    exit;
}

// Hitung harga yang akan digunakan (diskon atau normal)
$harga_aktif = $item['is_diskon'] ? $item['harga_diskon'] : $item['harga'];
$total_harga = $harga_aktif * $item['jumlah'];
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
        .product-img {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 8px;
        }

        .original-price {
            text-decoration: line-through;
            color: gray;
            font-size: 0.9rem;
        }

        .discounted-price {
            color: red;
            font-weight: bold;
        }

        .quantity-control input {
            width: 60px;
            text-align: center;
        }

        #totalHarga {
            font-weight: bold;
            margin-top: 10px;
            font-size: 1.2rem;
            color: #28a745;
        }
    </style>
</head>

<body class="bg-light">
    <div class="container py-5">
        <div class="card shadow">
            <div class="card-body">
                <h3>Checkout Produk</h3>
                <form action="" method="POST" id="checkoutForm">
                    <div class="d-flex p-3 bg-white mb-4">
                        <div class="col-md-3">
                            <img class="product-img img-fluid" src="../../admin/uploads/<?= htmlspecialchars($item['produk_foto']) ?>" alt="<?= htmlspecialchars($item['nama_produk']) ?>">
                        </div>
                        <div class="col-md-9">
                            <h5><?= htmlspecialchars($item['nama_produk']) ?></h5>

                            <?php if (!empty($item['varian'])): ?>
                                <p class="mb-1"><strong>Varian:</strong> <?= htmlspecialchars($item['varian']) ?></p>
                            <?php endif; ?>

                            <div class="mb-2">
                                <?php if ($item['is_diskon']): ?>
                                    <span class="original-price me-2">Rp<?= number_format($item['harga'], 0, ',', '.') ?></span>
                                    <span class="discounted-price">Rp<?= number_format($item['harga_diskon'], 0, ',', '.') ?></span>
                                <?php else: ?>
                                    <span class="fw-bold">Rp<?= number_format($item['harga'], 0, ',', '.') ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="d-flex align-items-center quantity-control">
                                <label class="me-1 mb-0">Jumlah:</label>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="updateQuantity(-1)">-</button>
                                <input type="text" class="mx-2" name="jumlah" id="jumlah" value="<?= intval($item['jumlah']) ?>" readonly>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="updateQuantity(1)">+</button>
                            </div>

                            <div id="totalHarga">Total Harga: Rp<?= number_format($total_harga, 0, ',', '.') ?></div>
                        </div>
                    </div>

                    <!-- alamat user -- masih ada bug di pilihan alamat -->
                    <div class="user-address">
                        <?php
                        if (!isset($_SESSION['user_id'])) {
                            $_SESSION['user_id'] = 1; // contoh user_id sementara
                        }

                        $user_id = $_SESSION['user_id'];
                        $user_name = '';

                        // Ambil nama user
                        $stmtUser = $conn->prepare("SELECT username FROM tb_userLogin WHERE id = ?");
                        $stmtUser->bind_param("i", $user_id);
                        $stmtUser->execute();
                        $stmtUser->bind_result($user_name);
                        $stmtUser->fetch();
                        $stmtUser->close();

                        $errors = [];
                        $success = '';

                        // Proses form tambah alamat
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

                            // Validasi input
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
                                    $success = "Alamat berhasil disimpan. Silakan pilih alamat untuk digunakan.";
                                    // Jangan langsung menampilkan alamat yang baru ditambah
                                    unset($_SESSION['selected_alamat_id']);
                                } else {
                                    $errors[] = "Gagal menyimpan alamat: " . $stmt->error;
                                }
                                $stmt->close();
                            }
                        }

                        // Ambil alamat berdasarkan pilihan user (jika sudah memilih)
                        $alamatUser = null;

                        if (isset($_SESSION['selected_alamat_id'])) {
                            $selected_id = $_SESSION['selected_alamat_id'];
                            $stmtAlamat = $conn->prepare("SELECT id, label_alamat, nama_user, nomor_hp, alamat_lengkap, kota, provinsi, kecamatan, kode_post FROM tb_alamat_user WHERE id = ? AND user_id = ?");
                            $stmtAlamat->bind_param("ii", $selected_id, $user_id);
                            $stmtAlamat->execute();
                            $resultAlamat = $stmtAlamat->get_result();
                            if ($resultAlamat->num_rows > 0) {
                                $alamatUser = $resultAlamat->fetch_assoc();
                            }
                            $stmtAlamat->close();
                        }
                        ?>

                        <!-- TAMPILKAN ALAMAT JIKA SUDAH DIPILIH -->
                        <?php if ($alamatUser): ?>
                            <h4 class="mt-5">Alamat Pengiriman</h4>
                            <div class="list-group">
                                <div class="list-group-item">
                                    <strong><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($alamatUser['label_alamat'] ?: 'Tanpa Label') ?></strong><br>
                                    id: <?= htmlspecialchars($alamatUser['id']) ?><br>
                                    Nama: <?= htmlspecialchars($alamatUser['nama_user']) ?><br>
                                    No. HP: <?= htmlspecialchars($alamatUser['nomor_hp']) ?><br>
                                    Alamat: <?= nl2br(htmlspecialchars($alamatUser['alamat_lengkap'])) ?>,
                                    <?= htmlspecialchars($alamatUser['kota']) ?>,
                                    <?= htmlspecialchars($alamatUser['provinsi']) ?>,
                                    Kode Pos: <?= htmlspecialchars($alamatUser['kode_post']) ?>
                                    <div class="mt-3 mb-3">
                                        <a href="../map address/editAlamat.php?id=<?= $alamatUser['id'] ?>" class="btn btn-primary btn-sm">Edit alamat</a>
                                    </div>
                                    <a href="../map address/alamatLainCart.php?cart_id=<?= $cart_id ?>" class="btn btn-success btn-sm">Ganti alamat</a>
                                    <br>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="container mt-5">
                                <p class="text-muted">Belum ada alamat yang dipilih.</p>
                                <a href="../map address/alamatLainCart.php" class="btn btn-success">Pilih alamat</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Metode Pembayaran -->

                    
                    <div class="mt-3">
                        <button type="button" class="btn btn-success">Konfirmasi Pesanan</button>
                    </div>
                </form>

                <script>
                    // Harga per item dari PHP
                    const hargaPerItem = <?= $harga_aktif ?>;
                    let selectedMethod = null;

                    // Format angka ke Rupiah
                    function formatRupiah(angka) {
                        return 'Rp' + angka.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                    }

                    // Update total harga
                    function updateTotalHarga(qty) {
                        const total = hargaPerItem * qty;
                        document.getElementById('totalHarga').textContent = 'Total Harga: ' + formatRupiah(total);
                    }

                    // Fungsi untuk update quantity
                    function updateQuantity(change) {
                        const qtyInput = document.getElementById('jumlah');
                        let qty = parseInt(qtyInput.value);
                        qty += change;

                        // Pastikan tidak kurang dari 1
                        if (qty < 1) qty = 1;

                        qtyInput.value = qty;
                        updateTotalHarga(qty);

                        // Kirim update ke server
                        const cartId = <?= $item['cart_id'] ?>;
                        const formData = new FormData();
                        formData.append('cart_id', cartId);
                        formData.append('jumlah', qty);

                        fetch(window.location.href, {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => response.text())
                            .then(data => {
                                if (data !== "success") {
                                    console.error("Gagal menyimpan jumlah");
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                            });
                    }
                </script>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>