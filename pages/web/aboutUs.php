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
    <title>About Us - Noorden Website</title>
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

    <!-- AOS -->
    <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css" />
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
                        <a href="../products/product.php?kategori=<?= urlencode($row['nama_kategori']) ?>">
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
                    <div onclick="darkmode()" style="cursor: pointer;">
                        <i class="bi bi-moon"></i> darkmode
                    </div>
                </div>
            </li>
        </ul>
    </div>

    <!-- Navbar -->
    <nav class="navbar" role="navigation" aria-label="Main navigation" style="position: sticky; top:0;">
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
        <!-- Search bar Mobile -->
        <div class="search-container-mobile" id="mobileSearch">
            <input type="text" class="search-input" placeholder="Cari..." aria-label="Search mobile" />
        </div>
    </nav>

    <!-- breadcrumb -->
    <div class="container mt-5 pt-5">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../home.php" class="text-decoration-none">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Tentang kami</li>
            </ol>
        </nav>
    </div>

    <div class="container mt-5">
        <div class="wrapper mb-5 pb-5">
            <div class="text-center">
                <h1 class="about-us fw-bold" data-aos="fade-down" data-aos-duration="1800">Tentang Kami</h1>
            </div>
            <div class="about-section">
                <div>
                    <h2 class="fw-bold about-section-title" data-aos="fade-right" data-aos-duration="2000">Filosofi kami</h2>
                    <p class="typing-text-filosofi" id="typing-text" style="font-size: 25px;"></p>
                    <script>
                        const text = "Di Noorden, kami percaya bahwa parfum bukan sekadar wewangian—ia adalah ekspresi diri. Remaja adalah masa pencarian jati diri, eksplorasi, dan keberanian untuk tampil beda. Itulah mengapa Noorden hadir untuk menemani setiap langkahmu dalam menemukan identitas melalui aroma yang unik, segar, dan berkarakter.";
                        const target = document.getElementById("typing-text");

                        let i = 0;

                        function typeWriter() {
                            if (i < text.length) {
                                target.innerHTML += text.charAt(i);
                                i++;
                                setTimeout(typeWriter, 25); // bisa diubah kecepatannya
                            } else {
                                target.style.borderRight = "none"; // hilangkan garis setelah selesai
                            }
                        }

                        window.onload = typeWriter;
                    </script>
                </div>
                <div class="logo-img">
                    <img src="../img/logo/logo aboutUs.svg" alt="Tentang Kami">
                </div>
            </div>
        </div>
        <style>
            .about-us {
                position: relative;
                display: inline-block;
                cursor: pointer;
                text-align: center;
                /* Memastikan teks tetap di tengah */
            }

            .about-us::after {
                content: '';
                position: absolute;
                width: 0;
                height: 3px;
                /* Ketebalan underline */
                bottom: -5px;
                /* Jarak underline dari teks */
                left: 50%;
                /* Mulai dari tengah */
                transform: translateX(-50%);
                /* Pusatkan underline */
                background-color: currentColor;
                /* Warna sama dengan teks */
                transition: width 0.3s ease-in-out;
            }

            .about-us:hover::after {
                width: 100%;
                /* Lebar penuh saat hover */
                left: 0;
                /* Mulai dari kiri saat hover */
                transform: translateX(0);
                /* Hapus transform saat hover */
            }

            .about-section {
                display: grid;
                grid-template-columns: 1fr 1fr;
                align-items: center;
                gap: 40px;
                padding: 40px 20px;
            }

            .about-section img {
                max-width: 100%;
                height: auto;
            }

            @media (max-width: 768px) {
                .about-section {
                    grid-template-columns: 1fr;
                    text-align: center;
                }
            }

            .text-filosofi {
                border-right: 2px solid #000;
                overflow: hidden;
                display: inline-block;
                font-size: 1rem;
                font-size: 20px;
            }

            .logo-img:hover {
                filter: drop-shadow(1px 1px 20px #cfcd00);
                transition: .6s;
            }
        </style>
    </div>

    <div class="container">
        <div class="text-center">
            <h2 class="fw-bold my-4 about-section-title" data-aos="fade-down" data-aos-duration="1500">Apa yang kami tawarkan?</h2>
        </div>
        <div class="slider-wrapper">
            <div class="slider-track" id="sliderTrack">
                <div class="slider-item" style="background-image: url('https://via.placeholder.com/900x400/00a65a/ffffff?text=Produk+Premium');">
                    <div class="item-title">Produk Premium</div>
                    <div class="item-overlay">
                        <a href="#" onclick="toggleDetail(event, this)" class="btn btn-light">Lihat Detail</a>
                    </div>
                    <div class="item-detail">
                        <h3 class="fw-bold">Produk Premium</h3>
                        <p style="font-family: Arial, Helvetica, sans-serif; font-size: 25px;">Hanya bahan terbaik yang kami gunakan untuk menghasilkan produk dengan kualitas premium.</p>
                    </div>
                </div>

                <div class="slider-item" style="background-image: url('https://via.placeholder.com/900x400/f39c12/ffffff?text=Harga+Terjangkau');">
                    <div class="item-title">Harga Terjangkau</div>
                    <div class="item-overlay">
                        <a href="#" onclick="toggleDetail(event, this)" class="btn btn-light">Lihat Detail</a>
                    </div>
                    <div class="item-detail">
                        <h3 class="fw-bold">Harga Terjangkau</h3>
                        <p style="font-family: Arial, Helvetica, sans-serif; font-size: 25px;">Kami percaya bahwa kualitas terbaik tidak harus mahal. Nikmati produk hebat dengan harga bersahabat.</p>
                    </div>
                </div>

                <div class="slider-item" style="background-image: url('https://via.placeholder.com/900x400/3c8dbc/ffffff?text=Aroma+Yang+Mengesankan');">
                    <div class="item-title">Aroma Mengesankan</div>
                    <div class="item-overlay">
                        <a href="#" onclick="toggleDetail(event, this)" class="btn btn-light">Lihat Detail</a>
                    </div>
                    <div class="item-detail">
                        <h3 class="fw-bold">Aroma Mengesankan</h3>
                        <p style="font-family: Arial, Helvetica, sans-serif; font-size: 25px;">Kami menyajikan produk dengan aroma yang khas dan menggugah selera, membuat setiap momen lebih istimewa.</p>
                    </div>
                </div>
            </div>

            <button class="nav-btn prev" onclick="goPrev()">❮</button>
            <button class="nav-btn next" onclick="goNext()">❯</button>
        </div>
        <style>

            /* Untuk container penengah */
            .text-center {
                text-align: center;
            }

            /* Style untuk judul section */
            .about-section-title {
                position: relative;
                display: inline-block;
                cursor: pointer;
            }

            /* Animasi underline */
            .about-section-title::after {
                content: '';
                position: absolute;
                width: 0;
                height: 2px;
                /* Lebih tipis dari h1 */
                bottom: -3px;
                /* Jarak lebih dekat dari h1 */
                left: 0;
                background-color: currentColor;
                transition: width 0.25s ease-out;
                /* Animasi lebih cepat */
            }

            .about-section-title:hover::after {
                width: 100%;
            }

            .slider-wrapper {
                position: relative;
                margin: auto;
                overflow: hidden;
                border-radius: 20px;
                box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            }

            .slider-track {
                display: flex;
                transition: transform 0.5s ease-in-out;
            }

            .slider-item {
                min-width: 100%;
                height: 500px;
                background-size: cover;
                background-position: center;
                position: relative;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
            }

            .item-title {
                font-size: 2rem;
                font-weight: bold;
                color: #bfa75c;
                text-shadow: 0 1px 3px rgba(0, 0, 0, 0.5);
            }

            .item-title:hover {
                filter: drop-shadow(1px 1px 20px #cfcd00);
            }

            .item-overlay {
                position: absolute;
                bottom: 30px;
                z-index: 10;
            }

            .item-detail {
                height: 100%;
                background-color: #cfcd00;

                /* biar lebih elegan */
                color: white;
                position: absolute;
                bottom: -100%;
                left: 0;
                width: 100%;
                padding: 20px;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                /* tengahin vertikal */
                text-align: center;
                transition: bottom 0.4s ease;
            }


            .slider-item.show-detail .item-detail {
                bottom: 0;
            }

            .nav-btn {
                position: absolute;
                top: 75%;
                transform: translateY(-50%);
                font-size: 2rem;
                background: rgba(0, 0, 0, 0.4);
                color: white;
                border: none;
                cursor: pointer;
                padding: 10px 15px;
                z-index: 20;
            }

            .nav-btn:hover {
                background: rgba(0, 0, 0, 0.7);
            }

            .prev {
                left: 10px;
            }

            .next {
                right: 10px;
            }
        </style>

        <script>
            let currentIndex = 0;
            const totalSlides = document.querySelectorAll('.slider-item').length;
            const track = document.getElementById('sliderTrack');

            function goNext() {
                currentIndex = (currentIndex + 1) % totalSlides;
                updateSlide();
            }

            function goPrev() {
                currentIndex = (currentIndex - 1 + totalSlides) % totalSlides;
                updateSlide();
            }

            function updateSlide() {
                track.style.transform = `translateX(-${currentIndex * 100}%)`;
            }

            function toggleDetail(e, btn) {
                e.preventDefault();
                const slide = btn.closest('.slider-item');
                const isShown = slide.classList.toggle('show-detail');
                btn.textContent = isShown ? 'Tutup Detail' : 'Lihat Detail';
            }
        </script>
    </div>

    <section class="container visi-misi mt-5">
        <h1 class="fw-bold about-section-title" data-aos="fade-up" data-aos-duration="1500">Visi dan Misi</h1>
        <style>
            .visi-misi {
                margin: 2rem auto;
                padding: 2rem;
                border-radius: 12px;
                box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
                font-family: 'Inter', sans-serif;
                color: #222;
                display: flex;
                flex-direction: column;
                gap: 3.5rem;
            }

            .section {
                display: flex;
                flex-wrap: wrap;
                gap: 2rem;
                /* Increased gap for better spacing */
            }

            .section-top {
                padding-bottom: 1rem;
                align-items: flex-start;
                justify-content: space-between;
            }

            .section-bottom {
                padding-top: 1.25rem;
                justify-content: space-between;
                align-items: flex-start;
                gap: 2rem;
                /* Consistent gap with top section */
            }

            .section-left,
            .section-right {
                flex: 1 1 200px;
                display: flex;
            }

            .section-title {
                font-weight: 700;
                font-size: 1.3rem;
                min-width: 120px;
                border-top: 2px solid #1e40af;
                padding-top: 0.5rem;
                /* Increased padding */
                margin-bottom: 1.5rem;
                /* Simplified margin */
                align-self: flex-start;
                color: #1e40af;
                padding-right: 1rem;
                /* Added right padding */
            }

            .section-content {
                flex-direction: column;
                gap: 1rem;
                /* Increased gap */
                flex: 1;
                padding-left: 0.5rem;
                /* Added left padding */
            }

            .section-list {
                list-style: disc;
                padding-left: 1.5rem;
                /* Increased padding */
                margin: 0;
                font-size: 1.1rem;
                color: #444;
                line-height: 1.6;
                /* Better line spacing */
            }

            .section-list li {
                position: relative;
                margin-bottom: 0.5rem;
                /* Space between items */
            }

            @media (max-width: 767px) {
                .visi-misi {
                    margin: 1rem;
                    padding: 1.5rem;
                    gap: 2.5rem;
                    /* Increased vertical gap */
                }

                .section {
                    flex-direction: column;
                    gap: 1.5rem;
                }

                .section-top,
                .section-bottom {
                    gap: 1.5rem;
                    padding: 0;
                }

                .section-bottom {
                    flex-direction: column-reverse;
                }

                .section-left,
                .section-right {
                    flex: 1 1 auto;
                    width: 100%;
                    padding: 0;
                }

                .section-title {
                    border-top: none;
                    border-left: 3px solid #1e40af;
                    padding: 0 0 0 0.75rem;
                    margin: 0 0 1rem 0;
                    min-width: auto;
                }

                .section-content {
                    width: 100%;
                    padding-left: 0.25rem;
                    /* Adjusted mobile padding */
                }

                .section-list {
                    padding-left: 1.25rem;
                    /* Adjusted for mobile */
                }
            }

            /* Additional small device optimization */
            @media (max-width: 480px) {
                .visi-misi {
                    padding: 1.25rem;
                    margin: 0.75rem;
                }

                .section-list {
                    font-size: 1rem;
                    /* Slightly smaller text on very small devices */
                }
            }
        </style>
        <!-- Top Section -->
        <div class="section section-top" aria-labelledby="visi-label">
            <div class="section-left section-title fw-bold" id="visi-label" data-aos="fade-right" data-aos-duration="1500">Visi</div>
            <div class="section-right section-content" role="list" aria-label="Daftar visi">
                <ol class="section-list visi-misi-list" data-aos="fade-left" data-aos-duration="1500">
                    <li role="listitem">Membuat produk makanan lokal menjadi diminati dan dapat bersaing dipasar lokal hingga global</li>
                </ol>
            </div>
        </div>

        <!-- Bottom Section -->
        <div class="section section-bottom" aria-labelledby="misi-label">
            <div class="section-left section-content" role="list" aria-label="Daftar misi">
                <ul class="section-list visi-misi-list">
                    <li role="listitem"  data-aos="fade-right" data-aos-duration="2000">Terus melakukan inovasi ke produk</li>
                    <li role="listitem"  data-aos="fade-right" data-aos-duration="2500">Memperkenalkan kembali produk ke masyarakat dan dunia</li>
                    <li role="listitem"  data-aos="fade-right" data-aos-duration="3000">Mengikuti tren perkembangan zaman</li>
                </ul>
            </div>
            <div class="section-right section-title fw-bold" id="misi-label" data-aos="fade-left" data-aos-duration="1500">Misi</div>
        </div>
    </section>

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
                        <li><a href="../home.php" class="text-white text-decoration-none">Home</a></li>
                        <li><a href="../products/product.php" class="text-white text-decoration-none">Produk</a></li>
                        <li><a href="../web/aboutUs.php" class="text-white text-decoration-none">About Us</a></li>
                        <li><a href="../web/blog.php" class="text-white text-decoration-none">Blog</a></li>
                        <li><a href="../web/contactUs.php" class="text-white text-decoration-none">Contact Us</a></li>
                    </ul>
                </div>

                <!-- Sosial Media -->
                <div class="col-md-3 mb-3">
                    <h5>Social Media</h5>
                    <a href="https://www.instagram.com/noorden_parfum?igsh=cGtmcm92aGU0M2k0" class="text-white me-3"><i class="bi bi-instagram fs-4"></i></a>
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

    <script src="../js/darkmode.js"></script>

    <!-- script navbar & sidebar hamburger -->
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

    <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
    <script>
        AOS.init();
    </script>
</body>

</html>