<?php
session_start();
require '../db.php';

$username = isset($_SESSION['username']) ? $_SESSION['username'] : null;
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

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
    <title>Produk - Noorden Website</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="icon" href="../img/logo/icon web.svg">
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
                        <a href="product.php?kategori=<?= urlencode($row['nama_kategori']) ?>">
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
            <li>
                <!-- darkmode -->
                <link rel="stylesheet" href="../css/darkmode.css">
                <div class="container">
                    <div onclick="darkmode()">
                        <i class="bi bi-moon"></i> darkmode
                    </div>
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
                <a class="navbar-brand logo text-white" href="../home.php"><img src="../img/logo/logo.svg" height="40px"></a>
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
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <div class="total-price">Total: Rp<?= number_format($total_price, 0, ',', '.') ?></div>
                            <a href="../cart/cart.php" class="view-all">Lihat semua</a>
                        <?php else: ?>
                            <div class="empty-cart">Keranjang belanja kosong</div>
                        <?php endif; ?>
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
        <!-- Search bar Mobile -->
        <div class="search-container-mobile" id="mobileSearch">
            <input type="text" class="search-input" placeholder="Cari..." aria-label="Search mobile" />
        </div>
    </nav>

    <!-- cart dropdown -->
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

    <!-- css cart -->
    <style>
        .cart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 1rem;
            border-bottom: 1px solid #eee;
        }

        .cart-footer {
            padding: 0.5rem 1rem;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .item-total {
            font-weight: bold;
            margin-top: 0.25rem;
        }
    </style>

    <!-- konten -->
    <div class="container-fluid">
        <style>
            .sidebar-etalase {
                min-height: 100vh;
                background-color: #f8f9fa;
                border-right: 1px solid #dee2e6;
            }

            .sidebar-etalase .nav-link {
                color: #333;
                border-radius: 5px;
                margin-bottom: 5px;
            }

            .sidebar-etalase .nav-link:hover,
            .sidebar-etalase .nav-link.active {
                background-color: #0d6efd;
                color: white;
            }

            .sidebar-etalase .nav-link i {
                margin-right: 10px;
            }

            .content-area {
                padding: 20px;
            }

            .product-card {
                margin-bottom: 20px;
                border: 1px solid #ddd;
                border-radius: 5px;
                padding: 15px;
            }

            .product-image-container {
                height: 180px;
                overflow: hidden;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .product-image {
                max-height: 100%;
                max-width: 100%;
                object-fit: contain;
            }

            .original-price {
                text-decoration: line-through;
                color: #6c757d;
                font-size: 0.8rem;
                margin-right: 5px;
            }

            .discounted-price {
                color: #dc3545;
                font-weight: bold;
            }
        </style>

        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block sidebar-etalase collapse " id="sidebarMenu">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <?php
                        $etalaseQuery = "SELECT 
                    etalase_toko, 
                    COUNT(*) as count 
                 FROM tb_adminProduct 
                 WHERE etalase_toko IS NOT NULL 
                 AND etalase_toko != '' 
                 AND stok = 'tersedia'
                 GROUP BY etalase_toko";
                        $etalaseResult = $conn->query($etalaseQuery);

                        // Hitung total etalase unik
                        $totalProduk = $etalaseResult->num_rows;
                        ?>

                        <li class="nav-item">
                            <a class="nav-link active" href="#" data-target="all-products">
                                <i class="fas fa-store"></i> Etalase toko (<?= $totalProduk ?>)
                            </a>
                        </li>

                        <?php
                        $etalaseCounter = 1;
                        while ($etalase = $etalaseResult->fetch_assoc()):
                            // Hitung jumlah produk per etalase
                            $countQuery = "SELECT COUNT(*) as count FROM tb_adminProduct WHERE etalase_toko = ? AND stok = 'tersedia'";
                            $stmt = $conn->prepare($countQuery);
                            $stmt->bind_param("s", $etalase['etalase_toko']);
                            $stmt->execute();
                            $countResult = $stmt->get_result();
                            $count = $countResult->fetch_assoc()['count'];
                        ?>
                            <li class="nav-item ms-2">
                                <a class="nav-link" href="#" data-target="etalase<?= $etalaseCounter ?>">
                                    <i class="fas fa-box-open"></i> <?= htmlspecialchars($etalase['etalase_toko']) ?> (<?= $count ?>)
                                </a>
                            </li>
                        <?php
                            $etalaseCounter++;
                        endwhile;
                        ?>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 content-area">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3">
                    <button class="btn btn-primary d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu">
                        <i class="fas fa-bars"></i> Menu
                    </button>
                </div>

                <!-- Konten Semua Produk -->
                <div id="all-products" class="content-section">
                    <h2 class="showcase-title">Semua Produk</h2>
                    <div class="row">
                        <?php
                        $sql = "SELECT * FROM tb_adminProduct WHERE stok = 'tersedia'";
                        $result = $conn->query($sql);

                        while ($row = $result->fetch_assoc()): ?>
                            <div class="col-6 col-md-4 col-lg-3 mb-3">
                                <div class="card product-card h-100 border-0 shadow-sm">
                                    <a href="detail-product.php?id=<?= $row['id'] ?>">
                                        <div class="product-image-container ratio ratio-1x1 overflow-hidden">
                                            <img src="../../admin/uploads/<?= htmlspecialchars($row['foto_thumbnail'] ?? 'default.jpg') ?>"
                                                class="w-100 h-100 object-fit-contain p-1"
                                                alt="<?= htmlspecialchars($row['nama_produk']) ?>">
                                        </div>
                                    </a>

                                    <div class="card-body p-2">
                                        <h6 class="card-title mb-1" style="font-size: 0.9rem;">
                                            <a href="detail-product.php?id=<?= $row['id'] ?>" class="text-decoration-none product-title">
                                                <?= htmlspecialchars($row['nama_produk']) ?>
                                            </a>
                                        </h6>

                                        <p class="card-text small text-muted mb-1" style="font-size: 0.75rem;">
                                            <?= htmlspecialchars($row['detail']) ?>
                                        </p>

                                        <div class="mb-1">
                                            <?php if ($row['is_diskon'] && $row['harga_diskon'] > 0): ?>
                                                <small class="discount-price text-decoration-line-through ">Rp<?= number_format($row['harga'], 0, ',', '.') ?></small><br>
                                                <span class="text-success fw-semibold small">Rp<?= number_format($row['harga_diskon'], 0, ',', '.') ?></span>
                                            <?php else: ?>
                                                <span class="text-success fw-semibold small">Rp<?= number_format($row['harga'], 0, ',', '.') ?></span>
                                            <?php endif; ?>
                                        </div>

                                        <?php
                                        $product_id = $row['id'];
                                        $varianQuery = "SELECT id, varian FROM tb_varian_product WHERE product_id = $product_id AND stok > 0";
                                        $varianResult = $conn->query($varianQuery);
                                        if ($varianResult->num_rows > 0): ?>
                                            <select class="form-select form-select-sm mb-2 varian-select" data-product-id="<?= $product_id ?>">
                                                <option value="">Varian</option>
                                                <?php while ($v = $varianResult->fetch_assoc()): ?>
                                                    <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['varian']) ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                        <?php endif; ?>
                                    </div>

                                    <div class="card-footer border-top-0 p-0">
                                        <button type="button" class="btn btn-sm btn-outline-primary w-100 add-to-cart-btn"
                                            data-product-id="<?= $product_id ?>"
                                            <?= $varianResult->num_rows > 0 ? 'data-has-varian="true"' : 'data-has-varian="false"' ?>>
                                            <i class="fas fa-cart-plus"></i> Keranjang
                                        </button>
                                    </div>
                                </div>
                            </div>

                        <?php endwhile; ?>
                    </div>
                </div>

                <!-- Konten per Etalase -->
                <?php
                // Reset pointer hasil query
                $etalaseResult->data_seek(0);
                $etalaseCounter = 1;

                while ($etalase = $etalaseResult->fetch_assoc()):
                    $productsQuery = "SELECT * FROM tb_adminProduct WHERE etalase_toko = ? AND stok = 'tersedia'";
                    $stmt = $conn->prepare($productsQuery);
                    $stmt->bind_param("s", $etalase['etalase_toko']);
                    $stmt->execute();
                    $productsResult = $stmt->get_result();
                ?>
                    <div id="etalase<?= $etalaseCounter ?>" class="content-section" style="display: none;">
                        <h2 class="showcase-title"><?= htmlspecialchars($etalase['etalase_toko']) ?></h2>
                        <div class="row">
                            <?php while ($product = $productsResult->fetch_assoc()): ?>
                                <div class="col-6 col-md-4 col-lg-3 mb-3">
                                    <div class="card product-card h-100 border-0 shadow-sm">
                                        <!-- Gambar produk -->
                                        <a href="detail-product.php?id=<?= $product['id'] ?>">
                                            <div class="product-image-container">
                                                <img src="../../admin/uploads/<?= htmlspecialchars($product['foto_thumbnail'] ?? 'default.jpg') ?>"
                                                    class="card-img-top w-100 h-100 object-fit-contain p-1" alt="<?= htmlspecialchars($product['nama_produk']) ?>">
                                            </div>
                                        </a>

                                        <div class="card-body">
                                            <!-- Nama produk -->
                                            <h5 class="card-title">
                                                <a href="detail-product.php?id=<?= $product['id'] ?>" class="text-decoration-none product-title">
                                                    <?= htmlspecialchars($product['nama_produk']) ?>
                                                </a>
                                            </h5>

                                            <!-- Detail produk -->
                                            <p class="card-text small text-muted">
                                                <?= htmlspecialchars($product['detail']) ?>
                                            </p>

                                            <!-- Harga produk -->
                                            <div class="mb-2">
                                                <?php if ($product['is_diskon'] && $product['harga_diskon'] > 0): ?>
                                                    <span class="original-price">Rp<?= number_format($product['harga'], 0, ',', '.') ?></span>
                                                    <span class="discounted-price">Rp<?= number_format($product['harga_diskon'], 0, ',', '.') ?></span>
                                                <?php else: ?>
                                                    <span class="text-success fw-bold">Rp<?= number_format($product['harga'], 0, ',', '.') ?></span>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Varian produk -->
                                            <?php
                                            $product_id = $product['id'];
                                            $varianQuery = "SELECT id, varian FROM tb_varian_product WHERE product_id = $product_id AND stok > 0";
                                            $varianResult = $conn->query($varianQuery);
                                            if ($varianResult->num_rows > 0): ?>
                                                <select class="form-select form-select-sm mb-2 varian-select" data-product-id="<?= $product_id ?>">
                                                    <option value="">Pilih varian</option>
                                                    <?php while ($v = $varianResult->fetch_assoc()): ?>
                                                        <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['varian']) ?></option>
                                                    <?php endwhile; ?>
                                                </select>
                                            <?php endif; ?>
                                        </div>

                                        <div class="card-footer border-top-0 p-0">
                                            <!-- Tombol keranjang -->
                                            <button type="button" class="btn btn-sm btn-outline-primary w-100 add-to-cart-btn"
                                                data-product-id="<?= $product_id ?>"
                                                <?= $varianResult->num_rows > 0 ? 'data-has-varian="true"' : 'data-has-varian="false"' ?>>
                                                <i class="fas fa-cart-plus"></i> Keranjang
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                <?php
                    $etalaseCounter++;
                endwhile;
                ?>
            </main>

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
        </div>

        <script>
            // Fungsi untuk menangani klik pada menu sidebar
            document.querySelectorAll('.nav-link').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();

                    // Hapus kelas active dari semua link
                    document.querySelectorAll('.nav-link').forEach(el => {
                        el.classList.remove('active');
                    });

                    // Tambahkan kelas active ke link yang diklik
                    this.classList.add('active');

                    // Sembunyikan semua konten
                    document.querySelectorAll('.content-section').forEach(section => {
                        section.style.display = 'none';
                    });

                    // Tampilkan konten yang sesuai
                    const target = this.getAttribute('data-target');
                    document.getElementById(target).style.display = 'block';
                });
            });

            // Fungsi untuk menangani tombol keranjang
            document.querySelectorAll('.add-to-cart-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const productId = this.getAttribute('data-product-id');
                    const hasVariant = this.getAttribute('data-has-varian') === 'true';

                    if (hasVariant) {
                        const variantSelect = this.closest('.card').querySelector('.varian-select');
                        const variantId = variantSelect.value;



                        // Tambahkan ke keranjang dengan varian
                        addToCart(productId, variantId);
                    } else {
                        // Tambahkan ke keranjang tanpa varian
                        addToCart(productId);
                    }
                });
            });

            function addToCart(productId, variantId = null) {
                // Implementasi AJAX untuk menambahkan ke keranjang
                console.log('Menambahkan ke keranjang:', {
                    productId,
                    variantId
                });
            }
        </script>
    </div>

    <!-- footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container">
            <div class="row">
                <!-- Logo -->
                <div class="col-md-2 offset-md-1 mb-3">
                    <div class="fw-bold"><img src="../img/logo/logo.svg" height="50px"></div>
                </div>

                <!-- Menu Navigasi -->
                <div class="col-md-2 mb-3">
                    <h5>Menu</h5>
                    <ul class="list-unstyled">
                        <li><a href="home.php" class="text-white text-decoration-none">Home</a></li>
                        <li><a href="../products/product.php" class="text-white text-decoration-none">Produk</a></li>
                        <li><a href="../web/aboutUs.php" class="text-white text-decoration-none">About Us</a></li>
                        <li><a href="../web/blog.php" class="text-white text-decoration-none">Blog</a></li>
                        <li><a href="../web/contactUs.php" class="text-white text-decoration-none">Contact Us</a></li>
                    </ul>
                </div>

                <!-- Sosial Media -->
                <div class="col-md-3 mb-3">
                    <h5>Social Media</h5>
                    <a href="#" class="text-white me-3"><i class="bi bi-instagram fs-4"></i></a>
                    <a href="#" class="text-white me-3"><i class="bi bi-tiktok fs-4"></i></a>
                    <a href="#" class="text-white"><i class="bi bi-facebook fs-4"></i></a>
                </div>

                <!-- Marketplace -->
                <div class="col-md-2 mb-3">
                    <h5>Marketplace</h5>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-white text-decoration-none">Shopee</a></li>
                        <li><a href="#" class="text-white text-decoration-none">Tokopedia</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </footer>

    <!-- script navbar -->
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

        // Fungsi untuk menampilkan modal varian
        function showVarianModal(productId, errorMessage = null) {
            modalProductId.value = productId;
            modalErrorMessage.textContent = errorMessage || '';

            // Kosongkan select terlebih dahulu
            modalVarianSelect.innerHTML = '<option value="">Pilih varian</option>';

            // Ambil varian dari select yang sesuai dengan productId
            const originalSelect = document.querySelector(`.varian-select[data-product-id="${productId}"]`);
            if (originalSelect) {
                // Clone semua option dari select asli ke modal select
                const options = originalSelect.querySelectorAll('option');
                options.forEach(option => {
                    if (option.value) { // Skip option pertama yang kosong
                        const newOption = document.createElement('option');
                        newOption.value = option.value;
                        newOption.textContent = option.textContent;
                        modalVarianSelect.appendChild(newOption);
                    }
                });
            }

            varianModal.style.display = "block";
        }

        // Fungsi untuk menutup modal
        function closeVarianModal() {
            varianModal.style.display = "none";
        }

        // Event listener untuk tombol close modal
        closeModal.addEventListener("click", closeVarianModal);

        // Tutup modal ketika klik di luar modal
        window.addEventListener("click", (e) => {
            if (e.target === varianModal) {
                closeVarianModal();
            }
        });

        // Handle submit form varian
        varianForm.addEventListener("submit", function(e) {
            e.preventDefault();
            if (!modalVarianSelect.value) {
                modalErrorMessage.textContent = "Silakan pilih varian terlebih dahulu";
                return;
            }
            this.submit();
        });

        // Handle klik tombol add to cart
        document.querySelectorAll('.add-to-cart-btn').forEach(button => {
            button.addEventListener('click', function() {
                const productId = this.getAttribute('data-product-id');
                const hasVarian = this.getAttribute('data-has-varian') === 'true';

                if (hasVarian) {
                    // Cek apakah varian sudah dipilih
                    const varianSelect = document.querySelector(`.varian-select[data-product-id="${productId}"]`);
                    if (!varianSelect || !varianSelect.value) {
                        // Tampilkan modal untuk memilih varian
                        showVarianModal(productId, "Silakan pilih varian terlebih dahulu");
                        return;
                    }

                    // Jika varian sudah dipilih, submit form
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

                    const addToCartInput = document.createElement('input');
                    addToCartInput.type = 'hidden';
                    addToCartInput.name = 'add_to_cart';
                    addToCartInput.value = '1';
                    form.appendChild(addToCartInput);

                    document.body.appendChild(form);
                    form.submit();
                } else {
                    // Jika produk tidak memiliki varian, langsung submit
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '';

                    const productIdInput = document.createElement('input');
                    productIdInput.type = 'hidden';
                    productIdInput.name = 'product_id';
                    productIdInput.value = productId;
                    form.appendChild(productIdInput);

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

    <script src="../js/darkmode.js"></script>
</body>

</html>