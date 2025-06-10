<?php
session_start();
require '../db.php';

$username = isset($_SESSION['username']) ? $_SESSION['username'] : null;
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// Handle cart operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$user_id) {
        header("Location: ./user-controller/login.php");
        exit();
    }

    // Handle add to cart
    if (isset($_POST['add_to_cart'])) {
        $product_id = (int)$_POST['product_id'];
        $varian_id = isset($_POST['varian_id']) && !empty($_POST['varian_id']) ? (int)$_POST['varian_id'] : null;

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
            header("Location: " . $_SERVER['PHP_SELF']);
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
            // Update quantity if product already exists
            $row = $check_result->fetch_assoc();
            $new_jumlah = $row['jumlah'] + 1;
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

            $insert_stmt = $conn->prepare("INSERT INTO tb_cart (user_id, product_id, varian_id, foto_thumbnail, jumlah) VALUES (?, ?, ?, ?, 1)");
            $insert_stmt->bind_param("iiis", $user_id, $product_id, $varian_id, $foto_thumbnail);
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
    header("Location: " . $_SERVER['PHP_SELF']);
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

    // Count DISTINCT cart items (products + variants)
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
    $total_items = 0; // For total quantity of all items

    while ($item = $cart_items_result->fetch_assoc()) {
        $cart_items[] = $item;
        $total_price += $item['harga'] * $item['jumlah'];
        $total_items += $item['jumlah'];
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
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Makaroni website</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/navbar.css">
    <link rel="stylesheet" href="../css/sidebar.css">
    <style>
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

        .product-link {
            text-decoration: none;
            color: inherit;
        }

        .product-link:hover {
            color: inherit;
        }
    </style>
    <script src="../js/sidebarDropdown.js"></script>
</head>

<body>

    <!-- sidebar -->
    <?php
    // Ambil data kategori dari database
    $query = "SELECT * FROM tb_adminCategory ORDER BY nama_kategori ASC";
    $result = mysqli_query($conn, $query);
    ?>

    <div class="sidebar" id="sidebar" aria-label="Sidebar navigation">
        <button class="close-btn" onclick="toggleSidebar()" aria-label="Close sidebar">×</button>
        <ul>
            <li><a href="../home.php" class="menu-item">Home</a></li>
            <li><a href="../web/aboutUs.php" class="menu-item">About Us</a></li>

            <li>
                <div class="menu-item" onclick="toggleDropdown('produkDropdown', this)">
                    <span>Produk</span>
                    <i class="fa-solid fa-chevron-down"></i>
                </div>
                <div class="dropdown-content" id="produkDropdown">
                    <?php while ($row = mysqli_fetch_assoc($result)) : ?>
                        <a href="./products/product.php?kategori=<?= urlencode($row['nama_kategori']) ?>">
                            <?= htmlspecialchars($row['nama_kategori']) ?>
                        </a>
                    <?php endwhile; ?>
                </div>
            </li>

            <li><a href="../cart/cart.php" class="menu-item">Keranjang</a></li>

            <li>
                <div class="menu-item" onclick="toggleDropdown('marketplaceDropdown', this)">
                    <span>Marketplace</span>
                    <i class="fa-solid fa-chevron-down"></i>
                </div>
                <div class="dropdown-content" id="marketplaceDropdown">
                    <a href="#">Tokopedia</a>
                    <a href="#">Shopee</a>
                </div>
            </li>
        </ul>
    </div>

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
                                                    <form method="POST" action="" onsubmit="event.stopPropagation();">
                                                        <input type="hidden" name="cart_id" value="<?= $item['id'] ?>">
                                                        <input type="hidden" name="action" value="decrease">
                                                        <button type="submit" name="update_quantity" class="quantity-btn">-</button>
                                                    </form>

                                                    <span class="quantity-input"><?= $item['jumlah'] ?></span>

                                                    <form method="POST" action="" onsubmit="event.stopPropagation();">
                                                        <input type="hidden" name="cart_id" value="<?= $item['id'] ?>">
                                                        <input type="hidden" name="action" value="increase">
                                                        <button type="submit" name="update_quantity" class="quantity-btn">+</button>
                                                    </form>

                                                    <form method="POST" action="" onsubmit="event.stopPropagation();">
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

                            .product-price {
                                white-space: nowrap;
                                text-align: right;
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

    <!-- Cart Dropdown Section - Modified to show both counts -->
    <div class="cart-dropdown" id="cartDropdown" role="menu" aria-hidden="<?= $keep_cart_open ? 'false' : 'true' ?>">
        <?php if (isset($cart_count) && $cart_count > 0): ?>
            <div class="cart-header">
                <strong>Keranjang (<?= $cart_count ?> item<?= $cart_count > 1 ? 's' : '' ?>)</strong>
                <small class="text-muted"><?= $total_items ?> barang</small>
            </div>
            <ul>
                <?php foreach ($cart_items as $item): ?>
                    <li>
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
                                    <button type="submit" name="update_quantity" class="quantity-btn">-</button>
                                </form>
                                <span class="quantity-input"><?= $item['jumlah'] ?></span>
                                <form method="POST" action="" class="d-inline" onsubmit="event.stopPropagation();">
                                    <input type="hidden" name="cart_id" value="<?= $item['id'] ?>">
                                    <input type="hidden" name="action" value="increase">
                                    <button type="submit" name="update_quantity" class="quantity-btn">+</button>
                                </form>
                                <form method="POST" action="" class="d-inline" onsubmit="event.stopPropagation();">
                                    <input type="hidden" name="cart_id" value="<?= $item['id'] ?>">
                                    <button type="submit" name="remove_item" class="remove-btn" title="Hapus">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                        <div class="product-price">
                            <?php if ($item['is_diskon']): ?>
                                <div class="price-container-navbar">
                                    <span class="original-price">Rp<?= number_format($item['harga_asli'], 0, ',', '.') ?></span>
                                    <span class="discounted-price">Rp<?= number_format($item['harga'], 0, ',', '.') ?></span>
                                </div>
                            <?php else: ?>
                                Rp<?= number_format($item['harga'], 0, ',', '.') ?>
                            <?php endif; ?>
                            <div class="item-total">
                                Rp<?= number_format($item['harga'] * $item['jumlah'], 0, ',', '.') ?>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="cart-footer">
                <div class="total-price">Total: Rp<?= number_format($total_price, 0, ',', '.') ?></div>
                <a href="#" class="view-all">Lihat semua</a>
            </div>
        <?php else: ?>
            <div class="empty-cart">Keranjang belanja kosong</div>
        <?php endif; ?>
    </div>

    <div class="container">
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
                                    <button type="submit" name="update_quantity" class="quantity-btn">-</button>
                                </form>
                                <span class="quantity-input"><?= $item['jumlah'] ?></span>
                                <form method="POST" action="" class="d-inline" onsubmit="event.stopPropagation();">
                                    <input type="hidden" name="cart_id" value="<?= $item['id'] ?>">
                                    <input type="hidden" name="action" value="increase">
                                    <button type="submit" name="update_quantity" class="quantity-btn">+</button>
                                </form>
                                <form method="POST" action="" class="d-inline" onsubmit="event.stopPropagation();">
                                    <input type="hidden" name="cart_id" value="<?= $item['id'] ?>">
                                    <button type="submit" name="remove_item" class="remove-btn" title="Hapus">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                        <div class="product-price">
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
            <div class="total-price">Total: Rp<?= number_format($total_price, 0, ',', '.') ?></div>
        </ul>
    </div>

    <!-- Rest of your HTML remains the same -->
    <script>
        const hamburger = document.getElementById("hamburger");
        const sidebar = document.getElementById("sidebar");
        const cartContainer = document.getElementById("cartContainer");
        const cartDropdown = document.getElementById("cartDropdown");
        const searchIcon = document.getElementById("searchIcon");
        const mobileSearch = document.getElementById("mobileSearch");

        // Set cart dropdown tetap terbuka jika ada flag
        <?php if ($keep_cart_open): ?>
            document.addEventListener('DOMContentLoaded', function() {
                cartContainer.classList.add("show");
                cartDropdown.setAttribute("aria-hidden", "false");
            });
        <?php endif; ?>

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
            // Update aria-hidden
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

        // Klik di luar dropdown untuk menutup
        document.addEventListener("click", (e) => {
            // Jangan tutup jika klik berasal dari form dalam cart
            if (e.target.closest('form') && e.target.closest('form').method === 'post') {
                return;
            }

            if (!cartContainer.contains(e.target) && cartContainer.classList.contains("show")) {
                cartContainer.classList.remove("show");
                cartDropdown.setAttribute("aria-hidden", "true");
            }
        });

        // Toggle search bar mobile
        searchIcon.addEventListener("click", () => {
            mobileSearch.classList.toggle("show");
        });
        searchIcon.addEventListener("keydown", (e) => {
            if (e.key === "Enter" || e.key === " ") {
                e.preventDefault();
                mobileSearch.classList.toggle("show");
            }
        });

        // Handle form submission untuk mencegah event bubbling
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.stopPropagation();
            });
        });
    </script>
</body>

</html>