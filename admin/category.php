<?php
require 'db.php';
session_start();

// Fungsi untuk sanitize input
function clean_input($data)
{
    return htmlspecialchars(trim($data));
}

// Proses Tambah kategori
if (isset($_POST['add_submit'])) {
    $new_name = $conn->real_escape_string($_POST['new_nama_kategori']);
    if (!empty($new_name)) {
        $conn->query("INSERT INTO tb_adminCategory (nama_kategori) VALUES ('$new_name')");
        header("Location: category.php");
        exit;
    } else {
        $error = "Nama kategori tidak boleh kosong.";
    }
}

// Proses Hapus kategori
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $conn->query("DELETE FROM tb_adminCategory WHERE id = $delete_id");
    header("Location: category.php"); // redirect agar refresh tidak ulang hapus
    exit;
}

// Proses Edit kategori (submit form edit)
if (isset($_POST['edit_submit'])) {
    $edit_id = intval($_POST['edit_id']);
    $edit_name = $conn->real_escape_string($_POST['edit_nama_kategori']);
    if (!empty($edit_name)) {
        $conn->query("UPDATE tb_adminCategory SET nama_kategori = '$edit_name' WHERE id = $edit_id");
        header("Location: category.php"); // redirect agar form hilang setelah update
        exit;
    } else {
        $error = "Nama kategori tidak boleh kosong.";
    }
}

// Ambil semua kategori untuk ditampilkan
$query = $conn->query("SELECT * FROM tb_adminCategory ORDER BY id DESC");
$categories = $query->fetch_all(MYSQLI_ASSOC);

// Jika mode edit aktif (klik tombol edit), ambil data kategori yang ingin diedit
$edit_mode = false;
$edit_data = null;
if (isset($_GET['edit_id'])) {
    $edit_mode = true;
    $edit_id = intval($_GET['edit_id']);
    $res = $conn->query("SELECT * FROM tb_adminCategory WHERE id = $edit_id LIMIT 1");
    $edit_data = $res->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <title>Daftar Kategori</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>

<body>
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3>Daftar Kategori</h3>
            <!-- Hilangkan link ke addCategory.php karena form tambah ada di sini -->
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <!-- Form Tambah Kategori -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                Tambah Kategori Baru
            </div>
            <div class="card-body">
                <form method="post" action="category.php" class="row g-3 align-items-center">
                    <div class="col-auto flex-grow-1">
                        <label for="new_nama_kategori" class="form-label visually-hidden">Nama Kategori</label>
                        <input type="text" class="form-control" id="new_nama_kategori" name="new_nama_kategori" placeholder="Masukkan nama kategori" required>
                    </div>
                    <div class="col-auto">
                        <button type="submit" name="add_submit" class="btn btn-success">Tambah</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($edit_mode && $edit_data): ?>
            <!-- Form Edit -->
            <div class="card mb-4">
                <div class="card-header bg-warning text-dark">
                    Edit Kategori
                </div>
                <div class="card-body">
                    <form method="post" action="category.php">
                        <input type="hidden" name="edit_id" value="<?= $edit_data['id'] ?>">
                        <div class="mb-3">
                            <label for="edit_nama_kategori" class="form-label">Nama Kategori</label>
                            <input type="text" class="form-control" id="edit_nama_kategori" name="edit_nama_kategori"
                                value="<?= htmlspecialchars($edit_data['nama_kategori']) ?>" required>
                        </div>
                        <button type="submit" name="edit_submit" class="btn btn-success">Simpan Perubahan</button>
                        <a href="category.php" class="btn btn-secondary">Batal</a>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <?php if (count($categories) > 0): ?>
            <table class="table table-hover table-bordered">
                <thead class="table-primary text-dark">
                    <tr>
                        <th>No</th>
                        <th>Nama Kategori</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $index => $cat): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= htmlspecialchars($cat['nama_kategori']) ?></td>
                            <td>
                                <a href="category.php?edit_id=<?= $cat['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                                <button type="button"
                                    class="btn btn-sm btn-danger btn-delete"
                                    data-id="<?= $cat['id'] ?>"
                                    data-name="<?= htmlspecialchars($cat['nama_kategori']) ?>">
                                    Hapus
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-warning">Belum ada kategori.</div>
        <?php endif; ?>
    </div>

    <!-- Modal Konfirmasi Hapus -->
    <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="confirmDeleteLabel">Konfirmasi Hapus</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    Apakah Anda yakin ingin menghapus kategori <strong id="categoryName"></strong>?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <a href="#" id="btnConfirmDelete" class="btn btn-danger">Ya, Hapus</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var deleteButtons = document.querySelectorAll('.btn-delete');
            var confirmModal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
            var categoryNameElem = document.getElementById('categoryName');
            var btnConfirmDelete = document.getElementById('btnConfirmDelete');

            deleteButtons.forEach(function(button) {
                button.addEventListener('click', function() {
                    var catId = this.getAttribute('data-id');
                    var catName = this.getAttribute('data-name');
                    categoryNameElem.textContent = catName;
                    btnConfirmDelete.href = 'category.php?delete_id=' + catId;
                    confirmModal.show();
                });
            });
        });
    </script>
</body>

</html>
