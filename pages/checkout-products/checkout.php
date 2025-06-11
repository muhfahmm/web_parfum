<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$default_jumlah = isset($_GET['jumlah']) ? (int)$_GET['jumlah'] : 1;
$variant_id = isset($_GET['variant_id']) ? intval($_GET['variant_id']) : null;

if (isset($_GET['update_jumlah'])) {
    $_SESSION['current_jumlah'][$product_id] = (int)$_GET['update_jumlah'];
    exit;
}

$current_jumlah = isset($_SESSION['current_jumlah'][$product_id]) ? $_SESSION['current_jumlah'][$product_id] : $default_jumlah;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    $jumlah = $current_jumlah;
    $pay_method = isset($_POST['pay_method']) ? trim($_POST['pay_method']) : '';

    if ($jumlah < 1) {
        $_SESSION['error'] = 'Jumlah tidak valid.';
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }

    if (empty($pay_method)) {
        $_SESSION['error'] = 'Silakan pilih metode pembayaran.';
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }

    $stmt = $conn->prepare("SELECT * FROM tb_adminProduct WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();

    if (!$product) {
        $_SESSION['error'] = 'Produk tidak ditemukan.';
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }

    $harga = $product['is_diskon'] ? $product['harga_diskon'] : $product['harga'];
    $total_harga = $harga * $jumlah;

    $has_variant = false;
    if ($variant_id) {
        $stmt = $conn->prepare("SELECT * FROM tb_varian_product WHERE id = ? AND product_id = ?");
        $stmt->bind_param("ii", $variant_id, $product_id);
        $stmt->execute();
        $variant = $stmt->get_result()->fetch_assoc();

        if ($variant) {
            $has_variant = true;
            if ($variant['stok'] < $jumlah) {
                $_SESSION['error'] = 'Stok varian tidak mencukupi.';
                header("Location: " . $_SERVER['HTTP_REFERER']);
                exit;
            }
        } else {
            $_SESSION['error'] = 'Varian tidak valid.';
            header("Location: " . $_SERVER['HTTP_REFERER']);
            exit;
        }
    } else {
        if ($product['jumlah_stok'] < $jumlah) {
            $_SESSION['error'] = 'Stok produk tidak mencukupi.';
            header("Location: " . $_SERVER['HTTP_REFERER']);
            exit;
        }
    }

    $selected_alamat_id = isset($_SESSION['selected_alamat_id']) ? (int)$_SESSION['selected_alamat_id'] : 0;
    $stmt = $conn->prepare("SELECT * FROM tb_alamat_user WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $selected_alamat_id, $user_id);
    $stmt->execute();
    $alamat = $stmt->get_result()->fetch_assoc();

    if (!$alamat) {
        $_SESSION['error'] = 'Alamat pengiriman belum dipilih.';
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }

    $conn->begin_transaction();

    try {
        if ($has_variant) {
            $stmt = $conn->prepare("INSERT INTO tb_historyTransactions 
                          (user_id, product_id, varian_id, alamat_id, harga, jumlah, total_harga, pay_method, date, status) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'kirim')");
            $stmt->bind_param("iiidiiis", $user_id, $product_id, $variant_id, $selected_alamat_id, $harga, $jumlah, $total_harga, $pay_method);
        } else {
            $stmt = $conn->prepare("INSERT INTO tb_historyTransactions 
                          (user_id, product_id, alamat_id, harga, jumlah, total_harga, pay_method, date, status) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'kirim')");
            $stmt->bind_param("iidiiis", $user_id, $product_id, $selected_alamat_id, $harga, $jumlah, $total_harga, $pay_method);
        }
        $stmt->execute();

        if ($has_variant) {
            $stmt = $conn->prepare("UPDATE tb_varian_product SET stok = stok - ? WHERE id = ?");
            $stmt->bind_param("ii", $jumlah, $variant_id);
        } else {
            $stmt = $conn->prepare("UPDATE tb_adminProduct SET jumlah_stok = jumlah_stok - ? WHERE id = ?");
            $stmt->bind_param("ii", $jumlah, $product_id);
        }
        $stmt->execute();

        $conn->commit();
        unset($_SESSION['current_jumlah'][$product_id]);
        $_SESSION['checkout_success'] = true;
        header("Location: checkout.php?id=" . $product_id . "&variant_id=" . ($variant_id ?: ''));
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = 'Gagal memproses pesanan: ' . $e->getMessage();
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }
}

$stmt = $conn->prepare("SELECT * FROM tb_adminProduct WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

$varianProduk = [];
$stmt = $conn->prepare("SELECT * FROM tb_varian_product WHERE product_id = ?");
$stmt->bind_param("i", $product['id']);
$stmt->execute();
$resultVarian = $stmt->get_result();
while ($row = $resultVarian->fetch_assoc()) {
    $varianProduk[] = $row;
}

if ($variant_id) {
    $stmt = $conn->prepare("SELECT stok FROM tb_varian_product WHERE id = ?");
    $stmt->bind_param("i", $variant_id);
    $stmt->execute();
    $max_stock = $stmt->get_result()->fetch_assoc()['stok'];
} else {
    $max_stock = $product['jumlah_stok'];
}

$selected_alamat_id = isset($_SESSION['selected_alamat_id']) ? (int)$_SESSION['selected_alamat_id'] : 0;
$stmt = $conn->prepare("SELECT * FROM tb_alamat_user WHERE user_id = ? ORDER BY id = ? DESC, id ASC LIMIT 1");
$stmt->bind_param("ii", $user_id, $selected_alamat_id);
$stmt->execute();
$alamat = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Checkout Produk</title>
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
        .product-image {
            height: 120px;
            width: 120px;
            object-fit: cover;
            border-radius: 8px;
        }
        .card {
            border-radius: 10px;
        }
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
</head>
<body>
    <div class="container mt-5 mb-5">
        <div class="card shadow">
            <div class="card-body">
                <h3 class="mb-4">Checkout Produk</h3>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <div class="d-flex p-3 bg-white mb-4">
                    <div class="col-md-3">
                        <img src="../../admin/uploads/<?= htmlspecialchars($product['foto_thumbnail']) ?>"
                            class="product-image"
                            alt="<?= htmlspecialchars($product['nama_produk']) ?>">
                    </div>
                    <div class="col-md-6">
                        <h5><?= htmlspecialchars($product['nama_produk']) ?></h5>

                        <?php if ($variant_id): ?>
                            <?php
                            $stmt = $conn->prepare("SELECT * FROM tb_varian_product WHERE id = ?");
                            $stmt->bind_param("i", $variant_id);
                            $stmt->execute();
                            $variant = $stmt->get_result()->fetch_assoc();
                            ?>
                            <?php if ($variant): ?>
                                <p class="mb-1"><strong>Varian:</strong> <?= htmlspecialchars($variant['varian']) ?></p>
                                <p class="mb-2 text-muted"><small>Stok tersedia: <?= (int)$variant['stok'] ?></small></p>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="mb-2 text-muted"><small>Stok tersedia: <?= (int)$product['jumlah_stok'] ?></small></p>
                        <?php endif; ?>

                        <div class="mb-3">
                            <strong class="fs-4">Rp<?= number_format($product['is_diskon'] ? $product['harga_diskon'] : $product['harga'], 0, ',', '.') ?></strong>
                            <?php if ($product['is_diskon']): ?>
                                <span class="text-decoration-line-through text-muted ms-2">Rp<?= number_format($product['harga'], 0, ',', '.') ?></span>
                                <span class="badge bg-danger ms-2">Diskon</span>
                            <?php endif; ?>
                        </div>

                        <form method="POST" id="checkoutForm">
                            <input type="hidden" name="checkout" value="1">
                            <input type="hidden" name="variant_id" value="<?= htmlspecialchars($variant_id) ?>">
                            <input type="hidden" name="pay_method" id="payMethodInput">

                            <div class="jumlah-input mt-2 mb-3">
                                <label for="jumlah" class="me-2">Jumlah:</label>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="ubahJumlah(-1)">-</button>
                                <input type="number" id="jumlah" name="jumlah"
                                    value="<?= $current_jumlah ?>"
                                    min="1" max="<?= $max_stock ?>"
                                    class="form-control form-control-sm">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="ubahJumlah(1)">+</button>
                            </div>

                            <div class="mt-2 mb-4 fs-5 fw-bold text-success" id="totalHarga">
                                Total Harga: Rp<?= number_format(($product['is_diskon'] ? $product['harga_diskon'] : $product['harga']) * $current_jumlah, 0, ',', '.') ?>
                            </div>
                        </form>
                    </div>
                </div>

                <h5>Alamat Pengiriman</h5>
                <?php if ($alamat): ?>
                    <div class="border rounded bg-white p-3 mb-3">
                        <p class="mb-1"><strong><?= htmlspecialchars($alamat['label_alamat'] ?: 'Alamat Utama') ?></strong></p>
                        <p class="mb-1">Nama: <?= htmlspecialchars($alamat['nama_user']) ?></p>
                        <p class="mb-1">No. HP: <?= htmlspecialchars($alamat['nomor_hp']) ?></p>
                        <p>Alamat: <?= htmlspecialchars($alamat['alamat_lengkap']) ?>,
                            <?= htmlspecialchars($alamat['kota']) ?>,
                            <?= htmlspecialchars($alamat['provinsi']) ?>,
                            Kode Pos: <?= htmlspecialchars($alamat['kode_post']) ?>
                        </p>
                        <div class="mt-3 mb-3">
                            <a href="../map address/editAlamat.php?id=<?= $alamat['id'] ?>" class="btn btn-primary btn-sm">Edit alamat</a>
                            <a href="../map address/alamatLain.php?id=<?= $product_id ?>" class="btn btn-success btn-sm ms-2">Ganti alamat</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">Alamat belum tersedia. Silakan tambahkan alamat.</div>
                    <a href="../map address/alamatLain.php?id=<?= $product_id ?>" class="btn btn-success btn-sm">Pilih alamat</a>
                <?php endif; ?>

                <div class="container mt-5">
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
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h3 class="modal-title fs-5" id="paymentModalLabel">Pilih Metode Pembayaran</h3>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="accordion" id="paymentMethodsAccordion">
                                        <div class="payment-method">
                                            <div class="method-header collapsed" data-bs-toggle="collapse" data-bs-target="#collapseEwallet" aria-expanded="false" aria-controls="collapseEwallet">
                                                <h5 class="mb-0">E-Wallet</h5>
                                                <i class="fas fa-chevron-down"></i>
                                            </div>
                                            <div id="collapseEwallet" class="collapse" aria-labelledby="headingEwallet" data-bs-parent="#paymentMethodsAccordion">
                                                <div class="method-content pt-3">
                                                    <div class="method-option" data-method="Gopay">
                                                        <img src="../img/pay method/e wallet/gopay.png" alt="Gopay">
                                                        <span>Gopay</span>
                                                    </div>
                                                    <div class="method-option" data-method="OVO">
                                                        <img src="../img/pay method/e wallet/ovo.png" alt="OVO">
                                                        <span>OVO</span>
                                                    </div>
                                                    <div class="method-option" data-method="DANA">
                                                        <img src="../img/pay method/e wallet/dana.png" alt="DANA">
                                                        <span>DANA</span>
                                                    </div>
                                                    <div class="method-option" data-method="ShopeePay">
                                                        <img src="../img/pay method/e wallet/shopeepay.png" alt="ShopeePay">
                                                        <span>ShopeePay</span>
                                                    </div>
                                                    <div class="method-option" data-method="LinkAja">
                                                        <img src="../img/pay method/e wallet/linkaja.png" alt="LinkAja">
                                                        <span>LinkAja</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="payment-method">
                                            <div class="method-header collapsed" data-bs-toggle="collapse" data-bs-target="#collapseBankTransfer" aria-expanded="false" aria-controls="collapseBankTransfer">
                                                <h5 class="mb-0">Virtual Account</h5>
                                                <i class="fas fa-chevron-down"></i>
                                            </div>
                                            <div id="collapseBankTransfer" class="collapse" aria-labelledby="headingBankTransfer" data-bs-parent="#paymentMethodsAccordion">
                                                <div class="method-content pt-3">
                                                    <div class="method-option" data-method="Virtual Account BCA">
                                                        <img src="../img/pay method/virtual account/bca.png" alt="BCA">
                                                        <span>BCA</span>
                                                    </div>
                                                    <div class="method-option" data-method="Virtual Account Mandiri">
                                                        <img src="../img/pay method/virtual account/mandiri.webp" alt="Mandiri">
                                                        <span>Mandiri</span>
                                                    </div>
                                                    <div class="method-option" data-method="Virtual Account BNI">
                                                        <img src="../img/pay method/virtual account/bni.jpg" alt="BNI">
                                                        <span>BNI</span>
                                                    </div>
                                                    <div class="method-option" data-method="Virtual Account BRI">
                                                        <img src="../img/pay method/virtual account/bri.png" alt="BRI">
                                                        <span>BRI</span>
                                                    </div>
                                                    <div class="method-option" data-method="Virtual Account BSI">
                                                        <img src="../img/pay method/virtual account/bsi.jpg" alt="BSI">
                                                        <span>BSI</span>
                                                    </div>
                                                    <div class="method-option" data-method="Virtual Account CIMB">
                                                        <img src="../img/pay method/virtual account/CIMB Niaga.png" alt="CIMB">
                                                        <span>CIMB Niaga</span>
                                                    </div>
                                                    <div class="method-option" data-method="Virtual Account Muamalat">
                                                        <img src="../img/pay method/virtual account/bank muamalat.jpg" alt="Muamalat">
                                                        <span>Bank Muamalat</span>
                                                    </div>
                                                    <div class="method-option" data-method="Virtual Account Mega">
                                                        <img src="../img/pay method/virtual account/bank mega.png" alt="Mega">
                                                        <span>Bank Mega</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="payment-method">
                                            <div class="method-header collapsed" data-bs-toggle="collapse" data-bs-target="#collapseDebitCard" aria-expanded="false" aria-controls="collapseDebitCard">
                                                <h5 class="mb-0">Kartu Debit</h5>
                                                <i class="fas fa-chevron-down"></i>
                                            </div>
                                            <div id="collapseDebitCard" class="collapse" aria-labelledby="headingDebitCard" data-bs-parent="#paymentMethodsAccordion">
                                                <div class="method-content pt-3">
                                                    <div class="method-option" data-method="Visa">
                                                        <img src="../img/pay method/kartu debit/visa.webp" alt="Visa">
                                                        <span>Visa</span>
                                                    </div>
                                                    <div class="method-option" data-method="Mastercard">
                                                        <img src="../img/pay method/kartu debit/mastercard.png" alt="Mastercard">
                                                        <span>Mastercard</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="payment-method">
                                            <div class="method-header collapsed" data-bs-toggle="collapse" data-bs-target="#collapsePayLater" aria-expanded="false" aria-controls="collapsePayLater">
                                                <h5 class="mb-0">Pay Later</h5>
                                                <i class="fas fa-chevron-down"></i>
                                            </div>
                                            <div id="collapsePayLater" class="collapse" aria-labelledby="headingPayLater" data-bs-parent="#paymentMethodsAccordion">
                                                <div class="method-content pt-3">
                                                    <div class="method-option" data-method="ShopeePay Later">
                                                        <img src="../img/pay method/pay later/SP later.webp" alt="ShopeePay Later">
                                                        <span>ShopeePay Later</span>
                                                    </div>
                                                    <div class="method-option" data-method="Gopay Later">
                                                        <img src="../img/pay method/pay later/gopay later.png" alt="Gopay Later">
                                                        <span>Gopay Later</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="payment-method">
                                            <div class="method-header collapsed" data-bs-toggle="collapse" data-bs-target="#collapseOffline" aria-expanded="false" aria-controls="collapseOffline">
                                                <h5 class="mb-0">Offline</h5>
                                                <i class="fas fa-chevron-down"></i>
                                            </div>
                                            <div id="collapseOffline" class="collapse" aria-labelledby="headingOffline" data-bs-parent="#paymentMethodsAccordion">
                                                <div class="method-content pt-3">
                                                    <div class="method-option" data-method="QRIS">
                                                        <img src="../img/pay method/offline pay/qris.png" alt="QRIS">
                                                        <span>QRIS</span>
                                                    </div>
                                                    <div class="method-option" data-method="Cash on Delivery">
                                                        <img src="../img/pay method/offline pay/cash on delivery.png" alt="Cash on Delivery">
                                                        <span>Cash on Delivery</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="payment-method">
                                            <div class="method-header collapsed" data-bs-toggle="collapse" data-bs-target="#collapseRetail" aria-expanded="false" aria-controls="collapseRetail">
                                                <h5 class="mb-0">Gerai Offline</h5>
                                                <i class="fas fa-chevron-down"></i>
                                            </div>
                                            <div id="collapseRetail" class="collapse" aria-labelledby="headingRetail" data-bs-parent="#paymentMethodsAccordion">
                                                <div class="method-content pt-3">
                                                    <div class="method-option" data-method="Alfamart">
                                                        <img src="../img/gerai offline/alfamart.webp" alt="Alfamart">
                                                        <span>Alfamart</span>
                                                    </div>
                                                    <div class="method-option" data-method="Indomaret">
                                                        <img src="../img/gerai offline/indomaret.webp" alt="Indomaret">
                                                        <span>Indomaret</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-3 mt-4">
                        <a href="<?= isset($_SERVER['HTTP_REFERER']) ? htmlspecialchars($_SERVER['HTTP_REFERER']) : '../products.php' ?>"
                            class="btn btn-outline-secondary">Kembali</a>
                        <button type="submit" form="checkoutForm" class="btn btn-success" <?= !$alamat ? 'disabled' : '' ?>>
                            <i class="fas fa-shopping-cart me-2"></i>Konfirmasi Pesanan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="checkoutSuccessModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Checkout Berhasil</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <i class="fas fa-check-circle text-success" style="font-size: 5rem;"></i>
                    <h4 class="mt-3">Pesanan Anda Berhasil Diproses!</h4>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal">Tutup</button>
                    <a href="../transactions/BuyingHistory.php" class="btn btn-outline-success">Lihat Riwayat</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function ubahJumlah(delta) {
            const input = document.getElementById("jumlah");
            const max = parseInt(input.max);
            let value = parseInt(input.value) + delta;

            if (value < 1) value = 1;
            if (value > max) value = max;

            input.value = value;
            hitungTotalHarga();

            fetch(`?update_jumlah=${value}&id=<?= $product_id ?>`, {
                method: 'GET'
            });
        }

        function hitungTotalHarga() {
            const jumlah = parseInt(document.getElementById("jumlah").value);
            const harga = <?= $product['is_diskon'] ? $product['harga_diskon'] : $product['harga'] ?>;
            const total = jumlah * harga;
            document.getElementById("totalHarga").innerText = "Total Harga: Rp" + total.toLocaleString("id-ID");
        }

        document.addEventListener('DOMContentLoaded', function() {
            const methodOptions = document.querySelectorAll('.method-option');
            const payMethodInput = document.getElementById('payMethodInput');
            const selectedPaymentText = document.getElementById('selectedPaymentText');
            
            methodOptions.forEach(option => {
                option.addEventListener('click', function() {
                    methodOptions.forEach(opt => opt.classList.remove('active'));
                    this.classList.add('active');
                    const method = this.getAttribute('data-method');
                    payMethodInput.value = method;
                    selectedPaymentText.textContent = method;
                    bootstrap.Modal.getInstance(document.getElementById('paymentModal')).hide();
                });
            });
            
            if (payMethodInput.value) {
                selectedPaymentText.textContent = payMethodInput.value;
            } else {
                selectedPaymentText.textContent = 'Metode pembayaran belum dipilih';
            }

            document.getElementById('checkoutForm').addEventListener('submit', function(e) {
                if (!payMethodInput.value) {
                    e.preventDefault();
                    alert('Silakan pilih metode pembayaran terlebih dahulu');
                    new bootstrap.Modal(document.getElementById('paymentModal')).show();
                }
            });

            <?php if (isset($_SESSION['checkout_success'])): ?>
                new bootstrap.Modal(document.getElementById('checkoutSuccessModal')).show();
                fetch('?clear_checkout=1', {
                    method: 'GET'
                });
            <?php unset($_SESSION['checkout_success']);
            endif; ?>
        });
    </script>
</body>
</html>
<?php
if (isset($_GET['clear_checkout'])) {
    unset($_SESSION['checkout_success']);
    exit;
}
?>