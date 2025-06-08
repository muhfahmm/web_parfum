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
            font-size: 1.25rem;
            font-weight: bold;
            margin-top: 10px;
            color: #28a745;
        }
    </style>
    <script>
        // Harga per item sesuai diskon atau tidak, di-escape agar aman di JS
        const hargaPerItem = <?= $item['is_diskon'] ? intval($item['harga_diskon']) : intval($item['harga']) ?>;

        function updateQuantity(change) {
            const qtyInput = document.getElementById('jumlah');
            let qty = parseInt(qtyInput.value);
            qty += change;
            if (qty < 1) qty = 1;
            qtyInput.value = qty;

            // Update ke total harga
            updateTotalHarga(qty);

            const cartId = <?= $item['cart_id'] ?>;

            // Kirim ke server
            const formData = new URLSearchParams();
            formData.append("cart_id", cartId);
            formData.append("jumlah", qty);

            fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: formData.toString()
                })
                .then(response => response.text())
                .then(data => {
                    if (data !== "success") {
                        alert("Gagal menyimpan jumlah.");
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        }

        // Update teks total harga
        function updateTotalHarga(qty) {
            const totalHargaEl = document.getElementById('totalHarga');
            const total = hargaPerItem * qty;

            // Format angka ke format rupiah
            const formatted = new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR'
            }).format(total);
            totalHargaEl.textContent = `Total Harga: ${formatted}`;
        }

        // Inisialisasi saat halaman load
        window.onload = function() {
            const initialQty = parseInt(document.getElementById('jumlah').value);
            updateTotalHarga(initialQty);
        };
    </script>
</head>

<body class="bg-light">
    <div class="container py-5">
        <div class="card shadow">
            <div class="card-body">
                <h3 class="mb-4">Checkout Produk</h3>
                <form action="../checkout-process.php" method="POST">
                    <div class="row mb-4 align-items-center">
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
                                <label class="me-2 mb-0">Jumlah:</label>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="updateQuantity(-1)">-</button>
                                <input type="text" class="form-control mx-2" name="jumlah" id="jumlah" value="<?= intval($item['jumlah']) ?>" readonly>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="updateQuantity(1)">+</button>
                            </div>

                            <div id="totalHarga"></div>
                        </div>
                    </div>

                    <!-- Alamat Pengiriman -->
                    <?php
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
                        <h4 class="mt-5">Alamat Pengiriman Terakhir yang Disimpan</h4>
                        <div class="list-group">
                            <div class="list-group-item">
                                <strong><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($alamatUser['label_alamat'] ?: 'Alamat tanpa label') ?></strong><br>
                                Nama penerima: <?= htmlspecialchars($alamatUser['nama_user']) ?> <br>
                                No. HP: <?= htmlspecialchars($alamatUser['nomor_hp']) ?> <br>
                                Alamat: <?= nl2br(htmlspecialchars($alamatUser['alamat_lengkap'])) ?>, <?= htmlspecialchars($alamatUser['kecamatan']) ?>, <?= htmlspecialchars($alamatUser['kota']) ?>, <?= htmlspecialchars($alamatUser['provinsi']) ?>, Kode Pos: <?= htmlspecialchars($alamatUser['kode_post']) ?>
                                <div class="mt-3">
                                    <a href="../map address/gantiMap.php" class="btn btn-primary">ganti alamat</a>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="container">
                            <p class="text-muted mt-4">Belum ada alamat yang disimpan.</p>
                            <a href="../map address/maps.php" class="btn btn-success">tambah alamat</a>
                        </div>
                    <?php endif; ?>

                    <!-- Metode Pembayaran -->
                    <div class="container mt-5">
                        <style>
                            .payment-method {
                                border: 1px solid #dee2e6;
                                border-radius: 8px;
                                padding: 15px;
                                margin-bottom: 15px;
                                transition: all 0.3s;
                            }

                            .payment-method:hover {
                                border-color: #0d6efd;
                                background-color: #f8f9fa;
                            }

                            .payment-method .method-header {
                                display: flex;
                                justify-content: space-between;
                                align-items: center;
                                cursor: pointer;
                            }

                            .payment-method .method-header.collapsed .fa-chevron-down {
                                transform: rotate(0deg);
                            }

                            .payment-method .method-header .fa-chevron-down {
                                transform: rotate(180deg);
                                transition: transform 0.3s;
                            }

                            .method-option {
                                display: flex;
                                align-items: center;
                                padding: 10px;
                                border-radius: 5px;
                                margin-bottom: 5px;
                                cursor: pointer;
                            }

                            .method-option:hover {
                                background-color: #f1f1f1;
                            }

                            .method-option.active {
                                background-color: #e7f1ff;
                            }

                            .method-option img {
                                width: 40px;
                                margin-right: 15px;
                            }

                            .selected-method {
                                font-weight: bold;
                                color: #0d6efd;
                            }

                            .sub-accordion .payment-method {
                                margin-bottom: 10px;
                            }
                        </style>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title">Ringkasan Pembayaran</h5>
                                        <div class="mb-3">
                                            <label class="form-label">Metode Pembayaran</label>
                                            <button type="button" class="btn btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#paymentModal">
                                                <span id="selectedPaymentText">Metode pembayaran belum dipilih</span>
                                                <i class="fas fa-chevron-right ms-2"></i>
                                            </button>
                                        </div>
                                        <button class="btn btn-primary w-100">Bayar Sekarang</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Modal -->
                    <div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h3 class="modal-title fs-5" id="paymentModalLabel">Pilih Metode Pembayaran</h3>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="accordion" id="paymentMethodsAccordion">
                                        <!-- E-Wallet -->
                                        <div class="payment-method">
                                            <div class="method-header collapsed" data-bs-toggle="collapse" data-bs-target="#collapseEwallet" aria-expanded="false" aria-controls="collapseEwallet">
                                                <h5 class="mb-0">E-Wallet</h5>
                                                <i class="fas fa-chevron-down"></i>
                                            </div>
                                            <div id="collapseEwallet" class="collapse" aria-labelledby="headingEwallet" data-bs-parent="#paymentMethodsAccordion">
                                                <div class="method-content pt-3">
                                                    <div class="method-option" data-method="Gopay">
                                                        <img src="./img/e wallet/gopay.png" alt="Gopay">
                                                        <span>Gopay</span>
                                                    </div>
                                                    <div class="method-option" data-method="OVO">
                                                        <img src="https://via.placeholder.com/40?text=OVO" alt="OVO">
                                                        <span>OVO</span>
                                                    </div>
                                                    <div class="method-option" data-method="DANA">
                                                        <img src="https://via.placeholder.com/40?text=DANA" alt="DANA">
                                                        <span>DANA</span>
                                                    </div>
                                                    <div class="method-option" data-method="ShopeePay">
                                                        <img src="https://via.placeholder.com/40?text=ShopeePay" alt="ShopeePay">
                                                        <span>ShopeePay</span>
                                                    </div>
                                                    <div class="method-option" data-method="LinkAja">
                                                        <img src="https://via.placeholder.com/40?text=LinkAja" alt="LinkAja">
                                                        <span>LinkAja</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Virtual Account -->
                                        <div class="payment-method">
                                            <div class="method-header collapsed" data-bs-toggle="collapse" data-bs-target="#collapseBankTransfer" aria-expanded="false" aria-controls="collapseBankTransfer">
                                                <h5 class="mb-0">Virtual Account</h5>
                                                <i class="fas fa-chevron-down"></i>
                                            </div>
                                            <div id="collapseBankTransfer" class="collapse" aria-labelledby="headingBankTransfer" data-bs-parent="#paymentMethodsAccordion">
                                                <div class="method-content pt-3">
                                                    <div class="method-option" data-method="Virtual Account BCA">
                                                        <img src="https://via.placeholder.com/40?text=BCA" alt="BCA">
                                                        <span>BCA</span>
                                                    </div>
                                                    <div class="method-option" data-method="Virtual Account Mandiri">
                                                        <img src="https://via.placeholder.com/40?text=Mandiri" alt="Mandiri">
                                                        <span>Mandiri</span>
                                                    </div>
                                                    <div class="method-option" data-method="Virtual Account BNI">
                                                        <img src="https://via.placeholder.com/40?text=BNI" alt="BNI">
                                                        <span>BNI</span>
                                                    </div>
                                                    <div class="method-option" data-method="Virtual Account BRI">
                                                        <img src="https://via.placeholder.com/40?text=BRI" alt="BRI">
                                                        <span>BRI</span>
                                                    </div>
                                                    <div class="method-option" data-method="Virtual Account BSI">
                                                        <img src="https://via.placeholder.com/40?text=BSI" alt="BSI">
                                                        <span>BSI</span>
                                                    </div>
                                                    <div class="method-option" data-method="Virtual Account CIMB">
                                                        <img src="https://via.placeholder.com/40?text=CIMB" alt="CIMB">
                                                        <span>CIMB Niaga</span>
                                                    </div>
                                                    <div class="method-option" data-method="Virtual Account Muamalat">
                                                        <img src="https://via.placeholder.com/40?text=Muamalat" alt="Muamalat">
                                                        <span>Bank Muamalat</span>
                                                    </div>
                                                    <div class="method-option" data-method="Virtual Account Mega">
                                                        <img src="https://via.placeholder.com/40?text=Mega" alt="Mega">
                                                        <span>Bank Mega</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Kartu Debit -->
                                        <div class="payment-method">
                                            <div class="method-header collapsed" data-bs-toggle="collapse" data-bs-target="#collapseDebitCard" aria-expanded="false" aria-controls="collapseDebitCard">
                                                <h5 class="mb-0">Kartu Debit</h5>
                                                <i class="fas fa-chevron-down"></i>
                                            </div>
                                            <div id="collapseDebitCard" class="collapse" aria-labelledby="headingDebitCard" data-bs-parent="#paymentMethodsAccordion">
                                                <div class="method-content pt-3">
                                                    <div class="method-option" data-method="Visa">
                                                        <img src="https://via.placeholder.com/40?text=Visa" alt="Visa">
                                                        <span>Visa</span>
                                                    </div>
                                                    <div class="method-option" data-method="Mastercard">
                                                        <img src="https://via.placeholder.com/40?text=Mastercard" alt="Mastercard">
                                                        <span>Mastercard</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Pay Later -->
                                        <div class="payment-method">
                                            <div class="method-header collapsed" data-bs-toggle="collapse" data-bs-target="#collapsePayLater" aria-expanded="false" aria-controls="collapsePayLater">
                                                <h5 class="mb-0">Pay Later</h5>
                                                <i class="fas fa-chevron-down"></i>
                                            </div>
                                            <div id="collapsePayLater" class="collapse" aria-labelledby="headingPayLater" data-bs-parent="#paymentMethodsAccordion">
                                                <div class="method-content pt-3">
                                                    <div class="method-option" data-method="ShopeePay Later">
                                                        <img src="https://via.placeholder.com/40?text=SPLater" alt="ShopeePay Later">
                                                        <span>ShopeePay Later</span>
                                                    </div>
                                                    <div class="method-option" data-method="Gopay Later">
                                                        <img src="https://via.placeholder.com/40?text=GPLater" alt="Gopay Later">
                                                        <span>Gopay Later</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Offline -->
                                        <div class="payment-method">
                                            <div class="method-header collapsed" data-bs-toggle="collapse" data-bs-target="#collapseOffline" aria-expanded="false" aria-controls="collapseOffline">
                                                <h5 class="mb-0">Offline</h5>
                                                <i class="fas fa-chevron-down"></i>
                                            </div>
                                            <div id="collapseOffline" class="collapse" aria-labelledby="headingOffline" data-bs-parent="#paymentMethodsAccordion">
                                                <div class="method-content pt-3">
                                                    <div class="method-option" data-method="QRIS">
                                                        <img src="https://via.placeholder.com/40?text=QRIS" alt="QRIS">
                                                        <span>QRIS</span>
                                                    </div>
                                                    <div class="method-option" data-method="Cash on Delivery">
                                                        <img src="https://via.placeholder.com/40?text=COD" alt="Cash on Delivery">
                                                        <span>Cash on Delivery</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Gerai Offline -->
                                        <div class="payment-method">
                                            <div class="method-header collapsed" data-bs-toggle="collapse" data-bs-target="#collapseRetail" aria-expanded="false" aria-controls="collapseRetail">
                                                <h5 class="mb-0">Gerai Offline</h5>
                                                <i class="fas fa-chevron-down"></i>
                                            </div>
                                            <div id="collapseRetail" class="collapse" aria-labelledby="headingRetail" data-bs-parent="#paymentMethodsAccordion">
                                                <div class="method-content pt-3">
                                                    <div class="method-option" data-method="Alfamart">
                                                        <img src="https://via.placeholder.com/40?text=Alfamart" alt="Alfamart">
                                                        <span>Alfamart</span>
                                                    </div>
                                                    <div class="method-option" data-method="Indomaret">
                                                        <img src="https://via.placeholder.com/40?text=Indomaret" alt="Indomaret">
                                                        <span>Indomaret</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                                    <button type="button" class="btn btn-primary" id="confirmPaymentMethod">Konfirmasi</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bootstrap JS Bundle with Popper -->

                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            let selectedMethod = null;

                            // Pilih metode pembayaran
                            const methodOptions = document.querySelectorAll('.method-option');
                            methodOptions.forEach(option => {
                                option.addEventListener('click', function() {
                                    // Hapus active class dari semua opsi
                                    methodOptions.forEach(opt => opt.classList.remove('active'));

                                    // Tambahkan active class ke opsi yang dipilih
                                    this.classList.add('active');
                                    selectedMethod = this.getAttribute('data-method');
                                });
                            });

                            // Konfirmasi pilihan
                            document.getElementById('confirmPaymentMethod').addEventListener('click', function() {
                                if (selectedMethod) {
                                    document.getElementById('selectedPaymentText').textContent = selectedMethod;
                                    document.getElementById('selectedPaymentText').classList.add('selected-method');

                                    // Tutup modal
                                    const modal = bootstrap.Modal.getInstance(document.getElementById('paymentModal'));
                                    modal.hide();
                                } else {
                                    alert('Silakan pilih metode pembayaran terlebih dahulu');
                                }
                            });

                            // Reset pilihan saat modal dibuka
                            document.getElementById('paymentModal').addEventListener('show.bs.modal', function() {
                                methodOptions.forEach(opt => opt.classList.remove('active'));
                                selectedMethod = null;
                            });
                        });
                    </script>

                    <!-- Catatan -->
                    <div class="mb-3">
                        <label for="catatan" class="form-label">Catatan Pembelian (Opsional)</label>
                        <textarea name="catatan" id="catatan" class="form-control" rows="2"></textarea>
                    </div>

                    <!-- Voucher -->
                    <div class="mb-3">
                        <label for="voucher" class="form-label">Kode Voucher</label>
                        <input type="text" name="voucher" id="voucher" class="form-control" placeholder="Masukkan kode voucher jika ada">
                    </div>

                    <!-- Hidden input -->
                    <input type="hidden" name="cart_id" value="<?= $item['cart_id'] ?>">

                    <div class="text-end">
                        <button type="submit" class="btn btn-success">Lanjut Bayar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>