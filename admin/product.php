<?php
require 'db.php';

// Handle penghapusan produk jika ada parameter delete_id
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];

    // Hapus varian produk terkait
    $conn->query("DELETE FROM tb_varian_product WHERE product_id = $delete_id");

    // Hapus produk utama
    $conn->query("DELETE FROM tb_adminProduct WHERE id = $delete_id");

    // Redirect agar tidak terjadi penghapusan berulang saat refresh
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit;
}

$produk_query = $conn->query("SELECT p.*, c.nama_kategori FROM tb_adminProduct p 
LEFT JOIN tb_adminCategory c ON p.category_id = c.id 
ORDER BY p.id DESC");
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Daftar Produk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .product-images {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }

        .product-images img {
            border: 1px solid #ddd;
            border-radius: 4px;
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
    <div class="container mt-5">
        <h3 class="mb-3">Daftar Produk</h3>
        <a href="addProduct.php" class="btn btn-primary mb-3">Tambah Produk</a>

        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Produk</th>
                        <th>Harga</th>
                        <th>Kategori</th>
                        <th>Etalase Toko</th>
                        <th>Detail</th>
                        <th>Foto Thumbnail</th>
                        <th>Foto Produk</th>
                        <th>Varian</th>
                        <th>Jumlah Stok</th>
                        <th>Status Stok</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($produk_query->num_rows > 0): ?>
                        <?php $no = 1;
                        while ($produk = $produk_query->fetch_assoc()): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= htmlspecialchars($produk['nama_produk']) ?></td>
                                <td>
                                    <?php if ($produk['is_diskon'] == 1 && $produk['harga_diskon'] > 0): ?>
                                        <span style="text-decoration: line-through; color: #888;">
                                            Rp<?= number_format($produk['harga'], 0, ',', '.') ?>
                                        </span>
                                        <br>
                                        <span class="text-danger fw-bold">
                                            Rp<?= number_format($produk['harga_diskon'], 0, ',', '.') ?>
                                        </span>
                                    <?php else: ?>
                                        Rp<?= number_format($produk['harga'], 0, ',', '.') ?>
                                    <?php endif; ?>
                                </td>

                                <td><?= htmlspecialchars($produk['nama_kategori']) ?></td>
                                <td><?= htmlspecialchars($produk['etalase_toko']) ?></td>
                                <td><?= htmlspecialchars($produk['detail']) ?></td>
                                <td>
                                    <?php if ($produk['foto_thumbnail']): ?>
                                        <img src="uploads/<?= htmlspecialchars($produk['foto_thumbnail']) ?>" width="60" alt="Thumbnail">
                                    <?php else: ?>
                                        <span class="text-muted">Tidak ada</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $foto_produk = json_decode($produk['foto_produk'] ?? '[]', true);

                                    if (is_array($foto_produk) && count($foto_produk) > 0): ?>
                                        <div class="product-images">
                                            <?php foreach ($foto_produk as $foto): ?>
                                                <img src="uploads/<?= htmlspecialchars($foto) ?>" width="60" alt="Foto Produk">
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">Tidak ada foto</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $varian_query = $conn->query("SELECT * FROM tb_varian_product WHERE product_id = {$produk['id']}");
                                    if ($varian_query->num_rows > 0) {
                                        while ($varian = $varian_query->fetch_assoc()) {
                                            echo "<div>" . htmlspecialchars($varian['varian']) . " (Stok: " . $varian['stok'] . ")</div>";
                                        }
                                    } else {
                                        echo "<em>Tidak ada</em>";
                                    }
                                    ?>
                                </td>
                                <td><?= $produk['jumlah_stok'] ?></td>
                                <td><?= $produk['stok'] ?></td>
                                <td>
                                    <a href="editProduct.php?id=<?= $produk['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                                    <a href="?delete_id=<?= $produk['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin ingin menghapus?')">Hapus</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="12" class="text-center text-muted">Tidak ada produk yang tercatat pada tabel.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>
