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
                    <div class="col-md-3">
                        <h5><?= htmlspecialchars($product['nama_produk']) ?></h5>
                        <div><strong>Rp<?= number_format($product['is_diskon'] ? $product['harga_diskon'] : $product['harga'], 0, ',', '.') ?></strong></div>
                        <div class="jumlah-input mt-2">
                            <label for="jumlah">Jumlah:</label>
                            <button class="btn btn-outline-secondary btn-sm" onclick="ubahJumlah(-1)">-</button>
                            <input type="text" id="jumlah" name="jumlah" value="1" min="1" max="<?= $product['jumlah_stok'] ?>" readonly>
                            <button class="btn btn-outline-secondary btn-sm" onclick="ubahJumlah(1)">+</button>
                        </div>
                        <div class="mt-2 text-success fw-bold" id="totalHarga">
                            Total Harga: Rp<?= number_format($product['is_diskon'] ? $product['harga_diskon'] : $product['harga'], 0, ',', '.') ?>
                        </div>
                    </div>
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
                        }

                        .sub-accordion .payment-method {
                            margin-bottom: 10px;
                        }
                    </style>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
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
                    <!-- Modal metode pembayran -->
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
                                                        <img src="../img/e wallet/gopay.png" alt="Gopay">
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