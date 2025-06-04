<?php
require 'db.php';

// Ambil ID produk dari URL
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Ambil data produk yang akan diedit
$product = null;
$varian = [];
if ($product_id > 0) {
    // Ambil data produk utama
    $stmt = $conn->prepare("SELECT * FROM tb_adminProduct WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    $stmt->close();
    
    // Ambil data varian produk
    $stmt = $conn->prepare("SELECT * FROM tb_varian_product WHERE product_id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $varian[] = $row;
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil data dari form
    $category_id = $_POST['category_id'];
    $nama_produk = $_POST['nama_produk'];
    $harga = $_POST['harga'];
    $harga_diskon = $_POST['harga_diskon'] !== '' ? $_POST['harga_diskon'] : NULL;
    $is_diskon = isset($_POST['is_diskon']) ? 1 : 0;
    $etalase_toko = $_POST['etalase_toko'];
    $detail = $_POST['detail'];
    $jumlah_stok = $_POST['jumlah_stok'];
    $stok = $_POST['stok'];

    // Handle foto thumbnail
    $thumbnailName = $product['foto_thumbnail'];
    if (isset($_FILES['foto_thumbnail']) && $_FILES['foto_thumbnail']['error'] === UPLOAD_ERR_OK) {
        // Hapus foto lama jika ada
        if ($thumbnailName && file_exists('uploads/' . $thumbnailName)) {
            unlink('uploads/' . $thumbnailName);
        }
        
        // Upload foto baru
        $tmp_name = $_FILES['foto_thumbnail']['tmp_name'];
        $ext = pathinfo($_FILES['foto_thumbnail']['name'], PATHINFO_EXTENSION);
        $thumbnailName = uniqid('thumb_') . '.' . $ext;
        move_uploaded_file($tmp_name, 'uploads/' . $thumbnailName);
    }

    // Handle foto produk
    $foto_produk_names = json_decode($product['foto_produk'], true) ?? [];
    if (isset($_FILES['foto_produk'])) {
        // Hapus foto lama jika ada
        foreach ($foto_produk_names as $oldPhoto) {
            if (file_exists('uploads/' . $oldPhoto)) {
                unlink('uploads/' . $oldPhoto);
            }
        }
        
        // Upload foto baru
        $foto_produk_names = [];
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

    // Update data produk
    $stmt = $conn->prepare("UPDATE tb_adminProduct SET 
        category_id = ?, 
        nama_produk = ?, 
        harga = ?, 
        harga_diskon = ?, 
        is_diskon = ?, 
        etalase_toko = ?, 
        foto_thumbnail = ?, 
        foto_produk = ?, 
        detail = ?, 
        jumlah_stok = ?, 
        stok = ? 
        WHERE id = ?");
    $stmt->bind_param(
        "isdiissssisi",
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
        $stok,
        $product_id
    );
    $stmt->execute();
    $stmt->close();

    // Handle varian produk
    // Hapus varian lama
    $stmt = $conn->prepare("DELETE FROM tb_varian_product WHERE product_id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $stmt->close();

    // Tambah varian baru
    if (!empty($_POST['varian'])) {
        $varian_arr = $_POST['varian'];
        $stok_varian_arr = $_POST['stok_varian'];
        $varian_id_arr = $_POST['varian_id'] ?? [];

        $stmtVarian = $conn->prepare("INSERT INTO tb_varian_product (id, product_id, varian, stok) VALUES (?, ?, ?, ?)");
        for ($i = 0; $i < count($varian_arr); $i++) {
            $nama_varian = trim($varian_arr[$i]);
            $stok_varian = (int) $stok_varian_arr[$i];
            $varian_id = !empty($varian_id_arr[$i]) ? $varian_id_arr[$i] : NULL;
            
            if ($nama_varian !== '' && $stok_varian >= 0) {
                $stmtVarian->bind_param("iisi", $varian_id, $product_id, $nama_varian, $stok_varian);
                $stmtVarian->execute();
            }
        }
        $stmtVarian->close();
    }

    echo "<div class='alert alert-success'>Produk berhasil diperbarui!</div>";
    
    // Refresh data produk setelah update
    $stmt = $conn->prepare("SELECT * FROM tb_adminProduct WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    $stmt->close();
    
    // Refresh data varian
    $varian = [];
    $stmt = $conn->prepare("SELECT * FROM tb_varian_product WHERE product_id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $varian[] = $row;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <title>Edit Produk - Kipli Makaroni</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .varian-row {
      display: flex;
      gap: 10px;
      margin-bottom: 10px;
      align-items: center;
    }
    .preview-image {
      max-width: 100px;
      max-height: 100px;
      margin-right: 10px;
      margin-bottom: 10px;
    }
    .image-container {
      display: flex;
      flex-wrap: wrap;
      margin-bottom: 15px;
    }
  </style>
</head>

<body>
  <div class="container mt-5">
    <h3>Edit Produk - Kipli Makaroni</h3>
    
    <?php if (!$product): ?>
      <div class="alert alert-danger">Produk tidak ditemukan</div>
    <?php else: ?>
      <form action="?id=<?= $product_id ?>" method="POST" enctype="multipart/form-data">

        <!-- Kategori Produk -->
        <div class="mb-3">
          <label for="category_id" class="form-label">Kategori Produk</label>
          <select name="category_id" id="category_id" class="form-select" required>
            <option value="">-- Pilih Kategori --</option>
            <?php
            $kategori = $conn->query("SELECT * FROM tb_adminCategory");
            while ($kat = $kategori->fetch_assoc()):
            ?>
              <option value="<?= $kat['id'] ?>" <?= $kat['id'] == $product['category_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($kat['nama_kategori']) ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>

        <!-- Nama Produk -->
        <div class="mb-3">
          <label class="form-label">Nama Produk</label>
          <input type="text" name="nama_produk" class="form-control" value="<?= htmlspecialchars($product['nama_produk']) ?>" required>
        </div>

        <!-- Harga dan Harga Diskon -->
        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">Harga</label>
            <input type="number" name="harga" class="form-control" value="<?= $product['harga'] ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Harga Diskon</label>
            <input type="number" name="harga_diskon" class="form-control" value="<?= $product['harga_diskon'] ?>">
            <div class="form-check mt-1">
              <input class="form-check-input" type="checkbox" name="is_diskon" value="1" id="diskonCheck" <?= $product['is_diskon'] ? 'checked' : '' ?>>
              <label class="form-check-label" for="diskonCheck">Harga Diskon</label>
            </div>
          </div>
        </div>

        <!-- Etalase Toko -->
        <div class="mb-3">
          <label class="form-label">Etalase Toko</label>
          <input type="text" name="etalase_toko" class="form-control" value="<?= htmlspecialchars($product['etalase_toko']) ?>">
        </div>

        <!-- Foto Thumbnail -->
        <div class="mb-3">
          <label class="form-label">Foto Thumbnail (1 Foto)</label>
          <?php if ($product['foto_thumbnail']): ?>
            <div class="mb-2">
              <img src="uploads/<?= htmlspecialchars($product['foto_thumbnail']) ?>" class="preview-image">
            </div>
          <?php endif; ?>
          <input type="file" name="foto_thumbnail" class="form-control" accept="image/*">
        </div>

        <!-- Foto Produk -->
        <div class="mb-3">
          <label class="form-label">Foto Produk (boleh lebih dari 1)</label>
          <?php 
          $foto_produk = json_decode($product['foto_produk'], true) ?? [];
          if (!empty($foto_produk)): ?>
            <div class="image-container">
              <?php foreach ($foto_produk as $foto): ?>
                <img src="uploads/<?= htmlspecialchars($foto) ?>" class="preview-image">
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <input type="file" name="foto_produk[]" class="form-control" accept="image/*" multiple>
        </div>

        <!-- Detail Produk -->
        <div class="mb-3">
          <label class="form-label">Detail Produk</label>
          <textarea name="detail" class="form-control" rows="4"><?= htmlspecialchars($product['detail']) ?></textarea>
        </div>

        <!-- Jumlah Stok dan Status -->
        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">Jumlah Stok</label>
            <input type="number" name="jumlah_stok" class="form-control" value="<?= $product['jumlah_stok'] ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Status Stok</label>
            <select name="stok" class="form-select" required>
              <option value="tersedia" <?= $product['stok'] == 'tersedia' ? 'selected' : '' ?>>Tersedia</option>
              <option value="habis" <?= $product['stok'] == 'habis' ? 'selected' : '' ?>>Habis</option>
            </select>
          </div>
        </div>

        <!-- Varian Produk -->
        <div class="mb-3">
          <label class="form-label">Varian Produk</label>
          <div id="varian-container">
            <?php if (empty($varian)): ?>
              <div class="varian-row">
                <input type="text" name="varian[]" class="form-control" placeholder="Nama Varian">
                <input type="number" name="stok_varian[]" class="form-control" placeholder="Stok Varian">
                <button type="button" class="btn btn-danger btn-sm remove-varian">X</button>
              </div>
            <?php else: ?>
              <?php foreach ($varian as $v): ?>
                <div class="varian-row">
                  <input type="hidden" name="varian_id[]" value="<?= $v['id'] ?>">
                  <input type="text" name="varian[]" class="form-control" placeholder="Nama Varian" value="<?= htmlspecialchars($v['varian']) ?>">
                  <input type="number" name="stok_varian[]" class="form-control" placeholder="Stok Varian" value="<?= $v['stok'] ?>">
                  <button type="button" class="btn btn-danger btn-sm remove-varian">X</button>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
          <button type="button" class="btn btn-secondary btn-sm" id="add-varian">+ Tambah Varian</button>
        </div>

        <button type="submit" class="btn btn-primary">Update Produk</button>
        <a href="produk.php" class="btn btn-secondary">Kembali</a>
      </form>
    <?php endif; ?>
  </div>

  <script>
    document.getElementById('add-varian').addEventListener('click', function() {
      const container = document.getElementById('varian-container');
      const row = document.createElement('div');
      row.className = 'varian-row';
      row.innerHTML = `
        <input type="text" name="varian[]" class="form-control" placeholder="Nama Varian">
        <input type="number" name="stok_varian[]" class="form-control" placeholder="Stok Varian">
        <button type="button" class="btn btn-danger btn-sm remove-varian">X</button>
      `;
      container.appendChild(row);
    });

    document.addEventListener('click', function(e) {
      if (e.target.classList.contains('remove-varian')) {
        e.target.parentElement.remove();
      }
    });
  </script>
</body>

</html>