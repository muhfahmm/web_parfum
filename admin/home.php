<?php
session_start();
require 'db.php'
// Check if user is logged in, if not redirect to login page
// if (!isset($_SESSION['admin_logged_in'])) {
//     header("Location: login.php");
//     exit();
// }
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Dashboard Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #212529;
            color: #fff;
        }

        .card-custom {
            border: none;
            border-radius: 15px;
            padding: 20px;
            transition: 0.3s;
        }

        .card-custom:hover {
            transform: scale(1.03);
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: #0d6efd;">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Dashboard Admin</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link active" href="#">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="category.php">Category</a></li>
                    <li class="nav-item"><a class="nav-link" href="product.php">Product</a></li>
                    <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">🌞</a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#">Light</a></li>
                            <li><a class="dropdown-item" href="#">Dark</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mt-5">
        <div class="mb-4">
            <i class="bi bi-house-door-fill"></i> Home
        </div>
        <h2 class="mb-4">Selamat Datang Admin <b><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></b></h2>

        <div class="row g-4">
            <!-- Kategori -->
            <div class="col-md-4">
                <div class="card-custom bg-primary text-white text-center">
                    <h4>Category</h4>
                    <p class="fs-4">
                        <?php
                        $kategori = mysqli_query($conn, "SELECT COUNT(*) AS total FROM tb_adminCategory");
                        if ($kategori) {
                            $jumlahKategori = mysqli_fetch_assoc($kategori);
                            echo $jumlahKategori['total'] . ' kategori';
                        } else {
                            echo "Error: " . mysqli_error($conn);
                        }
                        ?>
                    </p>
                    <a href="category.php" class="text-white text-decoration-none">
                        <i class="bi bi-eye"></i> lihat detail
                    </a>
                </div>
            </div>

            <!-- Produk -->
            <div class="col-md-4">
                <div class="card-custom bg-success text-white text-center">
                    <h4>Product</h4>
                    <p class="fs-4">
                        <?php
                        $produk = mysqli_query($conn, "SELECT COUNT(*) AS total FROM tb_adminProduct");
                        if ($produk) {
                            $jumlahProduk = mysqli_fetch_assoc($produk);
                            echo $jumlahProduk['total'] . ' produk';
                        } else {
                            echo "Error: " . mysqli_error($conn);
                        }
                        ?>
                    </p>
                    <a href="product.php" class="text-white text-decoration-none">
                        <i class="bi bi-eye"></i> lihat detail
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>

<?php
// Close database connection
mysqli_close($conn);
?>