<?php
session_start();
require '../db.php';

$username = isset($_SESSION['username']) ? $_SESSION['username'] : null;
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// Get product ID from URL
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$product_id) {
    header("Location: ../index.php");
    exit();
}

// Handle cart operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$user_id) {
        header("Location: ../user-controller/login.php");
        exit();
    }

    // Handle add to cart
    if (isset($_POST['add_to_cart'])) {
        $product_id = (int)$_POST['product_id'];
        $varian_id = isset($_POST['varian_id']) && !empty($_POST['varian_id']) ? (int)$_POST['varian_id'] : null;
        $quantity = isset($_POST['quantity']) ? max(1, (int)$_POST['quantity']) : 1; // Ensure quantity is at least 1

        // Check if product has variants
        $check_has_varian = $conn->prepare("SELECT COUNT(*) FROM tb_varian_product WHERE product_id = ?");
        $check_has_varian->bind_param("i", $product_id);
        $check_has_varian->execute();
        $check_has_varian->bind_result($has_varian);
        $check_has_varian->fetch();
        $check_has_varian->close();

        // If product has variants but user hasn't selected one
        if ($has_varian > 0 && !$varian_id) {
            $_SESSION['error_message'] = "Silakan pilih varian terlebih dahulu";
            $_SESSION['product_id_needs_varian'] = $product_id;
            header("Location: " . $_SERVER['PHP_SELF'] . "?id=$product_id");
            exit();
        }

        // Validate variant_id matches product_id
        if ($varian_id) {
            $check_varian = $conn->prepare("SELECT id FROM tb_varian_product WHERE id = ? AND product_id = ?");
            $check_varian->bind_param("ii", $varian_id, $product_id);
            $check_varian->execute();
            $check_varian->store_result();

            if ($check_varian->num_rows === 0) {
                // Invalid variant for this product
                $varian_id = null;
            }
            $check_varian->close();
        }

        // Check if product (with same variant) already exists in cart
        $check_stmt = $conn->prepare("SELECT id, jumlah FROM tb_cart WHERE user_id = ? AND product_id = ? AND varian_id <=> ?");
        $check_stmt->bind_param("iii", $user_id, $product_id, $varian_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            // Update quantity by ADDING the new quantity to existing quantity
            $row = $check_result->fetch_assoc();
            $new_jumlah = $row['jumlah'] + $quantity; // Tambahkan quantity baru ke yang sudah ada
            $update_stmt = $conn->prepare("UPDATE tb_cart SET jumlah = ? WHERE id = ?");
            $update_stmt->bind_param("ii", $new_jumlah, $row['id']);
            $update_stmt->execute();
            $update_stmt->close();
        } else {
            // Add new product to cart
            // Get product thumbnail
            $product_stmt = $conn->prepare("SELECT foto_thumbnail FROM tb_adminProduct WHERE id = ?");
            $product_stmt->bind_param("i", $product_id);
            $product_stmt->execute();
            $product_stmt->bind_result($foto_thumbnail);
            $product_stmt->fetch();
            $product_stmt->close();

            $insert_stmt = $conn->prepare("INSERT INTO tb_cart (user_id, product_id, varian_id, foto_thumbnail, jumlah) VALUES (?, ?, ?, ?, ?)");
            $insert_stmt->bind_param("iiisi", $user_id, $product_id, $varian_id, $foto_thumbnail, $quantity);
            $insert_stmt->execute();
            $insert_stmt->close();
        }

        $check_stmt->close();
    }
    // Handle update quantity
    elseif (isset($_POST['update_quantity'])) {
        $cart_id = (int)$_POST['cart_id'];
        $action = $_POST['action'];

        // Get current quantity
        $get_qty = $conn->prepare("SELECT jumlah FROM tb_cart WHERE id = ? AND user_id = ?");
        $get_qty->bind_param("ii", $cart_id, $user_id);
        $get_qty->execute();
        $get_qty->bind_result($current_qty);
        $get_qty->fetch();
        $get_qty->close();

        if ($action === 'increase') {
            $new_qty = $current_qty + 1;
        } elseif ($action === 'decrease' && $current_qty > 1) {
            $new_qty = $current_qty - 1;
        } else {
            $new_qty = $current_qty;
        }

        // Update quantity
        $update_qty = $conn->prepare("UPDATE tb_cart SET jumlah = ? WHERE id = ? AND user_id = ?");
        $update_qty->bind_param("iii", $new_qty, $cart_id, $user_id);
        $update_qty->execute();
        $update_qty->close();
    }
    // Handle remove item
    elseif (isset($_POST['remove_item'])) {
        $cart_id = (int)$_POST['cart_id'];

        $delete_stmt = $conn->prepare("DELETE FROM tb_cart WHERE id = ? AND user_id = ?");
        $delete_stmt->bind_param("ii", $cart_id, $user_id);
        $delete_stmt->execute();
        $delete_stmt->close();
    }

    // Set session flag to keep cart dropdown open
    $_SESSION['keep_cart_open'] = true;
    header("Location: " . $_SERVER['PHP_SELF'] . "?id=$product_id");
    exit();
}

// Get user info if logged in
if ($username) {
    $stmt = $conn->prepare("SELECT email, nomor_hp FROM tb_userLogin WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->bind_result($email, $nomor_hp);
    $stmt->fetch();
    $stmt->close();

    // Count cart items
    // Count cart items (count distinct products, not sum quantities)
    $cart_count_stmt = $conn->prepare("SELECT COUNT(*) FROM tb_cart WHERE user_id = ?");
    $cart_count_stmt->bind_param("i", $user_id);
    $cart_count_stmt->execute();
    $cart_count_stmt->bind_result($cart_count);
    $cart_count_stmt->fetch();
    $cart_count_stmt->close();

    // Get cart data for dropdown
    $cart_items_stmt = $conn->prepare("
        SELECT c.id, p.nama_produk, 
               CASE WHEN p.is_diskon = 1 THEN p.harga_diskon ELSE p.harga END as harga,
               c.jumlah, c.foto_thumbnail, v.varian, p.id as product_id, v.id as varian_id,
               p.harga as harga_asli, p.is_diskon
        FROM tb_cart c
        JOIN tb_adminProduct p ON c.product_id = p.id
        LEFT JOIN tb_varian_product v ON c.varian_id = v.id
        WHERE c.user_id = ?
        LIMIT 5
    ");
    $cart_items_stmt->bind_param("i", $user_id);
    $cart_items_stmt->execute();
    $cart_items_result = $cart_items_stmt->get_result();
    $cart_items = [];
    $total_price = 0;

    while ($item = $cart_items_result->fetch_assoc()) {
        $cart_items[] = $item;
        $total_price += $item['harga'] * $item['jumlah'];
    }
    $cart_items_stmt->close();
}

// Reset flag after use
$keep_cart_open = $_SESSION['keep_cart_open'] ?? false;
unset($_SESSION['keep_cart_open']);

// Get error message if any
$error_message = $_SESSION['error_message'] ?? null;
$product_id_needs_varian = $_SESSION['product_id_needs_varian'] ?? null;
unset($_SESSION['error_message']);
unset($_SESSION['product_id_needs_varian']);

// Get product details
$product_stmt = $conn->prepare("SELECT * FROM tb_adminProduct WHERE id = ? AND stok = 'tersedia'");
$product_stmt->bind_param("i", $product_id);
$product_stmt->execute();
$product_result = $product_stmt->get_result();
$product = $product_result->fetch_assoc();
$product_stmt->close();

if (!$product) {
    header("Location: ../index.php");
    exit();
}

// Get product variants
$varian_stmt = $conn->prepare("SELECT id, varian FROM tb_varian_product WHERE product_id = ? AND stok > 0");
$varian_stmt->bind_param("i", $product_id);
$varian_stmt->execute();
$varian_result = $varian_stmt->get_result();
$variants = [];
while ($variant = $varian_result->fetch_assoc()) {
    $variants[] = $variant;
}
$varian_stmt->close();

// Calculate product price (use discounted price if available)
$product_price = $product['is_diskon'] && $product['harga_diskon'] > 0 ? $product['harga_diskon'] : $product['harga'];
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= htmlspecialchars($product['nama_produk']) ?> - Makaroni website</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/navbar.css">
    <link rel="stylesheet" href="../css/sidebar.css">
    <style>
        /* Product card styles */
        .product-card {
            transition: transform 0.2s;
            height: 100%;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .product-image-container {
            aspect-ratio: 4/3;
            overflow: hidden;
            border-top-left-radius: 1rem;
            border-top-right-radius: 1rem;
        }

        .product-image {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 5px;
        }

        /* Product detail page styles */
        .product-detail-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .product-image {
            max-height: 400px;
            object-fit: contain;
            width: 100%;
        }

        .product-title {
            font-size: 1.8rem;
            margin-bottom: 1rem;
        }

        .product-description {
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }

        .variant-select {
            margin-bottom: 1rem;
        }

        .add-to-cart-btn {
            width: 100%;
            padding: 10px;
            font-size: 1.1rem;
        }

        /* Quantity controls styling */
        .quantity-control {
            margin-bottom: 1.5rem;
        }

        .quantity-btn {
            width: 40px;
            height: 40px;
            font-weight: bold;
            font-size: 1.1rem;
            border: 1px solid #ced4da;
            background-color: #f8f9fa;
            cursor: pointer;
            transition: all 0.2s;
        }

        .quantity-btn-nav {
            width: 25px;
            height: 25px;
            font-size: 1.1rem;
            border: 1px solid #ced4da;
            background-color: #f8f9fa;
            cursor: pointer;
            transition: all 0.2s;
        }

        .quantity-btn:hover {
            background-color: #e9ecef;
        }

        .quantity-btn:focus {
            box-shadow: none;
            outline: none;
        }

        .quantity-input {
            width: 50px;
            height: 40px;
            text-align: center;
            border-left: none;
            border-right: none;
            font-weight: bold;
        }

        .quantity-input-nav {
            width: 30px;
            text-align: center;
            font-weight: bold;
            margin: 0 5px;
        }

        .quantity-input::-webkit-outer-spin-button,
        .quantity-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .input-group {
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            border-radius: 0.375rem;
        }

        .input-group .btn:first-child {
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }

        .input-group .btn:last-child {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
        }

        /* Total price styling */
        .total-price-container {
            margin: 1rem 0;
            padding: 1rem;
            background-color: #f8f9fa;
            border-radius: 0.5rem;
            border: 1px solid #dee2e6;
        }

        .total-price-label {
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .total-price-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #28a745;
        }
    </style>
</head>

<body>
    <!-- sidebar -->
    <?php
    // Get categories from database
    $query = "SELECT * FROM tb_adminCategory ORDER BY nama_kategori ASC";
    $result = mysqli_query($conn, $query);
    ?>

    <div class="sidebar" id="sidebar" aria-label="Sidebar navigation">
        <button class="close-btn" onclick="toggleSidebar()" aria-label="Close sidebar">×</button>
        <ul>
            <li><a href="../home.php" class="menu-item">Home</a></li>
            <li><a href="../web/aboutUs.php" class="menu-item">About Us</a></li>

            <!-- Produk Dropdown -->
            <li>
                <div class="menu-item custom-dropdown-toggle" onclick="toggleDropdown('produkDropdown', this)">
                    <span>Produk</span>
                    <i class="bi bi-chevron-down"></i>
                </div>
                <div class="dropdown-content" id="produkDropdown">
                    <?php while ($row = mysqli_fetch_assoc($result)) : ?>
                        <a href="../products/product.php?kategori=<?= urlencode($row['nama_kategori']) ?>">
                            <?= htmlspecialchars($row['nama_kategori']) ?>
                        </a>
                    <?php endwhile; ?>
                </div>
            </li>

            <li><a href="../cart/cart.php" class="menu-item">Keranjang</a></li>

            <!-- Marketplace Dropdown -->
            <li>
                <div class="menu-item custom-dropdown-toggle" onclick="toggleDropdown('marketplaceDropdown', this)">
                    <span>Marketplace</span>
                    <i class="bi bi-chevron-down"></i>
                </div>
                <div class="dropdown-content" id="marketplaceDropdown">
                    <a href="#">Tokopedia</a>
                    <a href="#">Shopee</a>
                </div>
            </li>
        </ul>
    </div>
    <!-- CSS sidebar -->
    <style>
        .dropdown-content {
            display: none;
            padding-left: 20px;
            margin-top: 5px;
            flex-direction: column;
        }

        .dropdown-content a {
            display: block;
            padding: 5px 0;
            text-decoration: none;
            color: #333;
        }

        .menu-item.custom-dropdown-toggle {
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
        }

        .menu-item.custom-dropdown-toggle.active+.dropdown-content {
            display: flex;
        }

        .menu-item.custom-dropdown-toggle.active i {
            transform: rotate(180deg);
        }

        /* Tidak mengganggu toggle Bootstrap */
        .menu-item.custom-dropdown-toggle::after {
            content: none !important;
        }
    </style>
    <!-- JavaScript sidebar -->
    <script>
        function toggleDropdown(dropdownId, toggleElement) {
            const dropdown = document.getElementById(dropdownId);
            toggleElement.classList.toggle('active');

            if (dropdown.style.display === 'flex') {
                dropdown.style.display = 'none';
            } else {
                dropdown.style.display = 'flex';
            }
        }
    </script>

    <!-- Navbar -->
    <nav class="navbar" role="navigation" aria-label="Main navigation">
        <div class="container">
            <div class="left-section">
                <div
                    class="hamburger"
                    id="hamburger"
                    aria-label="Toggle menu"
                    role="button"
                    tabindex="0">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
                <a class="navbar-brand logo text-white" href="../home.php">Navbar</a>
            </div>

            <div class="search-bar">
                <input type="text" class="search-input" placeholder="Cari..." aria-label="Search" />
            </div>
            <div
                class="search-icon"
                id="searchIcon"
                aria-label="Toggle search"
                role="button"
                tabindex="0">
                <i class="bi bi-search"></i>
            </div>

            <div class="right-section" role="region" aria-label="User and cart menu">
                <div class="cart-container" id="cartContainer" <?= $keep_cart_open ? 'class="show"' : '' ?>>
                    <a
                        href="javascript:void(0);"
                        id="cartToggle"
                        aria-label="Toggle cart dropdown"
                        role="button"
                        tabindex="0">
                        <span class="cart-icon">
                            <i class="bi bi-cart3"></i>
                            <span class="cart-badge"><?= isset($cart_count) ? $cart_count : 0 ?></span>
                        </span>
                    </a>
                    <div class="cart-dropdown" id="cartDropdown" role="menu" aria-hidden="<?= $keep_cart_open ? 'false' : 'true' ?>">
                        <?php if (isset($cart_count) && $cart_count > 0): ?>
                            <strong>Keranjang (<?= $cart_count ?>)</strong>
                            <ul>
                                <?php foreach ($cart_items as $item): ?>
                                    <li>
                                        <a href="../checkout-products/checkoutFromCart.php?id=<?= $item['id'] ?>" class="cart-item-link">
                                            <img src="../../admin/uploads/<?= htmlspecialchars($item['foto_thumbnail'] ?? 'default.jpg') ?>" alt="<?= htmlspecialchars($item['nama_produk']) ?>" />
                                            <div class="product-info">
                                                <?= htmlspecialchars($item['nama_produk']) ?>
                                                <?php if (!empty($item['varian'])): ?>
                                                    <small>(<?= htmlspecialchars($item['varian']) ?>)</small>
                                                <?php endif; ?>
                                                <div class="quantity-controls">
                                                    <form method="POST" action="" class="d-inline" onsubmit="event.stopPropagation();">
                                                        <input type="hidden" name="cart_id" value="<?= $item['id'] ?>">
                                                        <input type="hidden" name="action" value="decrease">
                                                        <button type="submit" name="update_quantity" class="quantity-btn-nav">-</button>
                                                    </form>
                                                    <span class="quantity-input-nav border"><?= $item['jumlah'] ?></span>
                                                    <form method="POST" action="" class="d-inline" onsubmit="event.stopPropagation();">
                                                        <input type="hidden" name="cart_id" value="<?= $item['id'] ?>">
                                                        <input type="hidden" name="action" value="increase">
                                                        <button type="submit" name="update_quantity" class="quantity-btn-nav">+</button>
                                                    </form>
                                                    <form method="POST" action="" class="d-inline" onsubmit="event.stopPropagation();">
                                                        <input type="hidden" name="cart_id" value="<?= $item['id'] ?>">
                                                        <button type="submit" name="remove_item" class="remove-btn" title="Hapus">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                            <div class="product-price text-dark">
                                                <?php if ($item['is_diskon']): ?>
                                                    <div class="price-container-navbar">
                                                        <span class="original-price">Rp<?= number_format($item['harga_asli'], 0, ',', '.') ?></span>
                                                        <span class="discounted-price">Rp<?= number_format($item['harga'], 0, ',', '.') ?></span>
                                                    </div>
                                                <?php else: ?>
                                                    Rp<?= number_format($item['harga'], 0, ',', '.') ?>
                                                <?php endif; ?>
                                            </div>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <div class="total-price">Total: Rp<?= number_format($total_price, 0, ',', '.') ?></div>
                            <a href="../cart/cart.php" class="view-all">Lihat semua</a>
                        <?php else: ?>
                            <div class="empty-cart">Keranjang belanja kosong</div>
                        <?php endif; ?>
                        <style>
                            .cart-item-link {
                                display: flex;
                                align-items: center;
                                justify-content: space-between;
                                gap: 10px;
                                padding: 10px 0;
                                text-decoration: none;
                                color: inherit;
                            }

                            .cart-item-link img {
                                width: 50px;
                                height: 50px;
                                object-fit: cover;
                                flex-shrink: 0;
                                border-radius: 6px;
                            }

                            .product-info {
                                flex-grow: 1;
                                display: flex;
                                flex-direction: column;
                                gap: 4px;
                                color: black;
                            }

                            .quantity-controls {
                                display: flex;
                                align-items: center;
                                gap: 6px;
                                margin-top: 4px;
                            }

                            .quantity-btn,
                            .remove-btn {
                                background: none;
                                border: 1px solid #ccc;
                                padding: 2px 6px;
                                cursor: pointer;
                                font-size: 14px;
                                border-radius: 4px;
                            }

                            .quantity-input {
                                min-width: 20px;
                                text-align: center;
                            }


                            .price-container-navbar {
                                display: flex;
                                flex-direction: column;
                                gap: 2px;
                            }

                            .original-price {
                                text-decoration: line-through;
                                color: gray;
                                font-size: 12px;
                            }

                            .discounted-price {
                                color: red;
                                font-weight: bold;
                            }

                            .empty-cart {
                                padding: 10px;
                                text-align: center;
                                color: #777;
                            }

                            .total-price {
                                font-weight: bold;
                                text-align: right;
                                padding-top: 10px;
                            }

                            .view-all {
                                display: block;
                                text-align: right;
                                padding-top: 5px;
                                font-size: 14px;
                                text-decoration: underline;
                            }

                            .quantity-input {
                                min-width: 24px;
                                padding: 2px 4px;
                                text-align: center;
                                border: 1px solid #ccc;
                                border-radius: 4px;
                                display: inline-block;
                                background-color: #f9f9f9;
                                color: black;
                                font-size: 14px;
                            }
                        </style>
                    </div>
                </div>

                <!-- dropdown username -->
                <?php if ($username) : ?>
                    <div class="user-dropdown">
                        <a href="#" aria-label="User profile"><?= htmlspecialchars($username) ?></a>
                        <div class="user-dropdown-content">
                            <div class="user-info">
                                <span><strong>Username:</strong> <?= htmlspecialchars($username) ?></span>
                                <span><strong>Email:</strong> <?= htmlspecialchars($email) ?></span>
                                <span><strong>No HP:</strong> <?= htmlspecialchars($nomor_hp) ?></span>
                            </div>
                            <hr>
                            <a href="../transactions/BuyingHistory.php" class="menu-item"><i class="bi bi-bag"></i> Pembelian</a>
                            <a href="../settings/settingsAccount.php" class="menu-item"><i class="bi bi-gear"></i> Pengaturan</a>
                            <hr>
                            <a href="../logout.php" class="menu-item text-danger"><i class="bi bi-arrow-bar-left"></i> Logout</a>
                        </div>
                        <style>
                            .user-dropdown {
                                position: relative;
                                display: inline-block;
                            }

                            .user-dropdown-content {
                                display: none;
                                position: absolute;
                                right: 0;
                                background-color: #fff;
                                min-width: 220px;
                                box-shadow: 0px 8px 16px rgba(0, 0, 0, 0.2);
                                z-index: 1;
                                border: 1px solid #ccc;
                                border-radius: 6px;
                                padding: 10px;
                            }

                            .user-dropdown:hover .user-dropdown-content {
                                display: block;
                            }

                            .user-dropdown-content hr {
                                margin: 8px 0;
                                border: 0;
                                border-top: 1px solid #ccc;
                            }

                            .user-dropdown-content .menu-item {
                                padding: 5px 0;
                                color: #333;
                                text-decoration: none;
                                display: block;
                            }

                            .user-dropdown-content .menu-item:hover {
                                background-color: #f0f0f0;
                            }

                            .user-dropdown-content .user-info {
                                font-size: 14px;
                                color: #333;
                            }

                            .user-dropdown-content .user-info span {
                                display: block;
                                margin-bottom: 4px;
                            }
                        </style>
                    </div>
                <?php else : ?>
                    <a href="../user-controller/login.php">Login</a>
                    <a href="../user-controller/register.php">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Search bar Mobile -->
    <div class="search-container-mobile" id="mobileSearch">
        <input type="text" class="search-input" placeholder="Cari..." aria-label="Search mobile" />
    </div>

    <!-- Product Detail Section -->
    <div class="product-detail-container">
        <div class="row">
            <div class="col-md-6">
                <!-- Gambar utama yang besar -->
                <img id="mainProductImage"
                    src="../../admin/uploads/<?= htmlspecialchars($product['foto_thumbnail'] ?? 'default.jpg') ?>"
                    alt="<?= htmlspecialchars($product['nama_produk']) ?>"
                    class="product-image img-fluid mb-3 rounded"
                    style="max-height: 400px; width: auto; object-fit: contain;">

                <?php
                $foto_produk = json_decode($product['foto_produk'], true);
                ?>

                <!-- Container untuk thumbnail -->
                <div class="thumbnail-container d-flex flex-wrap gap-2">
                    <?php
                    // Selalu tampilkan thumbnail utama pertama
                    echo "<img src='../../admin/uploads/" . htmlspecialchars($product['foto_thumbnail'] ?? 'default.jpg') . "' 
              alt='Thumbnail' 
              class='img-thumbnail thumb-img active'
              style='width: 80px; height: 80px; object-fit: cover; cursor: pointer;'
              onclick='changeMainImage(this, \"" . htmlspecialchars($product['foto_thumbnail'] ?? 'default.jpg') . "\")'>";

                    // Tampilkan foto produk lainnya jika ada
                    if (is_array($foto_produk) && count($foto_produk) > 0) {
                        foreach ($foto_produk as $foto) {
                            echo "<img src='../../admin/uploads/" . htmlspecialchars($foto) . "' 
                      alt='Foto Produk' 
                      class='img-thumbnail thumb-img'
                      style='width: 80px; height: 80px; object-fit: cover; cursor: pointer;'
                      onclick='changeMainImage(this, \"" . htmlspecialchars($foto) . "\")'>";
                        }
                    } else {
                        echo "<span class='text-muted align-self-center'>Tidak ada foto produk lainnya.</span>";
                    }
                    ?>
                </div>
            </div>

            <!-- JavaScript untuk mengelola galeri foto -->
            <script>
                function changeMainImage(thumbElement, imagePath) {
                    const mainImage = document.getElementById('mainProductImage');
                    const allThumbs = document.querySelectorAll('.thumb-img');

                    // Update gambar utama
                    mainImage.src = `../../admin/uploads/${imagePath}`;

                    // Update active state pada thumbnail
                    allThumbs.forEach(thumb => {
                        thumb.classList.remove('active');
                        thumb.style.opacity = '0.7';
                    });

                    thumbElement.classList.add('active');
                    thumbElement.style.opacity = '1';

                    // Animasi transisi halus
                    mainImage.style.opacity = 0;
                    setTimeout(() => {
                        mainImage.style.opacity = 1;
                    }, 100);
                }

                // Inisialisasi - beri style pada thumbnail aktif pertama
                document.addEventListener('DOMContentLoaded', function() {
                    const firstThumb = document.querySelector('.thumb-img');
                    if (firstThumb) {
                        firstThumb.classList.add('active');
                        firstThumb.style.opacity = '1';
                    }
                });
            </script>

            <!-- CSS tambahan -->
            <style>
                .product-image {
                    transition: opacity 0.3s ease;
                }

                .thumb-img {
                    transition: all 0.2s ease;
                }

                .thumb-img:hover {
                    transform: scale(1.05);
                    box-shadow: 0 0 5px rgba(0, 0, 0, 0.3);
                }

                .thumb-img.active {
                    border: 2px solid #0d6efd;
                    opacity: 1 !important;
                }

                .thumbnail-container {
                    max-height: 200px;
                    overflow-y: auto;
                    padding: 5px;
                }
            </style>
            <?php
            // Controller Code (should be at the top of your file or in a separate controller)
            $product_id = $_GET['id'] ?? 0;

            // Fetch main product data
            $product_query = $conn->prepare("SELECT * FROM tb_adminProduct WHERE id = ?");
            $product_query->bind_param("i", $product_id);
            $product_query->execute();
            $product_result = $product_query->get_result();
            $product = $product_result->fetch_assoc();

            // Fetch variants if they exist
            $variants_query = $conn->prepare("SELECT id, varian, stok FROM tb_varian_product WHERE product_id = ?");
            $variants_query->bind_param("i", $product_id);
            $variants_query->execute();
            $variants_result = $variants_query->get_result();
            $variants = $variants_result->fetch_all(MYSQLI_ASSOC);

            // Determine the product price (considering discount)
            $product_price = $product['is_diskon'] && $product['harga_diskon'] > 0 ? $product['harga_diskon'] : $product['harga'];
            ?>

            <!-- View Code -->
            <div class="col-md-6">
                <h1 class="product-title"><?= htmlspecialchars($product['nama_produk']) ?></h1>

                <!-- Price Display -->
                <div class="product-price mb-3">
                    <?php if ($product['is_diskon'] && $product['harga_diskon'] > 0): ?>
                        <div class="price-container">
                            <span class="original-price">Rp<?= number_format($product['harga'], 0, ',', '.') ?></span>
                            <span class="discounted-price">Rp<?= number_format($product['harga_diskon'], 0, ',', '.') ?></span>
                        </div>
                    <?php else: ?>
                        <span class="text-success fw-bold">Rp<?= number_format($product['harga'], 0, ',', '.') ?></span>
                    <?php endif; ?>
                </div>

                <!-- Stock Information -->
                <div class="product-stock mb-3">
                    <span class="stock-label fw-bold">Stok:</span>
                    <?php if (!empty($variants)): ?>
                        <span class="stock-value" id="variantStock">Pilih varian untuk melihat stok</span>
                    <?php else: ?>
                        <span class="stock-value">
                            <?= $product['jumlah_stok'] ?> (<?= $product['stok'] == 'tersedia' ? 'Tersedia' : 'Habis' ?>)
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Product Description -->
                <p class="product-description mb-4"><?= htmlspecialchars($product['detail']) ?></p>

                <!-- Variant Selection -->
                <?php if (!empty($variants)): ?>
                    <div class="mb-3">
                        <label for="productVariant" class="form-label">Varian:</label>
                        <select class="form-select variant-select" id="productVariant" data-product-id="<?= $product['id'] ?>">
                            <option value="">Pilih varian</option>
                            <?php foreach ($variants as $variant): ?>
                                <?php
                                $variant_stock = (int)$variant['stok'];
                                $variant_status = $variant_stock > 0 ? 'Tersedia' : 'Habis';
                                ?>
                                <option value="<?= $variant['id'] ?>" data-stock="<?= $variant_stock ?>">
                                    <?= htmlspecialchars($variant['varian']) ?> (Stok: <?= $variant_stock ?>, <?= $variant_status ?>)
                                    <?= htmlspecialchars($variant['id']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <!-- Quantity Control -->
                <div class="quantity-control mb-3">
                    <label class="form-label">Jumlah:</label>
                    <div class="input-group" style="max-width: 150px;">
                        <button class="btn btn-outline-secondary quantity-btn minus" type="button">-</button>
                        <input type="number" class="form-control text-center quantity-input"
                            value="1" min="1"
                            max="<?= empty($variants) ? $product['jumlah_stok'] : '' ?>"
                            aria-label="Quantity" name="quantity" id="productQuantity">
                        <button class="btn btn-outline-secondary quantity-btn plus" type="button">+</button>
                    </div>
                </div>

                <!-- Total Price -->
                <div class="total-price-container mb-4">
                    <div class="total-price-label fw-bold">Total Harga:</div>
                    <div class="total-price-value fw-bold" id="totalPriceValue">
                        Rp<?= number_format($product_price, 0, ',', '.') ?>
                    </div>
                </div>

                <!-- Add to Cart Button -->
                <button type="button" class="btn btn-primary add-to-cart-btn w-100 py-2 mb-2"
                    data-product-id="<?= $product['id'] ?>"
                    <?= !empty($variants) ? 'data-has-varian="true"' : 'data-has-varian="false"' ?>
                    <?= (empty($variants) && ($product['stok'] == 'habis' || $product['jumlah_stok'] <= 0)) ? 'disabled' : '' ?>>
                    <i class="bi bi-cart-plus me-2"></i>Tambah ke Keranjang
                </button>

                <!-- Tombol Checkout -->
                <button type="button" class="btn btn-success w-100 py-2 checkout-btn"
                    data-product-id="<?= $product['id'] ?>"
                    <?= !empty($variants) ? 'data-has-varian="true"' : 'data-has-varian="false"' ?>
                    <?= (empty($variants) && ($product['stok'] == 'habis' || $product['jumlah_stok'] <= 0)) ? 'disabled' : '' ?>>
                    <i class="bi bi-bag-check me-2"></i>Checkout
                </button>

                <!-- Script Checkout -->
                <script>
                    document.querySelector('.checkout-btn').addEventListener('click', function() {
                        const hasVarian = this.getAttribute('data-has-varian') === 'true';
                        const productId = this.getAttribute('data-product-id');
                        let variantId = '';
                        let jumlah = 1;

                        if (hasVarian) {
                            const selected = document.querySelector('#productVariant')?.value;
                            if (!selected || selected === '') {
                                const modal = new bootstrap.Modal(document.getElementById('modalPilihVarian'));
                                modal.show();
                                return;
                            }
                            variantId = selected;
                        }

                        jumlah = document.getElementById('jumlah')?.value || 1;

                        // Tetap gunakan ?id=... untuk konsistensi
                        let url = `../checkout-products/checkout.php?id=${productId}&jumlah=${jumlah}`;
                        if (variantId) {
                            url += `&variant_id=${variantId}`;
                        }

                        window.location.href = url;
                    });
                </script>
                <!-- Modal jika belum memilih varian -->
                <div class="modal fade" id="modalPilihVarian" tabindex="-1" aria-labelledby="modalPilihVarianLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content rounded-3 shadow">
                            <div class="modal-header bg-success text-dark">
                                <h5 class="modal-title" id="modalPilihVarianLabel">Pilih Varian</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                            </div>
                            <div class="modal-body">
                                Silakan pilih varian produk terlebih dahulu sebelum melanjutkan ke checkout.
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- JavaScript for Variant Selection -->
            <?php if (!empty($variants)): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const variantSelect = document.getElementById('productVariant');
                        const stockDisplay = document.getElementById('variantStock');
                        const quantityInput = document.getElementById('productQuantity');
                        const addToCartBtn = document.querySelector('.add-to-cart-btn');

                        variantSelect.addEventListener('change', function() {
                            const selectedOption = this.options[this.selectedIndex];

                            if (selectedOption && selectedOption.value) {
                                const stock = parseInt(selectedOption.getAttribute('data-stock'));
                                const status = stock > 0 ? 'Tersedia' : 'Habis';

                                stockDisplay.textContent = `${stock} (${status})`;
                                quantityInput.max = stock;
                                quantityInput.value = Math.min(1, stock);
                                addToCartBtn.disabled = stock <= 0;
                            } else {
                                stockDisplay.textContent = 'Pilih varian untuk melihat stok';
                                quantityInput.removeAttribute('max');
                                addToCartBtn.disabled = true;
                            }
                        });
                    });
                </script>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal untuk memilih varian -->
    <div id="varianModal" class="varian-modal">
        <div class="varian-modal-content">
            <span class="close-modal">&times;</span>
            <h5>Pilih Varian</h5>
            <p id="modalErrorMessage" class="text-danger"></p>
            <form id="varianForm" method="POST">
                <input type="hidden" name="product_id" id="modalProductId">
                <div class="mb-3">
                    <select class="form-select" name="varian_id" id="modalVarianSelect" required>
                        <option value="">Pilih varian</option>
                    </select>
                </div>
                <button type="submit" name="add_to_cart" class="btn btn-primary">Tambahkan ke Keranjang</button>
            </form>
        </div>
    </div>

    <script>
        const hamburger = document.getElementById("hamburger");
        const sidebar = document.getElementById("sidebar");
        const cartContainer = document.getElementById("cartContainer");
        const cartDropdown = document.getElementById("cartDropdown");
        const searchIcon = document.getElementById("searchIcon");
        const mobileSearch = document.getElementById("mobileSearch");
        const varianModal = document.getElementById("varianModal");
        const modalVarianSelect = document.getElementById("modalVarianSelect");
        const modalProductId = document.getElementById("modalProductId");
        const modalErrorMessage = document.getElementById("modalErrorMessage");
        const varianForm = document.getElementById("varianForm");
        const closeModal = document.querySelector(".close-modal");
        const quantityInput = document.getElementById("productQuantity");
        const minusBtn = document.querySelector(".quantity-btn.minus");
        const plusBtn = document.querySelector(".quantity-btn.plus");
        const totalPriceValue = document.getElementById("totalPriceValue");

        // Product price from PHP
        const productPrice = <?= $product_price ?>;

        // Function to update total price
        function updateTotalPrice() {
            const quantity = parseInt(quantityInput.value) || 1;
            const totalPrice = productPrice * quantity;
            totalPriceValue.textContent = 'Rp' + totalPrice.toLocaleString('id-ID');
        }

        // Set cart dropdown tetap terbuka jika ada flag
        <?php if ($keep_cart_open): ?>
            document.addEventListener('DOMContentLoaded', function() {
                cartContainer.classList.add("show");
                cartDropdown.setAttribute("aria-hidden", "false");
            });
        <?php endif; ?>

        // Tampilkan modal varian jika ada error
        <?php if ($error_message && $product_id_needs_varian): ?>
            document.addEventListener('DOMContentLoaded', function() {
                showVarianModal(<?= $product_id_needs_varian ?>, "<?= $error_message ?>");
            });
        <?php endif; ?>

        // Initialize total price
        updateTotalPrice();

        // Quantity controls
        minusBtn.addEventListener('click', function() {
            let value = parseInt(quantityInput.value);
            if (value > 1) {
                quantityInput.value = value - 1;
                updateTotalPrice();
            }
        });

        plusBtn.addEventListener('click', function() {
            let value = parseInt(quantityInput.value);
            if (value < 99) {
                quantityInput.value = value + 1;
                updateTotalPrice();
            }
        });

        quantityInput.addEventListener('change', function() {
            let value = parseInt(this.value);
            if (isNaN(value) || value < 1) {
                this.value = 1;
            } else if (value > 99) {
                this.value = 99;
            }
            updateTotalPrice();
        });

        function toggleSidebar() {
            sidebar.classList.toggle("active");
            hamburger.classList.toggle("active");
        }

        hamburger.addEventListener("click", toggleSidebar);
        hamburger.addEventListener("keydown", (e) => {
            if (e.key === "Enter" || e.key === " ") {
                e.preventDefault();
                toggleSidebar();
            }
        });

        function toggleCartDropdown() {
            cartContainer.classList.toggle("show");
            const expanded = cartContainer.classList.contains("show");
            cartDropdown.setAttribute("aria-hidden", !expanded);
        }

        document.getElementById("cartToggle").addEventListener("click", (e) => {
            e.preventDefault();
            toggleCartDropdown();
        });

        document.getElementById("cartToggle").addEventListener("keydown", (e) => {
            if (e.key === "Enter" || e.key === " ") {
                e.preventDefault();
                toggleCartDropdown();
            }
        });

        document.addEventListener("click", (e) => {
            if (e.target.closest('form') && e.target.closest('form').method === 'post') {
                return;
            }

            if (!cartContainer.contains(e.target) && cartContainer.classList.contains("show")) {
                cartContainer.classList.remove("show");
                cartDropdown.setAttribute("aria-hidden", "true");
            }
        });

        searchIcon.addEventListener("click", () => {
            mobileSearch.classList.toggle("show");
        });
        searchIcon.addEventListener("keydown", (e) => {
            if (e.key === "Enter" || e.key === " ") {
                e.preventDefault();
                mobileSearch.classList.toggle("show");
            }
        });

        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.stopPropagation();
            });
        });

        function showVarianModal(productId, errorMessage = null) {
            modalProductId.value = productId;
            modalErrorMessage.textContent = errorMessage || '';

            modalVarianSelect.innerHTML = '<option value="">Pilih varian</option>';

            const originalSelect = document.querySelector(`.variant-select[data-product-id="${productId}"]`);
            if (originalSelect) {
                const options = originalSelect.querySelectorAll('option');
                options.forEach(option => {
                    if (option.value) {
                        const newOption = document.createElement('option');
                        newOption.value = option.value;
                        newOption.textContent = option.textContent;
                        modalVarianSelect.appendChild(newOption);
                    }
                });
            }

            varianModal.style.display = "block";
        }

        function closeVarianModal() {
            varianModal.style.display = "none";
        }

        closeModal.addEventListener("click", closeVarianModal);

        window.addEventListener("click", (e) => {
            if (e.target === varianModal) {
                closeVarianModal();
            }
        });

        varianForm.addEventListener("submit", function(e) {
            e.preventDefault();
            if (!modalVarianSelect.value) {
                modalErrorMessage.textContent = "Silakan pilih varian terlebih dahulu";
                return;
            }
            this.submit();
        });

        document.querySelectorAll('.add-to-cart-btn').forEach(button => {
            button.addEventListener('click', function() {
                const productId = this.getAttribute('data-product-id');
                const hasVarian = this.getAttribute('data-has-varian') === 'true';
                const quantity = parseInt(document.getElementById('productQuantity').value) || 1;

                if (hasVarian) {
                    const varianSelect = document.querySelector(`.variant-select[data-product-id="${productId}"]`);
                    if (!varianSelect || !varianSelect.value) {
                        showVarianModal(productId, "Silakan pilih varian terlebih dahulu");
                        return;
                    }

                    // Create form and submit
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '';

                    const productIdInput = document.createElement('input');
                    productIdInput.type = 'hidden';
                    productIdInput.name = 'product_id';
                    productIdInput.value = productId;
                    form.appendChild(productIdInput);

                    const varianIdInput = document.createElement('input');
                    varianIdInput.type = 'hidden';
                    varianIdInput.name = 'varian_id';
                    varianIdInput.value = varianSelect.value;
                    form.appendChild(varianIdInput);

                    const quantityInput = document.createElement('input');
                    quantityInput.type = 'hidden';
                    quantityInput.name = 'quantity';
                    quantityInput.value = quantity;
                    form.appendChild(quantityInput);

                    const addToCartInput = document.createElement('input');
                    addToCartInput.type = 'hidden';
                    addToCartInput.name = 'add_to_cart';
                    addToCartInput.value = '1';
                    form.appendChild(addToCartInput);

                    document.body.appendChild(form);
                    form.submit();
                } else {
                    // Create form and submit
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '';

                    const productIdInput = document.createElement('input');
                    productIdInput.type = 'hidden';
                    productIdInput.name = 'product_id';
                    productIdInput.value = productId;
                    form.appendChild(productIdInput);

                    const quantityInput = document.createElement('input');
                    quantityInput.type = 'hidden';
                    quantityInput.name = 'quantity';
                    quantityInput.value = quantity;
                    form.appendChild(quantityInput);

                    const addToCartInput = document.createElement('input');
                    addToCartInput.type = 'hidden';
                    addToCartInput.name = 'add_to_cart';
                    addToCartInput.value = '1';
                    form.appendChild(addToCartInput);

                    document.body.appendChild(form);
                    form.submit();
                }
            });
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>