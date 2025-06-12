<?php
session_start();
require_once '../db.php';

// Handle payment method selection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_payment_method'])) {
    $_SESSION['selected_payment_method'] = $_POST['payment_method'];
    echo json_encode(['status' => 'success']);
    exit;
}

// Handle order confirmation and stock reduction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_order'])) {
    $cart_id = intval($_POST['cart_id']);
    $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
    
    // Check if address and payment method are selected
    if (!isset($_SESSION['selected_alamat_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Silakan pilih alamat pengiriman terlebih dahulu.']);
        exit;
    }
    
    if (!isset($_SESSION['selected_payment_method'])) {
        echo json_encode(['status' => 'error', 'message' => 'Silakan pilih metode pembayaran terlebih dahulu.']);
        exit;
    }

    // Get cart item details
    $query = $conn->prepare("
        SELECT 
            c.product_id,
            c.varian_id,
            c.jumlah,
            c.foto_thumbnail,
            p.nama_produk,
            p.jumlah_stok AS produk_stok,
            v.stok AS varian_stok,
            p.harga,
            p.harga_diskon,
            p.is_diskon
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
        echo json_encode(['status' => 'error', 'message' => 'Item tidak ditemukan.']);
        exit;
    }

    // Calculate the active price
    $harga_aktif = $item['is_diskon'] ? $item['harga_diskon'] : $item['harga'];
    $total_harga = $harga_aktif * $item['jumlah'];

    // Begin transaction
    $conn->begin_transaction();

    try {
        // Update stock based on whether it's a variant or main product
        if ($item['varian_id']) {
            $new_stock = $item['varian_stok'] - $item['jumlah'];
            $stmt = $conn->prepare("UPDATE tb_varian_product SET stok = ? WHERE id = ?");
            $stmt->bind_param("ii", $new_stock, $item['varian_id']);
            $stmt->execute();

            // Also update main product stock if needed (optional)
            $new_main_stock = $item['produk_stok'] - $item['jumlah'];
            $stmt = $conn->prepare("UPDATE tb_adminProduct SET jumlah_stok = ? WHERE id = ?");
            $stmt->bind_param("ii", $new_main_stock, $item['product_id']);
            $stmt->execute();

            $remaining_stock = $new_stock;
        } else {
            $new_stock = $item['produk_stok'] - $item['jumlah'];
            $stmt = $conn->prepare("UPDATE tb_adminProduct SET jumlah_stok = ? WHERE id = ?");
            $stmt->bind_param("ii", $new_stock, $item['product_id']);
            $stmt->execute();

            $remaining_stock = $new_stock;
        }

        // Insert into transaction history
        $insert_stmt = $conn->prepare("
            INSERT INTO tb_historytransactions (
                user_id, 
                alamat_id, 
                product_id, 
                varian_id, 
                foto_thumbnail, 
                harga, 
                jumlah, 
                total_harga, 
                pay_method, 
                status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'kirim')
        ");
        
        $insert_stmt->bind_param(
            "iiiisddds",
            $user_id,
            $_SESSION['selected_alamat_id'],
            $item['product_id'],
            $item['varian_id'],
            $item['foto_thumbnail'],
            $harga_aktif,
            $item['jumlah'],
            $total_harga,
            $_SESSION['selected_payment_method']
        );
        $insert_stmt->execute();
        $transaction_id = $conn->insert_id;

        // Remove item from cart
        $stmt = $conn->prepare("DELETE FROM tb_cart WHERE id = ?");
        $stmt->bind_param("i", $cart_id);
        $stmt->execute();

        $conn->commit();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Pesanan berhasil diproses!',
            'remaining_stock' => $remaining_stock,
            'transaction_id' => $transaction_id
        ]);
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
        exit;
    }
}

// Update jumlah via fetch()
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cart_id'], $_POST['jumlah'])) {
    $cart_id = intval($_POST['cart_id']);
    $jumlah = intval($_POST['jumlah']);

    if ($cart_id > 0 && $jumlah > 0) {
        $stmt = $conn->prepare("UPDATE tb_cart SET jumlah = ? WHERE id = ?");
        $stmt->bind_param("ii", $jumlah, $cart_id);
        $stmt->execute();
        echo "success";
        exit;
    } else {
        echo "invalid";
        exit;
    }
}

// Validasi GET id keranjang
if (!isset($_GET['id'])) {
    echo "Produk tidak ditemukan.";
    exit;
}

$cart_id = intval($_GET['id']);

$query = $conn->prepare("
    SELECT 
        c.id AS cart_id,
        c.jumlah,
        c.foto_thumbnail,
        c.product_id,
        c.varian_id,
        p.nama_produk,
        p.foto_thumbnail AS produk_foto,
        p.harga,
        p.harga_diskon,
        p.is_diskon,
        p.jumlah_stok AS produk_stok,
        v.varian,
        v.stok AS varian_stok
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
    header("Location: ../home.php");
    exit;
}

$stok_tersedia = $item['varian_id'] ? $item['varian_stok'] : $item['produk_stok'];
$harga_aktif = $item['is_diskon'] ? $item['harga_diskon'] : $item['harga'];
$total_harga = $harga_aktif * $item['jumlah'];

// Check if user has address and payment method selected
$has_address = isset($_SESSION['selected_alamat_id']);
$has_payment = isset($_SESSION['selected_payment_method']);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Checkout Produk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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

        .stock-info {
            color: #6c757d;
            font-size: 0.9rem;
            margin-top: 5px;
        }

        .stock-warning {
            color: #dc3545;
            font-weight: bold;
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
            border: 1px solid #0d6efd;
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
        
        .address-missing, .payment-missing {
            color: #dc3545;
            font-weight: bold;
            display: none;
        }
        
        #checkoutBtn:disabled {
            opacity: 0.65;
            cursor: not-allowed;
        }
        
        .success-icon {
            font-size: 4rem;
            color: #28a745;
            margin-bottom: 1rem;
        }
    </style>
</head>

<body class="bg-light">
    <div class="container py-5">
        <div class="card shadow">
            <div class="card-body">
                <h3>Checkout Produk</h3>
                <form action="" method="POST" id="checkoutForm">
                    <input type="hidden" name="cart_id" value="<?= $item['cart_id'] ?>">

                    <div class="d-flex p-3 bg-white mb-4">
                        <div class="col-md-3">
                            <img class="product-img img-fluid" src="../../admin/uploads/<?= htmlspecialchars($item['produk_foto']) ?>" alt="<?= htmlspecialchars($item['nama_produk']) ?>">
                        </div>
                        <div class="col-md-9">
                            <div>
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

                                <div class="stock-info">
                                    Stok tersedia: <span id="stokTersedia"><?= $stok_tersedia ?></span>
                                    <span class="stock-warning" id="warningText" style="<?= $item['jumlah'] > $stok_tersedia ? '' : 'display:none;' ?>"> (Jumlah melebihi stok tersedia!)</span>
                                </div>

                                <div id="totalHarga">Total Harga: Rp<?= number_format($total_harga, 0, ',', '.') ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Alamat Pengiriman -->
                    <div class="user-address mb-4">
                        <h4 class="mb-3">Alamat Pengiriman</h4>
                        <div class="address-missing alert alert-warning">Silakan pilih alamat pengiriman terlebih dahulu.</div>
                        
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

                        <?php if ($alamatUser): ?>
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
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="container">
                                <a href="../map address/alamatLainCart.php" class="btn btn-success">Pilih alamat</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Metode Pembayaran -->
                    <div class="payment-method mb-4">
                        <h4 class="mb-3">Metode Pembayaran</h4>
                        <div class="payment-missing alert alert-warning">Silakan pilih metode pembayaran terlebih dahulu.</div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">Metode Pembayaran</label>
                                            <button type="button" class="btn btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#paymentModal">
                                                <span id="selectedPaymentText">
                                                    <?= isset($_SESSION['selected_payment_method']) ? htmlspecialchars($_SESSION['selected_payment_method']) : 'Metode pembayaran belum dipilih' ?>
                                                </span>
                                                <i class="fas fa-chevron-right ms-2"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <button type="button" class="btn btn-success" id="checkoutBtn" data-bs-toggle="modal" data-bs-target="#confirmModal">Konfirmasi Pesanan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Payment Method Modal -->
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

                        <!-- Virtual Account -->
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

                        <!-- Kartu Debit -->
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

                        <!-- Pay Later -->
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

                        <!-- Offline -->
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

                        <!-- Gerai Offline -->
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
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="button" class="btn btn-primary" id="confirmPaymentMethod">Konfirmasi</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmModalLabel">Konfirmasi Pesanan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Anda akan memesan <strong><?= htmlspecialchars($item['nama_produk']) ?></strong> dengan jumlah <strong id="confirmQty"><?= $item['jumlah'] ?></strong>.</p>
                    <p>Total yang harus dibayar: <strong id="confirmTotal">Rp<?= number_format($total_harga, 0, ',', '.') ?></strong></p>
                    <div class="alert alert-warning">
                        Pastikan alamat pengiriman dan detail pesanan sudah benar. Pesanan tidak dapat dibatalkan setelah dikonfirmasi.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" id="confirmOrderBtn">Konfirmasi</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="successModalLabel">Pesanan Berhasil</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="success-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h4 class="mb-3">Pesanan Berhasil Diproses!</h4>
                    <p id="successMessage">Transaksi Anda telah berhasil dibuat dengan ID: <span id="transactionId"></span></p>
                    <div class="d-flex justify-content-center mt-4">
                        <a href="../transactions/BuyingHistory.php" class="btn btn-primary me-2">Lihat Riwayat Transaksi</a>
                        <a href="../home.php" class="btn btn-success">Kembali ke Beranda</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const hargaPerItem = <?= $harga_aktif ?>;
        let maxStock = <?= $stok_tersedia ?>;
        const cartId = <?= $item['cart_id'] ?>;
        const productName = "<?= htmlspecialchars($item['nama_produk']) ?>";
        const hasAddress = <?= $has_address ? 'true' : 'false' ?>;
        const hasPayment = <?= $has_payment ? 'true' : 'false' ?>;

        function formatRupiah(angka) {
            return 'Rp' + angka.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }

        function updateTotalHarga(qty) {
            const total = hargaPerItem * qty;
            document.getElementById('totalHarga').textContent = 'Total Harga: ' + formatRupiah(total);
            document.getElementById('confirmTotal').textContent = formatRupiah(total);
            document.getElementById('confirmQty').textContent = qty;

            const warning = document.getElementById('warningText');
            warning.style.display = qty > maxStock ? 'inline' : 'none';
        }

        function updateQuantity(change) {
            let qtyInput = document.getElementById('jumlah');
            let qty = parseInt(qtyInput.value) + change;

            if (qty < 1) qty = 1;
            qtyInput.value = qty;

            updateTotalHarga(qty);
            updateToDatabase(qty);
        }

        function updateToDatabase(qty) {
            fetch("", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded"
                    },
                    body: `cart_id=${cartId}&jumlah=${qty}`
                })
                .then(res => res.text())
                .then(res => {
                    if (res !== "success") alert("Gagal memperbarui jumlah.");
                });
        }

        // Payment Method Selection
        document.addEventListener('DOMContentLoaded', function() {
            const methodOptions = document.querySelectorAll('.method-option');
            const selectedPaymentText = document.getElementById('selectedPaymentText');
            const confirmPaymentBtn = document.getElementById('confirmPaymentMethod');
            const paymentModal = document.getElementById('paymentModal');
            const checkoutBtn = document.getElementById('checkoutBtn');
            const addressMissing = document.querySelector('.address-missing');
            const paymentMissing = document.querySelector('.payment-missing');
            
            let selectedMethod = '<?= $_SESSION['selected_payment_method'] ?? '' ?>';
            
            // Set active method if already selected
            if (selectedMethod) {
                methodOptions.forEach(option => {
                    if (option.getAttribute('data-method') === selectedMethod) {
                        option.classList.add('active');
                    }
                });
            }
            
            methodOptions.forEach(option => {
                option.addEventListener('click', function() {
                    methodOptions.forEach(opt => opt.classList.remove('active'));
                    this.classList.add('active');
                    selectedMethod = this.getAttribute('data-method');
                });
            });
            
            confirmPaymentBtn.addEventListener('click', function() {
                if (selectedMethod) {
                    // Save payment method via AJAX
                    fetch("", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded",
                        },
                        body: `set_payment_method=1&payment_method=${encodeURIComponent(selectedMethod)}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            selectedPaymentText.textContent = selectedMethod;
                            paymentMissing.style.display = 'none';
                            
                            // Enable checkout button if all requirements are met
                            if (hasAddress) {
                                checkoutBtn.disabled = false;
                            }
                            
                            // Close the modal
                            const modal = bootstrap.Modal.getInstance(paymentModal);
                            modal.hide();
                        }
                    });
                } else {
                    alert('Silakan pilih metode pembayaran terlebih dahulu');
                }
            });
            
            // Show missing warnings if needed
            if (!hasAddress) {
                addressMissing.style.display = 'block';
                checkoutBtn.disabled = true;
            }
            
            if (!hasPayment) {
                paymentMissing.style.display = 'block';
                checkoutBtn.disabled = true;
            } else {
                checkoutBtn.disabled = !hasAddress;
            }
            
            // Order Confirmation
            document.getElementById('confirmOrderBtn').addEventListener('click', function() {
                const confirmModal = bootstrap.Modal.getInstance(document.getElementById('confirmModal'));
                
                fetch("", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded"
                        },
                        body: `cart_id=${cartId}&confirm_order=1`
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success') {
                            // Update stok tersedia secara real-time
                            maxStock = data.remaining_stock;
                            document.getElementById('stokTersedia').textContent = data.remaining_stock;

                            // Periksa apakah jumlah pesanan melebihi stok baru
                            const currentQty = parseInt(document.getElementById('jumlah').value);
                            const warning = document.getElementById('warningText');
                            warning.style.display = currentQty > data.remaining_stock ? 'inline' : 'none';

                            confirmModal.hide();
                            const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                            document.getElementById('successMessage').textContent = data.message;
                            successModal.show();

                            // Nonaktifkan tombol konfirmasi setelah checkout
                            document.getElementById('checkoutBtn').disabled = true;
                        } else {
                            alert(data.message || 'Terjadi kesalahan saat memproses pesanan.');
                            
                            // Show missing warnings if needed
                            if (data.message.includes('alamat pengiriman')) {
                                addressMissing.style.display = 'block';
                            }
                            if (data.message.includes('metode pembayaran')) {
                                paymentMissing.style.display = 'block';
                            }
                        }
                    })
                    .catch(error => {
                        alert('Terjadi kesalahan: ' + error.message);
                    });
            });
        });
    </script>
</body>
</html>