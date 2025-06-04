<?php
session_start();
require './db.php';

// Contoh simulasi username, biasanya dari session login asli
// $_SESSION['username'] = 'AlyaAmanda'; // Uncomment untuk testing login

$username = isset($_SESSION['username']) ? $_SESSION['username'] : null;

// Misalnya $username sudah ada di session untuk navbar
if ($username) {
    $stmt = $conn->prepare("SELECT email, nomor_hp FROM tb_userLogin WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->bind_result($email, $nomor_hp);
    $stmt->fetch();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Makaroni website</title>
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" />
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            overflow-x: hidden;
        }

        /* Container untuk membatasi lebar */
        .container {
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
            padding-left: 20px;
            padding-right: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .navbar {
            background-color: #333;
            color: white;
            padding: 10px 0;
            position: relative;
            z-index: 1000;
        }

        .left-section {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo {
            font-weight: bold;
            font-size: 20px;
        }

        /* Hamburger styling */
        .hamburger {
            width: 30px;
            height: 24px;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            z-index: 1100;
        }

        .hamburger span {
            background: white;
            height: 3px;
            width: 30px;
            border-radius: 2px;
            transition: transform 0.4s ease, opacity 0.4s ease;
            transform-origin: center;
        }

        /* Saat hamburger aktif jadi silang */
        .hamburger.active span:nth-child(1) {
            transform: translateY(10.5px) rotate(45deg);
        }

        .hamburger.active span:nth-child(2) {
            opacity: 0;
        }

        .hamburger.active span:nth-child(3) {
            transform: translateY(-10.5px) rotate(-45deg);
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            height: 100vh;
            background-color: #333;
            padding: 20px;
            transform: translateX(-100%);
            transition: transform 0.3s ease;
            z-index: 999;
            color: white;
        }

        .sidebar.active {
            transform: translateX(0);
        }

        .sidebar .close-btn {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 28px;
            background: none;
            border: none;
            color: white;
            cursor: pointer;
        }

        .sidebar ul {
            list-style: none;
            padding: 0;
            margin-top: 50px;
        }

        .sidebar ul li {
            margin: 15px 0;
        }

        .sidebar ul li a {
            color: white;
            text-decoration: none;
            font-size: 16px;
        }

        /* Search Bar Desktop */
        .search-bar {
            max-width: 400px;
            width: 100%;
        }

        .search-input {
            width: 100%;
            padding: 6px 12px;
            border-radius: 5px;
            border: none;
            font-size: 14px;
        }

        /* Search Icon Mobile */
        .search-icon {
            color: white;
            font-size: 20px;
            cursor: pointer;
            display: none;
        }

        /* Right Section */
        .right-section {
            display: flex;
            align-items: center;
            gap: 15px;
            position: relative;
            flex-wrap: nowrap;
        }

        .right-section a {
            color: white;
            text-decoration: none;
            font-size: 14px;
            position: relative;
            white-space: nowrap;
        }

        /* Cart Icon with Badge - Diperbesar */
        .cart-icon {
            position: relative;
            display: inline-block;
            font-size: 24px;
            /* Ukuran ikon diperbesar */
        }

        .cart-badge {
            position: absolute;
            top: -5px;
            /* Posisi disesuaikan */
            right: -5px;
            /* Posisi disesuaikan */
            background-color: #ff4757;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Dropdown Cart - Diperbesar */
        .cart-container {
            position: relative;
        }

        .cart-dropdown {
            position: absolute;
            top: 50px;
            /* Disesuaikan karena ikon lebih besar */
            right: 10px;
            background: white;
            color: black;
            width: 400px;
            max-width: 90vw;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            padding: 20px;
            display: none;
            z-index: 1001;
            font-size: 16px;
        }

        .cart-dropdown strong {
            font-size: 18px;
            display: block;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .cart-dropdown ul {
            list-style: none;
            padding: 0;
            margin: 0;
            max-height: 350px;
            overflow-y: auto;
        }

        .cart-dropdown ul li {
            border-bottom: 1px solid #eee;
            padding: 12px 0;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .cart-dropdown ul li img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
        }

        .cart-dropdown ul li .product-info {
            flex: 1;
        }

        .cart-dropdown ul li .product-price {
            font-weight: bold;
            white-space: nowrap;
            margin-left: 15px;
        }

        .cart-dropdown ul li:last-child {
            border-bottom: none;
        }

        .cart-dropdown .total-price {
            font-weight: bold;
            font-size: 16px;
            text-align: right;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        .cart-dropdown .view-all {
            display: block;
            text-align: right;
            margin-top: 15px;
            font-size: 14px;
            color: #0066cc;
            text-decoration: none;
        }

        .cart-dropdown .view-all:hover {
            text-decoration: underline;
        }

        .cart-container.show .cart-dropdown {
            display: block;
        }

        /* Search bar Mobile */
        .search-container-mobile {
            display: none;
            background: #f1f1f1;
            padding: 10px 20px;
            animation: slideDown 0.4s ease-in-out;
        }

        .search-container-mobile.show {
            display: block;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .search-bar {
                display: none;
            }

            .search-icon {
                display: block;
            }

            .right-section {
                gap: 10px;
            }

            .cart-dropdown {
                width: 320px;
                max-width: 90vw;
                right: 5vw;
                left: auto;
                margin-left: auto;
                margin-right: auto;
                padding: 15px;
            }

            .cart-dropdown ul li img {
                width: 40px;
                height: 40px;
            }
        }

        @media (min-width: 769px) {
            .search-icon {
                display: none;
            }

            .search-container-mobile {
                display: none !important;
            }
        }
    </style>
</head>

<body>
    <style>
        .sidebar {
            width: 250px;
            background: #333;
            color: white;
            padding: 20px;
            position: fixed;
            height: 100%;
            top: 0;
            left: 0;
            overflow-y: auto;
        }

        .sidebar .close-btn {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            float: right;
            cursor: pointer;
        }

        .sidebar ul {
            list-style-type: none;
            padding: 0;
        }

        .sidebar ul li {
            padding: 10px 0;
        }

        .sidebar ul li .menu-item {
            color: white;
            text-decoration: none;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
        }

        .sidebar ul li .menu-item:hover {
            text-decoration: underline;
        }

        .dropdown-content {
            display: none;
            padding-left: 20px;
        }

        .dropdown-content a {
            display: block;
            padding: 5px 0;
            font-size: 14px;
            color: white;
            text-decoration: none;
        }

        .dropdown-content a:hover {
            text-decoration: underline;
        }

        .show {
            display: block;
        }
    </style>

    <div class="sidebar" id="sidebar" aria-label="Sidebar navigation">
        <button class="close-btn" onclick="toggleSidebar()" aria-label="Close sidebar">×</button>
        <ul>
            <li><a href="#" class="menu-item">Home</a></li>
            <li><a href="#" class="menu-item">About Us</a></li>

            <li>
                <div class="menu-item" onclick="toggleDropdown('produkDropdown', this)">
                    <span>Produk</span>
                    <i class="fa-solid fa-chevron-down"></i>
                </div>
                <div class="dropdown-content" id="produkDropdown">
                    <a href="#">Makaroni</a>
                    <a href="#">Mie Lidi</a>
                    <a href="#">Basreng</a>
                </div>
            </li>

            <li><a href="#" class="menu-item">Keranjang</a></li>

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

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById("sidebar");
            sidebar.style.display = sidebar.style.display === "none" ? "block" : "none";
        }

        function toggleDropdown(id, element) {
            const dropdown = document.getElementById(id);
            dropdown.classList.toggle("show");

            // Toggle icon rotation
            const icon = element.querySelector("i");
            icon.classList.toggle("rotate");
        }
    </script>

    <style>
        .rotate {
            transform: rotate(180deg);
            transition: transform 0.3s ease;
        }
    </style>


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
                <div class="logo" aria-label="Website logo">LOGO</div>
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
                <div class="cart-container" id="cartContainer">
                    <a
                        href="javascript:void(0);"
                        id="cartToggle"
                        aria-label="Toggle cart dropdown"
                        role="button"
                        tabindex="0">
                        <span class="cart-icon">
                            <i class="bi bi-cart3"></i>
                            <span class="cart-badge">5</span>
                        </span>
                    </a>
                    <div class="cart-dropdown" id="cartDropdown" role="menu" aria-hidden="true">
                        <strong>Keranjang (5)</strong>
                        <ul>
                            <li>
                                <img src="https://images.tokopedia.net/img/cache/100-square/VqbcmM/2023/10/13/2d8c73bb-0786-4178-baaa-d40decc9f5a3.jpg" alt="Produk 1" />
                                <div class="product-info">iPhone 15 Pro Max</div>
                                <div class="product-price">1 x Rp3.799.000</div>
                            </li>
                            <li>
                                <img src="https://images.tokopedia.net/img/cache/100-square/VqbcmM/2023/10/13/2d8c73bb-0786-4178-baaa-d40decc9f5a3.jpg" alt="Produk 2" />
                                <div class="product-info">Apple Watch Series 9</div>
                                <div class="product-price">1 x Rp135.000</div>
                            </li>
                            <li>
                                <img src="https://images.tokopedia.net/img/cache/100-square/VqbcmM/2023/10/13/2d8c73bb-0786-4178-baaa-d40decc9f5a3.jpg" alt="Produk 3" />
                                <div class="product-info">MacBook Air M2</div>
                                <div class="product-price">1 x Rp2.985.000</div>
                            </li>
                            <li>
                                <img src="https://images.tokopedia.net/img/cache/100-square/VqbcmM/2023/10/13/2d8c73bb-0786-4178-baaa-d40decc9f5a3.jpg" alt="Produk 4" />
                                <div class="product-info">AirPods Pro 2</div>
                                <div class="product-price">1 x Rp98.000</div>
                            </li>
                            <li>
                                <img src="https://images.tokopedia.net/img/cache/100-square/VqbcmM/2023/10/13/2d8c73bb-0786-4178-baaa-d40decc9f5a3.jpg" alt="Produk 5" />
                                <div class="product-info">iPad Pro 11"</div>
                                <div class="product-price">1 x Rp2.549.000</div>
                            </li>
                        </ul>
                        <div class="total-price">Total: Rp10.566.000</div>
                        <a href="#" class="view-all">Lihat semua</a>
                    </div>
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
                            <a href="./riwayat_pembelian.php" class="menu-item"><i class="bi bi-bag"></i> Pembelian</a>
                            <a href="./pengaturan_akun.php" class="menu-item"><i class="bi bi-gear"></i> Pengaturan</a>
                            <hr>
                            <a href="./logout.php" class="menu-item text-danger"><i class="bi bi-arrow-bar-left"></i> Logout</a>
                        </div>
                    </div>
                <?php else : ?>
                    <a href="./user controller/login.php">Login</a>
                    <a href="./user controller/register.php">Register</a>
                <?php endif; ?>

            </div>
        </div>
    </nav>

    <!-- Search bar Mobile -->
    <div class="search-container-mobile" id="mobileSearch">
        <input type="text" class="search-input" placeholder="Cari..." aria-label="Search mobile" />
    </div>

    <script>
        const hamburger = document.getElementById("hamburger");
        const sidebar = document.getElementById("sidebar");
        const cartContainer = document.getElementById("cartContainer");
        const cartDropdown = document.getElementById("cartDropdown");
        const searchIcon = document.getElementById("searchIcon");
        const mobileSearch = document.getElementById("mobileSearch");

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
    </script>
</body>

</html>