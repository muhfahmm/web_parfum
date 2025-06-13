<?php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil data dari form
    $category_id = $_POST['category_id'];
    $nama_produk = $_POST['nama_produk'];
    $harga = $_POST['harga'];
    $harga_diskon = $_POST['harga_diskon'] !== '' ? $_POST['harga_diskon'] : NULL;
    $is_diskon = isset($_POST['is_diskon']) ? 1 : 0;
    $etalase_toko = $_POST['etalase_toko'];
    $detail = $_POST['detail'];
    
    // Modifikasi: Jika jumlah_stok kosong, set ke 0
    $jumlah_stok = $_POST['jumlah_stok'] !== '' ? (int)$_POST['jumlah_stok'] : 0;
    $stok = $_POST['stok'];

    // Upload foto thumbnail
    $thumbnailName = null;
    if (isset($_FILES['foto_thumbnail']) && $_FILES['foto_thumbnail']['error'] === UPLOAD_ERR_OK) {
        $tmp_name = $_FILES['foto_thumbnail']['tmp_name'];
        $ext = pathinfo($_FILES['foto_thumbnail']['name'], PATHINFO_EXTENSION);
        $thumbnailName = uniqid('thumb_') . '.' . $ext;
        move_uploaded_file($tmp_name, 'uploads/' . $thumbnailName);
    }

    // Upload foto produk (multiple)
    $foto_produk_names = [];
    if (isset($_FILES['foto_produk'])) {
        foreach ($_FILES['foto_produk']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['foto_produk']['error'][$key] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES['foto_produk']['name'][$key], PATHINFO_EXTENSION);
                $newName = uniqid('prod_') . '.' . $ext;
                move_uploaded_file($tmp_name, 'uploads/' . $newName);
                $foto_produk_names[] = $newName;
            }
        }
    }

    $foto_produk_json = json_encode($foto_produk_names);

    // Simpan produk ke tb_adminProduct
    $stmt = $conn->prepare("INSERT INTO tb_adminProduct 
      (category_id, nama_produk, harga, harga_diskon, is_diskon, etalase_toko, foto_thumbnail, foto_produk, detail, jumlah_stok, stok) 
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param(
        "isdiissssis",
        $category_id,
        $nama_produk,
        $harga,
        $harga_diskon,
        $is_diskon,
        $etalase_toko,
        $thumbnailName,
        $foto_produk_json,
        $detail,
        $jumlah_stok,
        $stok
    );
    $stmt->execute();

    $product_id = $stmt->insert_id;
    $stmt->close();

    // Hitung total stok varian jika jumlah_stok utama 0
    $total_stok_varian = 0;
    $has_valid_varian = false;
    
    // Simpan varian produk ke tb_varian_product
    if (!empty($_POST['varian'])) {
        $varian_arr = $_POST['varian'];
        $stok_varian_arr = $_POST['stok_varian'] ?? [];

        $stmtVarian = $conn->prepare("INSERT INTO tb_varian_product (product_id, varian, stok) VALUES (?, ?, ?)");
        for ($i = 0; $i < count($varian_arr); $i++) {
            $nama_varian = trim($varian_arr[$i]);
            $stok_varian = isset($stok_varian_arr[$i]) ? (int)$stok_varian_arr[$i] : 0;
            
            if ($nama_varian !== '') {
                $stmtVarian->bind_param("isi", $product_id, $nama_varian, $stok_varian);
                $stmtVarian->execute();
                $total_stok_varian += $stok_varian;
                $has_valid_varian = true;
            }
        }
        $stmtVarian->close();
        
        // Update stok utama jika hanya varian yang diisi
        if ($jumlah_stok == 0 && $total_stok_varian > 0) {
            $updateStmt = $conn->prepare("UPDATE tb_adminProduct SET jumlah_stok = ? WHERE id = ?");
            $updateStmt->bind_param("ii", $total_stok_varian, $product_id);
            $updateStmt->execute();
            $updateStmt->close();
        }
    }

    // Validasi minimal ada stok utama atau varian
    if ($jumlah_stok == 0 && !$has_valid_varian) {
        echo "<div class='alert alert-danger'>Error: Harap isi stok utama atau tambahkan varian dengan stok</div>";
        // Hapus produk yang sudah dibuat jika validasi gagal
        $conn->query("DELETE FROM tb_adminProduct WHERE id = $product_id");
    } else {
        // Redirect ke product.php setelah berhasil menyimpan
        header("Location: product.php");
        exit(); // Pastikan tidak ada kode yang dieksekusi setelah redirect
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Tambah Produk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .varian-row {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            align-items: center;
        }
        .alert {
            margin-top: 20px;
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
        <h3>Tambah Produk</h3>
        <form action="" method="POST" enctype="multipart/form-data" id="productForm">

            <!-- Kategori Produk -->
            <div class="mb-3">
                <label for="category_id" class="form-label">Kategori Produk</label>
                <select name="category_id" id="category_id" class="form-select" required>
                    <option value="">-- Pilih Kategori --</option>
                    <?php
                    $kategori = $conn->query("SELECT * FROM tb_adminCategory");
                    while ($kat = $kategori->fetch_assoc()):
                    ?>
                        <option value="<?= $kat['id'] ?>"><?= htmlspecialchars($kat['nama_kategori']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <!-- Nama Produk -->
            <div class="mb-3">
                <label class="form-label">Nama Produk</label>
                <input type="text" name="nama_produk" class="form-control" required>
            </div>

            <!-- Harga dan Harga Diskon -->
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Harga</label>
                    <input type="number" name="harga" class="form-control" required min="0">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Harga Diskon</label>
                    <input type="number" name="harga_diskon" class="form-control" min="0">
                    <div class="form-check mt-1">
                        <input class="form-check-input" type="checkbox" name="is_diskon" value="1" id="diskonCheck">
                        <label class="form-check-label" for="diskonCheck">Harga Diskon</label>
                    </div>
                </div>
            </div>

            <!-- Etalase Toko -->
            <div class="mb-3">
                <label class="form-label">Etalase Toko</label>
                <input type="text" name="etalase_toko" class="form-control">
            </div>

            <!-- Foto Thumbnail -->
            <div class="mb-3">
                <label class="form-label">Foto Thumbnail (1 Foto)</label>
                <input type="file" name="foto_thumbnail" class="form-control" accept="image/*" required>
            </div>

            <!-- Foto Produk -->
            <div class="mb-3">
                <label class="form-label">Foto Produk (boleh lebih dari 1)</label>
                <input type="file" name="foto_produk[]" class="form-control" accept="image/*" multiple>
            </div>

            <!-- Detail Produk -->
            <div class="mb-3">
                <label class="form-label">Detail Produk</label>
                <textarea name="detail" class="form-control" rows="4"></textarea>
            </div>

            <!-- Jumlah Stok dan Status -->
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Jumlah Stok</label>
                    <input type="number" name="jumlah_stok" class="form-control" min="0" id="jumlah_stok">
                    <small class="text-muted">Biarkan kosong (0) jika hanya mengisi stok varian</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Status Stok</label>
                    <select name="stok" class="form-select" required>
                        <option value="tersedia">Tersedia</option>
                        <option value="habis">Habis</option>
                    </select>
                </div>
            </div>

            <!-- Varian Produk -->
            <div class="mb-3">
                <label class="form-label">Varian Produk</label>
                <div id="varian-container">
                    <div class="varian-row">
                        <input type="text" name="varian[]" class="form-control" placeholder="Nama Varian">
                        <input type="number" name="stok_varian[]" class="form-control" placeholder="Stok Varian" min="0">
                        <button type="button" class="btn btn-danger btn-sm remove-varian">X</button>
                    </div>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" id="add-varian">+ Tambah Varian</button>
            </div>

            <button type="submit" class="btn btn-primary">Simpan Produk</button>
        </form>
    </div>

    <script>
        // Tambahkan baris varian baru
        document.getElementById('add-varian').addEventListener('click', function() {
            const container = document.getElementById('varian-container');
            const row = document.createElement('div');
            row.className = 'varian-row';
            row.innerHTML = `
                <input type="text" name="varian[]" class="form-control" placeholder="Nama Varian">
                <input type="number" name="stok_varian[]" class="form-control" placeholder="Stok Varian" min="0">
                <button type="button" class="btn btn-danger btn-sm remove-varian">X</button>
            `;
            container.appendChild(row);
        });

        // Hapus baris varian
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-varian')) {
                e.target.parentElement.remove();
            }
        });

        // Validasi form sebelum submit
        document.getElementById('productForm').addEventListener('submit', function(e) {
            const jumlahStok = document.getElementById('jumlah_stok').value;
            const varianInputs = document.querySelectorAll('[name="varian[]"]');
            let hasValidVarian = false;
            
            // Cek apakah ada varian yang diisi
            varianInputs.forEach(input => {
                if (input.value.trim() !== '') {
                    hasValidVarian = true;
                }
            });
            
            // Validasi minimal ada stok utama atau varian
            if (jumlahStok === '0' && !hasValidVarian) {
                e.preventDefault();
                alert('Harap isi jumlah stok utama atau tambahkan varian dengan stok');
            }
        });
    </script>
</body>

</html>