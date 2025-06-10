<?php
session_start();
require '../db.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('Silakan login terlebih dahulu.'); location.href='../login.php';</script>";
    exit;
}

$user_id = $_SESSION['user_id'];
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$default_jumlah = isset($_GET['jumlah']) ? (int)$_GET['jumlah'] : 1;
$variant_id = isset($_GET['variant_id']) ? intval($_GET['variant_id']) : null; // Changed to null as default

// Process checkout if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    $jumlah = intval($_POST['jumlah']);
    
    // Validate quantity
    if ($jumlah < 1) {
        $_SESSION['error'] = 'Jumlah tidak valid.';
        header("Location: ".$_SERVER['HTTP_REFERER']);
        exit;
    }
    
    // Get product data
    $stmt = $conn->prepare("SELECT * FROM tb_adminProduct WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    
    if (!$product) {
        $_SESSION['error'] = 'Produk tidak ditemukan.';
        header("Location: ".$_SERVER['HTTP_REFERER']);
        exit;
    }
    
    // Calculate price
    $harga = $product['is_diskon'] ? $product['harga_diskon'] : $product['harga'];
    $total_harga = $harga * $jumlah;
    
    // Check variant if exists
    $has_variant = false;
    if ($variant_id) {
        $stmt = $conn->prepare("SELECT * FROM tb_varian_product WHERE id = ? AND product_id = ?");
        $stmt->bind_param("ii", $variant_id, $product_id);
        $stmt->execute();
        $variant = $stmt->get_result()->fetch_assoc();
        
        if ($variant) {
            $has_variant = true;
            // Check variant stock
            if ($variant['stok'] < $jumlah) {
                $_SESSION['error'] = 'Stok varian tidak mencukupi.';
                header("Location: ".$_SERVER['HTTP_REFERER']);
                exit;
            }
        } else {
            $_SESSION['error'] = 'Varian tidak valid.';
            header("Location: ".$_SERVER['HTTP_REFERER']);
            exit;
        }
    } else {
        // Check product stock (for non-variant products)
        if ($product['jumlah_stok'] < $jumlah) {
            $_SESSION['error'] = 'Stok produk tidak mencukupi.';
            header("Location: ".$_SERVER['HTTP_REFERER']);
            exit;
        }
    }
    
    // Get selected address
    $selected_alamat_id = isset($_SESSION['selected_alamat_id']) ? (int)$_SESSION['selected_alamat_id'] : 0;
    $stmt = $conn->prepare("SELECT * FROM tb_alamat_user WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $selected_alamat_id, $user_id);
    $stmt->execute();
    $alamat = $stmt->get_result()->fetch_assoc();
    
    if (!$alamat) {
        $_SESSION['error'] = 'Alamat pengiriman belum dipilih.';
        header("Location: ".$_SERVER['HTTP_REFERER']);
        exit;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Insert into transaction history
        if ($has_variant) {
            $stmt = $conn->prepare("INSERT INTO tb_historyTransactions (user_id, product_id, varian_id, harga, jumlah, date, status) VALUES (?, ?, ?, ?, ?, NOW(), 'kirim')");
            $stmt->bind_param("iiidi", $user_id, $product_id, $variant_id, $harga, $jumlah);
        } else {
            $stmt = $conn->prepare("INSERT INTO tb_historyTransactions (user_id, product_id, harga, jumlah, date, status) VALUES (?, ?, ?, ?, NOW(), 'kirim')");
            $stmt->bind_param("iidi", $user_id, $product_id, $harga, $jumlah);
        }
        $stmt->execute();
        
        // Update stock
        if ($has_variant) {
            // Update variant stock
            $stmt = $conn->prepare("UPDATE tb_varian_product SET stok = stok - ? WHERE id = ?");
            $stmt->bind_param("ii", $jumlah, $variant_id);
        } else {
            // Update product stock
            $stmt = $conn->prepare("UPDATE tb_adminProduct SET jumlah_stok = jumlah_stok - ? WHERE id = ?");
            $stmt->bind_param("ii", $jumlah, $product_id);
            
            // Update stock status if needed
            $new_stock = $product['jumlah_stok'] - $jumlah;
            if ($new_stock <= 0) {
                $stmt2 = $conn->prepare("UPDATE tb_adminProduct SET stok = 'habis' WHERE id = ?");
                $stmt2->bind_param("i", $product_id);
                $stmt2->execute();
            }
        }
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        // Set success session
        $_SESSION['checkout_success'] = true;
        header("Location: checkout.php?id=".$product_id."&variant_id=".($variant_id ?: ''));
        exit;
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $_SESSION['error'] = 'Gagal memproses pesanan: '.$e->getMessage();
        header("Location: ".$_SERVER['HTTP_REFERER']);
        exit;
    }
}

// Get product data to display
$stmt = $conn->prepare("SELECT * FROM tb_adminProduct WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

// Get product variants (if any)
$varianProduk = [];
$stmt = $conn->prepare("SELECT * FROM tb_varian_product WHERE product_id = ?");
$stmt->bind_param("i", $product['id']);
$stmt->execute();
$resultVarian = $stmt->get_result();
while ($row = $resultVarian->fetch_assoc()) {
    $varianProduk[] = $row;
}

// Determine maximum stock
if ($variant_id) {
    $stmt = $conn->prepare("SELECT stok FROM tb_varian_product WHERE id = ?");
    $stmt->bind_param("i", $variant_id);
    $stmt->execute();
    $max_stock = $stmt->get_result()->fetch_assoc()['stok'];
} else {
    $max_stock = $product['jumlah_stok'];
}

// Get user's address
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
        .product-image {
            height: 120px;
            width: 120px;
            object-fit: cover;
            border-radius: 8px;
        }
        .card {
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="container mt-5 mb-5">
        <div class="card shadow">
            <div class="card-body">
                <h3 class="mb-4">Checkout Produk</h3>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>
                
                <div class="d-flex p-3 bg-white mb-4">
                    <div class="col-md-3">
                        <img src="../../admin/uploads/<?= htmlspecialchars($product['foto_thumbnail']) ?>" class="product-image" alt="<?= htmlspecialchars($product['nama_produk']) ?>">
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
                            <input type="hidden" name="variant_id" value="<?= $variant_id ?>">
                            
                            <div class="jumlah-input mt-2 mb-3">
                                <label for="jumlah" class="me-2">Jumlah:</label>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="ubahJumlah(-1)">-</button>
                                <input type="number" id="jumlah" name="jumlah" value="<?= $default_jumlah ?>" min="1" max="<?= $max_stock ?>" class="form-control form-control-sm">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="ubahJumlah(1)">+</button>
                            </div>

                            <div class="mt-2 mb-4 fs-5 fw-bold text-success" id="totalHarga">
                                Total Harga: Rp<?= number_format(($product['is_diskon'] ? $product['harga_diskon'] : $product['harga']) * $default_jumlah, 0, ',', '.') ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Shipping Address -->
                <h5>Alamat Pengiriman</h5>
                <?php if ($alamat): ?>
                    <div class="border rounded bg-white p-3 mb-3">
                        <p class="mb-1"><strong><i class="bi bi-geo-alt-fill"></i> <?= htmlspecialchars($alamat['label_alamat'] ?: 'Alamat Utama') ?></strong></p>
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

                <!-- Checkout Button -->
                <div class="d-flex gap-3 mt-4">
                    <a href="<?= isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '../products.php' ?>" class="btn btn-outline-secondary">Kembali</a>
                    <button type="submit" form="checkoutForm" class="btn btn-success" <?= !$alamat ? 'disabled' : '' ?>>
                        <i class="fas fa-shopping-cart me-2"></i>Konfirmasi Pesanan
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="checkoutSuccessModal" tabindex="-1" aria-labelledby="checkoutSuccessModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="checkoutSuccessModalLabel">Checkout Berhasil</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <i class="fas fa-check-circle text-success" style="font-size: 5rem;"></i>
                    <h4 class="mt-3">Pesanan Anda Berhasil Diproses!</h4>
                    <p>Terima kasih telah berbelanja dengan kami. Pesanan Anda akan segera kami proses.</p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal">Tutup</button>
                    <a href="../transactions/BuyingHistory.php" class="btn btn-outline-success">Lihat Riwayat Pesanan</a>
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
        }

        function hitungTotalHarga() {
            const jumlah = parseInt(document.getElementById("jumlah").value);
            const harga = <?= $product['is_diskon'] ? $product['harga_diskon'] : $product['harga'] ?>;
            const total = jumlah * harga;
            document.getElementById("totalHarga").innerText = "Total Harga: Rp" + total.toLocaleString("id-ID");
        }

        // Show modal if checkout success
        <?php if (isset($_SESSION['checkout_success'])): ?>
            document.addEventListener('DOMContentLoaded', function() {
                const modal = new bootstrap.Modal(document.getElementById('checkoutSuccessModal'));
                modal.show();
                
                // Clear session after modal is shown
                fetch('?clear_checkout=1', {method: 'GET'});
            });
        <?php unset($_SESSION['checkout_success']); endif; ?>
    </script>
</body>
</html>
<?php
// Clear checkout success flag if requested
if (isset($_GET['clear_checkout'])) {
    unset($_SESSION['checkout_success']);
    exit;
}
?>